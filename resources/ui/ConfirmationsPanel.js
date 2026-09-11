ext.pageReadConfirmations.ConfirmationsPanel = function( config ) {
	this.grid = null;
	this.dialog = config.dialog;
	this.windowManager = null;
	this.allowEditing = typeof config.allowEditing === 'boolean' ? config.allowEditing : true;
	this.pendingCount = 0;
	this.requestInfo = config.requestInfo

	ext.pageReadConfirmations.ConfirmationsPanel.super.call( this, Object.assign( {
		expanded: false,
		padded: false
	}, config ) );
};

OO.inheritClass( ext.pageReadConfirmations.ConfirmationsPanel, OO.ui.PanelLayout );

ext.pageReadConfirmations.ConfirmationsPanel.prototype.setWindowManager = function ( windowManager ) {
	this.windowManager = windowManager || null;
};

ext.pageReadConfirmations.ConfirmationsPanel.prototype.confirm = function ( message, options ) {
	if ( !this.windowManager ) {
		return OO.ui.confirm( message, options );
	}
	return this.windowManager.openWindow( 'message', Object.assign( {
		message: message
	}, options ) ).closed.then( ( data ) => !!( data && data.action === 'accept' ) );
};

ext.pageReadConfirmations.ConfirmationsPanel.prototype.alert = function ( message, options ) {
	if ( !this.windowManager ) {
		return OO.ui.alert( message, options );
	}
	return this.windowManager.openWindow( 'message', Object.assign( {
		message: message,
		actions: [ OO.ui.MessageDialog.static.actions[ 0 ] ]
	}, options ) ).closed.then( () => undefined );
};

ext.pageReadConfirmations.ConfirmationsPanel.prototype.init = async function ( force ) {
	if ( force ) {
		this.$element.empty();
		this.grid = null;
	}
	if ( !this.grid ) {
		await this.initConfirmationPanel();
	}
};

ext.pageReadConfirmations.ConfirmationsPanel.prototype.initConfirmationPanel = async function () {
	this.$element.append( new OO.ui.ProgressBarWidget( { progress: false } ).$element );
	await mw.loader.using( [ 'ext.oOJSPlus.data', 'ext.oOJSPlus.widgets' ] );
	this.$element.empty();

	this.versionLabel = new OO.ui.LabelWidget( {
		label: ''
	} );
	this.pendingMessage = new OO.ui.MessageWidget( {
		inline: true,
		type: this.pendingCount > 0 ? 'warning' : 'success',
		label: new OO.ui.HtmlSnippet(
			mw.msg( 'page-read-confirmations-pending-count', this.pendingCount, this.totalRequested )
		),
		classes: [ 'ext-page-read-confirmations-pending-message' ]
	} );

	this.cancelButton = new OO.ui.ButtonWidget( {
		label: mw.msg( 'page-read-confirmations-cancel-request' ),
		icon: 'trash',
		framed: false,
		flags: [ 'destructive' ],
		classes: [ 'ext-page-read-confirmations-cancel-request' ]
	} );
	this.cancelButton.connect( this, { click: 'onCancelRequestClick' } );

	this.sendReminderButton = new OO.ui.ButtonWidget( {
		label: mw.msg( 'page-read-confirmations-send-reminder' ),
		framed: false,
		flags: [ 'progressive' ]
	} );
	this.sendReminderButton.connect( this, { click: 'onSendReminderClick' } );

	this.$element.append(
		new OO.ui.HorizontalLayout( {
			items: [
				this.versionLabel,
				this.cancelButton,
			]
		} ).$element
	);
	this.$element.append( this.pendingMessage.$element, this.sendReminderButton.$element );

	this.renderRequestInfo( this.requestInfo );

	this.confirmationStore = new OOJSPlus.ui.data.store.RemoteRestStore( {
		path: 'page_read_confirmations/' + mw.config.get( 'wgArticleId' ),
		pageSize: 20
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

ext.pageReadConfirmations.ConfirmationsPanel.prototype.renderRequestInfo = function ( requestInfo ) {
	this.pendingCount = 0;
	const message = mw.msg( 'page-read-confirmations-request-info', requestInfo.version_link.anchor );
	this.versionLabel.setLabel( new OO.ui.HtmlSnippet( message ) );
	this.pendingCount = requestInfo.pending;
	this.readCount = requestInfo.read;
	this.totalRequested = requestInfo.total;
	this.pendingCount > 0 ? this.versionLabel.$element.show() : this.versionLabel.$element.hide();
	this.cancelButton.setDisabled( this.pendingCount === 0 );
	this.pendingCount > 0 ? this.cancelButton.$element.show() : this.cancelButton.$element.hide();
	this.sendReminderButton.setDisabled( this.pendingCount === 0 );
	this.pendingCount > 0 ? this.sendReminderButton.$element.show() : this.sendReminderButton.$element.hide();

	if ( this.pendingCount === 0 ) {
		this.pendingMessage.setLabel( mw.msg( 'page-read-confirmations-no-pending', requestInfo.version_label ) );
		this.pendingMessage.setType( 'success' );
	} else {
		this.pendingMessage.setLabel( new OO.ui.HtmlSnippet(
			mw.msg( 'page-read-confirmations-pending-count', this.pendingCount, this.totalRequested )
		) );
		this.pendingMessage.setType( 'warning' );
	}
}

ext.pageReadConfirmations.ConfirmationsPanel.prototype.onSendReminderClick = function () {
	this.confirm(
		mw.msg( 'page-read-confirmations-confirm-remind-request' ), {
			actions: [
				{
					label: mw.msg( 'page-read-confirmations-action-cancel' ),
					action: 'cancel'
				},
				{
					label: mw.msg( 'page-read-confirmations-action-remind' ),
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
				const res = await ext.pageReadConfirmations.api.remindUsers( mw.config.get( 'wgPageName' ) );
				if ( !res.success ) {
					throw new Error( 'API error' );
				}
				mw.notify( mw.msg( 'page-read-confirmations-reminder-sent' ), { type: 'success' } );
				this.sendReminderButton.setDisabled( true );
			} catch ( e ) {
				this.alert( mw.msg( 'page-read-confirmations-error' ), { type: 'error' } );
			}
		} );
};

ext.pageReadConfirmations.ConfirmationsPanel.prototype.onCancelRequestClick = async function ( item ) {
	this.confirm(
		mw.msg( 'page-read-confirmations-confirm-cancel-request' ), {
			actions: [
				{
					label: mw.msg( 'page-read-confirmations-action-delete' ),
					flags: [ 'destructive' ],
					action: 'accept'
				},
				{
					label: mw.msg( 'page-read-confirmations-action-cancel' ),
					action: 'cancel'
				}
			]
		} )
		.done( async ( confirmed ) => {
			if ( !confirmed ) {
				return;
			}
			try {
				const res = await ext.pageReadConfirmations.api.cancelRequest( mw.config.get( 'wgPageName' ) );
				if ( !res.success ) {
					throw new Error( 'API error' );
				}
				this.emit( 'requestCancel' );
			} catch ( e ) {
				this.alert( mw.msg( 'page-read-confirmations-error' ), { type: 'error' } );
			}
		} );
};
