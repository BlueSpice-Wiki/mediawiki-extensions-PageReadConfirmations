<?php

namespace MediaWiki\Extension\PageReadConfirmations\Integration\Hook;

use MediaWiki\Extension\PageReadConfirmations\Integration\UnifiedTaskOverview\ReadConfirmationTaskDescriptor;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationEntity;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserFactory;

class UpdateUnifiedTaskOverview {

	private ReadConfirmationManager $manager;
	private TitleFactory $titleFactory;
	private UserFactory $userFactory;

	public function __construct(
		ReadConfirmationManager $manager,
		TitleFactory $titleFactory,
		UserFactory $userFactory
	) {
		$this->manager = $manager;
		$this->titleFactory = $titleFactory;
		$this->userFactory = $userFactory;
	}

	public function onPageReadConfirmationConfirmed( ReadConfirmationEntity $confirmation ): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'UnifiedTaskOverview' ) ) {
			return;
		}
		$title = $this->titleFactory->newFromID( $confirmation->revision->getPageId() );
		if ( !$title ) {
			return;
		}
		$user = $this->userFactory->newFromUserIdentity( $confirmation->assignee );
		$descriptor = new ReadConfirmationTaskDescriptor( $title, $confirmation->revision );
		MediaWikiServices::getInstance()->getService( 'UnifiedTaskOverview.TaskStore' )
			->updateTask( $descriptor, $user, true );
	}

	/**
	 * @param PageIdentity $page
	 * @param RevisionRecord $revisionToRead
	 * @param Authority $actor
	 * @return void
	 */
	public function onPageReadConfirmationRequested(
		PageIdentity $page,
		RevisionRecord $revisionToRead,
		$actor
	): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'UnifiedTaskOverview' ) ) {
			return;
		}
		$title = $this->titleFactory->newFromPageIdentity( $page );
		$taskStore = MediaWikiServices::getInstance()->getService( 'UnifiedTaskOverview.TaskStore' );
		$descriptor = new ReadConfirmationTaskDescriptor( $title, $revisionToRead );
		foreach ( $this->manager->getPendingUsers( $page ) as $pendingUser ) {
			$user = $this->userFactory->newFromUserIdentity( $pendingUser );
			$taskStore->updateTask( $descriptor, $user, false );
		}
	}

}
