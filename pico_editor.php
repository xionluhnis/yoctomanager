<?php

/**
 * Editor plugin for Pico
 *
 * @author Gilbert Pellegrom
 * @link http://pico.dev7studios.com
 * @license http://opensource.org/licenses/MIT
 * @version 1.1
 */
class Pico_Editor {

  private $is_admin;
  private $is_logout;
  private $plugin_path;
  private $settings;

  public function __construct() {
    $this->is_admin = false;
    $this->is_logout = false;
    $this->plugin_path = dirname(__FILE__);
    $this->settings = array();
    session_start();

    // include configuration
    if(file_exists($this->plugin_path .'/config.php')){
      global $editor_settings;
      include_once($this->plugin_path .'/config.php');
      $this->settings = $editor_settings;
    }
  }

  /**
   * Access a setting
   *
   * @param $name string the setting name
   * @param $defaultValue varying value to return if no key is found
   * @return the value of the named setting or FALSE
   */
  protected function setting($name, $defaultValue = FALSE) {
    if(array_key_exists($name, $this->settings)) return $this->settings[$name];
    return $defaultValue;
  }

  /**
   * Return whether $needle begins the string $haystack
   * @see http://stackoverflow.com/questions/834303/startswith-and-endswith-functions-in-php
   */
  private static function startsWith($haystack, $needle) {
    $length = strlen($needle);
    return substr($haystack, 0, $length) === $needle;
  }

  /**
   * Return whether $needle ends the string $haystack
   *
   * @see http://stackoverflow.com/questions/834303/startswith-and-endswith-functions-in-php
   */
  private static function endsWith($haystack, $needle) {
    $length = strlen($needle);
    if($length == 0) {
      return TRUE;
    }
    return substr($haystack, -$length) === $needle;
  }

  /**
   * Hook: request mapping into actions
   * No twig rendering is made here.
   *
   * @param $url string the current url to respond for
   */
  public function request_url(&$url) {
    // Are we looking for /admin?
    if(self::startsWith($url, 'admin')){
      $this->is_admin = true;

      // is it a command?
      if(substr_compare($url, '/', 5, 1) === 0){
        $cmd = substr($url, 6);
        switch($cmd){
          // basic editor
        case 'new':     $this->do_new(); break;
        case 'open':    $this->do_open(); break;
        case 'save':    $this->do_save(); break;
        case 'delete':  $this->do_delete(); break;
        case 'logout':  $this->is_logout = true; break;
          // media editor
        case 'media/new':     $this->do_media_new(); break;
        case 'media/list':    $this->do_media_list(); break;
        case 'media/delete':  $this->do_media_delete(); break;
        default:
          die(json_encode(array('error' => 'Error: Wrong request')));
        }
      }
    }
  }

  /**
   * Hook: before rendering with Twig
   *
   * @param $twig_vars array twig environment variables
   * @param $twig Twig instance of twig
   */
  public function before_render(&$twig_vars, &$twig) {
    if($this->is_logout){
      session_destroy();
      header('Location: '. $twig_vars['base_url'] .'/admin');
      exit;
    }

    if($this->is_admin){
      header($_SERVER['SERVER_PROTOCOL'].' 200 OK'); // Override 404 header
      $loader = new Twig_Loader_Filesystem($this->plugin_path);
      $twig_editor = new Twig_Environment($loader, $twig_vars);
      if(!$this->setting('password')){
        $twig_vars['login_error'] = 'No password set for the Yocto Editor.';
        echo $twig_editor->render('login.html', $twig_vars); // Render login.html
        exit;
      }

      if(!isset($_SESSION['pico_logged_in']) || !$_SESSION['pico_logged_in']){
        if(isset($_POST['password'])){
          if(hash($this->setting('hash', 'sha1'), $_POST['password']) == $this->setting('password')){
            $_SESSION['pico_logged_in'] = true;
            $_SESSION['pico_config'] = $twig_vars['config'];
          } else {
            $twig_vars['login_error'] = 'Invalid password.';
            echo $twig_editor->render('login.html', $twig_vars); // Render login.html
            exit;
          }
        } else {
          echo $twig_editor->render('login.html', $twig_vars); // Render login.html
          exit;
        }
      }

      echo $twig_editor->render('editor.html', $twig_vars); // Render editor.html
      exit; // Don't continue to render template
    }
  }

  /**
   * Returns real file name to be edited.
   *
   * @param string $file_url the file URL to be edited
   * @return string
   */
  private static function get_real_filename($file_url) {
    $file_components = parse_url($file_url); // inner
    $base_components = parse_url($_SESSION['pico_config']['base_url']);
    $file_path = rtrim($file_components['path'], '/');
    $base_path = rtrim($base_components['path'], '/');

    if(empty($file_path) || $file_path === $base_path) {
      return '/index';
    } else {
      $file_path = strip_tags(substr($file_path, strlen($base_path)));
      if(is_dir(CONTENT_DIR . $file_path))
        $file_path .= "/index";

      return $file_path;
    }
  }

  /**
   * Transform text into a slug version for file names
   *
   * @param $text string the string to filter
   * @return the filtered text
   */
  private static function slugify($text) {
    // replace non letter or digits by -
    $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
    // trim
    $text = trim($text, '-');
    // transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    // lowercase
    $text = strtolower($text);
    // remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);

    if (empty($text)){
      return 'n-a';
    }
    return $text;
  }

  /**
   * Check whether login is done
   * or die as not authorized to go farther
   */
  private function check_login() {
    if(!isset($_SESSION['pico_logged_in']) || !$_SESSION['pico_logged_in']){
      die(json_encode(array('error' => 'Error: Unauthorized')));
    }
  }

  /**
   * Return the media directory corresponding to a page
   *
   * @param $file_url string a page url
   * @return the corresponding media directory
   */
  private function get_media_dir(&$file_url) {
    // must be logged in
    $this->check_login();

    // get file path
    $file_url = isset($_POST['file']) && $_POST['file'] ? $_POST['file'] : '';
    $file = self::get_real_filename($file_url);
    if(!$file) die('Error: Invalid file');

    // get media directory
    // echo "File=$file\n";
    $media_dir = $this->setting('media_dir') . dirname($file) . $this->setting('media_sub', '');
    if(!self::endsWith($media_dir, '/')) $media_dir .= '/';
    // echo "MDir=$media_dir\n";
    return $media_dir;
  }

  //
  // Create a new page ////////////////////////////////////////////////////////
  //
  private function do_new()  {
    $this->check_login();
    $title = isset($_POST['title']) && $_POST['title'] ? strip_tags($_POST['title']) : '';
    $dir = isset($_POST['dir']) && $_POST['dir'] ? strip_tags($_POST['dir']) : '';

    $contentDir = CONTENT_DIR . $dir;
    if($contentDir[strlen(count($contentDir)-1)] != '/') $contentDir .= '/';

    if(!is_dir($contentDir)) {
      if (!mkdir($contentDir, 0777, true)) {
        die(json_encode(array('error' => 'Can\'t create directory...')));
      }
    }

    $file = $this->slugify(basename($title));
    if(!$file) die(json_encode(array('error' => 'Error: Invalid file name')));

    $error = '';
    $file .= CONTENT_EXT;
    $content = '/*
      Title: '. $title .'
      Author:
      Date: '. date('Y/m/d') .'
     */';
    if(file_exists($contentDir . $file)) {
      $error = 'Error: A post already exists with this title';
    } else {
      if(strlen($content) !== file_put_contents($contentDir . $file, $content))
        $error = 'Error: can not create the post ... ';
    }

    $file_url = $dir .'/'. str_replace(CONTENT_EXT, '', $file);

    die(json_encode(array(
      'title' => $title,
      'content' => $content,
      'file' => $file_url,
      'error' => $error
    )));
  }

  //
  // Create a new page ////////////////////////////////////////////////////////
  //
  private function do_open() {
    $this->check_login();
    $file_url = isset($_POST['file']) && $_POST['file'] ? $_POST['file'] : '';
    $file = self::get_real_filename($file_url);
    if(!$file) die('Error: Invalid file');

    $file .= CONTENT_EXT;
    if(file_exists(CONTENT_DIR . $file)) die(file_get_contents(CONTENT_DIR . $file));
    else die('Error: Invalid file');
  }

  //
  // Save a page //////////////////////////////////////////////////////////////
  //
  private function do_save() {
    $this->check_login();
    $file_url = isset($_POST['file']) && $_POST['file'] ? $_POST['file'] : '';
    $file = self::get_real_filename($file_url);
    if(!$file) die('Error: Invalid file');
    $content = isset($_POST['content']) && $_POST['content'] ? $_POST['content'] : '';
    if(!$content) die('Error: Invalid content');

    $file .= CONTENT_EXT;
    $error = '';
    if(strlen($content) !== file_put_contents(CONTENT_DIR . $file, $content))
      $error = 'Error: can not save changes ... ';

    die(json_encode(array(
      'content' => $content,
      'file' => $file_url,
      'error' => $error
    )));
  }

  //
  // Remove a page ////////////////////////////////////////////////////////////
  //
  private function do_delete() {
    $this->check_login();
    $file_url = isset($_POST['file']) && $_POST['file'] ? $_POST['file'] : '';
    $file = self::get_real_filename($file_url);
    if(!$file) die('Error: Invalid file');

    $file .= CONTENT_EXT;
    if(file_exists(CONTENT_DIR . $file)) die(unlink(CONTENT_DIR . $file));
  }

  //
  // Retrieve the list of media file //////////////////////////////////////////
  //
  private function do_media_list() {
    $media_dir = $this->get_media_dir($file_url);
    $media_list = array();
    if(is_dir($media_dir) && $handle = opendir($media_dir)){
      while( ($entry = readdir($handle)) !== FALSE) {
        if(!is_dir($media_dir . $entry)){
          $media_list[] = $entry;
        }
      }
      natsort($media_list);
      closedir($handle);
    }
    die(json_encode(array(
      'list'  => $media_list,
      'dir'   => $media_dir,
      'file'  => $file_url,
      'error' => ''
    )));
  }

  //
  // Upload new media files ///////////////////////////////////////////////////
  //
  private function do_media_new() {
    $media_dir = $this->get_media_dir($file_url);

    // 1 = create media directory if needed
    if(!file_exists($media_dir)){
      mkdir($media_dir, $this->setting('mkdir_mode', 0755), true);
    }
    // 2 = upload files
    include_once($this->plugin_path .'/fileupload/php/UploadHandler.class.php');
    $upload = new UploadHandler(array(
      'upload_dir'        => $media_dir,
      'upload_url'        => $_SESSION['pico_config']['base_url'] . '/' . $media_dir,
      'mkdir_mode'        => $this->setting('mkdir_mode', 0755),
      'param_name'        => 'medias',
      'accept_file_types' => $this->setting('accept_file_types', '/.+$/i'),
      'image_file_types'  => $this->setting('image_file_types', '/\.(gif|jpe?g|png)$/i'),
      'max_file_size'     => $this->setting('max_file_size', NULL),
      'image_versions' => array(
        '' => array(
          'auto_orient' => true
        ),
        'medium' => array(
          'max_width' => 800,
          'max_height' => 800
        ),
        'thumbnail' => array(
          'max_width' => 80,
          'max_height' => 80,
          'crop' => true
        )
      )
    ));

    // done
    exit;
  }

  //
  // Delete a media file //////////////////////////////////////////////////////
  //
  private function do_media_delete() {
    if(empty($_POST['media'])) die('Error: Missing media');
    $media_name = $_POST['media'];
    $media_dir = $this->get_media_dir($file_url);
    $media_file = $media_dir . $media_name;

    if(file_exists($media_file)){
      die(unlink($media_file));
    }
  }

}
?>
