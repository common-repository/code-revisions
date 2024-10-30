<?php
/**
 * Class which contains functionality for the editor pages. Only required to be
 * loaded there.
 *
 * @since 0.1
 */
class Code_Revisions_Editors {

	/**
	 * ID of the post associated with the current file
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Key of the post meta field containing the file's meta data
	 *
	 * @var string
	 */
	private $metakey;

	/**
	 * File meta data, also saved in post meta: "type", "package", "file"
	 *
	 * @var associative array
	 */
	private $meta;

	/**
	 * File title: "Package: subfolder/file.ext"
	 *
	 * @var string
	 */
	private $title;

	/**
	 * Initializes all actions needed on editor pages.
	 *
	 * @since 0.1
	 *
	 * @param string $metakey key used for the custom post meta containing file info
	 */
	public function __construct() {
		$this->init();

		add_action( 'admin_enqueue_scripts', array( $this, 'scripts'       ) );
		add_action( 'admin_notices',         array( $this, 'syntax_notice' ) );
	}

	/**
	 * Initializes class variables and more, depending on request data.
	 *
	 * @since 0.1
	 */
	public function init() {
		$this->metakey = '_' . str_replace( '-', '_', CODE_REVISIONS_NAME );

		// generate meta class variables (meta & title)
		$this->generate_meta();

		// get id of the post associated with the current file
		$this->id = $this->retrieve();

		// remove the old draft if we are not viewing it
		if ( ! isset( $_GET['syntax_error'] ) )
			delete_post_meta( $this->id, $this->metakey . '_draft' );

		// file update process
		if ( isset( $_POST['action'] ) && 'update' == $_POST['action'] )
			$this->handle_file_update();
		else
			$this->handle_direct_changes();
	}

	/**
	 * Enqueue scripts required on theme-editor.php and plugin-editor.php
	 *
	 * @since 0.2
	 *
	 * @param string $pagenow current admin page
	 */
	function scripts( $pagenow ) {
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

		wp_register_style( 'code_revisions_editors', CODE_REVISIONS_URL . "css/editors$suffix.css", array(), CODE_REVISIONS_VER );
		wp_enqueue_style(  'code_revisions_editors' );

		wp_register_script( 'code_revisions_editors', CODE_REVISIONS_URL . "js/editors$suffix.js", array( 'jquery' ), CODE_REVISIONS_VER, true );
		wp_enqueue_script(  'code_revisions_editors' );

		$revisions = wp_get_post_revisions( $this->id );
		$count     = number_format_i18n( count( $revisions ) );
		$link      = esc_url( get_edit_post_link( key( $revisions ) ) );

		wp_localize_script( 'code_revisions_editors', '_code_revisions', array(
			'post_id'        => $this->id,
			'revisions_list' => $this->get_revisions_list( $this->id ),
			'line'           => isset( $_GET['syntax_error'] ) ? urldecode( $_GET['syntax_error'] ) : '',
			'newcontent'     => get_post_meta( $this->id, $this->metakey . '_draft', true ),
			'text'           => $count ? sprintf( __( 'This file has %1$s %2$s. %3$sBrowse%4$s', 'code-revisions' ), "<b>$count</b>", _n( 'revision', 'revisions', $count, 'code-revisions' ), "<a class=hide-if-no-js' href='$link'>", "</a>" ) : '',
		));
	}

	/**
	 * Process the information we can obtain from the GET or POST request;
	 * if there is no data generate default data.
	 *
	 * @since 0.1
	 */
	private function generate_meta() {
		global $pagenow;
		$this->meta['type'] = substr( $pagenow, 0, strpos( $pagenow, '-' ) );

		// Use _GET and _POST data
		$file    = $f = ! empty( $_REQUEST['file'  ] ) ? $_REQUEST['file'  ] : ''  ;
		$package = $p = ! empty( $_REQUEST['theme' ] ) ? $_REQUEST['theme' ] :
		              ( ! empty( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '' );

		// theme specific
		if ( 'theme' == $this->meta['type'] ) {
			// Get default values if necessary
			$package = $p = ! empty( $package ) ? $package : get_stylesheet();
			$file    = $f = ! empty( $file    ) ? $file    : 'style.css';
		}

		// plugin specific
		else {
			// Passed $package often contains wrong values or is empty, fix it
			if ( empty( $package ) || strpos($package, '/') ) {
				$path    = explode( '/', $file );
				$data    = get_plugins( '/' . $path[0] );
				$plugins = array_keys( $data );
				$package = ltrim( $path[0] . '/' . $plugins[0], '/' );

			// plugins without subfolder
			} else {
				$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $package );
			}

			// file is either passed or equal to $package
			$file = empty( $file ) ? $package : $file;

			// get clean plugin and file name
			$path = explode( '/', $file );
			array_shift( $path ); // get rid of plugin folder
			$f = ! empty( $path[0] ) ? implode( '/', $path ) : $file;
			$p = ! empty( $data['Name'] ) ? $data['Name'] : $data[ $plugins[0] ]['Name'];
		}

		// save meta array
		$this->meta['package' ] = $package;
		$this->meta['file'    ] = $file;
		$this->meta['checksum'] = md5_file( $this->get_abs() );

		// build post title
		$this->title = "$p: $f";
	}

	/**
	 * Gets the file's absolute path.
	 *
	 * @since 0.4
	 *
	 * @return string absolute file path
	 */
	private function get_abs() {
		extract( $this->meta );
		return 'theme' == $type ? get_theme_root() . "/$package/$file" : WP_PLUGIN_DIR . "/$file";
	}

	/**
	 * If the file contents have actually changed this function inserts a post
	 * (if there is none for this file yet) and updates it.
	 *
	 * @since 0.1
	 *
	 * @return boolean success/failure; might not return but redirect
	 */
	private function handle_file_update() {
		$old = @file_get_contents( $this->get_abs() );

		// Did we successfully get the file?
		if ( false === $old )
			return false;

		// get new file content
		$new = wp_unslash( $_POST['newcontent'] );

		// did the file contents change?
		if ( $old === $new )
			return false;

		// file wasn't saved to the database yet: save with old content
		if ( ! $existed_before = $this->id )
			$this->id = $this->dispatch( $old );

		// check php files for parse errors
		$check = 'php' == pathinfo( $this->get_abs(), PATHINFO_EXTENSION) ? $this->check_syntax( $new ) : true;

		// update database entry with new content if there are no errors
		if ( empty( $check['message'] ) ) {
			// required so the two revisions have different timestamps for restoring
			if ( ! $existed_before ) sleep( 1 );

			return 0 < $this->dispatch( $new );

		// syntax error found, save draft and redirect with error message
		} else {
			// save draft
			update_post_meta( $this->id, $this->metakey . '_draft', wp_slash( htmlspecialchars( $new ) ) );

			// error info
			$line    = ! empty( $check['line'   ] ) ? $check['line'   ] : 0;
			$message = ! empty( $check['message'] ) ? $check['message'] : 'Your changes contain errors, not saved.';

			// redirect with error information and autosave id
			wp_redirect( admin_url( $this->meta['type'].'-editor.php?file=' . urlencode( $this->meta['file'] ) . '&'.$this->meta['type'].'=' . urlencode( $this->meta['package'] ) . '&syntax_error=' . urlencode( $line ) . '&error_message=' . urlencode( $message ) ) );
			exit;
		}
	}

	/**
	 * Checks if the file changed since last visit and, if thats the case,
	 * notifies the user and updates the post.
	 *
	 * @since  0.4
	 */
	private function handle_direct_changes() {
		$meta = get_post_meta( $this->id, $this->metakey, true );

		if ( empty( $meta['checksum'] ) || $meta['checksum'] == $this->meta['checksum'] )
			return;

		// add admin notice
		add_action( 'admin_notices', array( $this, 'notice' ) );

		// read file
		$new = @file_get_contents( $this->get_abs() );

		// error occured when reading the file
		if ( false === $new )
			return false;

		// update post
		$this->dispatch( $new );
	}

	/**
	 * Used for displaying an admin notice above the editor which notifies the user
	 * that the file changed since his last visit.
	 *
	 * @since 0.4
	 * @uses wp_get_post_revisions()
	 * @uses get_edit_post_link()
	 */
	public function notice() {
		$revision = array_keys( wp_get_post_revisions( $this->id, array( 'numberposts' => 1 ) ) );
		$link = get_edit_post_link( $revision[0] );
		echo '<div class="updated"><p>' . __('This file has changed in the meantime.', 'code-revisions') . "&nbsp;<a href='$link'>" . __('View changes', 'code-revisions') . '</a></p></div>';
	}

	/**
	 * Used for displaying the admin notice when the file changes contained
	 *
	 * @since 0.5
	 */
	public function syntax_notice() {
		if ( ! isset( $_GET['syntax_error'] ) || empty( $_GET['error_message'] ) || ! is_numeric( $_GET['syntax_error'] ) )
			return;

		$line    = urldecode( $_GET['syntax_error'] );
		$message = ucfirst( urldecode( stripslashes( $_GET['error_message'] ) ) );

		if ( $line > 0 )
			echo '<div class="error"><p>File not saved, error in <a href="#" id="highlight_line">line ' . $line . '</a>: ' . $message . '</p></div>';
		else
			echo '<div class="error"><p>File not saved, an error occured: ' . $message . '</p></div>';
	}

	/**
	 * Basic php syntax checking.
	 *
	 * @since  0.5
	 *
	 * @param  string $code php code to check for syntax errors
	 * @return array/boolean    array contains type, message and line of error, if no error found returns true
	 */
	function check_syntax( $code ) {

		// use php -l if available
		if ( function_exists( 'system' ) ) {

			// create temporary file with the code as contents
			$path = $this->get_abs();
			$info = pathinfo( $path );
			$tmp = tempnam( $info['dirname'], 'tmp-' );
			file_put_contents( $tmp, $code );

			$bin = defined( 'PHP_BINARY' ) ? PHP_BINARY : 'php';

			// Check file using php -l
			ob_start();
			system( "{$bin} -l '" . escapeshellarg( $tmp ) . "'" );
			$output = ob_get_clean();

			// process output
			if ( ! empty( $output ) ) {
				$path_tmp = pathinfo( $tmp );
				$output = str_replace( $tmp, $info['basename'], $output );

				preg_match( '/(.+) in .+ on line (\d+)\n/', $output, $match );

				$error['message'] = ! empty( $match[1] ) ? $match[1] : '';
				$error['line']    = ! empty( $match[2] ) ? $match[2] : '';
			}

			// delete tmp file
			unlink( $tmp );
		}

		// use eval
		if ( empty( $output ) ) {
			ob_start();
			var_dump(eval("return true; if(0){?>{$code}?><?php };"));
			$error = error_get_last(); // type, message, line
			$output = ob_end_clean();
		}

		// return result
		return ( ! empty( $error['message'] ) ) ? $error : true;
	}

	/**
	 * Depending on if a post for this file already exists in the database the
	 * function either creates a new post or updates the existing post with the
	 * new content.
	 *
	 * @since 0.4
	 * @uses wp_update_post()
	 * @uses wp_insert_post()
	 * @uses wp_save_post_revision()
	 *
	 * @param  string $content new file contents
	 * @return int             post id on success, otherwise zero
	 */
	private function dispatch( $content ) {
		$args = array(
			'post_type'    => CODE_REVISIONS_POST_TYPE,
			'post_title'   => $this->title,
			'post_name'    => sanitize_title( $this->title ),
			'post_content' => wp_slash( $content ),
			'post_status'  => 'private',
		);

		// insert post and add first revision
		if ( ! $this->id ){
			$id = wp_insert_post( $args );
			wp_save_post_revision( $id );
		}

		// update post
		else {
			$args['ID'] = $this->id;
			$id = wp_update_post($args);
			delete_post_meta( $this->id, $this->metakey . '_draft' );
		}

		// update/insert post meta with new checksum
		$this->meta['checksum'] = md5( $content );
		update_post_meta( $id, $this->metakey, $this->meta );

		return $id;
	}

	/**
	 * Retrieves the ID of the post which corresponds to the current file
	 * (specified by $this->title) from the database.
	 *
	 * @since 0.1
	 * @uses get_posts()
	 *
	 * @return int post_id on success : zero on error
	 */
	public function retrieve() {
		$args = array(
			'name'        => sanitize_title( $this->title ),
			'post_type'   => CODE_REVISIONS_POST_TYPE,
			'post_status' => 'private',
			'numberposts' => 1,
		);
		$post = get_posts( $args );
		return isset( $post[0] ) ? $post[0]->ID : 0;
	}

	/**
	 * wp_list_post_revisions() directly echos it's results. We want to work
	 * with it in JS and therefore need to buffer it. metabox.php furthermore adds
	 * the layout around the revisions list so we get a metabox similar to the
	 * originals on the post and page edit screens.
	 *
	 * NOTE: metabox.php requires $id to be set.
	 *
	 * @since 0.2
	 * @uses code-revisions/inc/metabox.php
	 * @uses  wp_list_post_revisions()
	 *
	 * @param  int    $id The id of the post to get the revisions list for
	 * @return string     Revisions list ready for printing.
	 */
	function get_revisions_list( $id ) {
		if ( ! $id )
			return;

		ob_start();
		include_once( CODE_REVISIONS_DIR . 'inc/metabox.php' );
		return ob_get_clean();
	}

}
