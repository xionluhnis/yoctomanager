YoctoPhoto
==========
Media manager for [PicoCMS](http://picocms.org/) based on [Pico-Editor-Plugin](https://github.com/gilbitron/Pico-Editor-Plugin).

How to use
----------
Clone plugin or unzip archive (as `yoctophoto`) in your plugins directory.
Generate your hash into `.passwd` or replace the call to `file_get_contents` by your hash string in `config.php`.

Configure the rest as you need (especially media location).

TODO
----
  * Use [JQuery File Uploader](https://github.com/blueimp/jQuery-File-Upload) to implement the media manager
  * Provide data for twig templates to go over available media for a given namespace
  * Have default media gallery template
  * Possibly have thumbnail generation with GD
  * Have slider to change left panel width

License
-------
Released under the [MIT license](http://www.opensource.org/licenses/MIT).
