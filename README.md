YoctoPhoto
==========
Media manager based on [https://github.com/gilbitron/Pico-Editor-Plugin](Pico-Editor-Plugin).

How to use
----------
Clone plugin or unzip archive in your plugins directory.
Generate your hash into `.passwd` or replace the call to `file_get_contents` by your hash string in `config.php`.

TODO
----
  * Use git submodule for [https://github.com/OscarGodson/EpicEditor](EpicEditor)
  * File tree view in the left of the editor
  * Use [https://github.com/blueimp/jQuery-File-Upload](Jquery File Uploader) to implement a media manager
  * Provide data for twig templates to go over available media for a given namespace
  * Have default media gallery template

License
-------
Released under the [http://www.opensource.org/licenses/MIT](MIT license).
