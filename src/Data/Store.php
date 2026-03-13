<?php

namespace MediaWiki\Extension\PageReadConfirmations\Data;

use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Language\Language;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\DataStore\IStore;
use MWStake\MediaWiki\Component\DataStore\NoWriterException;

readonly class Store implements IStore {

	/**
	 *
	 * @param PageIdentity $forPage
	 * @param UserIdentity $forUser
	 * @param ReadConfirmationManager $confirmationManager
	 * @param Language $language
	 * @param LinkRenderer $linkRenderer
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct(
		private PageIdentity $forPage,
		private UserIdentity $forUser,
		private ReadConfirmationManager $confirmationManager,
		private Language $language,
		private LinkRenderer $linkRenderer,
		private RevisionLookup $revisionLookup
	) {
	}

	/** @inheritDoc */
	public function getReader() {
		return new Reader(
			$this->forPage, $this->forUser, $this->confirmationManager, $this->language,
			$this->linkRenderer, $this->revisionLookup
		);
	}

	/** @inheritDoc */
	public function getWriter() {
		throw new NoWriterException();
	}
}
