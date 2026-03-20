<?php

namespace MediaWiki\Extension\PageReadConfirmations\Data;

use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionLookup;
use MWStake\MediaWiki\Component\DataStore\IPrimaryDataProvider;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;

class PrimaryDataProvider implements IPrimaryDataProvider {

	/**
	 * @param PageIdentity $forPage
	 * @param ReadConfirmationManager $confirmationManager
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct(
		private readonly PageIdentity $forPage,
		private readonly ReadConfirmationManager $confirmationManager,
		private readonly RevisionLookup $revisionLookup
	) {
	}

	/**
	 * @param ReaderParams $params
	 * @return array|\MWStake\MediaWiki\Component\DataStore\Record[]
	 */
	public function makeData( $params ) {
		$assignmentStore = $this->confirmationManager->getConfirmationAssignmentStore();
		$assignees = $assignmentStore->getAssignees( $this->forPage );
		$latestMustRead = $this->confirmationManager->getRequestedRevisionId( $this->forPage );
		$latestMustReadRevision = $latestMustRead ? $this->revisionLookup->getRevisionById( $latestMustRead ) : null;

		// Performance note: This will create user object, check their permissions and retrieve confirmations for them
		// This is needed for filtering - even though it is not ideal to do it in PDP, for 2000 assignees takes ~3s
		// If we want to get rid of ability to filter, we can bring down to negligible time
		$data = [];
		foreach ( $assignees as $assignee ) {
			// Confirmation for this requested revision
			$confirmationForRequested = $this->confirmationManager->getConfirmation(
				$assignee, $latestMustReadRevision, $this->forPage
			);
			$confirmation = $confirmationForRequested;
			if ( !$confirmationForRequested ) {
				// If no confirmation for requested rev, get latest confirmation
				$confirmation = $this->confirmationManager->getConfirmation( $assignee, null, $this->forPage );
			}

			// Pending is if there is an active request and user
			// did not read anything or did not read requested revision yet
			$isPending = $latestMustRead && !$confirmationForRequested;

			$readAt = $confirmationForRequested?->readAt?->format( 'YmdHis' ) ?? '';
			$rowData = [
				Record::USER_ID => $assignee->getId(),
				Record::USER_NAME => $assignee->getName(),
				Record::READ_AT => $readAt,
				Record::READ_REVISION => $confirmation?->revision->getId() ?? null,
				Record::READ_AT_FOR_USER => '',
				// If nothing is requested, cannot be pending - set null to "disable" the field
				Record::HAS_CONFIRMED => $latestMustRead === null ? null : !$isPending
			];
			$data[] = new Record( (object)$rowData );
		}

		return $data;
	}
}
