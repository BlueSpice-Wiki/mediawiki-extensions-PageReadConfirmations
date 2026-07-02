ext.pageReadConfirmations.ConfirmationsPanel = function( config ) {
	this.grid = null;
	this.dialog = config.dialog;
	this.windowManager = null;
	this.allowEditing = typeof config.allowEditing === 'boolean' ? config.allowEditing : true;
	this.pendingCount = 0;
	this.isOnLatestRev = mw.config.get( 'wgCurRevisionId' ) === mw.config.get( 'wgRevisionId' );
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
	this.oldRevisionWarning = new OO.ui.PopupButtonWidget( {
		icon: 'alert',
		framed: false,
		label: mw.msg( 'page-read-confirmations-old-revision-warning-title' ),
		invisibleLabel: true,
		popup: {
			$content: $( '<p>' ).text( mw.msg( 'page-read-confirmations-old-revision-warning-body' ) ),
			padded: true,
			align: 'force-left',
			autoFlip: false
		}
	} );
	this.oldRevisionWarning.$element.css( 'margin-left', '4px' );
	this.oldRevisionWarning.$element.hide();

	this.requestButton = new OO.ui.ButtonWidget( {
		label: mw.msg( 'page-read-confirmations-request-confirmation-button' ),
		flags: [ 'primary', 'progressive' ],
		title: mw.msg( 'page-read-confirmations-request-confirmation-button-title' ),
		framed: false,
		classes: [ 'page-read-confirmations-request-confirmation-button' ]
	} );
	this.requestButton.connect( this, {
		click: 'onRequestConfirmationClick'
	} );
	this.requestButton.$element.hide();

	this.$element.append( this.versionLabel.$element, this.oldRevisionWarning.$element, this.requestButton.$element );
	await this.setRequestInfo();
	this.$actionHeadingCnt = $( '<div>' );
	this.$element.append( this.$actionHeadingCnt );
	this.addActionHeading();

	this.confirmationStore = new OOJSPlus.ui.data.store.RemoteRestStore( {
		path: 'page_read_confirmations/' + mw.config.get( 'wgArticleId' ),
		pageSize: 20
	} );
	this.confirmationStore.connect( this, {
		reload: 'onReload'
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
				invisibleLabel: true,
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

ext.pageReadConfirmations.ConfirmationsPanel.prototype.setRequestInfo = async function () {
	const requestInfo = await ext.pageReadConfirmations.api.getRequestInfo( mw.config.get( 'wgArticleId' ) );
	this.pendingCount = 0;
	if ( requestInfo ) {
		const message = mw.msg( 'page-read-confirmations-request-info', requestInfo.version_link.anchor );
		this.versionLabel.setLabel( new OO.ui.HtmlSnippet( message ) );
		this.pendingCount = requestInfo.pending;
		if ( requestInfo.is_current ) {
			this.oldRevisionWarning.$element.hide();
		} else {
			this.oldRevisionWarning.$element.show();
		}
		this.requestButton.$element.hide();
	} else {
		this.versionLabel.setLabel( mw.msg( 'page-read-confirmations-no-request-info' ) );
		if ( this.isOnLatestRev ) {
			this.requestButton.$element.show();
		}
	}
};

ext.pageReadConfirmations.ConfirmationsPanel.prototype.addActionHeading = function () {
	let actionItems;
	if ( this.pendingCount ) {
		this.sendReminderButton = new OO.ui.ButtonWidget( {
			data: 'sendReminder',
			label: mw.msg( 'page-read-confirmations-send-reminder' ),
			framed: false
		} );
		this.sendReminderButton.connect( this, { click: 'onSendReminderClick' } );
		const menuOptions = [];
		if ( this.allowEditing ) {
			menuOptions.push(
				new OO.ui.MenuOptionWidget( {
					icon: 'edit',
					data: 'editAssignments',
					label: mw.msg( 'page-read-confirmations-edit-assignments' )
				} )
			);
		}
		menuOptions.push(
			new OO.ui.MenuOptionWidget( {
				icon: 'trash',
				data: 'cancelRequest',
				label: mw.msg( 'page-read-confirmations-cancel-request' ),
				flags: [ 'destructive' ]
			} )
		);

		const additionalActionsButton = new OO.ui.ButtonMenuSelectWidget( {
			icon: 'verticalEllipsis',
			title: mw.msg( 'page-read-confirmations-more-actions' ),
			framed: false,
			$overlay: this.dialog.$overlay,
			menu: {
				items: menuOptions
			}
		} );
		additionalActionsButton.getMenu().connect( this, {
			choose: function ( item ) {
				if ( item.getData() === 'editAssignments' ) {
					this.onEditAssignmentsClick();
				}
				if ( item.getData() === 'cancelRequest' ) {
					this.onCancelRequestClick( item );
				}
			}
		} );
		actionItems = [
			this.sendReminderButton,
			additionalActionsButton
		]
	} else if ( this.allowEditing ) {
		const editAssignmentsButton = new OO.ui.ButtonWidget( {
			data: 'editAssignments',
			label: mw.msg( 'page-read-confirmations-edit-assignments' ),
			framed: false
		} )
		editAssignmentsButton.connect( this, { click: 'onEditAssignmentsClick' } );
		actionItems = [ editAssignmentsButton ];
	}

	this.actionHeading = new OO.ui.HorizontalLayout( {
		items: [
			new OO.ui.LabelWidget( {
				label: this.pendingCount ?
					mw.msg( 'page-read-confirmations-pending-count', this.pendingCount ) :
					mw.msg( 'page-read-confirmations-no-pending' )
			} ),
			new OO.ui.ButtonGroupWidget( {
				classes: [ 'ext-page-read-confirmations-actions' ],
				items: actionItems
			} )
		]
	} );

	this.$actionHeadingCnt.html( this.actionHeading.$element );
};

ext.pageReadConfirmations.ConfirmationsPanel.prototype.onReload = async function () {
	await this.setRequestInfo();
	this.addActionHeading();
};

ext.pageReadConfirmations.ConfirmationsPanel.prototype.onSendReminderClick = function () {
	this.confirm(
		mw.msg( 'page-read-confirmations-confirm-remind-request' ), {
			actions: [
				{
					label: mw.msg( 'page-read-confirmations-action-remind' ),
					flags: [ 'progressive' ],
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

ext.pageReadConfirmations.ConfirmationsPanel.prototype.onEditAssignmentsClick = async function () {
	await mw.loader.using( [ 'ext.pageReadConfirmations.assignments' ] );
	const wm = OO.ui.getWindowManager();
	const dialog = new ext.pageReadConfirmations.ui.AssignmentOnlyDialog(
		{ assignmentsOnly: true }
	);
	wm.addWindows( [ dialog ] );
	wm.openWindow( dialog ).closed.then( ( data ) => {
		if ( data && data.action === 'save' ) {
			this.confirmationStore.reload();
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
				this.grid.store.reload();
			} catch ( e ) {
				this.alert( mw.msg( 'page-read-confirmations-error' ), { type: 'error' } );
			}
		} );
};

ext.pageReadConfirmations.ConfirmationsPanel.prototype.onRequestConfirmationClick = async function () {
	this.confirm(
		mw.msg( 'page-read-confirmations-confirm-request' ), {
			actions: [
				{
					label: mw.msg( 'page-read-confirmations-action-request' ),
					flags: [ 'progressive' ],
					action: 'accept'
				},
				{
					label: mw.msg( 'page-read-confirmations-action-cancel' ),
					action: 'cancel'
				}
			],
			size: 'large'
		} )
		.done( async ( confirmed ) => {
			if ( !confirmed ) {
				return;
			}
			try {
				const res = await ext.pageReadConfirmations.api.requestConfirmation( mw.config.get( 'wgArticleId' ) );
				if ( !res.success ) {
					throw new Error( 'API error' );
				}
				this.grid.store.reload();
			} catch ( e ) {
				this.alert( mw.msg( 'page-read-confirmations-error' ), { type: 'error' } );
			}
		} );
}
