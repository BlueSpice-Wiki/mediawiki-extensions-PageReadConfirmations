<?php

namespace MediaWiki\Extension\PageReadConfirmations\Hook;

use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationEntity;

interface PageReadConfirmationConfirmedHook {

	/**
	 * @param ReadConfirmationEntity $confirmation
	 * @return void
	 */
	public function onPageReadConfirmationConfirmed( ReadConfirmationEntity $confirmation ): void;
}
