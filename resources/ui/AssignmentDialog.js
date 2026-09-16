ext.pageReadConfirmations.ui.AssignmentDialog = function ( config ) {
	config = config || {};
	ext.pageReadConfirmations.ui.AssignmentDialog.super.call( this, Object.assign( {
		size: 'large'
	}, config ) );
};

OO.inheritClass( ext.pageReadConfirmations.ui.AssignmentDialog, OO.ui.ProcessDialog );

ext.pageReadConfirmations.ui.AssignmentDialog.static.name = 'page-read-confirmations-assignment-dialog';
ext.pageReadConfirmations.ui.AssignmentDialog.static.title =
	mw.msg( 'page-read-confirmations-assignment-dialog-title' );

ext.pageReadConfirmations.ui.AssignmentDialog.static.actions = [
	{
		action: 'request',
		label: mw.msg( 'page-read-confirmations-action-request' ),
		flags: [ 'primary', 'progressive' ],
		modes: [ 'request' ]
	},
	{
		action: 'save_assignments',
		label: mw.msg( 'page-read-confirmations-action-save' ),
		flags: [ 'primary', 'progressive' ],
		modes: [ 'edit_assignments' ]
	},
	{
		action: 'view_other',
		label: mw.msg( 'page-read-assignments-has-another-request-button' ),
		flags: [ 'primary', 'progressive' ],
		modes: [ 'unable' ]
	},
	{
		action: 'cancel',
		icon: 'close',
		modes: [ 'request', 'manage', 'assign', 'unable', 'history' ],
		title: mw.msg( 'page-read-confirmations-action-cancel' ),
		flags: [ 'safe', 'close' ]
	},
	{
		action: 'back',
		icon: 'previous',
		modes: [ 'edit_assignments' ],
		title: mw.msg( 'page-read-confirmations-action-previous' ),
		flags: [ 'safe' ]
	},
	{
		action: 'edit_assignments',
		label: mw.msg( 'page-read-confirmations-edit-assignments' ),
		modes: [ 'manage' ],
	}
];

ext.pageReadConfirmations.ui.AssignmentDialog.prototype.getSetupProcess = function ( data ) {
	return ext.pageReadConfirmations.ui.AssignmentDialog.parent.prototype.getSetupProcess.call( this, data )
		.next( function () {
			// Prevent flickering, disable all actions before init is done
			this.actions.setMode( 'request' );
		}, this );
};

ext.pageReadConfirmations.ui.AssignmentDialog.prototype.initialize = function () {
	ext.pageReadConfirmations.ui.AssignmentDialog.super.prototype.initialize.call( this );
	this.actions.setAbilities( { request: false, save_assignments: false, edit_assignments: false } );
	this.confirmWindowManager = new OO.ui.WindowManager();
	$( OO.ui.getTeleportTarget() ).append( this.confirmWindowManager.$element );
	this.confirmWindowManager.addWindows( [ new OO.ui.MessageDialog() ] );

	const initPanels = async () => {
		try {
			this.request = await ext.pageReadConfirmations.api.getRequestInfo(
				mw.config.get( 'wgArticleId' ),
				mw.config.get( 'wgRevisionId' )
			);
			if (
				this.request &&
				this.request.another_active
			) {
				this.unablePanel = new OO.ui.PanelLayout( {
					expanded: false,
					padded: true,
				} );

				this.unablePanel.$element.append(
					new OO.ui.MessageWidget( {
						label: mw.msg( 'page-read-assignments-has-another-request-label-extended' ),
						type: 'warning',
					} ).$element
				);
				this.$body.append( this.unablePanel.$element );
				this.switchMode( 'unable' );
				return;

			}
 		} catch ( e ) {
			this.request = null;
		}

		this.assignmentPanel = new ext.pageReadConfirmations.ui.AssignmentPanel(
			{ padded: true, dialog: this }
		);
		this.assignmentPanel.connect( this, {
			loaded: function () {
				this.updateSize();
			},
			change: 'setDirty',
			error: function () {
				this.actions.setAbilities( { request: false } );
			}
		} );

		this.$body.append( this.assignmentPanel.$element );

		if ( this.request ) {
			this.confirmationPanel = new ext.pageReadConfirmations.ConfirmationsPanel( {
				padded: true,
				dialog: this,
				// Prevent editing assignments from confirmation dialog, as we are already in assignment editor
				allowEditing: false,
				requestInfo: this.request
			} );
			this.confirmationPanel.connect( this, {
				requestCancel: () => {
					window.location.reload();
				}
			} );
			this.confirmationPanel.setWindowManager( this.confirmWindowManager );
			this.confirmationPanel.init();
			this.$body.append( this.confirmationPanel.$element );
			if ( this.request.is_active ) {
				this.switchMode( 'manage' );
			} else {
				this.switchMode( 'history' );
			}
		}
	}

	initPanels();
};

ext.pageReadConfirmations.ui.AssignmentDialog.prototype.getTeardownProcess = function ( data ) {
	return ext.pageReadConfirmations.ui.AssignmentDialog.super.prototype.getTeardownProcess.call( this, data )
		.first( () => {
			if ( this.confirmWindowManager ) {
				this.confirmWindowManager.destroy();
				this.confirmWindowManager = null;
			}
		} );
};

ext.pageReadConfirmations.ui.AssignmentDialog.prototype.switchMode = function ( mode ) {
	this.actions.setMode( mode );
	if ( mode === 'request' || mode === 'edit_assignments' ) {
		this.setSize( 'large' );
		this.assignmentPanel.$element.show();
		this.confirmationPanel.$element.hide();
	} else if ( mode === 'unable' ) {
		this.setSize( 'large' );
	} else {
		this.setSize( 'larger' );
		this.assignmentPanel.$element.hide();
		this.confirmationPanel.$element.show();
	}
	this.updateSize();
};

ext.pageReadConfirmations.ui.AssignmentDialog.prototype.getActionProcess = function ( action ) {
	return ext.pageReadConfirmations.ui.AssignmentDialog.super.prototype.getActionProcess
		.call( this, action )
		.next( () => {
			if ( action === 'request' ) {
				const dfd = $.Deferred();

				this.pushPending();
				this.saveAssignments()
					.then( () => {
						this.close( { action: 'save' } );
						this.get
					} ).catch( () => {
					this.popPending();
					dfd.reject( new OO.ui.Error( mw.msg( 'page-read-confirmations-error' ) ) );
				} );
				return dfd.promise();
			}

			if ( action === 'cancel' ) {
				this.close();
			}

			if ( action === 'edit_assignments' ) {
				this.switchMode( 'edit_assignments' );
			}

			if ( action === 'save_assignments' ) {
				const dfd = $.Deferred();

				this.pushPending();
				this.saveAssignments()
					.then( () => {
						ext.pageReadConfirmations.api.getRequestInfo(
							mw.config.get( 'wgArticleId' ),
							mw.config.get( 'wgRevisionId' )
						)
							.then( ( requestInfo ) => {
								this.assignmentPanel.updateOriginalValue();
								this.request = requestInfo;
								this.confirmationPanel.renderRequestInfo( requestInfo );
								this.confirmationPanel.confirmationStore.reload();
								this.switchMode( 'manage' );
								this.popPending();
								dfd.resolve();
							} )
					} )
					.catch( () => {
						this.popPending();
						dfd.reject( new OO.ui.Error( mw.msg( 'page-read-confirmations-error' ) ) );
					} );
				return dfd.promise();
			}

			if ( action === 'back' ) {
				if ( this.dirty ) {
					this.confirmWindowManager.openWindow( 'message', {
						message: mw.msg( 'page-read-confirmations-confirm-assign-dirty' ),
						actions: [
							{
								label: mw.msg( 'page-read-confirmations-action-discard' ),
								flags: [ 'destructive' ],
								action: 'accept'
							},
							{
								label: mw.msg( 'page-read-confirmations-action-cancel' ),
								action: 'cancel'
							}
						],
						size: 'medium'
					} ).closed
						.then( ( data ) => !!( data && data.action === 'accept' ) )
						.done( ( confirmed ) => {
							if ( !confirmed ) {
								return;
							}
							this.assignmentPanel.resetValues();
							this.setDirty( false );
							this.switchMode( 'manage' );
						} )
				} else {
					this.switchMode( 'manage' );
				}
			}

			if ( action === 'view_other' ) {
				window.location.href = mw.Title.newFromText( mw.config.get( 'wgPageName' ) )
					.getUrl( { oldid: this.request.another_active } );
			}
		}, this );
};

ext.pageReadConfirmations.ui.AssignmentDialog.prototype.saveAssignments = function () {
	const value = this.assignmentPanel.getValue();
	const rev = mw.config.get( 'wgRevisionId' );
	return ext.pageReadConfirmations.api.storeAssignment( mw.config.get( 'wgPageName' ), value, rev );
};

ext.pageReadConfirmations.ui.AssignmentDialog.prototype.setDirty = function ( dirty ) {
	this.dirty = dirty
	this.actions.setAbilities( {
		save_assignments: dirty,
		request: this.assignmentPanel.getValue().length > 0
	} );
};
