$( () => {
	$( '.page-read-confirmations-another-version-button' ).each( ( i, el ) => {
		const btn = OO.ui.infuse( $( el ) );
		const data = JSON.parse( btn.getData() );
		const title = mw.Title.newFromText( mw.config.get( 'wgPageName' ) )
			.getUrl( { oldid: data.mustRead } );

		const popup = new OO.ui.PopupWidget( {
			$anchor: btn.$element,
			padded: true,
			$content: $( '<div>' ).addClass( 'page-read-confirmations-another-version-popup' ).append(
				new OO.ui.LabelWidget( {
					label: mw.message( 'page-read-assignments-has-another-request-label' ).parse()
				} ).$element,
				new OO.ui.ButtonWidget( {
					label: mw.msg( 'page-read-assignments-has-another-request-button' ),
					href: title,
					flags: [ 'primary', 'progressive' ]
				} ).$element
			)
		} );
		btn.$element.parent().append( popup.$element );
		btn.connect( this, {
			click: function () {
				popup.toggle();
			}
		} );
	} );
} );
