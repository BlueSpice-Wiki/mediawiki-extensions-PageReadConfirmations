ext.pageReadConfirmations.ReadersPage = function( name, config ) {
	ext.pageReadConfirmations.ReadersPage.super.call( this, name, config );
	this.panel = new ext.pageReadConfirmations.ReadersPanel( config );
	this.dialog = config.dialog;
	this.$element.append( this.panel.$element );
};

OO.inheritClass( ext.pageReadConfirmations.ReadersPage, StandardDialogs.ui.BasePage );

ext.pageReadConfirmations.ReadersPage.prototype.setupOutlineItem = function () {
	ext.pageReadConfirmations.ReadersPage.super.prototype.setupOutlineItem.apply( this, arguments );

	if ( this.outlineItem ) {
		this.outlineItem.setLabel( mw.message( 'page-read-confirmations-label' ).plain() );
	}
};

ext.pageReadConfirmations.ReadersPage.prototype.setup = function () {
	return;
};

ext.pageReadConfirmations.ReadersPage.prototype.onInfoPanelSelect = async function () {
	this.dialog.setSize( 'larger' );
	await this.panel.init();
};

if ( ext.pageReadConfirmations._currentPageSupported() ) {
	registryPageInformation.register( 'read_confirmations', ext.pageReadConfirmations.ReadersPage );
}
