<?php

namespace MediaWiki\Extension\PageReadConfirmations\Data;

use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Language\Language;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\User\UserIdentity;

class Reader extends \MWStake\MediaWiki\Component\DataStore\Reader {

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
		private readonly PageIdentity $forPage,
		private readonly UserIdentity $forUser,
		private readonly ReadConfirmationManager $confirmationManager,
		private readonly Language $language,
		private readonly LinkRenderer $linkRenderer,
		private readonly RevisionLookup $revisionLookup
	) {
		parent::__construct();
	}

	/**
	 * @param array $params
	 * @return PrimaryDataProvider
	 */
	protected function makePrimaryDataProvider( $params ) {
		return new PrimaryDataProvider( $this->forPage, $this->confirmationManager, $this->revisionLookup );
	}

	/**
	 * @return SecondaryDataProvider
	 */
	protected function makeSecondaryDataProvider() {
		return new SecondaryDataProvider(
			$this->forPage, $this->forUser, $this->language, $this->linkRenderer, $this->revisionLookup
		);
	}

	/**
	 * @return Schema
	 */
	public function getSchema() {
		return new Schema();
	}
}
