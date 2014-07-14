YoctoPhoto
==========
Media manager for [http://picocms.org/](Pico CMS) based on [https://github.com/gilbitron/Pico-Editor-Plugin](Pico-Editor-Plugin).

How to use
----------
Clone plugin or unzip archive (as `yoctophoto`) in your plugins directory.
Generate your hash into `.passwd` or replace the call to `file_get_contents` by your hash string in `config.php`.

Configure the rest as you need (especially media location).

TODO
----
  * Use [https://github.com/blueimp/jQuery-File-Upload](Jquery File Uploader) to implement the media manager
  * Provide data for twig templates to go over available media for a given namespace
  * Have default media gallery template
  * Possibly have thumbnail generation with GD

License
-------
Released under the [http://www.opensource.org/licenses/MIT](MIT license).
