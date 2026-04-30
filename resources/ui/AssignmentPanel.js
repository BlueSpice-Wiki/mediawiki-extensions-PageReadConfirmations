ext.pageReadConfirmations.ui.AssignmentPanel = function ( config ) {
	ext.pageReadConfirmations.ui.AssignmentPanel.super.call( this, Object.assign( {
		padded: false,
		expanded: false
	}, config ) );
	this.dialog = config.dialog;

	this.userGroupPicker = new OOJSPlus.ui.widget.UserGroupMultiselectWidget( {
		$overlay: config.dialog.$overlay,
		allowEveryone: true,
		placeholder: mw.msg( 'page-read-confirmations-assign-placeholder' )
	} );
	this.userGroupPicker.connect( this, {
		change: () => {
			this.dialog.updateSize();
		}
	} );

	this.$element.append(
		new OO.ui.FieldLayout( this.userGroupPicker, {
			label: mw.msg( 'page-read-confirmations-assign-instruction' ),
			align: 'top'
		} ).$element,
	);

	this.userGroupPicker.setDisabled( true );
	this.loadValues();
};

OO.inheritClass( ext.pageReadConfirmations.ui.AssignmentPanel, OO.ui.PanelLayout );

ext.pageReadConfirmations.ui.AssignmentPanel.prototype.loadValues = async function () {
	try {
		const assignments = await ext.pageReadConfirmations.api.getAssignments( mw.config.get( 'wgArticleId' ) );
		const users = [];
		const groups = [];
		for ( const assignment of assignments ) {
			if ( assignment.type === 'user' ) {
				users.push( assignment.key );
			} else if ( assignment.type === 'group' ) {
				groups.push( assignment.key );
			}
		}

		const values = [];
		users.forEach( user => values.push( { key: user, type: 'user' } ) );
		groups.forEach( group => values.push( { key: group, type: 'group' } ) );
		this.userGroupPicker.setValue( values );
		this.userGroupPicker.setDisabled( false );
		this.dialog.updateSize();
		this.emit( 'loaded' );
	} catch ( e ) {
		this.emit( 'error' );
		this.$element.html( new OO.ui.MessageWidget( {
			type: 'error',
			label: mw.msg( 'page-read-confirmations-error' )
		} ).$element );
	}
};

ext.pageReadConfirmations.ui.AssignmentPanel.prototype.getValue = function () {
	return this.userGroupPicker.getValue();
}
