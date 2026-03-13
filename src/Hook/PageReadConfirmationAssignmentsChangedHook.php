<?php

namespace MediaWiki\Extension\PageReadConfirmations\Hook;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;

interface PageReadConfirmationAssignmentsChangedHook {

	/**
	 * @param PageIdentity $page
	 * @param array $added
	 * @param array $removed
	 * @param Authority $actor
	 * @return void
	 */
	public function onPageReadConfirmationAssignmentsChanged(
		PageIdentity $page, array $added, array $removed, Authority $actor
	): void;
}
