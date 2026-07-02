ext.pageReadConfirmations.ui.AssignmentOnlyDialog = function ( config ) {
	config = config || {};
	ext.pageReadConfirmations.ui.AssignmentOnlyDialog.super.call( this, Object.assign( {
		size: 'large'
	}, config ) );
};

OO.inheritClass( ext.pageReadConfirmations.ui.AssignmentOnlyDialog, OO.ui.ProcessDialog );

ext.pageReadConfirmations.ui.AssignmentOnlyDialog.static.name = 'page-read-confirmations-assignment-dialog';
ext.pageReadConfirmations.ui.AssignmentOnlyDialog.static.title =
	mw.msg( 'page-read-confirmations-assignment-dialog-title' );
ext.pageReadConfirmations.ui.AssignmentOnlyDialog.static.actions = [
	{
		action: 'assign',
		label: mw.msg( 'page-read-confirmations-action-assign' ),
		flags: [ 'primary', 'progressive' ]
	},
	{
		action: 'cancel',
		icon: 'close',
		title: mw.msg( 'page-read-confirmations-action-cancel' ),
		flags: [ 'safe', 'close' ]
	}
];

ext.pageReadConfirmations.ui.AssignmentOnlyDialog.prototype.initialize = function () {
	ext.pageReadConfirmations.ui.AssignmentOnlyDialog.super.prototype.initialize.call( this );
	this.actions.setAbilities( { assign: false } );

	this.assignmentPanel = new ext.pageReadConfirmations.ui.AssignmentPanel(
		{ padded: true, dialog: this }
	);
	this.assignmentPanel.connect( this, {
		loaded: function () {
			this.updateSize();
			this.actions.setAbilities( { assign: true } );
		},
		error: function () {
			this.actions.setAbilities( { assign: false } );
		}
	} );

	this.$body.append( this.assignmentPanel.$element );
};

ext.pageReadConfirmations.ui.AssignmentOnlyDialog.prototype.getActionProcess = function ( action ) {
	return ext.pageReadConfirmations.ui.AssignmentOnlyDialog.super.prototype.getActionProcess
		.call( this, action )
		.next( () => {
			if ( action === 'assign' ) {
				const dfd = $.Deferred();
				const value = this.assignmentPanel.getValue();
				this.pushPending();
				ext.pageReadConfirmations.api.storeAssignment( mw.config.get( 'wgPageName' ), value, true )
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
