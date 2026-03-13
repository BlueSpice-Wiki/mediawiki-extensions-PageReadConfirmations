<?php

namespace MediaWiki\Extension\PageReadConfirmations\Integration\MetaItemProvider;

use BlueSpice\Discovery\IMetaItemProvider;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Revision\RevisionLookup;
use MWStake\MediaWiki\Component\CommonUserInterface\IComponent;

class ReadConfirmation implements IMetaItemProvider {

	/**
	 * @param ReadConfirmationManager $confirmationManager
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct(
		private readonly ReadConfirmationManager $confirmationManager,
		private readonly RevisionLookup $revisionLookup
	) {
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function getName(): string {
		return 'read-confirmations';
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function getComponent(): IComponent {
		return new ReadConfirmationsTool( $this->confirmationManager, $this->revisionLookup );
	}
}
