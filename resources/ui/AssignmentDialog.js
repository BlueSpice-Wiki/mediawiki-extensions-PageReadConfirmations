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
		action: 'assign',
		label: mw.msg( 'page-read-confirmations-action-assign' ),
		flags: [ 'primary', 'progressive' ],
		modes: [ 'assign' ]
	},
	{
		action: 'cancel',
		icon: 'close',
		modes: [ 'assign' ],
		title: mw.msg( 'page-read-confirmations-action-cancel' ),
		flags: [ 'safe', 'close' ]
	},
	{
		action: 'back',
		icon: 'previous',
		modes: [ 'confirmations' ],
		title: mw.msg( 'page-read-confirmations-action-previous' ),
		flags: [ 'safe' ]
	},
	{
		action: 'show_confirmations',
		label: mw.msg( 'page-read-confirmations-action-show-confirmations' ),
		modes: [ 'assign' ],
		title: mw.msg( 'page-read-confirmations-action-cancel' )
	}
];

ext.pageReadConfirmations.ui.AssignmentDialog.prototype.getSetupProcess = function ( data ) {
	return ext.pageReadConfirmations.ui.AssignmentDialog.parent.prototype.getSetupProcess.call( this, data )
		.next( function () {
			// Prevent flickering, disable all actions before init is done
			this.switchMode( 'assign' );
		}, this );
};

ext.pageReadConfirmations.ui.AssignmentDialog.prototype.initialize = function () {
	ext.pageReadConfirmations.ui.AssignmentDialog.super.prototype.initialize.call( this );
	this.actions.setAbilities( { assign: false } );
	this.confirmWindowManager = new OO.ui.WindowManager();
	$( OO.ui.getTeleportTarget() ).append( this.confirmWindowManager.$element );
	this.confirmWindowManager.addWindows( [ new OO.ui.MessageDialog() ] );


	this.assignmentPanel = new ext.pageReadConfirmations.ui.AssignmentPanel(
		{ padded: true, dialog: this }
	);
	this.assignmentPanel.connect( this, {
		loaded: function () {
			this.updateSize();
		},
		change: 'setDirty',
		error: function () {
			this.actions.setAbilities( { assign: false } );
		}
	} );

	this.confirmationPanel = new ext.pageReadConfirmations.ConfirmationsPanel( {
		padded: true,
		dialog: this,
		// Prevent editing assignments from confirmation dialog, as we are already in assignment editor
		allowEditing: false
	} );
	this.confirmationPanel.setWindowManager( this.confirmWindowManager );
	this.confirmationPanel.init();
	this.confirmationPanel.$element.hide();

	this.$body.append( this.assignmentPanel.$element, this.confirmationPanel.$element );
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
	if ( mode === 'assign' ) {
		this.assignmentPanel.$element.show();
		this.confirmationPanel.$element.hide();
	} else {
		this.assignmentPanel.$element.hide();
		this.confirmationPanel.$element.show();
	}
	this.updateSize();
};

ext.pageReadConfirmations.ui.AssignmentDialog.prototype.getActionProcess = function ( action ) {
	return ext.pageReadConfirmations.ui.AssignmentDialog.super.prototype.getActionProcess
		.call( this, action )
		.next( () => {
			if ( action === 'show_confirmations' ) {
				if ( !this.dirty ) {
					this.switchMode( 'confirmations' );
					return;
				}
				const dfd = $.Deferred();
				// Open in dedicated win manager, to allow opening window-in-window
				this.confirmWindowManager.openWindow( 'message', {
					message: mw.msg( 'page-read-confirmations-confirm-assign-dirty' ),
					actions: [
						{
							label: mw.msg( 'page-read-confirmations-action-assign' ),
							flags: [ 'progressive' ],
							action: 'accept'
						},
						{
							label: mw.msg( 'page-read-confirmations-action-cancel' ),
							action: 'cancel'
						}
					],
					size: 'large'
				} ).closed
					.then( ( data ) => !!( data && data.action === 'accept' ) )
					.done( ( confirmed ) => {
						if ( !confirmed ) {
							dfd.resolve();
							return;
						}
						this.pushPending();
						this.saveAssignments()
							.then( () => {
								this.setDirty( false );
								this.assignmentPanel.updateOriginalValue();
								this.confirmationPanel.init( true );
								this.switchMode( 'confirmations' );
								this.popPending();
								dfd.resolve();
							} ).catch( () => {
							this.popPending();
							dfd.reject( new OO.ui.Error( mw.msg( 'page-read-confirmations-error' ) ) );
						} );
					} )
					.fail( () => {
						dfd.reject( new OO.ui.Error( mw.msg( 'page-read-confirmations-error' ) ) );
					} );
				return dfd.promise();
			}
			if ( action === 'back' ) {
				this.switchMode( 'assign' );
			}
			if ( action === 'assign' ) {
				const dfd = $.Deferred();

				this.pushPending();
				this.saveAssignments()
					.then( () => {
						this.close( { action: 'save' } );
					} ).catch( () => {
						this.popPending();
						dfd.reject( new OO.ui.Error( mw.msg( 'page-read-confirmations-error' ) ) );
					} );
				return dfd.promise();
			}
			if ( action === 'cancel' ) {
				this.close();
			}
		}, this );
};

ext.pageReadConfirmations.ui.AssignmentDialog.prototype.saveAssignments = function () {
	const value = this.assignmentPanel.getValue();
	return ext.pageReadConfirmations.api.storeAssignment( mw.config.get( 'wgPageName' ), value, true );
};

ext.pageReadConfirmations.ui.AssignmentDialog.prototype.setDirty = function ( dirty ) {
	this.dirty = dirty
	this.actions.setAbilities( { assign: dirty } );
};
