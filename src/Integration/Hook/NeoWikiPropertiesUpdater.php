<?php

namespace MediaWiki\Extension\PageReadConfirmations\Integration\Hook;

use MediaWiki\Extension\PageReadConfirmations\Hook\PageReadConfirmationAssignmentsChangedHook;
use MediaWiki\Extension\PageReadConfirmations\Hook\PageReadConfirmationConfirmedHook;
use MediaWiki\Extension\PageReadConfirmations\Hook\PageReadConfirmationRequestDeletedHook;
use MediaWiki\Extension\PageReadConfirmations\Hook\PageReadConfirmationRequestedHook;
use MediaWiki\Extension\PageReadConfirmations\Integration\NeoWiki\ConfirmationProperties;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationEntity;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiRegistrar;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class NeoWikiPropertiesUpdater implements
	PageReadConfirmationConfirmedHook,
	PageReadConfirmationAssignmentsChangedHook,
	PageReadConfirmationRequestedHook,
	PageReadConfirmationRequestDeletedHook
{

	/**
	 * @param ReadConfirmationManager $confirmationManager
	 * @param TitleFactory $titleFactory
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct(
		private readonly ReadConfirmationManager $confirmationManager,
		private readonly TitleFactory $titleFactory,
		private readonly RevisionLookup $revisionLookup
	) {
	}

	/**
	 * @param NeoWikiRegistrar $registrar
	 * @return void
	 */
	public function onNeoWikiRegistration( NeoWikiRegistrar $registrar ): void {
		$registrar->addPagePropertyProvider(
			new ConfirmationProperties( $this->confirmationManager, $this->titleFactory, $this->revisionLookup )
		);
	}

	/**
	 * @param PageIdentity $page
	 * @return void
	 */
	private function tryUpdateNeoWikiProperties( PageIdentity $page ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'NeoWiki' ) ) {
			return;
		}
		$title = $this->titleFactory->castFromPageIdentity( $page );
		if ( !$title ) {
			return;
		}
		NeoWikiExtension::getInstance()
			->newPageRebuilder()
			->rebuild( $title );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageReadConfirmationAssignmentsChanged(
		PageIdentity $page, array $added, array $removed, Authority $actor
	): void {
		$this->tryUpdateNeoWikiProperties( $page );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageReadConfirmationConfirmed( ReadConfirmationEntity $confirmation ): void {
		$this->tryUpdateNeoWikiProperties( $confirmation->revision->getPage() );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageReadConfirmationRequestDeleted( PageIdentity $page, Authority $authority ): void {
		$this->tryUpdateNeoWikiProperties( $page );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageReadConfirmationRequested(
		PageIdentity $page, RevisionRecord $revisionToRead, Authority $actor
	): void {
		$this->tryUpdateNeoWikiProperties( $page );
	}
}
