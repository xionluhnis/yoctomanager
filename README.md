YoctoPhoto
==========
Media manager for [PicoCMS](http://picocms.org/) based on [Pico-Editor-Plugin](https://github.com/gilbitron/Pico-Editor-Plugin).

How to use
----------
  1. Clone plugin or unzip archive (as `yoctophoto`) in your plugins directory.
  2. Generate your hash into `.passwd` or replace the call to `file_get_contents` by your hash string in `config.php`.
  3. Visit [](http://www.yoursite.com/admin) and login

The file `config.php` contains different extra configuration.

To generate a hash of `yourpassword` with `sha128` with php in command-line:
  php -r 'echo hash("sha128", "yourpassword");'

TODO
----
  * Provide data for twig templates to go over available media for a given namespace
  * Have default media gallery template
  * Have slider to change left panel width
  * Provide meaningful examples in `config.php` especially about image versions

License
-------
Released under the [MIT license](http://www.opensource.org/licenses/MIT).
