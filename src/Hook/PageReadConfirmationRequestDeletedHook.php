<?php

namespace MediaWiki\Extension\PageReadConfirmations\Hook;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;

interface PageReadConfirmationRequestDeletedHook {

	/**
	 * @param PageIdentity $page
	 * @param Authority $authority
	 * @return void
	 */
	public function onPageReadConfirmationRequestDeleted( PageIdentity $page, Authority $authority ): void;
}
