<?php
/**
 * Class required to be loaded on all WordPress pages. Initializes more
 * functionality for specific pages, fixes redirects and handles restores.
 *
 * @since 0.1
 */
class Code_Revisions {

	/**
	 * Key of the post meta field containing the file's meta data
	 *
	 * @var string
	 */
	private $metakey;

	/**
	 * When viewing code revisions in the revision viewer on wp-admin/revision.php
	 * this contains the it's parent post id; otherwise 0.
	 *
	 * @var int
	 */
	private $id = 0;

	/**
	 * Class constructor for adding actions to hook into core.
	 *
	 * @since 0.1
	 */
	public function __construct() {
		add_action( 'init',                     array( $this, 'post_type'        ) );
		add_filter( 'parent_file',              array( $this, 'parentage'        ) );
		add_action( 'admin_enqueue_scripts',    array( $this, 'styles'           ) );
		add_action( 'load-post.php',            array( $this, 'redirect'         ) );
		add_action( 'load-themes.php',          array( $this, 'uninstall_theme'  ) );
		add_action( 'load-plugins.php',         array( $this, 'uninstall_plugin' ) );
		add_action( 'wp_restore_post_revision', array( $this, 'restore' ), 10, 2   );

		$this->metakey = '_' . str_replace( '-', '_', CODE_REVISIONS_NAME );
	}

	/**
	 * Register the custom post type for saving file contents
	 * as posts for revision capabilities.
	 *
	 * @since 0.1
	 * @uses register_post_type
	 */
	public function post_type() {
		register_post_type( CODE_REVISIONS_POST_TYPE,	array(
			'labels' => array(
				'name'          => __( 'Editor Files', 'code-revisions' ),
				'singular_name' => __( 'Editor File',  'code-revisions' ),
			),
			'public'       => false,
			'rewrite'      => false,
			'query_var'    => false,
			'can_export'   => false,
			'supports'     => array(
				'title',
				'editor',
				'author',
				'revisions'
			),
		) );
	}

	/**
	 * Filter $parent_file and change $submenu_file to expand and highlight the
	 * correct submenu when viewing code revisions in the revision viewer.
	 *
	 * @since  0.9
	 *
	 * @param  string $parent_file File which acts as parent for the currently viewed screen
	 *                             for use in the admin menu system. E.g. edit.php
	 * @return string New $parent_file variable possibly adjusted for code revisions.
	 */
	public function parentage( $parent_file ) {
		global $submenu_file;

		if ( $this->id = $this->viewing_code_revision() ) {
			$meta = get_post_meta( $this->id, $this->metakey, true );
			$submenu_file = $meta['type'] . '-editor.php';
			$parent_file  = $meta['type'] . 's.php';
		}

		return $parent_file;
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @since 0.4
	 */
	public function styles() {
		if ( ! $this->id = $this->viewing_code_revision() )
			return;

		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

		wp_register_style( 'code_revisions_viewer', CODE_REVISIONS_URL . "css/viewer$suffix.css", array(), CODE_REVISIONS_VER );
		wp_enqueue_style(  'code_revisions_viewer' );
	}

	/**
	 * Used for checking if currently viewing a code revision on wp-admin/revision.php.
	 *
	 * @since  0.9
	 *
	 * @uses   $this->id to avoid checking post parent and post type twice.
	 * @return int The parent post id of the currently viewed revision if it is a
	 *             code revision; otherwise 0.
	 */
	private function viewing_code_revision() {
		global $pagenow;

		// only check once
		if ( $this->id )
			return $this->id;

		if ( 'revision.php' == $pagenow ) {
			$revision_id = ! empty( $_GET['revision'] ) ? $_GET['revision'] : $_GET['from'];
			$revision    = get_post( $revision_id );
			$post        = get_post( $revision->post_parent );

			if ( CODE_REVISIONS_POST_TYPE == $post->post_type )
				return $post->ID;
		}

		return 0;
	}

	/**
	 * Redirect from post editor to file editor.
	 *
	 * @since 0.2
	 * @uses wp_redirect
	 */
	public function redirect() {
		if ( ! isset( $_REQUEST['post'] ) || ! is_numeric( $_REQUEST['post'] ) )
			return;

		// get post data
		$post = get_post( $_REQUEST['post'] );

		// check post type
		if ( $post->post_type != CODE_REVISIONS_POST_TYPE )
			return;

		// It's a file, not a post! Redirect to corresponding code editor.
		extract( get_post_meta( $post->ID, $this->metakey, true ) );
		wp_redirect( admin_url( "$type-editor.php?$type=".urlencode($package)."&file=".urlencode($file) ) );
		exit;
	}

	/**
	 * Update file contents on revision restore.
	 *
	 * @since 0.3
	 * @see wp_restore_post_revision(), wp-includes/revision.php:328
	 */
	public function restore( $post_id, $revision_id ) {
		// get post data
		$post = get_post( $post_id );

		// check post type
		if ( $post->post_type != CODE_REVISIONS_POST_TYPE )
			return;

		$meta = get_post_meta( $post->ID, $this->metakey, true );
		extract( $meta ); // type, package, file

		// get absolute file path
		if ( 'theme' == $type ) {
			$t   = wp_get_theme( $package );
			$abs = $t->get_stylesheet_directory() . '/' . $file;
		} else {
			$abs = WP_PLUGIN_DIR . '/' . $file;
		}

		if ( ! is_writeable( $abs ) )
			return;

		// open, write and close file
		$f = fopen( $abs, 'w+' );
		fwrite( $f, $post->post_content );
		fclose( $f );

		// update checksum in database
		$meta['checksum'] = md5( $post->post_content );
		update_post_meta( $post->ID, $this->metakey, $meta );

		// theme specific: reset cache
		if ( 'theme' == $type ) {
			$theme = wp_get_theme( $package );
			$theme->cache_delete();
		}

		// plugin specific: deactivate and reactivate for error checking
		else {
			$networkwide = is_plugin_active_for_network( $package );

			// deactivate plugin
			if ( is_plugin_active( $package ) )
				deactivate_plugins( $package, true );

			if ( ! is_network_admin() )
				update_option( 'recently_activated', array( $package => time() ) + (array) get_option( 'recently_activated' ) );

			$error = validate_plugin( $package );
			if ( is_wp_error( $error ) )
				wp_die( $error );

			// reactivate plugin
			if ( $networkwide || ! is_plugin_active( $package ) )
				activate_plugin($package, '', $networkwide );
		}
	}

	/**
	 * Hooks into the theme uninstall process and removes related code revision posts.
	 *
	 * @since 1.0
	 * @see   wp-admin/themes.php:17
	 */
	public function uninstall_theme() {
		if ( ! current_user_can('delete_themes') || empty( $_GET['action'] ) || 'delete' != $_GET['action'] || ! wp_verify_nonce( $_GET['_wpnonce'], 'delete-theme_' . $_GET['stylesheet'] ) )
			return;

		$theme = wp_get_theme( $_GET['stylesheet'] );
		if ( ! $theme->exists() )
			return;

		$this->uninstall_packages( array( $_GET['stylesheet'] ) );
	}

	/**
	 * Hooks into the plugin uninstall process and removes related code revision posts.
	 *
	 * @since 1.0
	 * @see   wp-admin/plugins.php:204
	 */
	public function uninstall_plugin() {
		if ( ! isset( $_REQUEST['verify-delete'] ) || empty ( $_GET['action'] ) || 'delete-selected' != $_GET['action'] || ! current_user_can('delete_plugins') || ! wp_verify_nonce( $_GET['_wpnonce'], 'bulk-plugins' ) )
			return;

		$plugins = isset( $_REQUEST['checked'] ) ? (array) $_REQUEST['checked'] : array();
		$plugins = array_filter( $plugins, 'is_plugin_inactive' );
		if ( empty( $plugins ) )
			return;

		$this->uninstall_packages( $plugins );
	}

	/**
	 * Used for removing code revisions associated with a specific package (theme or plugin).
	 *
	 * @since 1.0
	 *
	 * @param array $packages package name strings (either theme stylesheet or 'plugin/plugin.php')
	 */
	private function uninstall_packages( $packages ) {
		$posts = get_posts( array(
			'numberposts' => -1,
			'post_type'   => CODE_REVISIONS_POST_TYPE,
			'post_status' => 'any',
			'meta_key'    => $this->metakey,
		) );

		foreach ( $posts as $post ) {
			$meta = get_post_meta( $post->ID, $this->metakey, true );
			if ( ! empty( $meta ) && in_array( $meta['package'], $packages ) )
				wp_delete_post( $post->ID );
		}
	}

	/**
	 * Delete code revision posts and post meta fields on plugin uninstall.
	 *
	 * @since 0.8
	 */
	public static function uninstall() {
		if ( ! current_user_can( 'activate_plugins' ) )
			return;

		check_admin_referer( 'bulk-plugins' );

		$posts = get_posts( array(
			'numberposts' => -1,
			'post_type'   => CODE_REVISIONS_POST_TYPE,
			'post_status' => 'any',
		) );

		foreach ( $posts as $post )
			wp_delete_post( $post->ID );
	}

}
