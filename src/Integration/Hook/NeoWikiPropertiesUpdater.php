<?php

namespace MediaWiki\Extension\PageReadConfirmations\Integration\Hook;

use BlueSpice\GalaxyDistributionConnector\NeoWiki\PropertyType\UserProperty;
use BlueSpice\GalaxyDistributionConnector\NeoWiki\Util\PropertyWithLabel;
use MediaWiki\Extension\PageReadConfirmations\Hook\PageReadConfirmationAssignmentsChangedHook;
use MediaWiki\Extension\PageReadConfirmations\Hook\PageReadConfirmationConfirmedHook;
use MediaWiki\Extension\PageReadConfirmations\Hook\PageReadConfirmationRequestDeletedHook;
use MediaWiki\Extension\PageReadConfirmations\Hook\PageReadConfirmationRequestedHook;
use MediaWiki\Extension\PageReadConfirmations\Integration\NeoWiki\ConfirmationProperties;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationEntity;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\BooleanProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateTimeProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\TextProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
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
	 * @param array &$propertyDefinitions
	 * @return void
	 */
	public function onBlueSpiceGalaxyConnectorNeoWikiPagePropertyDefinitions( array &$propertyDefinitions ): void {
		$propertyDefinitions['read_confirmation/read'] = new PropertyWithLabel(
			label: Message::newFromKey( 'page-read-confirmations-neowiki-property-read' )->text(),
			definition: new UserProperty(
				core: new PropertyCore(
					description: Message::newFromKey( 'page-read-confirmations-neowiki-property-read' )->text(),
					required: true,
					default: ''
				)
			)
		);
		$propertyDefinitions['read_confirmation/read_count'] = new PropertyWithLabel(
			label: Message::newFromKey( 'page-read-confirmations-neowiki-property-read-count' )->text(),
			definition: new NumberProperty(
				core: new PropertyCore(
					description: Message::newFromKey(
						'page-read-confirmations-neowiki-property-read-count'
					)->text(),
					required: true,
					default: 0
				),
				precision: 0,
				minimum: 0, maximum: null
			)
		);
		$propertyDefinitions['read_confirmation/pending'] = new PropertyWithLabel(
			label: Message::newFromKey( 'page-read-confirmations-neowiki-property-pending' )->text(),
			definition: new UserProperty(
				core: new PropertyCore(
					description: Message::newFromKey(
						'page-read-confirmations-neowiki-property-pending'
					)->text(),
					required: true,
					default: null
				)
			)
		);
		$propertyDefinitions['read_confirmation/pending_count'] = new PropertyWithLabel(
			label: Message::newFromKey( 'page-read-confirmations-neowiki-property-pending-count' )->text(),
			definition: new NumberProperty(
				core: new PropertyCore(
					description: Message::newFromKey(
						'page-read-confirmations-neowiki-property-pending-count'
					)->text(),
					required: true,
					default: 0
				),
				precision: 0,
				minimum: 0, maximum: null
			)
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
