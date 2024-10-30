=== Code Revisions ===
Contributors: a.hoereth
Plugin Name: Code Revisions
Plugin URI: http://yrnxt.com/wordpress/code-revisions/
Tags: code, revisions, plugin, theme, editors, revision.php
Author: Alexander Höreth
Author URI: http://yrnxt.com/
Donate link:
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 3.6
Tested up to: 3.7
Stable tag: 1.0

WordPress native revisions for the theme and plugin editors.

== Description ==

This plugin will help you to keep track of changes made to theme and plugin files through the WordPress code editors. You no longer need to worry about possibly breaking something with bad changes because you can always return to an older version of the file. Additionally the plugin helps you to redo your changes when they might have been overwritten by a plugin update by easily showing you what changed. The revisions are handled in a way native to WordPress. Comfortably view revisions using the new revision viewer introduced in WordPress 3.6.

This plugin is part of my Google Summer of Code 2013 project at WordPress. You can find more information on [make/core](http://make.wordpress.org/core/tag/code-revisions/). It was also featured on [wptavern.com](http://www.wptavern.com/should-code-revisions-be-added-to-the-wordpress-core) if you are interested in some background information.

== Changelog ==

= 0.1 =
* Post and revision creation on file edits

= 0.2 =
* Revision list metabox and revision viewing

= 0.3 =
* Revision restoring

= 0.4 =
* Take direct file changes (e.g. ftp or plugin/theme updates) into account

= 0.5 =
* Basic php syntax checking

= 0.6 =
* Enhanced error proofing & revision browsing

= 0.7 =
* More code appropriate revision viewer styling

= 0.8 =
* WordPress.org release with uninstall automatism and bug fixes

= 0.9 =
* Bug fixes, smaller enhancements and readme update

= 0.95 =
* Remove revisions on package (theme/plugin) uninstall

= 1.0 =
* Bug fix for older PHP versions.

== Developer's Guide ==

__code-revisions.php:__ The main plugin file. It defines constants, loads the other files, instantiates the classes if appropriate and contains the uninstall automatism.

__inc/class-code-revisions.php:__ Loaded on all pages this class does multiple general things. It adds the custom post type required for saving the code revisions (`post_type()`) and redirects the user from the post editor (`wp-admin/edit.php`) to the appropriate code editor (`wp-admin/theme-editor.php` or `wp-admin/plugin-editor.php`) when he tries to view those posts directly (`redirect()`). Further more this class hooks into the WordPress revision restore process to not only restore the post but also the related file (`restore()`) and handles styling the WordPress revision viewer (`wp-admin/revisions.php`) when viewing code revisions so it feels more code-editor-ish (`styles()`).

__inc/code-revisions-editors.php:__ This file contains the `Code_Revisions_Editors` class which, in contrast to the `Code_Revisions` class in `class-code-revisions.php`, is only loaded on the WordPress code editor pages using the `load-plugin-editor.php` and `load-theme-editor.php` hooks. Using either, if available, `POST` and `GET` data or falling back to the appropriate default file the class generates an array containing meta information on the currently viewed file (`generate_meta()`). In an attempt to have as less theme or plugin file specific code this array contains 4 strings:

* type: 'plugin'/'theme'
* package: theme slug or 'plugin/plugin.php'
* file: relative file path from the theme's folder or the WordPress plugin directory
* checksum: md5-checksum of the file

Using this data the plugin can check the database for a related post and retrieve it's id if available (`retrieve()`). The meta information array is stored as custom post meta data alongside a file's post.

When a file is opened in the editor and a related post is found in the database the plugin checks if the post's content and the file's content still match. If they don't the post is updated with the new content (which results in a new revision) and the user is notified about the change using an admin notice (`handle_direct_changes()`).

On file updates through the code editor the plugin checks if the file has actually changed before WordPress writes to it. Only when changes are found a revision needs to be created. If no post is associated with the file yet a new post is created with the old contents. This post is then updated with the new content. This process guarantees that there is a revision with the initial file content to which the user can revert to (`handle_file_update()`). Additionally the plugin tries to do a syntax check for `*.php` files to prevent breaking the WordPress installation (`check_syntax()`). As mostly recommended the plugin utilizes `php -l` for this by writing the new contents to a temporary file. If this feature is not available a more basic check using eval is performed. When a syntax error is found the actual file is not written, but the user is redirected back to the editor with a notification about the error and it's location with line highlighting.

__inc/plugged.php:__ Contains a slightly changed version of the pluggable `wp_text_diff()` function. `wp_text_diff()` is utilized for generating the diffs rendered in the revision viewer. Normally it strips leading, trailing and multiple successive whitespaces. However this behavior is not very helpful when viewing code revisions, wherefore the plugin suppresses it for revisions associated with the custom code revisions post type.

__inc/metabox.php:__ The template for the revision metabox.

__js/editors.js:__ JavaScript for customizing the code editor pages (`wp-admin/plugin-editor.php` and `wp-admin/theme-editor.php`). It adds the revisions metabox below the editors, the revisions text with link next to the 'Update File' button and handles the text replacement and line highlighting when a syntax error was found.

__css/editors.css:__ Styles for the code editors. Enqueued in `inc/class-code-revisions-editors.php -> scripts()`.

__css/viewer.css:__ Styles for the revision viewer (`wp-admin/revisions.php`). Enqueued only when viewing code revisions in `inc/class-code-revisions.php -> styles()`.