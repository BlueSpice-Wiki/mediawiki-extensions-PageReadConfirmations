<?php

namespace MediaWiki\Extension\PageReadConfirmations\Hook;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;

interface PageReadConfirmationRequestedHook {

	/**
	 * @param PageIdentity $page
	 * @param RevisionRecord $revisionToRead
	 * @param Authority $actor
	 * @return void
	 */
	public function onPageReadConfirmationRequested(
		PageIdentity $page, RevisionRecord $revisionToRead, Authority $actor
	): void;
}
