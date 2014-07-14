<?php

global $editor_settings;



/**
 * Set your configuration here
 */
$editor_settings = array(
  'media_dir'   => 'media', // media directory (can be CONTENT_DIR)
  'media_sub'   => FALSE, // hidden subdirectory to use if putting everything in CONTENT_DIR
  // login
  'hash'        => 'sha1', // type of hash to use for passwords
  'password'    => @file_get_contents(dirname(__FILE__) . '/.passwd') // your password hash
);
?>
