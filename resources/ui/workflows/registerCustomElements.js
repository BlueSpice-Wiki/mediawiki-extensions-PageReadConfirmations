workflows.editor.element.registry.register( 'trigger_read_confirmation', {
	isUserActivity: false,
	class: 'activity-trigger-read-confirmation activity-bootstrap-icon',
	label: mw.message( 'page-read-confirmations-automation-action-trigger' ).text(),
	defaultData: {
		properties: {
			revision: '',
			pageId: '',
			pagename: '',
			audience_users: '', // eslint-disable-line camelcase
			audience_groups: '' // eslint-disable-line camelcase
		}
	}
} );
