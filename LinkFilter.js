/**
 * JavaScript for LinkFilter extension
 *
 * @file
 * @author Jack Phoenix <jack@countervandalism.net>
 * @date 29 August 2013
 */
var LinkFilter = {
	linkAction: function( action, link_id ) {
		jQuery( 'div.action-buttons-1' ).hide();

		jQuery.post(
			mw.util.wikiScript( 'api' ), {
				action: 'linkfilter',
				id: link_id,
				status: action,
				format: 'json'
			},
			function( data ) {
				var msg;
				switch ( action ) {
					case 1:
						msg = mw.msg( 'linkfilter-admin-accept-success' );
						break;
					case 2:
						msg = mw.msg( 'linkfilter-admin-reject-success' );
						break;
				}
				jQuery( '#action-buttons-' + link_id ).html( msg ).show( 1000 );
			}
		);
	},

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
		if ( document.getElementById( 'lf_type' ).value === '' ) {
			alert( mw.msg( 'linkfilter-submit-no-type' ) );
			return '';
		}
		document.link.submit();
	},

	limitText: function( field, limit ) {
		if ( field.value.length > limit ) {
			field.value = field.value.substring( 0, limit );
		}
		document.getElementById( 'desc-remaining' ).innerHTML = limit - field.value.length;
	}
};

jQuery( document ).ready( function() {
	// "Accept" links on Special:LinkApprove
	jQuery( 'a.action-accept' ).click( function() {
		var that = jQuery( this );
		LinkFilter.linkAction( 1, that.data( 'link-id' ) );
	} );

	// "Reject" links on Special:LinkApprove
	jQuery( 'a.action-reject' ).click( function() {
		var that = jQuery( this );
		LinkFilter.linkAction( 2, that.data( 'link-id' ) );
	} );

	// Textarea on Special:LinkEdit/Special:LinkSubmit
	jQuery( 'textarea.lr-input' ).bind( 'keyup', function() {
		LinkFilter.limitText( document.link.lf_desc, 300 );
	} ).bind( 'keydown', function() {
		LinkFilter.limitText( document.link.lf_desc, 300 );
	} );

	// Submit button on Special:LinkEdit/Special:LinkSubmit
	jQuery( '#link-submit-button' ).click( function() {
		LinkFilter.submitLink();
	} );
} );