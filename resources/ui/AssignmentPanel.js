ext.pageReadConfirmations.ui.AssignmentPanel = function ( config ) {
	ext.pageReadConfirmations.ui.AssignmentPanel.super.call( this, Object.assign( {
		padded: false,
		expanded: false
	}, config ) );

	this.userPicker = new OOJSPlus.ui.widget.UsersMultiselectWidget( {
		$overlay: config.dialog.$overlay
	} );
	this.groupPicker = new OOJSPlus.ui.widget.GroupMultiSelectWidget( {
		$overlay: config.dialog.$overlay
	} );

	this.$element.append(
		new OO.ui.LabelWidget( {
			label: mw.msg( 'page-read-confirmations-assign-instruction' )
		} ).$element,
		new OO.ui.FieldLayout( this.userPicker, {
			label: mw.msg( 'page-read-confirmations-assign-users' ),
			align: 'top'
		} ).$element,
		new OO.ui.FieldLayout( this.groupPicker, {
			label: mw.msg( 'page-read-confirmations-assign-groups' ),
			align: 'top'
		} ).$element
	);

	this.userPicker.setDisabled( true );
	this.groupPicker.setDisabled( true );
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
		this.userPicker.setValue( users );
		this.groupPicker.setValue( groups );
		this.userPicker.setDisabled( false );
		this.groupPicker.setDisabled( false );
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
	const users = this.userPicker.getValue();
	const groups = this.groupPicker.getValue();

	const assignments = [];
	for ( const user of users ) {
		assignments.push( {
			type: 'user',
			key: user
		} );
	}
	for ( const group of groups ) {
		assignments.push( {
			type: 'group',
			key: group
		} );
	}
	return assignments;
}
