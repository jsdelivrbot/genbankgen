<?php
namespace GenBankGen;

class ControllerTbl2asn {
    public function __construct($req, $res) {
        $this->Request = $req;
        $this->Response = $res;
        $this->Data = (object) array();
        if (isset($_POST['data'])) {
            $this->Data = json_decode($_POST['data']);
        }
    }

    private function _current_user() {
        return $this->Data->form->username;
    }

    private function _set_binary() {
        $bin = trim(exec("which tbl2asn", $status));
        if (count($status) != 0) {
            return $bin;
        }

        $bin_path = dirname(__FILE__)."/bin/linux.tbl2asn";
        if (file_exists($bin_path)) {
            return $bin_path;
        }
    }

    private function _get_template($type) {
        $base_path = dirname(__FILE__);
        switch ($type) {
          case 'sbt':
            return $base_path."/template.sbt";
            break;

          case 'tbl':
            return $base_path."/template.tbl";
            break;

          case 'add':
            return $base_path."/template.add";
            break;

          default:
            return false;
            break;
        }
        return false;
    }

    private function _render_wrap($array) {
        $reps = array();
        foreach($array as $key => $value) {
            $reps["{%".$key."%}"] = $value;
        }
        return $reps;
    }

    private function _render_template($template, $data) {
        $file = file_get_contents($template);
        $kv = $this->_render_wrap((array) $data);
        return str_replace(array_keys($kv), array_values($kv), $file);
    }

    private function _render_template_clean($text) {
        if (preg_match_all('/({%.*%})/', $text, $matches)) {
            $clean = array();
            foreach($matches[0] as $item) {
                $clean[$item] = "";
            }
            $text = str_replace(array_keys($clean), array_values($clean), $text);
        }
        return $text;
    }

    private function _render_additional_authors($template, $data) {
        $build = array();
        foreach($data->data as $author) {
            $block = $this->_render_template_clean($this->_render_template($template, $author));
            array_push($build, $block);
        }
        if (count((array) $data->data) == 0) { 
            $block = $this->_render_template_clean(file_get_contents($template));
            array_push($build, $block);
        }
        return implode(",\n", $build);
    }

    private function _render_source_modifier($key, $value) {
        if (is_null($value) || trim($value) == "") { return ""; }
        return "[$key=$value]";
    }

    private function _render_source_modifiers($array) {
        $line = "";
        foreach($array as $k => $v) {
            $line .= $this->_render_source_modifier($k, $v);
        }
        return $line;
    }

    private function _render_tsv($array) {
        $array = (array) $array;
        $n = (object) array("header" => array_keys($array), "data" => array_values($array));
        return implode("\n",array(implode("\t",$n->header), implode("\t",$n->data)));
    }

  	private function _cmd_create($args) {
  	    $TBL2ASN = $args->tbl2asn;
  	    $OUT = $args->build;
        $has_embargo = "";
        if ($args->has_embargo) { $has_embargo = "-H y"; }
        $source_modifiers = addslashes($args->source_modifiers);
        return "$TBL2ASN -t sequence.sbt -i sequence.fsa -p . -V vb $has_embargo -j \"$source_modifiers\"";
  	}

    private function _cmd_exec($args) {
  	    $CWD = $args->build;
        $SHELL_FILE = $CWD.DIRECTORY_SEPARATOR."tbl2asn.cmd";
        $TBL2ASN_CMD = file_get_contents($SHELL_FILE);
        $CMD="cd $CWD; $TBL2ASN_CMD;";
        shell_exec($CMD);
    }

    public function run() {
        date_default_timezone_set('America/Chicago');

        $wrapper = (object) array(
            "username" => $this->_current_user(),
            "storage" => $this->Request->storage_path,
            "folder" => "GenBank",
            "seq_id" => $this->Data->sqn->tbl->ID,
            "timestamp" => date('Ymd-h:i:sA'),
            "tbl2asn" => $this->_set_binary(),
            "sbt_template" => $this->_get_template("sbt"),
            "tbl_template" => $this->_get_template("tbl"),
            "add_template" => $this->_get_template("add"),
            "has_embargo" => $this->Data->form->has_embargo,
        );

        $wrapper->add = $this->_render_additional_authors($wrapper->add_template, $this->Data->sqn->{'publication-authors'});
        $this->Data->sqn->sbt->PublicationAuthors = $wrapper->add;
        $wrapper->sbt = $this->_render_template($wrapper->sbt_template, $this->Data->sqn->sbt);
        $wrapper->tbl = $this->_render_template($wrapper->tbl_template, $this->Data->sqn->tbl);
        $wrapper->source_modifiers = $this->_render_source_modifiers($this->Data->sqn->{'source-modifiers'});

        $output_dir = implode(DIRECTORY_SEPARATOR, array($wrapper->storage, $wrapper->username, $wrapper->folder, $wrapper->seq_id."-".$wrapper->timestamp));
        $build_dir = $output_dir.DIRECTORY_SEPARATOR."build";
        $build_data = $build_dir.DIRECTORY_SEPARATOR."data.json";
        $build_request = $build_dir.DIRECTORY_SEPARATOR."request.json";
        $build_sbt = $build_dir.DIRECTORY_SEPARATOR."sequence.sbt";
        $build_tbl = $build_dir.DIRECTORY_SEPARATOR."sequence.tbl";
        $build_fsa = $build_dir.DIRECTORY_SEPARATOR."sequence.fsa";
        $build_sqn = $build_dir.DIRECTORY_SEPARATOR."sequence.sqn";
        $build_gbf = $build_dir.DIRECTORY_SEPARATOR."sequence.gbf";
        $result_tsv = $output_dir.DIRECTORY_SEPARATOR.$wrapper->seq_id."-".$wrapper->timestamp.".tsv";
        $result_sqn = $output_dir.DIRECTORY_SEPARATOR.$wrapper->seq_id."-".$wrapper->timestamp.".sqn";
        $result_gbf = $output_dir.DIRECTORY_SEPARATOR.$wrapper->seq_id."-".$wrapper->timestamp.".gbf";
        $tbl2asn_cmd = $build_dir.DIRECTORY_SEPARATOR."tbl2asn.cmd";
        $wrapper->build = $build_dir;

        $fs = new Files();
        $fs->mkdir(array("path" => $build_dir, "recursive" => true));
        $fs->write(array("path" => $build_data, "content" => json_encode($this->Data)));
        $fs->write(array("path" => $build_request, "content" => json_encode($this->Request)));
        $fs->write(array("path" => $build_sbt, "content" => $wrapper->sbt));
        $fs->write(array("path" => $build_tbl, "content" => $wrapper->tbl));
        $fs->write(array("path" => $build_fsa, "content" => $this->Data->sqn->tbl->sequence));
        $fs->write(array("path" => $tbl2asn_cmd, "content" => $this->_cmd_create($wrapper)));
        $this->_cmd_exec($wrapper);
        $fs->write(array("path" => $result_tsv, "content" => $this->_render_tsv($this->Data->sqn->{'source-modifiers'})));
        $fs->rename(array("path" => $build_sqn, "destination" => $result_sqn));
        $fs->rename(array("path" => $build_gbf, "destination" => $result_gbf));

        return false;
    }
}
