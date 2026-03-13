<?php

namespace MediaWiki\Extension\PageReadConfirmations\Hook;

use MediaWiki\Page\PageIdentity;

interface PageReadConfirmationGetRequestInfoHook {

	/**
	 * @param PageIdentity $page
	 * @param array &$requestInfo
	 * @return void
	 */
	public function onPageReadConfirmationGetRequestInfo( PageIdentity $page, array &$requestInfo ): void;
}
