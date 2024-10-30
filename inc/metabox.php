<?php
// structure with classes and ids taken from the code generated on post.php

if ( empty( $id ) )
	die( 'For using the revisions list metabox template $id needs to be set:' . CODE_REVISIONS_DIR . 'inc/metabox.php' );
?>

<div class="metabox-holder">
	<div class="postbox" id="revisionsdiv">
		<h3 class="hndle">
			<span>
				Revisions
			</span>
		</h3>
		<div class="inside">
			<?php wp_list_post_revisions( $id, 'all' ); ?>

		</div>
	</div>
</div>
