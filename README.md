YoctoManager
============
Media manager for [PicoCMS](http://picocms.org/) based on [Pico-Editor-Plugin](https://github.com/gilbitron/Pico-Editor-Plugin).

How to use
----------
  1. Clone plugin or unzip archive in your plugins directory.
  2. Generate your hash into `.passwd` or replace the call to `file_get_contents` by your hash string in `config.php`.
  3. Visit <http://www.yoursite.com/admin> and login

The file `config.php` contains different extra configuration.

To generate a hash of `yourpassword` with `sha128` with php in command-line:

```bash
php -r 'echo hash("sha128", "yourpassword");'
```

TODO
----
  * Button to reprocess images (medium + thumbnail generation) in a directory
  * Have slider to change left panel width
  * Provide meaningful examples in `config.php` especially about image versions

License
-------
Released under the [MIT license](http://www.opensource.org/licenses/MIT).
