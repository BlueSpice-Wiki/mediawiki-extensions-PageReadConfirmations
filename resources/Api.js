ext.pageReadConfirmations.api =  {
	getAssignments: async ( pageId ) => {
		return ext.pageReadConfirmations.api._ajax( '/assignments/' + pageId, {}, 'GET' );
	},
	storeAssignment: async ( page, assignments, requestCurrentRevision ) => {
		return ext.pageReadConfirmations.api._ajax( '/set_confirmations', {
			page: page,
			assignments: JSON.stringify( assignments ),
			requestCurrentRevision: requestCurrentRevision || false
		}, 'POST' );
	},
	confirmRead: async ( revision ) => {
		return ext.pageReadConfirmations.api._ajax( '/confirm', {
			revisionId: revision
		}, 'POST' );
	},
	removeConfirmation: async ( page, user ) => {
		return ext.pageReadConfirmations.api._ajax( '/remove_confirmation', {
			page: page,
			user: user
		}, 'POST' );
	},
	getRequestInfo: async ( pageId ) => {
		return ext.pageReadConfirmations.api._ajax( '/request_info/' + pageId, {}, 'GET' );
	},
	cancelRequest: async ( page ) => {
		return ext.pageReadConfirmations.api._ajax( '/cancel_request', {
			page: page
		}, 'POST' );
	},
	remindUsers: async ( page ) => {
		return ext.pageReadConfirmations.api._ajax( '/remind', {
			page: page
		}, 'POST' );
	},
	requestConfirmation: async( pageId ) => {
		return ext.pageReadConfirmations.api._ajax( '/request/' + pageId, {}, 'POST' );
	},
	_ajax: async ( path, params, method ) => {
		const base = mw.util.wikiScript( 'rest' ) + '/page_read_confirmations';
		let url = base + path;

		const options = {
			method: method.toUpperCase(),
			headers: {
				'Content-Type': 'application/json'
			}
		};

		if ( options.method === 'POST' ) {
			options.body = JSON.stringify( params );
		} else if ( Object.keys( params ).length ) {
			const query = new URLSearchParams( params ).toString();
			url += ( url.includes( '?' ) ? '&' : '?' ) + query;
		}

		return fetch( url, options ).then( ( res ) => {
			if ( !res.ok ) {
				throw new Error( `REST request failed: ${res.status}` );
			}
			return res.json();
		} );
	}
};