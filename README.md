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
  * Provide link (with page parameter) to
    * Edit a page (popup window?)
    * Create a new page (and edit it), especially useful in the 404 page
  * Have smart editor that does indenting and delete right
  * Button to reprocess images (medium + thumbnail generation) in a directory
  * Have slider to change left panel width
  * Provide meaningful examples in `config.php` especially about image versions

TODO Design
-----------
In-place editing is probably not a good idea as we may want to use EpicEditor.
Then we may want to extract the new components:
  * Media manager
  * File manager

So that we can have the new popup page editor that also provides access to the medias and files.
This means using twig templates and including them where they need to be reused.
The javascript code may need refactoring.

License
-------
Released under the [MIT license](http://www.opensource.org/licenses/MIT).
