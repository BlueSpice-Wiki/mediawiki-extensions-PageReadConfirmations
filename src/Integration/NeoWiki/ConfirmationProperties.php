<?php

namespace MediaWiki\Extension\PageReadConfirmations\Integration\NeoWiki;

use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\NeoWiki\Domain\Page\PagePropertyProviderContext;

class ConfirmationProperties implements PagePropertyProvider {

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
	 * @param PagePropertyProviderContext $context
	 * @return array|mixed[]
	 */
	public function getProperties( PagePropertyProviderContext $context ): array {
		$title = $this->titleFactory->newFromID( $context->pageId->id );
		if ( !$title || !$title->exists() ) {
			return [];
		}
		$assignmentStore = $this->confirmationManager->getConfirmationAssignmentStore();
		$assignees = $assignmentStore->getAssignees( $title );
		$latestMustRead = $this->confirmationManager->getRequestedRevisionId( $title );
		$latestMustReadRevision = $latestMustRead ? $this->revisionLookup->getRevisionById( $latestMustRead ) : null;

		// Performance note: This will create user object, check their permissions and retrieve confirmations for them
		// This is needed for filtering - even though it is not ideal to do it in PDP, for 2000 assignees takes ~3s
		// If we want to get rid of ability to filter, we can bring down to negligible time
		$pending = [];
		$read = [];
		foreach ( $assignees as $assignee ) {
			// Confirmation for this requested revision
			$confirmationForRequested = $this->confirmationManager->getConfirmation(
				$assignee, $latestMustReadRevision, $title
			);

			// Pending is if there is an active request and user
			// did not read anything or did not read requested revision yet
			$isPending = $latestMustRead && !$confirmationForRequested;

			if ( $isPending && $latestMustRead ) {
				$pending[] = $assignee->getName();
			} else {
				$read[] = $assignee->getName();
			}
		}

		return [
			'read_confirmation/read' => $read,
			'read_confirmation/read_count' => count( $read ),
			'read_confirmation/pending' => $pending,
			'read_confirmation/pending_count' => count( $pending ),
		];
	}
}
