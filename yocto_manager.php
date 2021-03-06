<?php

/**
 * Editor plugin with Media manager for Pico
 *
 * @author Alexandre Kaspar
 * @link https://github.com/xionluhnis/yoctomanager
 * @license http://opensource.org/licenses/MIT
 * @version 0.1
 * @see https://github.com/gilbitron/Pico-Editor-Plugin
 */
class Yocto_Manager {

  private $is_admin;
  private $is_logout;
  private $plugin_path;
  private $settings;
  private $base_url;
  private $state_vars;
  private $admin_page;

  public function __construct() {
    $this->is_admin = false;
    $this->is_logout = false;
    $this->plugin_path = dirname(__FILE__);
    $this->settings = array();
    $this->state_vars = array();
    $this->admin_page = FALSE;
    session_start();

    // include configuration
    if(file_exists($this->plugin_path .'/config.php')){
      global $editor_settings;
      include_once($this->plugin_path .'/config.php');
      $this->settings = $editor_settings;
    }
  }

  /**
   * Hook: base config has been loaded
   */
  public function config_loaded(&$settings) {
    $this->base_url = $settings['base_url'];
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
    $this->state_vars['current_url'] = $this->base_url . '/' . $url;
    // Are we looking for /admin?
    if(self::startsWith($url, 'admin')){
      $this->is_admin = TRUE;

      // is it a command?
      if(substr_compare($url, '/', 5, 1) === 0){
        $cmd = substr($url, 6);
        switch($cmd){
        case 'beta':    $this->admin_page = 'editor_page.html';   $this->state_vars['tab'] = 'page'; break;
        case 'medias':  $this->admin_page = 'editor_medias.html'; $this->state_vars['tab'] = 'medias'; break;
        case 'tree':    $this->admin_page = 'editor_tree.html';   $this->state_vars['tab'] = 'tree'; break;
          // basic editor
        case 'new':     $this->do_new(); break;
        case 'open':    $this->do_open(); break;
        case 'save':    $this->do_save(); break;
        case 'delete':  $this->do_delete(); break;
        case 'logout':  $this->is_logout = true; break;
        // single editor
        case 'create':  $this->show_create(); break;
        case 'edit':    $this->show_edit(); break;
        // tree editor
        case 'tree/new':    $this->do_tree_new(); break;
        case 'tree/list':   $this->do_tree_list(); break;
        case 'tree/delete': $this->do_tree_delete(); break;
        // media editor
        case 'media/new':     $this->do_media_new(); break;
        case 'media/list':    $this->do_media_list(); break;
        case 'media/rename':  $this->do_media_rename(); break;
        case 'media/delete':  $this->do_media_delete(); break;
        default:
          die(json_encode(array('error' => 'Error: Wrong request')));
        }
      }
    }
  }

  /**
   * Hook: before loading the 404 content (path doesn't exist!)
   *
   * @param $file string the requested file which is missing
   */
  public function before_404_load_content(&$file) {
    $this->state_vars['is_404'] = TRUE;
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

    // special variables
    $twig_vars['yocto_dir'] = basename($this->plugin_path);
    $twig_vars = array_merge($twig_vars, $this->state_vars);

    // page filter
    $base_url = $twig_vars['base_url'];
    $shorturl = new Twig_SimpleFilter('shorturl', function ($string) use($base_url) {
      return str_replace($base_url, '', $string);
    });

    // login check
    $is_logged = isset($_SESSION['pico_logged_in']) && $_SESSION['pico_logged_in'];
    $twig_vars['is_logged'] = TRUE;

    // admin case
    if($this->is_admin){
      header($_SERVER['SERVER_PROTOCOL'].' 200 OK'); // Override 404 header
      $loader = new Twig_Loader_Filesystem($this->plugin_path);
      $twig_editor = new Twig_Environment($loader, $twig_vars);
      $twig_editor->addFilter($shorturl);
      if(!$this->setting('password')){
        $twig_vars['login_error'] = 'No password set for the Yocto Editor.';
        echo $twig_editor->render('login.html', $twig_vars); // Render login.html
        exit;
      }

      if(!$is_logged){
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

      // get page template
      $page = $this->admin_page;
      if($page === FALSE) $page = 'editor.html';

      // render it
      echo $twig_editor->render($page, $twig_vars);
      exit; // Don't continue to render template

    } else {
      $twig->addFilter($shorturl);
      // template rendering
      // 1. Save media list
      $curr_url = $twig_vars['current_page']['url'];
      $media_dir = $this->get_media_dir($file_url, false, $curr_url);
      $media_list = array();
      $versions = self::get_image_versions(TRUE);
      if(is_dir($media_dir) && $handle = opendir($media_dir)){
        while( ($entry = readdir($handle)) !== FALSE) {
          $file = $media_dir . $entry;
          if(!is_dir($file)){
            $data = array(
              'entry' => $entry,
              'file' => $file,
              'url' => $twig_vars['base_url'] . '/' . $file,
            );
            if(@is_array(getimagesize($file))){
              $data = array_merge($data, self::get_image_info($file, $versions));
            } else {
              $data['is_image'] = FALSE;
            }
            $media_list[] = $data;
          }
        }
        // TODO natsort($media_list);
        closedir($handle);
      }
      $twig_vars['medias'] = $media_list;
    }
  }

  /**
   * Returns real file name to be edited.
   *
   * @param string $file_url the file URL to be edited
   * @return string
   */
  private static function get_real_filename($file_url, $base_url) {
    $file_components = parse_url($file_url); // inner
    $base_components = parse_url($base_url);
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
  private function get_media_dir(&$file_url, $need_login = true, $default_url = '') {
    if($need_login){
      // must be logged in
      $this->check_login();
    }

    // get file path
    $file_url = isset($_POST['file']) && $_POST['file'] ? $_POST['file'] : $default_url;
    $file = self::get_real_filename($file_url, $this->base_url);
    if(!$file) die('Error: Invalid file');

    // get media directory
    // echo "File=$file\n";
    $media_dir = $this->setting('media_dir') . dirname($file) . $this->setting('media_sub', '');
    if(!self::endsWith($media_dir, '/')) $media_dir .= '/';
    // echo "MDir=$media_dir\n";
    return $media_dir;
  }

  /**
   * Return the image versions from the configuration
   *
   * @param $only_names bool whether to return only the image version names
   */
  private function get_image_versions($only_names = FALSE, $non_default = FALSE) {
    $v = $this->setting('image_versions', array(
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
    ));
    if($non_default){
      unset($v['']);
    }
    return $only_names ? array_keys($v) : $v;
  }

  private function get_image_info($img_file, $versions = FALSE) {
    if(!$versions) $versions = $this->get_image_versions(TRUE);
    $base_dir = dirname($img_file);
    $base_name = basename($img_file);
    $base_url = $this->base_url;
    $data = array('is_image' => true);
    foreach($versions as $v) {
      $v_prefix = empty($v) ? '' : $v . '/';
      $file = $base_dir . '/' . $v_prefix . $base_name;
      if(!is_file($file)) $file = $img_file;
      list($width, $height) = getimagesize($file);
      $idata = array(
        'file' => $file,
        'url' => $base_url . '/' . $file,
        'width' => $width,
        'height' => $height
      );
      if(!empty($v))
        $data[$v] = $idata;
      else
        $data = array_merge($data, $idata);
    }
    return $data;
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
Description:
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
    $file = self::get_real_filename($file_url, $this->base_url);
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
    $file = self::get_real_filename($file_url, $this->base_url);
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
    $file = self::get_real_filename($file_url, $this->base_url);
    if(!$file) die('Error: Invalid file');

    $file .= CONTENT_EXT;
    if(file_exists(CONTENT_DIR . $file)) die(unlink(CONTENT_DIR . $file));
  }

  //
  // Single editor page create ////////////////////////////////////////////////
  //
  private function show_create() {
    $this->check_login();
    $path = '';
    if(isset($_POST['path'])) $path = $_POST['path'];
    else if(isset($_GET['path'])) $path = $_GET['path'];
    $this->state_vars['path'] = $path;
    $this->admin_page = 'single_editor.html';

    if(!$this->check_path($path)){
      $this->state_vars['error'] = 'Invalid path parameter.';
      return;
    }

    $file = self::get_real_filename($path, $this->base_url);

    $dir = substr($file, 0, strrpos($file, '/'));
    $contentDir = CONTENT_DIR . $dir;
    if(!is_dir($contentDir)) {
      if (!mkdir($contentDir, 0777, true)) {
        $this->state_vars['error'] = 'Cannot create directory...';
        return;
      }
    }

    $title = basename($file);
    if(empty($title)) $title = 'Index';
    $file .= CONTENT_EXT;

    if(file_exists(CONTENT_DIR . $file)){
      $this->state_vars['error'] = 'File exists already!';
      return;
    }

    $content = '/*
Title: '. $title .'
Author:
Description:
Date: '. date('Y/m/d') .'
*/';
    // create empty post
    if(strlen($content) !== file_put_contents(CONTENT_DIR . $file, $content)){
      $this->state_vars['error'] = 'Cannot create file...';
    } else {
      $this->state_vars['content'] = $content;
    }
  }
  private function check_path($path) {
    if(empty($path)) return FALSE;
    if(strpos($path, '..') !== FALSE) return false;
    return TRUE;
  }

  //
  // Single editor page ///////////////////////////////////////////////////////
  //
  private function show_edit()  {
    $this->check_login();
    $path = '';
    if(isset($_POST['path'])) $path = $_POST['path'];
    else if(isset($_GET['path'])) $path = $_GET['path'];

    $this->state_vars['path'] = $path;
    $this->admin_page = 'single_editor.html';

    if(!$this->check_path($path)){
      $this->state_vars['error'] = 'Invalid path parameter.';
      return;
    }

    $file = self::get_real_filename($path, $this->base_url);
    $file .= CONTENT_EXT;
    if(file_exists(CONTENT_DIR . $file)) {
      $this->state_vars['content'] = file_get_contents(CONTENT_DIR . $file);
    } else {
      $this->state_vars['error'] = 'Could not read file...';
    }
  }

  //
  // Retrieve the list of media file //////////////////////////////////////////
  //
  private function do_media_list() {
    $media_dir = $this->get_media_dir($file_url);
    $versions = $this->get_image_versions(TRUE);
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
    $self = $this;
    $media_list = array_map(function($item) use(&$self, &$media_dir, &$versions){
      return $self->get_image_info($media_dir . $item, $versions);
    }, $media_list);
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
      'image_versions'    => $this->get_image_versions()
    ));

    // done
    exit;
  }

  //
  // Rename a media file //////////////////////////////////////////////////////
  //
  private function do_media_rename() {
    $media_dir = $this->get_media_dir();
    if(empty($_POST['oldName'])) die('Error: Missing old name');
    $old_name = $_POST['oldName'];
    if(empty($_POST['newName'])) die('Error: Missing new name');
    $new_name = $_POST['newName'];
    if (strpos($new_name, '..') !== FALSE) die('Invalid new name, cannot use ..');

    $old_file = $media_dir . $old_name;
    $new_file = $media_dir . $new_name;

    if(file_exists($old_file)){
      if(file_exists($new_file)){
        die('A file already exists with the new name!');
      }
      $versions = $this->get_image_versions(TRUE);
      foreach($versions as $version){
        $old_file = $media_dir . $version . '/' . $old_name;
        if(file_exists($old_file)){
          $new_file = $media_dir . $version . '/' . $new_name;
          if(!rename($old_file, $new_file)) die('Could not rename ' . $old_file . ' into ' . $new_file);
        }
      }
      die('Success');
    }
    die('File not found.');
  }

  //
  // Delete a media file //////////////////////////////////////////////////////
  //
  private function do_media_delete() {
    $media_dir = $this->get_media_dir();
    if(empty($_POST['name'])) die('Error: Missing file name');
    $media_name = $_POST['name'];

    // does the file exist?
    if(!file_exists($media_dir . $media_name)) die('File not found!');

    $versions = $this->get_image_versions(TRUE);
    foreach($versions as $version){
      if(strlen($version) == 0) continue; // keep it for the end!
      $file = $media_dir . $version . '/' . $media_name;
      if(file_exists($file)){
        if(!unlink($file)) die('Could not delete ' . $file);
      }
    }
    $file = $media_dir . $media_name;
    if(file_exists($file)){
      die(unlink($file) ? 'Success' : 'Could not delete ' . $file);
    }
  }

  //
  // Create a tree path ///////////////////////////////////////////////////////
  //
  private function checked_path() {
    $path = $_POST['path'];
    if(strlen($path) == 0 || strpos($path, '..') !== FALSE){
      die('Path problem');
    }
    return $path;
  }
  private function do_tree_new() {
    $this->check_login();
    $path = $this->checked_path();

    // create directory in media folder
    $media_dir = $this->setting('media_dir');
    if(mkdir($media_dir . '/' . $path, 0771, TRUE)){
      die('Success');
    } else {
      die('Could not create directory ' . $path);
    }
  }

  //
  // List all tree paths //////////////////////////////////////////////////////
  //
  private function do_tree_list() {
    $this->check_login();
    // paths from content directory
    $paths = array('');
    $iter = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator(CONTENT_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::SELF_FIRST,
      RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
    );
    $prefix_length = strlen(CONTENT_DIR);
    foreach ($iter as $path => $dir) {
      if ($dir->isDir()) {
        $paths[] = substr($path, $prefix_length);
      }
    }

    // paths from media directory
    $media_dir = $this->setting('media_dir');
    $iter = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($media_dir, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::SELF_FIRST,
      RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
    );
    $prefix_length = strlen($media_dir) + 1;
    $versions = $this->get_image_versions(TRUE, TRUE);
    foreach ($iter as $path => $dir) {
      if (in_array($dir->getFilename(), $versions)) continue; // skip version directories
      if ($dir->isDir()) {
        $paths[] = substr($path, $prefix_length);
      }
    }

    // only keep unique set
    $paths = array_unique($paths);

    // get information for each path
    $list = array();
    foreach ($paths as $p){
      $content = 0;
      $media = 0;
      $files = array();
      // over content
      foreach(scandir(CONTENT_DIR . $p) as $fname){
        $file = CONTENT_DIR . $p . '/' . $fname;
        if(is_file($file) && self::endsWith($file, CONTENT_EXT))
          ++$content;
      }
      // over content
      foreach(scandir($media_dir . '/' . $p) as $fname){
        $file = $media_dir . '/' . $p . '/' . $fname;
        if(!is_dir($file))
          ++$media;
      }
      $list[$p] = array(
        'content' => $content,
        'media' => $media
      );
    }

    die(json_encode($list));
  }

  //
  // Delete a tree path ///////////////////////////////////////////////////////
  //
  public static function del_tree($dir) {
    if(!is_dir($dir)) return TRUE;
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
      (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
  }
  private function do_tree_delete() {
    $this->check_login();
    $path = $this->checked_path();
    $media_dir = $this->setting('media_dir');

    // try to delete in content and in medias
    $res = TRUE;
    $res &= self::del_tree(CONTENT_DIR . '/' . $path);
    $res &= self::del_tree($media_dir . '/' . $path);

    die($res ? 'Success' : 'Could not clean tree correctly');
  }

}
?>
