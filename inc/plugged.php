<?php
if ( ! function_exists( 'wp_text_diff' ) ) {
	/**
	 * Overwritting pluggable function wp_text_diff to prettify indentation and for
	 * preserving empty lines on wp-admin/revisions.php for code revisions.
	 * See original phpdoc in wp-includes/pluggable.php#L1675 for more information
	 * on this function.
	 *
	 * @since 0.7
	 * @see  wp-includes/pluggable.php
	 * @see  http://core.trac.wordpress.org/browser/trunk/wp-includes/pluggable.php?rev=24490#L1675
	 */
	function wp_text_diff( $left_string, $right_string, $args = null ) {
		$defaults = array( 'title' => '', 'title_left' => '', 'title_right' => '' );
		$args = wp_parse_args( $args, $defaults );

		if ( !class_exists( 'WP_Text_Diff_Renderer_Table' ) )
			require( ABSPATH . WPINC . '/wp-diff.php' );

		// ***
		if( ! empty( $_REQUEST['post_id'] ) ) {
			$post = get_post( $_REQUEST['post_id'] );
		} else {
			$revision_id = ! empty( $_GET['revision'] ) ? $_GET['revision'] :
			             ( ! empty( $_GET['from'    ] ) ? $_GET['from'    ] : '' );
			$revision = get_post( $revision_id );
			$post     = get_post( $revision->post_parent );
		}

		if ( ! isset( $post ) || CODE_REVISIONS_POST_TYPE != $post->post_type ) {
			$left_string  = normalize_whitespace($left_string);
			$right_string = normalize_whitespace($right_string);
		}
		// ***

		$left_lines  = explode("\n", $left_string);
		$right_lines = explode("\n", $right_string);
		$text_diff = new Text_Diff($left_lines, $right_lines);
		$renderer  = new WP_Text_Diff_Renderer_Table( $args );
		$diff = $renderer->render($text_diff);

		if ( !$diff )
			return '';

		$r  = "<table class='diff'>\n";

		if ( ! empty( $args[ 'show_split_view' ] ) ) {
			$r .= "<col class='content diffsplit left' /><col class='content diffsplit middle' /><col class='content diffsplit right' />";
		} else {
			$r .= "<col class='content' />";
		}

		if ( $args['title'] || $args['title_left'] || $args['title_right'] )
			$r .= "<thead>";
		if ( $args['title'] )
			$r .= "<tr class='diff-title'><th colspan='4'>$args[title]</th></tr>\n";
		if ( $args['title_left'] || $args['title_right'] ) {
			$r .= "<tr class='diff-sub-title'>\n";
			$r .= "\t<td></td><th>$args[title_left]</th>\n";
			$r .= "\t<td></td><th>$args[title_right]</th>\n";
			$r .= "</tr>\n";
		}
		if ( $args['title'] || $args['title_left'] || $args['title_right'] )
			$r .= "</thead>\n";

		$r .= "<tbody>\n$diff\n</tbody>\n";
		$r .= "</table>";

		return $r;
	}
}
