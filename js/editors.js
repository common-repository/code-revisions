jQuery(document).ready( function($) {

	data = _code_revisions;

	/**
	 * Add revisions link below code editor.
	 *
	 * @since 0.6
	 */
	$('#template #submit').after(
		'<span class="revisions_link">'+data.text+'</span>'
	);

	/**
	 * Add revisions list below code editor textarea if there are any revisions
	 *
	 * @since 0.2
	 */
	if ( 0 < data.post_id ) {
		$('#template').append( data.revisions_list );
	}

	/**
	 * Highlight a specific line in the code editor textarea.
	 *
	 * @since 0.5
	 *
	 * @param int line line number
	 */
	function selectLine( line ) {
		line--; // array counting style
		var t = $('#newcontent');

		var lines = t.val().split("\n"),
		    start = 0;

		for ( var i = 0; i < lines.length; i++ ) {
			if ( i == line )
				break;
			start += (lines[i].length+1);
		}
		var end = lines[line].length+start;

    if( typeof t[0].selectionStart != "undefined" ) {
        t.focus();
        t[0].selectionStart = start;
        t[0].selectionEnd   = end;
        return true;
    }

    // IE
    if ( document.selection && document.selection.createRange ) {
        t.focus();
        t.select();
        var range = document.selection.createRange();
        range.collapse(true);
        range.moveEnd("character", end);
        range.moveStart("character", start);
        range.select();
        return true;
    }

    return false;
	}

	// If there is temporary code editor insert it in the textarea
	if ( data.newcontent.length > 0 )
		$('#newcontent').val( data.newcontent );

	// If a line for highlighting is passed, highlight it
	if ( data.line.length > 0 ) {
		selectLine( data.line );

		$('#highlight_line').click( function() {
			selectLine( data.line );
		});
	}

});