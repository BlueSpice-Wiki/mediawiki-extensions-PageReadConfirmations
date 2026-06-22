ext.pageReadConfirmations.ConfirmationsPanel = function( name, config ) {
	this.grid = null;
	this.dialog = config.dialog;
	this.pendingCount = 0;
	ext.pageReadConfirmations.ConfirmationsPanel.super.call( this, name, config );
};

OO.inheritClass( ext.pageReadConfirmations.ConfirmationsPanel, StandardDialogs.ui.BasePage );

ext.pageReadConfirmations.ConfirmationsPanel.prototype.setupOutlineItem = function () {
	ext.pageReadConfirmations.ConfirmationsPanel.super.prototype.setupOutlineItem.apply( this, arguments );

	if ( this.outlineItem ) {
		this.outlineItem.setLabel( mw.message( 'page-read-confirmations-label' ).plain() );
	}
};

ext.pageReadConfirmations.ConfirmationsPanel.prototype.setup = function () {
	return;
};

ext.pageReadConfirmations.ConfirmationsPanel.prototype.onInfoPanelSelect = async function () {
	this.dialog.setSize( 'larger' );
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
	this.$element.append( this.versionLabel.$element, this.oldRevisionWarning.$element );
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
	} else {
		this.versionLabel.setLabel( mw.msg( 'page-read-confirmations-no-request-info' ) );
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
		const additionalActionsButton = new OO.ui.ButtonMenuSelectWidget( {
			icon: 'verticalEllipsis',
			title: mw.msg( 'page-read-confirmations-more-actions' ),
			framed: false,
			$overlay: this.dialog.$overlay,
			menu: {
				items: [
					new OO.ui.MenuOptionWidget( {
						icon: 'edit',
						data: 'editAssignments',
						label: mw.msg( 'page-read-confirmations-edit-assignments' )
					} ),
					new OO.ui.MenuOptionWidget( {
						icon: 'trash',
						data: 'cancelRequest',
						label: mw.msg( 'page-read-confirmations-cancel-request' ),
						flags: [ 'destructive' ]
					} )
				]
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
	} else {
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
	OO.ui.confirm(
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
				OO.ui.alert( mw.msg( 'page-read-confirmations-error' ), { type: 'error' } );
			}
		} );
};

ext.pageReadConfirmations.ConfirmationsPanel.prototype.onEditAssignmentsClick = async function () {
	await mw.loader.using( [ 'ext.pageReadConfirmations.assignments' ] );
	const wm = OO.ui.getWindowManager();
	const dialog = new ext.pageReadConfirmations.ui.AssignmentDialog();
	wm.addWindows( [ dialog ] );
	wm.openWindow( dialog ).closed.then( ( data ) => {
		if ( data && data.action === 'save' ) {
			this.confirmationStore.reload();
		}
	} );
};

ext.pageReadConfirmations.ConfirmationsPanel.prototype.onCancelRequestClick = async function ( item ) {
	OO.ui.confirm(
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
				OO.ui.alert( mw.msg( 'page-read-confirmations-error' ), { type: 'error' } );
			}
		} );
};

if ( ext.pageReadConfirmations._currentPageSupported() ) {
	registryPageInformation.register( 'read_confirmations', ext.pageReadConfirmations.ConfirmationsPanel );
}
