/**
 * JavaScript for LinkFilter extension
 *
 * @file
 * @author Jack Phoenix <jack@countervandalism.net>
 * @date 20 June 2011
 */
var LinkFilter = {
	linkAction: function( action, link_id ) {
		var acceptMsg, rejectMsg;
		if( typeof( mediaWiki ) == 'undefined' ) {
			//acceptMsg = 'The link was accepted';
			//rejectMsg = 'The link was rejected';
			acceptMsg = _LINKFILTER_ACCEPT;
			rejectMsg = _LINKFILTER_REJECT;
		} else {
			acceptMsg = mediaWiki.msg( 'linkfilter-admin-accept-success' );
			rejectMsg = mediaWiki.msg( 'linkfilter-admin-reject-success' );
		}
		getElementsByClassName( document, 'div', 'action-buttons-1' ).display = 'none';
		sajax_request_type = 'POST';
		sajax_do_call(
			'wfLinkFilterStatus',
			[ link_id, action ], 
			function( response ) {
				var msg;
				switch( action ) {
					case 1:
						msg = acceptMsg;
						break;
					case 2:
						msg = rejectMsg;
						break;
				}
				var elementToDisplay = document.getElementById( 'action-buttons-' + link_id );
				elementToDisplay.display = 'block';
				elementToDisplay.innerHTML = msg;
			}
		);
	},

	submitLink: function() {
		var noTitleMsg, noTypeMsg;
		if( typeof( mediaWiki ) == 'undefined' ) {
			//noTitleMsg = 'Please enter a title';
			//noTypeMsg = 'Hey, pick a link type!';
			noTitleMsg = _LINKFILTER_NO_TITLE;
			noTypeMsg = _LINKFILTER_NO_TYPE;
		} else {
			noTitleMsg = mediaWiki.msg( 'linkfilter-submit-no-title' );
			noTypeMsg = mediaWiki.msg( 'linkfilter-submit-no-type' );
		}
		if (
			typeof( wgCanonicalSpecialPageName ) !== 'undefined' &&
			wgCanonicalSpecialPageName !== 'LinkEdit'
		)
		{
			if( document.getElementById( 'lf_title' ).value === '' ) {
				alert( noTitleMsg );
				return '';
			}
		}
		if( document.getElementById( 'lf_type' ).value === '' ) {
			alert( noTypeMsg );
			return '';
		}
		document.link.submit();
	},

	limitText: function( field, limit ) {
		if( field.value.length > limit ) {
			field.value = field.value.substring( 0, limit );
		}
		document.getElementById( 'desc-remaining' ).innerHTML = limit - field.value.length;
	}
};