<?php

namespace MediaWiki\Extension\PageReadConfirmations\Data;

use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationEntity;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionLookup;
use MWStake\MediaWiki\Component\DataStore\IPrimaryDataProvider;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;

class PrimaryDataProvider implements IPrimaryDataProvider {

	/**
	 * @param PageIdentity $forPage
	 * @param int|null $forRevision
	 * @param ReadConfirmationManager $confirmationManager
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct(
		private readonly PageIdentity $forPage,
		private readonly ?int $forRevision,
		private readonly ReadConfirmationManager $confirmationManager,
		private readonly RevisionLookup $revisionLookup
	) {
	}

	/**
	 * @param ReaderParams $params
	 * @return array|\MWStake\MediaWiki\Component\DataStore\Record[]
	 */
	public function makeData( $params ) {
		$data = [];
		$assignmentStore = $this->confirmationManager->getConfirmationAssignmentStore();
		$requestedRevision = $this->revisionLookup->getRevisionById( $this->forRevision );

		if ( !$requestedRevision ) {
			// Not requesting a particular revision, show all latest confirmations
			$confirmations = $this->confirmationManager->getLatestConfirmations( $this->forPage );
			foreach ( $confirmations as $confirmation ) {
				$data[] = $this->getConfirmationRecord( $confirmation );
			}
			return $data;
		}

		$latestMustRead = $this->confirmationManager->getRequestedRevisionId( $this->forPage ) ?? 0;
		$latestMustReadRevision = $latestMustRead ? $this->revisionLookup->getRevisionById( $latestMustRead ) : null;

		if ( $latestMustRead !== $requestedRevision->getId() ) {
			$confirmations = $this->confirmationManager->getConfirmationsForRevision( $requestedRevision );
			foreach ( $confirmations as $confirmation ) {
				$data[] = $this->getConfirmationRecord( $confirmation );
			}
			return $data;
		}
		if ( !$latestMustReadRevision ) {
			return [];
		}

		$assignees = $assignmentStore->getAssignees( $this->forPage );
		// Performance note: This will create user object, check their permissions and retrieve confirmations for them
		// This is needed for filtering - even though it is not ideal to do it in PDP, for 2000 assignees takes ~3s
		// If we want to get rid of ability to filter, we can bring down to negligible time

		foreach ( $assignees as $assignee ) {
			// Confirmation for this requested revision
			$confirmation = $this->confirmationManager->getConfirmation(
				$assignee, $latestMustReadRevision, $this->forPage
			);

			// Pending is if there is an active request and user
			// did not read anything or did not read requested revision yet
			$isPending = $latestMustRead && !$confirmation;

			if ( $confirmation ) {
				$record = $this->getConfirmationRecord( $confirmation );
			} else {
				$record = new Record( (object)[
					Record::USER_ID => $assignee->getId(),
					Record::USER_NAME => $assignee->getName(),
					Record::READ_AT => '',
					Record::READ_REVISION => null,
					Record::READ_AT_FOR_USER => '',
					// If nothing is requested, cannot be pending - set null to "disable" the field
					Record::HAS_CONFIRMED => $latestMustRead === null ? null : !$isPending
				] );
			}
			$data[] = $record;
		}

		return $data;
	}

	/**
	 * @param ReadConfirmationEntity $confirmation
	 * @return Record
	 */
	private function getConfirmationRecord( ReadConfirmationEntity $confirmation ): Record {
		return new Record( (object)[
			Record::USER_ID => $confirmation->assignee->getId(),
			Record::USER_NAME => $confirmation->assignee->getName(),
			Record::READ_AT => $confirmation->readAt?->format( 'YmdHis' ) ?? '',
			Record::READ_REVISION => $confirmation->revision->getId() ?? null,
			Record::READ_AT_FOR_USER => '',
			Record::HAS_CONFIRMED => true
		] );
	}

}
