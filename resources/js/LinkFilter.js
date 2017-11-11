/**
 * JavaScript for LinkFilter extension
 *
 * @file
 */
var LinkFilter = {
	/**
	 * Perform an administrative action (approval or rejection) on a submitted
	 * link.
	 *
	 * @param {Number} Action to perform (1 = accept, 2 = reject)
	 * @param {Number} ID of the link to approve or reject
	 */
	linkAction: function( action, link_id ) {
		$( 'div.action-buttons-1' ).hide();

		( new mw.Api() ).postWithToken( 'edit', {
			action: 'linkfilter',
			id: link_id,
			status: action,
			format: 'json'
		} ).done( function( data ) {
			var msg;
			switch ( action ) {
				case 1:
					msg = mw.msg( 'linkfilter-admin-accept-success' );
					break;
				case 2:
					msg = mw.msg( 'linkfilter-admin-reject-success' );
					break;
			}
			$( '#action-buttons-' + link_id ).html( msg ).show( 1000 );
		} );
	},

	/**
	 * Perform some JS-side validation when a user tries to submit a link.
	 * These are duplicated in PHP, obviously, but having 'em here means that
	 * we don't "force" a page reload for mobile users (for example) should they
	 * forget to fill in one of the mandatory fields.
	 *
	 * Provided that all the mandatory fields have been filled in, this submits
	 * the form.
	 */
	submitLink: function() {
		if (
			typeof mw.config.get( 'wgCanonicalSpecialPageName' ) !== 'undefined' &&
			mw.config.get( 'wgCanonicalSpecialPageName' ) !== 'LinkEdit'
		)
		{
			if ( document.getElementById( 'lf_title' ).value === '' ) {
				alert( mw.msg( 'linkfilter-submit-no-title' ) );
				return '';
			}
		}
		if ( document.getElementById( 'lf_desc' ).value === '' ) {
			alert( mw.msg( 'linkfilter-submit-no-desc' ) );
			return '';
		}
		if (
			document.getElementById( 'lf_URL' ).value === '' ||
			document.getElementById( 'lf_URL' ).value == 'http://' // this is the default value
		)
		{
			alert( mw.msg( 'linkfilter-submit-no-url' ) );
			return '';
		}
		if ( document.getElementById( 'lf_type' ).value === '' ) {
			alert( mw.msg( 'linkfilter-submit-no-type' ) );
			return '';
		}
		document.link.submit();
	},

	/**
	 * Update the "X characters remain" message as the user types
	 *
	 * @param {HTMLTextAreaElement} Name of the field whose length we're checking
	 * @param {Number} Maximum amount of characters allowed to be entered
	 */
	limitText: function( field, limit ) {
		if ( field.value.length > limit ) {
			field.value = field.value.substring( 0, limit );
		}
		document.getElementById( 'desc-remaining' ).innerHTML = limit - field.value.length;
	}
};

$( function() {
	// "Accept" links on Special:LinkApprove
	$( 'a.action-accept' ).click( function() {
		var that = $( this );
		LinkFilter.linkAction( 1, that.data( 'link-id' ) );
	} );

	// "Reject" links on Special:LinkApprove
	$( 'a.action-reject' ).click( function() {
		var that = $( this );
		LinkFilter.linkAction( 2, that.data( 'link-id' ) );
	} );

	// Textarea on Special:LinkEdit/Special:LinkSubmit
	$( 'textarea.lr-input' ).on( 'keyup', function() {
		LinkFilter.limitText( document.link.lf_desc, 300 );
	} ).on( 'keydown', function() {
		LinkFilter.limitText( document.link.lf_desc, 300 );
	} );

	// Submit button on Special:LinkEdit/Special:LinkSubmit
	$( '#link-submit-button' ).click( function() {
		LinkFilter.submitLink();
	} );
} );