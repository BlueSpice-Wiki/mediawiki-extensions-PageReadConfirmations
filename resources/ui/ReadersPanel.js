ext.pageReadConfirmations.ReadersPanel = function( config ) {
	this.grid = null;
	this.dialog = config.dialog;

	ext.pageReadConfirmations.ReadersPanel.super.call( this, Object.assign( {
		expanded: false,
		padded: false
	}, config ) );
};

OO.inheritClass( ext.pageReadConfirmations.ReadersPanel, OO.ui.PanelLayout );

ext.pageReadConfirmations.ReadersPanel.prototype.init = async function () {
	if ( !this.grid ) {
		await this.initConfirmationPanel();
	}
};

ext.pageReadConfirmations.ReadersPanel.prototype.initConfirmationPanel = async function () {
	this.$element.append( new OO.ui.ProgressBarWidget( { progress: false } ).$element );
	await mw.loader.using( [ 'ext.oOJSPlus.data', 'ext.oOJSPlus.widgets' ] );
	this.$element.empty();

	this.confirmationStore = new OOJSPlus.ui.data.store.RemoteRestStore( {
		path: 'page_read_confirmations/' + mw.config.get( 'wgArticleId' ),
		pageSize: 25
	} );

	this.grid = new OOJSPlus.ui.data.GridWidget( {
		sortable: true,
		store: this.confirmationStore,
		columns: {
			user_name: { // eslint-disable-line camelcase
				headerText: mw.msg( 'page-read-confirmations-grid-column-user' ),
				type: 'user',
				showImage: true
			},
			prc_rev: {
				headerText: mw.msg( 'page-read-confirmations-grid-column-read-version' ),
				type: 'text',
				valueParser: function ( value, row ) {
					return new OO.ui.HtmlSnippet( row.revision_link || '-' );
				}
			},
			prc_read_at: {
				headerText: mw.msg( 'page-read-confirmations-grid-column-read-time' ),
				type: 'text',
				valueParser: function ( value, row ) {
					return row.read_at_for_user;
				}
			},
			has_confirmed: {
				headerText: mw.msg( 'page-read-confirmations-grid-column-status' ),
				type: 'boolean',
				width: 40
			}
		}
	} );
	this.grid.connect( this, {
		datasetChange: function () {
			this.dialog.updateSize();
		}
	} );
	this.$element.append( this.grid.$element );
};
