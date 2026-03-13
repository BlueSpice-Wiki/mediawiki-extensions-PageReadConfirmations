<?php

namespace MediaWiki\Extension\PageReadConfirmations\Hook;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class RunDatabaseUpdates implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$base = dirname( __DIR__, 2 ) . '/db';
		$dbType = $updater->getDB()->getType();

		$updater->addExtensionTable(
			'page_read_confirmations',
			"$base/$dbType/page_read_confirmations.sql"
		);

		$updater->addExtensionTable(
			'page_read_confirmations_assignments',
			"$base/$dbType/page_read_confirmations_assignments.sql"
		);

		$updater->addExtensionTable(
			'page_read_confirmations_requests',
			"$base/$dbType/page_read_confirmations_requests.sql"
		);
	}
}
