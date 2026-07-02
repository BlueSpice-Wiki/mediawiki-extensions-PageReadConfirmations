ext.pageReadConfirmations.ConfirmationPage = function( name, config ) {
	ext.pageReadConfirmations.ConfirmationPage.super.call( this, name, config );
	this.panel = new ext.pageReadConfirmations.ConfirmationsPanel( config );
	this.dialog = config.dialog;
	this.$element.append( this.panel.$element );
};

OO.inheritClass( ext.pageReadConfirmations.ConfirmationPage, StandardDialogs.ui.BasePage );

ext.pageReadConfirmations.ConfirmationPage.prototype.setupOutlineItem = function () {
	ext.pageReadConfirmations.ConfirmationPage.super.prototype.setupOutlineItem.apply( this, arguments );

	if ( this.outlineItem ) {
		this.outlineItem.setLabel( mw.message( 'page-read-confirmations-label' ).plain() );
	}
};

ext.pageReadConfirmations.ConfirmationPage.prototype.setup = function () {
	return;
};

ext.pageReadConfirmations.ConfirmationPage.prototype.onInfoPanelSelect = async function () {
	this.dialog.setSize( 'larger' );
	await this.panel.init();
};

if ( ext.pageReadConfirmations._currentPageSupported() ) {
	registryPageInformation.register( 'read_confirmations', ext.pageReadConfirmations.ConfirmationPage );
}
