window.ext = window.ext || {};
window.ext.pageReadConfirmations = {
	ui: {
		workflows: {}
	},
	_currentPageSupported: function () {
		return mw.config.get( 'wgArticleId' );
	}
};

$( () => {
	$( '#ca-readConfirmationAssign' ).on( 'click', ( e ) => { // eslint-disable-line no-unused-vars
		mw.loader.using( 'ext.pageReadConfirmations.assignments', () => {
			const wm = OO.ui.getWindowManager();
			const dialog = new ext.pageReadConfirmations.ui.AssignmentDialog();
			wm.addWindows( [ dialog ] );
			wm.openWindow( dialog ).closed.then( ( data ) => {
				if ( data && data.action === 'save' ) {
					window.location.reload();
				}
			} );
		} );
	} );

	$( '.page-read-confirmations-confirm-button' ).on( 'click', ( e ) => { // eslint-disable-line no-unused-vars
		OO.ui.confirm(
			mw.msg( 'page-read-confirmations-confirm-confirmation' ), {
				title: mw.msg( 'page-read-confirmations-confirm-confirmation-title' ),
				actions: [
					{
						label: mw.msg( 'page-read-confirmations-action-cancel' ),
						action: 'cancel'
					},
					{
						label: mw.msg( 'page-read-confirmations-action-confirm' ),
						flags: [ 'progressive' ],
						action: 'accept'
					}
				]
			} )
			.done( async ( confirmed ) => {
				if ( !confirmed ) {
					return;
				}
				try {
					const res = await ext.pageReadConfirmations.api.confirmRead( mw.config.get( 'wgRevisionId' ) );
					if ( !res.success ) {
						throw new Error( 'API error' );
					}
					window.location.reload();
				} catch ( e ) { // eslint-disable-line no-shadow
					OO.ui.alert( mw.msg( 'page-read-confirmations-error' ), { type: 'error' } );
				}
			} );
	} );
} );
