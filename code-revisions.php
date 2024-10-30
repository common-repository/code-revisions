<?php
/**
Plugin Name: Code Revisions
Plugin URI: http://yrnxt.com/wordpress/code-revisions/
Description: Brings native code revisions to the WordPress integrated code editors.
Author: Alexander Höreth
Version: 1.0
Author URI: http://yrnxt.com
License: GPL2

	Copyright 2013 Alexander Höreth  (email : wordpress@yrnxt.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! defined( 'CODE_REVISIONS_VER' ) )
	define( 'CODE_REVISIONS_VER', '1.0' );
if ( ! defined( 'CODE_REVISIONS_NAME') )
	define( 'CODE_REVISIONS_NAME', basename(__FILE__, '.php') );
if ( ! defined( 'CODE_REVISIONS_DIR' ) )
	define( 'CODE_REVISIONS_DIR', plugin_dir_path(__FILE__) );
if ( ! defined( 'CODE_REVISIONS_URL' ) )
	define( 'CODE_REVISIONS_URL', plugins_url(CODE_REVISIONS_NAME) . '/' );
if ( !defined( 'CODE_REVISIONS_POST_TYPE' ) )
	define( 'CODE_REVISIONS_POST_TYPE', 'code' );

/*
 * Initialize the main class and register the uninstall hook.
 */
include_once( CODE_REVISIONS_DIR . 'inc/class-code-revisions.php' );
register_uninstall_hook( __FILE__, array( 'Code_Revisions', 'uninstall' ) );
new Code_Revisions;

/**
 * Initialize the Code_Revisions_Editors class only when required: On
 * theme-editor.php and plugin-editor.php
 *
 * @since 0.8
 */
function code_revisions_editors() {
	include_once( CODE_REVISIONS_DIR . 'inc/class-code-revisions-editors.php' );
	new Code_Revisions_Editors;
}
add_action( 'load-plugin-editor.php', 'code_revisions_editors' );
add_action( 'load-theme-editor.php',  'code_revisions_editors' );

/*
 * Including file which overwrites pluggable WP Core functions like wp_text_diff().
 */
include_once( 'inc/plugged.php' );
