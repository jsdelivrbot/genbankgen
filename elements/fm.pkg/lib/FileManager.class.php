<?php

class FileManager {
    /**
     * Delete  file or folder (recursively)
     * @param string $path
     * @return bool
     */
    public function rdelete($path)
    {
      if (is_link($path)) {
          return unlink($path);
      } elseif (is_dir($path)) {
          $objects = scandir($path);
          $ok = true;
          if (is_array($objects)) {
              foreach ($objects as $file) {
                  if ($file != '.' && $file != '..') {
                      if (!$this->rdelete($path . '/' . $file)) {
                          $ok = false;
                      }
                  }
              }
          }
          return ($ok) ? rmdir($path) : false;
      } elseif (is_file($path)) {
          return unlink($path);
      }
      return false;
    }

    /**
     * Recursive chmod
     * @param string $path
     * @param int $filemode
     * @param int $dirmode
     * @return bool
     * @todo Will use in mass chmod
     */
    public function rchmod($path, $filemode, $dirmode)
    {
        if (is_dir($path)) {
            if (!chmod($path, $dirmode)) {
                return false;
            }
            $objects = scandir($path);
            if (is_array($objects)) {
                foreach ($objects as $file) {
                    if ($file != '.' && $file != '..') {
                        if (!$this->rchmod($path . '/' . $file, $filemode, $dirmode)) {
                            return false;
                        }
                    }
                }
            }
            return true;
        } elseif (is_link($path)) {
            return true;
        } elseif (is_file($path)) {
            return chmod($path, $filemode);
        }
        return false;
    }

    /**
     * Safely rename
     * @param string $old
     * @param string $new
     * @return bool|null
     */
    public function rename($old, $new)
    {
        echo "OLD: $old".PHP_EOL;
        echo "NEW: $new".PHP_EOL;

        return (!file_exists($new) && file_exists($old)) ? rename($old, $new) : null;
    }

    /**
     * Copy file or folder (recursively).
     * @param string $path
     * @param string $dest
     * @param bool $upd Update files
     * @param bool $force Create folder with same names instead file
     * @return bool
     */
    public function rcopy($path, $dest, $upd = true, $force = true)
    {
        if (is_dir($path)) {
            if (!$this->mkdir($dest, $force)) {
                return false;
            }
            $objects = scandir($path);
            $ok = true;
            if (is_array($objects)) {
                foreach ($objects as $file) {
                    if ($file != '.' && $file != '..') {
                        if (!$this->rcopy($path . '/' . $file, $dest . '/' . $file)) {
                            $ok = false;
                        }
                    }
                }
            }
            return $ok;
        } elseif (is_file($path)) {
            return $this->copy($path, $dest, $upd);
        }
        return false;
    }

    /**
     * Safely create folder
     * @param string $dir
     * @param bool $force
     * @return bool
     */
    public function mkdir($dir, $force)
    {
        if (file_exists($dir)) {
            if (is_dir($dir)) {
                return $dir;
            } elseif (!$force) {
                return false;
            }
            unlink($dir);
        }
        return mkdir($dir, 0777, true);
    }

    public function create_file($path)
    {
        return @fopen($path, 'w') or die('Cannot open file:  '.$path);
    }

    public function file_exists($path)
    {
        return file_exists($path);
    }

    public function is_file($path)
    {
        return is_file($path);
    }

    public function filesize($path)
    {
        return filesize($path);
    }

    public function readfile($path)
    {
        return readfile($path);
    }

    public function move_uploaded_file($old, $new)
    {
        return move_uploaded_file($old, $new);
    }

    /**
     * Safely copy file
     * @param string $f1
     * @param string $f2
     * @param bool $upd
     * @return bool
     */
    public function copy($f1, $f2, $upd)
    {
        $time1 = filemtime($f1);
        if (file_exists($f2)) {
            $time2 = filemtime($f2);
            if ($time2 >= $time1 && $upd) {
                return false;
            }
        }
        $ok = copy($f1, $f2);
        if ($ok) {
            touch($f2, $time1);
        }
        return $ok;
    }

    /**
     * Get mime type
     * @param string $file_path
     * @return mixed|string
     */
    public function get_mime_type($file_path)
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file_path);
            finfo_close($finfo);
            return $mime;
        } elseif (function_exists('mime_content_type')) {
            return mime_content_type($file_path);
        } elseif (!stristr(ini_get('disable_functions'), 'shell_exec')) {
            $file = escapeshellarg($file_path);
            $mime = shell_exec('file -bi ' . $file);
            return $mime;
        } else {
            return '--';
        }
    }

    /**
     * HTTP Redirect
     * @param string $url
     * @param int $code
     */
    public function redirect($url, $code = 302)
    {
        header('Location: ' . $url, true, $code);
        exit;
    }

    /**
     * Clean path
     * @param string $path
     * @return string
     */
    public function clean_path($path)
    {
        $path = trim($path);
        $path = trim($path, '\\/');
        $path = str_replace(array('../', '..\\'), '', $path);
        if ($path == '..') {
            $path = '';
        }
        return str_replace('\\', '/', $path);
    }

    /**
     * Get parent path
     * @param string $path
     * @return bool|string
     */
    public function get_parent_path($path)
    {
        $path = $this->clean_path($path);
        if ($path != '') {
            $array = explode('/', $path);
            if (count($array) > 1) {
                $array = array_slice($array, 0, -1);
                return implode('/', $array);
            }
            return '';
        }
        return false;
    }

    /**
     * Get nice filesize
     * @param int $size
     * @return string
     */
    public function get_filesize($size)
    {
        if ($size < 1000) {
            return sprintf('%s B', $size);
        } elseif (($size / 1024) < 1000) {
            return sprintf('%s KiB', round(($size / 1024), 2));
        } elseif (($size / 1024 / 1024) < 1000) {
            return sprintf('%s MiB', round(($size / 1024 / 1024), 2));
        } elseif (($size / 1024 / 1024 / 1024) < 1000) {
            return sprintf('%s GiB', round(($size / 1024 / 1024 / 1024), 2));
        } else {
            return sprintf('%s TiB', round(($size / 1024 / 1024 / 1024 / 1024), 2));
        }
    }

    /**
     * Get info about zip archive
     * @param string $path
     * @return array|bool
     */
    public function get_zif_info($path)
    {
        if (function_exists('zip_open')) {
            $arch = zip_open($path);
            if ($arch) {
                $filenames = array();
                while ($zip_entry = zip_read($arch)) {
                    $zip_name = zip_entry_name($zip_entry);
                    $zip_folder = substr($zip_name, -1) == '/';
                    $filenames[] = array(
                        'name' => $zip_name,
                        'filesize' => zip_entry_filesize($zip_entry),
                        'compressed_size' => zip_entry_compressedsize($zip_entry),
                        'folder' => $zip_folder
                        //'compression_method' => zip_entry_compressionmethod($zip_entry),
                    );
                }
                zip_close($arch);
                return $filenames;
            }
        }
        return false;
    }

    /**
     * Encode html entities
     * @param string $text
     * @return string
     */
    public function enc($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * This function scans the files folder recursively, and builds a large array
     * @param string $dir
     * @return json
     */
    public function scan($dir){
        $files = array();
        $_dir = $dir;
        $dir = FM_ROOT_PATH.'/'.$dir;
        // Is there actually such a folder/file?
        if(file_exists($dir)){
            foreach(scandir($dir) as $f) {
                if(!$f || $f[0] == '.') {
                    continue; // Ignore hidden files
                }

                if(is_dir($dir . '/' . $f)) {
                    // The path is a folder
                    $files[] = array(
                        "name" => $f,
                        "type" => "folder",
                        "path" => $_dir.'/'.$f,
                        "items" => $this->scan($dir . '/' . $f), // Recursively get the contents of the folder
                    );
                } else {
                    // It is a file
                    $files[] = array(
                        "name" => $f,
                        "type" => "file",
                        "path" => $_dir,
                        "size" => filesize($dir . '/' . $f) // Gets the size of this file
                    );
                }
            }
        }
        return $files;
    }

    /**
     * Send email with file attached
     * @param string $msg, $to, $p
      */
    public function send_mail($path,$filename, $mailto, $message) {
        $file = $path.'/'.$filename;
        $content = file_get_contents( $file);
        $content = chunk_split(base64_encode($content));
        $uid = md5(uniqid(time()));
        $name = basename($file);

        // header
        $header = "From: Embeded File Manager <filemanager@mail.com>\r\n";
        $header .= "Reply-To: ".$mailto."\r\n";
        $header .= "MIME-Version: 1.0\r\n";
        $header .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"\r\n\r\n";

        // message & attachment
        $subject = "File is attached";
        $nmessage = "--".$uid."\r\n";
        $nmessage .= "Content-type:text/plain; charset=iso-8859-1\r\n";
        $nmessage .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $nmessage .= $message."\r\n\r\n";
        $nmessage .= "--".$uid."\r\n";
        $nmessage .= "Content-Type: application/octet-stream; name=\"".$filename."\"\r\n";
        $nmessage .= "Content-Transfer-Encoding: base64\r\n";
        $nmessage .= "Content-Disposition: attachment; filename=\"".$filename."\"\r\n\r\n";
        $nmessage .= $content."\r\n\r\n";
        $nmessage .= "--".$uid."--";

        if (mail($mailto, $subject, $nmessage, $header)) {
            return true; // Or do something here
        } else {
          return false;
        }
    }

    /**
     * Save message in session
     * @param string $msg
     * @param string $status
     */
    public function set_msg($msg, $status = 'ok')
    {
        $_SESSION['message'] = $msg;
        $_SESSION['status'] = $status;
    }

    /**
     * Check if string is in UTF-8
     * @param string $string
     * @return int
     */
    public function is_utf8($string)
    {
        return preg_match('//u', $string);
    }

    /**
     * Convert file name to UTF-8 in Windows
     * @param string $filename
     * @return string
     */
    public function convert_win($filename)
    {
        if (FM_IS_WIN && function_exists('iconv')) {
            $filename = iconv(FM_ICONV_INPUT_ENC, 'UTF-8//IGNORE', $filename);
        }
        return $filename;
    }

    /**
     * Get translated string
     * @param string $str
     * @param string|null $lang
     * @return string
     */
    public function t($str, $lang = null)
    {
        if ($lang === null) {
            if (defined('FM_LANG')) {
                $lang = FM_LANG;
            } else {
                return $str;
            }
        }
        $strings = $this->get_strings();
        if (!isset($strings[$lang]) || !is_array($strings[$lang])) {
            return $str;
        }
        if (array_key_exists($str, $strings[$lang])) {
            return $strings[$lang][$str];
        }
        return $str;
    }

    /**
     * Get CSS classname for file
     * @param string $path
     * @return string
     */
    public function get_file_icon_class($path)
    {
        // get extension
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        switch ($ext) {
            case 'ico': case 'gif': case 'jpg': case 'jpeg': case 'jpc': case 'jp2':
            case 'jpx': case 'xbm': case 'wbmp': case 'png': case 'bmp': case 'tif':
            case 'tiff': case 'svg':
                $img = 'fa fa-picture-o';
                break;
            case 'passwd': case 'ftpquota': case 'sql': case 'js': case 'json': case 'sh':
            case 'config': case 'twig': case 'tpl': case 'md': case 'gitignore':
            case 'c': case 'cpp': case 'cs': case 'py': case 'map': case 'lock': case 'dtd':
                $img = 'fa fa-file-code-o';
                break;
            case 'txt': case 'ini': case 'conf': case 'log': case 'htaccess':
                $img = 'fa fa-file-text-o';
                break;
            case 'css': case 'less': case 'sass': case 'scss':
                $img = 'fa fa-css3';
                break;
            case 'zip': case 'rar': case 'gz': case 'tar': case '7z':
                $img = 'fa fa-file-archive-o';
                break;
            case 'php': case 'php4': case 'php5': case 'phps': case 'phtml':
                $img = 'fa fa-code';
                break;
            case 'htm': case 'html': case 'shtml': case 'xhtml':
                $img = 'fa fa-html5';
                break;
            case 'xml': case 'xsl':
                $img = 'fa fa-file-excel-o';
                break;
            case 'wav': case 'mp3': case 'mp2': case 'm4a': case 'aac': case 'ogg':
            case 'oga': case 'wma': case 'mka': case 'flac': case 'ac3': case 'tds':
                $img = 'fa fa-music';
                break;
            case 'm3u': case 'm3u8': case 'pls': case 'cue':
                $img = 'fa fa-headphones';
                break;
            case 'avi': case 'mpg': case 'mpeg': case 'mp4': case 'm4v': case 'flv':
            case 'f4v': case 'ogm': case 'ogv': case 'mov': case 'mkv': case '3gp':
            case 'asf': case 'wmv':
                $img = 'fa fa-file-video-o';
                break;
            case 'eml': case 'msg':
                $img = 'fa fa-envelope-o';
                break;
            case 'xls': case 'xlsx':
                $img = 'fa fa-file-excel-o';
                break;
            case 'csv':
                $img = 'fa fa-file-text-o';
                break;
            case 'bak':
                $img = 'fa fa-clipboard';
                break;
            case 'doc': case 'docx':
                $img = 'fa fa-file-word-o';
                break;
            case 'ppt': case 'pptx':
                $img = 'fa fa-file-powerpoint-o';
                break;
            case 'ttf': case 'ttc': case 'otf': case 'woff':case 'woff2': case 'eot': case 'fon':
                $img = 'fa fa-font';
                break;
            case 'pdf':
                $img = 'fa fa-file-pdf-o';
                break;
            case 'psd': case 'ai': case 'eps': case 'fla': case 'swf':
                $img = 'fa fa-file-image-o';
                break;
            case 'exe': case 'msi':
                $img = 'fa fa-file-o';
                break;
            case 'bat':
                $img = 'fa fa-terminal';
                break;
            default:
                $img = 'fa fa-info-circle';
        }

        return $img;
    }

    /**
     * Get image files extensions
     * @return array
     */
    public function get_image_exts()
    {
        return array('ico', 'gif', 'jpg', 'jpeg', 'jpc', 'jp2', 'jpx', 'xbm', 'wbmp', 'png', 'bmp', 'tif', 'tiff', 'psd');
    }

    /**
     * Get video files extensions
     * @return array
     */
    public function get_video_exts()
    {
        return array('webm', 'mp4', 'm4v', 'ogm', 'ogv', 'mov');
    }

    /**
     * Get audio files extensions
     * @return array
     */
    public function get_audio_exts()
    {
        return array('wav', 'mp3', 'ogg', 'm4a');
    }

    /**
     * Get text file extensions
     * @return array
     */
    public function get_text_exts()
    {
        return array(
            'txt', 'css', 'ini', 'conf', 'log', 'htaccess', 'passwd', 'ftpquota', 'sql', 'js', 'json', 'sh', 'config',
            'php', 'php4', 'php5', 'phps', 'phtml', 'htm', 'html', 'shtml', 'xhtml', 'xml', 'xsl', 'm3u', 'm3u8', 'pls', 'cue',
            'eml', 'msg', 'csv', 'bat', 'twig', 'tpl', 'md', 'gitignore', 'less', 'sass', 'scss', 'c', 'cpp', 'cs', 'py',
            'map', 'lock', 'dtd', 'svg', 'sqn', 'tsv',
        );
    }

    /**
     * Get mime types of text files
     * @return array
     */
    public function get_text_mimes()
    {
        return array(
            'application/xml',
            'application/javascript',
            'application/x-javascript',
            'image/svg+xml',
            'message/rfc822',
        );
    }

    /**
     * Get file names of text files w/o extensions
     * @return array
     */
    public function get_text_names()
    {
        return array(
            'license',
            'readme',
            'authors',
            'contributors',
            'changelog',
        );
    }

    /**
     * Show nav block
     * @param string $path
     */
    public function show_nav_path($path)
    {
        global $lang;
        $nav_path_vars = array(

        );
        ?>
    <div class="path main-nav">

            <?php
            $path = $this->clean_path($path);
            $root_url = "<a href='".$this->base_query."'><i class='fa fa-home' aria-hidden='true' title='" . FM_ROOT_PATH . "'></i></a>";
            $sep = '<i class="fa fa-caret-right"></i>';
            if ($path != '') {
                $exploded = explode('/', $path);
                $count = count($exploded);
                $array = array();
                $parent = '';
                for ($i = 0; $i < $count; $i++) {
                    $parent = trim($parent . '/' . $exploded[$i], '/');
                    $parent_enc = urlencode($parent);
                    $array[] = "<a href='".$this->base_query."{$parent_enc}'>" . $this->convert_win($exploded[$i]) . "</a>";
                }
                $root_url .= $sep . implode($sep, $array);
            }
            echo '<div class="break-word float-left">' . $root_url . '</div>';
            ?>

            <div class="float-right">
            <?php if (!FM_READONLY): ?>
            <a title="<?php echo $this->t('Search', $lang) ?>" href="javascript:showSearch('<?php echo urlencode(FM_PATH) ?>')"><i class="fa fa-search"></i></a>
            <a title="<?php echo $this->t('Upload files', $lang) ?>" href="<?php echo $this->base_query; echo urlencode(FM_PATH) ?>&amp;upload"><i class="fa fa-cloud-upload" aria-hidden="true"></i></a>
            <a title="<?php echo $this->t('New folder', $lang) ?>" href="#createNewItem" ><i class="fa fa-plus-square"></i></a>
            <?php endif; ?>
            <?php if (FM_USE_AUTH): ?><a title="<?php echo $this->t('Logout', $lang) ?>" href="?logout=1"><i class="fa fa-sign-out" aria-hidden="true"></i></a><?php endif; ?>
            </div>
    </div>
    <?php
    }

    /**
     * Show message from session
     */
    public function show_message()
    {
        if (isset($_SESSION['message'])) {
            $class = isset($_SESSION['status']) ? $_SESSION['status'] : 'ok';
            echo '<p class="message ' . $class . '">' . $_SESSION['message'] . '</p>';
            unset($_SESSION['message']);
            unset($_SESSION['status']);
        }
    }

    /**
     * Show page header
     */
    private function _template_wrap_chars($str) {
        return "{%".$str."%}";
    }

    private function _template_render($template, $vars) {
        $pairs = array();
        foreach($vars as $k => $v) {
            $pairs[$this->_template_wrap_chars($k)] = $v;
        }
        return str_replace(array_keys($pairs),array_values($pairs),$template);
    }

    private function _use_highlightjs_css()
    {
        if (isset($_GET['view']) && FM_USE_HIGHLIGHTJS) {
            $style = FM_HIGHLIGHTJS_STYLE;
            return "<link rel=\"stylesheet\" href=\"//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.2.0/styles/$style.min.css\">";
        }
    }

    private function _use_highlightjs_js()
    {
        if (isset($_GET['view']) && FM_USE_HIGHLIGHTJS) {
            return "<script src=\"//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.2.0/highlight.min.js\"></script><script>hljs.initHighlightingOnLoad();</script>";
        }
    }

    private function _use_ace_editor_js()
    {
        if (isset($_GET['edit']) && isset($_GET['env']) && FM_EDIT_FILE) {
            return "<script src=\"//cdnjs.cloudflare.com/ajax/libs/ace/1.2.8/ace.js\"></script><script>var editor = ace.edit(\"editor\");editor.getSession().setMode(\"ace/mode/javascript\");</script>";
        }
    }

    public function show_header()
    {
        $sprites_ver = '20160315';
        header("Content-Type: text/html; charset=utf-8");
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
        header("Pragma: no-cache");

        global $lang;
        $header_vars = array(
            "doc_title" => "Embeded | File Manager",
            "fm_self_url" => FM_SELF_URL,
            "fm_use_highlightsjs_css" => $this->_use_highlightjs_css(),
            "control_close" => $this->t('Close', $lang),
            "control_create" => $this->t('Create New Item', $lang),
            "control_type" => $this->t('Item Type', $lang),
            "control_file" => $this->t('File', $lang),
            "control_folder" => $this->t('Folder', $lang),
            "control_name" => $this->t('Item Name', $lang),
            "control_now" => $this->t('Create Now', $lang),
            "enc_fm_path" => $this->enc(FM_PATH),
            "control_search" => $this->t('Find an item in current folder...', $lang),
            "control_results" => $this->t('Search Results', $lang),
        );
        $template = file_get_contents($this->template_header);
        echo $this->_template_render($template,$header_vars);
    }

    /**
     * Show page footer
     */
    public function show_footer()
    {
        global $lang;
        $footer_vars = array(
            "control_name" => $this->t('New name', $lang),
            "use_highlightjs_js" => $this->_use_highlightjs_js(),
            "use_ace_editor_js" => $this->_use_ace_editor_js(),
            "base_query" => $this->base_query,
            "base_query_noqmark" => $this->base_query_noqmark,
        );
        $template = file_get_contents($this->template_footer);
        echo $this->_template_render($template,$footer_vars);
    }

    /**
     * Show image
     * @param string $img
     */
    public function show_image($img)
    {
        $modified_time = gmdate('D, d M Y 00:00:00') . ' GMT';
        $expires_time = gmdate('D, d M Y 00:00:00', strtotime('+1 day')) . ' GMT';

        $img = trim($img);
        $images = $this->get_images();
        $image = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAEElEQVR42mL4//8/A0CAAQAI/AL+26JNFgAAAABJRU5ErkJggg==';
        if (isset($images[$img])) {
            $image = $images[$img];
        }
        $image = base64_decode($image);
        if (function_exists('mb_strlen')) {
            $size = mb_strlen($image, '8bit');
        } else {
            $size = strlen($image);
        }

        if (function_exists('header_remove')) {
            header_remove('Cache-Control');
            header_remove('Pragma');
        } else {
            header('Cache-Control:');
            header('Pragma:');
        }

        header('Last-Modified: ' . $modified_time, true, 200);
        header('Expires: ' . $expires_time);
        header('Content-Length: ' . $size);
        header('Content-Type: image/png');
        echo $image;

        exit;
    }

    /**
     * Get base64-encoded images
     * @return array
     */
    public function get_images()
    {
        return array(
            'favicon' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJ
    bWFnZVJlYWR5ccllPAAAAZVJREFUeNqkk79Lw0AUx1+uidTQim4Waxfpnl1BcHMR6uLkIF0cpYOI
    f4KbOFcRwbGTc0HQSVQQXCqlFIXgFkhIyvWS870LaaPYH9CDy8vdfb+fey930aSUMEvT6VHVzw8x
    rKUX3N3Hj/8M+cZ6GcOtBPl6KY5iAA7KJzfVWrfbhUKhALZtQ6myDf1+X5nsuzjLUmUOnpa+v5r1
    Z4ZDDfsLiwER45xDEATgOI6KntfDd091GidzC8vZ4vH1QQ09+4MSMAMWRREKPMhmsyr6voYmrnb2
    PKEizdEabUaeFCDKCCHAdV0wTVNFznMgpVqGlZ2cipzHGtKSZwCIZJgJwxB38KHT6Sjx21V75Jcn
    LXmGAKTRpGVZUx2dAqQzSEqw9kqwuGqONTufPrw37D8lQFxCvjgPXIixANLEGfwuQacMOC4kZz+q
    GdhJS550BjpRCdCbAJCMJRkMASEIg+4Bxz4JwAwDSEueAYDLIM+QrOk6GHiRxjXSkJY8KUCvdXZ6
    kbuvNx+mOcbN9taGBlpLAWf9nX8EGADoCfqkKWV/cgAAAABJRU5ErkJggg==',
            'sprites' => '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
    RK5CYII=',
        );
    }

    /**
     * Get all translations
     * @return array
     */
    public function get_strings()
    {
        static $strings;
        if ($strings !== null) {
            return $strings;
        }
        $strings = array();

        // get additional translations from 'filemanager-l10n.php'
        $l10n_path = __DIR__ . '/filemanager-l10n.php';
        if (is_readable($l10n_path)) {
            $l10n_strings = include $l10n_path;
            if (!empty($l10n_strings) && is_array($l10n_strings)) {
                $strings = array_merge($strings, $l10n_strings);
            }
        }

        return $strings;
    }

    /**
     * Get all available languages
     * @return array
     */
    public function get_available_langs()
    {
        $strings = $this->get_strings();
        $languages = array_keys($strings);
        $languages[] = 'en';
        return $languages;
    }

    public function render()
    {
      $this->show_header(); // HEADER
      $this->show_nav_path(FM_PATH); // current path

      // messages
      $this->show_message();
    }
}
