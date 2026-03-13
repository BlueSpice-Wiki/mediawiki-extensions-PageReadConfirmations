ext.pageReadConfirmations.ui.workflows.TriggerReadConfirmationActivityInspector =
	function ( element, dialog ) {
		ext.pageReadConfirmations.ui.workflows.TriggerReadConfirmationActivityInspector.parent
			.call( this, element, dialog );
	};

OO.inheritClass(
	ext.pageReadConfirmations.ui.workflows.TriggerReadConfirmationActivityInspector,
	workflows.editor.inspector.ActivityInspector
);

ext.pageReadConfirmations.ui.workflows.TriggerReadConfirmationActivityInspector
	.prototype.getDialogTitle = function () {
		return mw.message( 'page-read-confirmations-automation-action-trigger' ).text();
	};

ext.pageReadConfirmations.ui.workflows.TriggerReadConfirmationActivityInspector.prototype.getItems =
	function () {
		return [
			{
				type: 'section_label',
				title: mw.message( 'workflows-ui-editor-inspector-properties' ).text()
			},
			{
				type: 'text',
				name: 'properties.audience_users',
				label: mw.msg( 'page-read-confirmations-inspector-activity-trigger-audience-users' ),
				required: false
			},
			{
				type: 'text',
				name: 'properties.audience_groups',
				label: mw.msg( 'page-read-confirmations-inspector-activity-trigger-audience-groups' ),
				required: false
			},
			{
				type: 'text',
				name: 'properties.revision',
				label: mw.msg( 'page-read-confirmations-inspector-activity-trigger-revision' ),
				help: mw.msg( 'page-read-confirmations-inspector-activity-trigger-revision-help' ),
				required: true
			},
			{
				type: 'text',
				name: 'properties.pagename',
				label: mw.msg( 'page-read-confirmations-inspector-activity-trigger-pagename' )
			},
			{
				type: 'text',
				name: 'properties.pageId',
				hidden: true
			}
		];
	};

workflows.editor.inspector.Registry.register(
	'trigger_read_confirmation',
	ext.pageReadConfirmations.ui.workflows.TriggerReadConfirmationActivityInspector
);
