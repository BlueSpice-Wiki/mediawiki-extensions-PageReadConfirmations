<?php

namespace MediaWiki\Extension\PageReadConfirmations\Integration\Hook;

use MediaWiki\Extension\PageReadConfirmations\Hook\PageReadConfirmationAssignmentsChangedHook;
use MediaWiki\Extension\PageReadConfirmations\Hook\PageReadConfirmationConfirmedHook;
use MediaWiki\Extension\PageReadConfirmations\Hook\PageReadConfirmationRequestDeletedHook;
use MediaWiki\Extension\PageReadConfirmations\Hook\PageReadConfirmationRequestedHook;
use MediaWiki\Extension\PageReadConfirmations\Integration\UnifiedTaskOverview\ReadConfirmationTaskDescriptor;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationEntity;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Extension\PageReadConfirmations\Store\ReadConfirmationAssignmentStore;
use MediaWiki\Extension\UnifiedTaskOverview\TaskStore;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\TitleFactory;

class UpdateUnifiedTaskOverview implements
	PageReadConfirmationRequestedHook,
	PageReadConfirmationConfirmedHook,
	PageReadConfirmationAssignmentsChangedHook,
	PageReadConfirmationRequestDeletedHook
{

	/**
	 * @param ReadConfirmationManager $manager
	 * @param TitleFactory $titleFactory
	 * @param ReadConfirmationAssignmentStore $assignmentStore
	 */
	public function __construct(
		private readonly ReadConfirmationManager $manager,
		private readonly TitleFactory $titleFactory,
		private readonly ReadConfirmationAssignmentStore $assignmentStore
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onPageReadConfirmationConfirmed( ReadConfirmationEntity $confirmation ): void {
		$title = $this->titleFactory->newFromID( $confirmation->revision->getPageId() );
		if ( !$title ) {
			return;
		}
		$descriptor = new ReadConfirmationTaskDescriptor( $title );
		$this->getTaskStore()?->deleteTask( $descriptor, $confirmation->assignee );
	}

	/**
	 * @param PageIdentity $page
	 * @param RevisionRecord $revisionToRead
	 * @param Authority $actor
	 * @return void
	 * @throws \Throwable
	 */
	public function onPageReadConfirmationRequested(
		PageIdentity $page,
		RevisionRecord $revisionToRead,
		$actor
	): void {
		$title = $this->titleFactory->newFromPageIdentity( $page );
		$descriptor = new ReadConfirmationTaskDescriptor( $title, $revisionToRead );
		foreach ( $this->manager->getPendingUsers( $page ) as $pendingUser ) {
			$this->getTaskStore()?->storeTask( $descriptor, $pendingUser );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onPageReadConfirmationAssignmentsChanged(
		PageIdentity $page, array $added, array $removed, Authority $actor
	): void {
		$removed = $this->assignmentStore->resolveAssigneeArray( $removed );
		$added = $this->assignmentStore->resolveAssigneeArray( $added );
		$descriptor = new ReadConfirmationTaskDescriptor( $this->titleFactory->newFromPageIdentity( $page ) );

		foreach ( $removed as $user ) {
			$this->getTaskStore()?->deleteTask( $descriptor, $user );
		}

		foreach ( $added as $user ) {
			$this->getTaskStore()?->storeTask( $descriptor, $user );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onPageReadConfirmationRequestDeleted( PageIdentity $page, Authority $authority ): void {
		$assignees = $this->assignmentStore->getAssignees( $page );
		$descriptor = new ReadConfirmationTaskDescriptor( $this->titleFactory->newFromPageIdentity( $page ) );
		foreach ( $assignees as $assignee ) {
			$this->getTaskStore()?->deleteTask( $descriptor, $assignee );
		}
	}

	/**
	 * @return TaskStore|null
	 */
	private function getTaskStore(): ?TaskStore {
		$services = MediaWikiServices::getInstance();
		return $services->hasService( 'UnifiedTaskOverview.TaskStore' ) ?
			$services->getService( 'UnifiedTaskOverview.TaskStore' ) : null;
	}
}
