<?php

namespace MediaWiki\Extension\PageReadConfirmations;

use DateTime;
use Exception;
use MediaWiki\Config\Config;
use MediaWiki\Extension\PageReadConfirmations\Integration\Event\PageReadConfirmationReminderEvent;
use MediaWiki\Extension\PageReadConfirmations\Store\ReadConfirmationAssignmentStore;
use MediaWiki\Extension\PageReadConfirmations\Store\ReadConfirmationStore;
use MediaWiki\Extension\PageReadConfirmations\Util\ConfirmationLogger;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Language\Language;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use MWStake\MediaWiki\Component\Events\Notifier;

class ReadConfirmationManager {

	/**
	 * @param ReadConfirmationStore $confirmationStore
	 * @param ReadConfirmationAssignmentStore $assignmentStore
	 * @param PermissionManager $permissionManager
	 * @param RevisionLookup $revisionLookup
	 * @param HookContainer $hookContainer
	 * @param Language $language
	 * @param LinkRenderer $linkRenderer
	 * @param ConfirmationLogger $logger
	 * @param Notifier $notifier
	 * @param Config $config
	 */
	public function __construct(
		private readonly ReadConfirmationStore $confirmationStore,
		private readonly ReadConfirmationAssignmentStore $assignmentStore,
		private readonly PermissionManager $permissionManager,
		private readonly RevisionLookup $revisionLookup,
		private readonly HookContainer $hookContainer,
		private readonly Language $language,
		private readonly LinkRenderer $linkRenderer,
		private readonly ConfirmationLogger $logger,
		private readonly Notifier $notifier,
		private readonly Config $config
	) {
	}

	/**
	 * @param UserIdentity $user
	 * @param RevisionRecord|null $revision
	 * @param PageIdentity|null $page
	 * @return ReadConfirmationEntity|null
	 */
	public function getConfirmation(
		UserIdentity $user, ?RevisionRecord $revision = null, ?PageIdentity $page = null
	): ?ReadConfirmationEntity {
		$query = $this->confirmationStore->newQueryBuilder()
			->forUser( $user );
		if ( $revision ) {
			$query->forRevision( $revision );
		}
		if ( $page ) {
			$query->forPage( $page );
			$query->setOrderBy( [ 'prc_read_at' ], 'DESC' );
		}
		$row = $query->fetchOne();
		if ( !$row ) {
			return null;
		}
		$revision = $revision ?? $this->revisionLookup->getRevisionById( $row->prc_rev );
		if ( !$revision ) {
			return null;
		}
		return new ReadConfirmationEntity(
			assignee: $user,
			revision: $revision,
			wikiId: $row->prc_wiki_id,
			readAt: $row->prc_read_at ? DateTime::createFromFormat( 'YmdHis', $row->prc_read_at ) : null
		);
	}

	/**
	 * @return ReadConfirmationAssignmentStore
	 */
	public function getConfirmationAssignmentStore(): ReadConfirmationAssignmentStore {
		return $this->assignmentStore;
	}

	/**
	 * @param UserIdentity $user
	 * @param RevisionRecord $revisionRecord
	 * @return void
	 */
	public function confirm( UserIdentity $user, RevisionRecord $revisionRecord ): void {
		if ( !$this->isEnabled( $revisionRecord->getPage() ) ) {
			return;
		}
		if ( !$user->isRegistered() ) {
			throw new \InvalidArgumentException(
				Message::newFromKey( 'page-read-confirmations-registered-users-only' )->text()
			);
		}
		if ( !$this->assignmentStore->isAssigned( $user, $revisionRecord->getPage() ) ) {
			throw new \InvalidArgumentException(
				Message::newFromKey( 'page-read-confirmations-user-not-assigned' )->text()
			);
		}
		if ( !$this->mustRead( $user, $revisionRecord ) ) {
			throw new \InvalidArgumentException(
				Message::newFromKey( 'page-read-confirmations-user-not-requested' )->text()
			);
		}
		$confirmation = new ReadConfirmationEntity(
			assignee: $user,
			revision: $revisionRecord,
			wikiId: WikiMap::getCurrentWikiId(),
			readAt: new DateTime()
		);
		$this->confirmationStore->store( $confirmation );
		$this->logger->logConfirm( $user, $revisionRecord );
		$this->hookContainer->run( 'PageReadConfirmationConfirmed', [ $confirmation ] );
	}

	/**
	 * @param PageIdentity $page
	 * @param Authority $actor
	 * @return void
	 * @throws Exception
	 */
	public function deleteRequest( PageIdentity $page, Authority $actor ): void {
		$this->assertActorCan( 'deleteRequest', $page, $actor );
		$this->assignmentStore->deleteRequestForPage( $page->getId() );
		$this->hookContainer->run( 'PageReadConfirmationRequestDeleted', [ $page, $actor ] );
		$this->logger->logRemoveRequest( $actor->getUser(), $page );
	}

	/**
	 * @param UserIdentity $user
	 * @param PageIdentity $page
	 * @param Authority $actor
	 * @return void
	 * @throws Exception
	 */
	public function removeConfirmation( UserIdentity $user, PageIdentity $page, Authority $actor ) {
		$this->assertActorCan( 'removeConfirmation', $page, $actor );
		$confirmation = $this->getConfirmation( $user, null, $page );
		if ( !$confirmation ) {
			return;
		}
		$this->confirmationStore->remove( $confirmation );
		$this->logger->logRemoveConfirmation( $user, $confirmation );
	}

	/**
	 * @param UserIdentity $user
	 * @param RevisionRecord $revisionRecord
	 * @return DateTime|null
	 */
	public function getReadAt( UserIdentity $user, RevisionRecord $revisionRecord ): ?DateTime {
		$confirmation = $this->getConfirmation( $user, $revisionRecord );
		return $confirmation?->readAt;
	}

	/**
	 * @param UserIdentity $user
	 * @param PageIdentity $page
	 * @return RevisionRecord|null
	 */
	public function getMustReadRevisionId( UserIdentity $user, PageIdentity $page ): ?RevisionRecord {
		$isAssigned = $this->assignmentStore->isAssigned( $user, $page );
		if ( !$isAssigned ) {
			return null;
		}
		$requestedRev = $this->getRequestedRevisionId( $page );
		if ( !$requestedRev ) {
			return null;
		}
		$confirmation = $this->getConfirmation( $user, null, $page );
		if ( $confirmation && $confirmation->revision->getId() === $requestedRev ) {
			// Already read requestedRevision
			return null;
		}

		return $this->revisionLookup->getRevisionById( $requestedRev );
	}

	/**
	 * @param UserIdentity $user
	 * @param RevisionRecord $revision
	 * @return bool
	 */
	public function mustRead( UserIdentity $user, RevisionRecord $revision ): bool {
		$mustReadRequested = $this->getMustReadRevisionId( $user, $revision->getPage() );
		return $mustReadRequested && $mustReadRequested->getId() === $revision->getId();
	}

	/**
	 * @param PageIdentity $page
	 * @param array $assignments
	 * @param Authority $actor
	 * @return void
	 * @throws Exception
	 */
	public function storeAssignments( PageIdentity $page, array $assignments, Authority $actor ): void {
		if ( !$this->isEnabled( $page ) ) {
			throw new \InvalidArgumentException(
				Message::newFromKey( 'page-read-confirmations-disabled' )->text()
			);
		}
		$this->assertActorCan( 'assign', $page, $actor );
		foreach ( $assignments as $assignment ) {
			if ( !isset( $assignment['key'] ) || !isset( $assignment['type'] ) ) {
				throw new \InvalidArgumentException(
					Message::newFromKey( 'page-read-confirmations-invalid-assignment-format' )->text()
				);
			}
			if ( !in_array( $assignment['type'], [ 'group', 'user' ] ) ) {
				throw new \InvalidArgumentException(
					Message::newFromKey( 'page-read-confirmations-invalid-assignment-type' )->text()
				);
			}
		}
		[ $added, $removed ] = $this->assignmentStore->storeAssignments( $page->getId(), $assignments );
		foreach ( $added as $a ) {
			$this->logger->logAssign( $actor->getUser(), $page, $a );
		}
		foreach ( $removed as $r ) {
			$this->logger->logUnassign( $actor->getUser(), $page, $r );
		}

		if ( !empty( $added ) ) {
			$this->notifyUsers( $added, $page, $actor );
		}
		$this->hookContainer->run( 'PageReadConfirmationAssignmentsChanged', [ $page, $added, $removed, $actor ] );
	}

	/**
	 * @param PageIdentity $page
	 * @param RevisionRecord $revisionToRead
	 * @param Authority $actor
	 * @return void
	 * @throws Exception
	 */
	public function requestRevisionConfirmation(
		PageIdentity $page, RevisionRecord $revisionToRead, Authority $actor
	): void {
		if ( !$page->getId() || $page->getId() !== $revisionToRead->getPageId() ) {
			throw new \InvalidArgumentException(
				Message::newFromKey( 'page-read-confirmations-revision-not-for-page' )->text()
			);
		}
		if ( !$this->isEnabled( $page ) ) {
			throw new \InvalidArgumentException(
				Message::newFromKey( 'page-read-confirmations-disabled' )->text()
			);
		}
		$this->assertActorCan( 'request', $page, $actor );
		$current = $this->assignmentStore->getRequestedRevisionId( $page );
		if ( $current ) {
			if ( $revisionToRead->getId() === $current ) {
				// No update needed
				return;
			}
			if ( $revisionToRead->getId() < $current ) {
				throw new \InvalidArgumentException(
					Message::newFromKey( 'page-read-confirmations-revision-too-old' )->text()
				);
			}
			$this->assignmentStore->updateRequestedRevision( $page->getId(), $revisionToRead->getId() );
		} else {
			$this->assignmentStore->insertRequestedRevision( $page->getId(), $revisionToRead->getId() );
		}
		$this->logger->logRequest( $actor->getUser(), $revisionToRead );
		$this->hookContainer->run( 'PageReadConfirmationRequested', [ $page, $revisionToRead, $actor ] );

		$this->notifyPending( $page, $actor, $revisionToRead );
	}

	/**
	 * @param PageIdentity $page
	 * @param RevisionRecord|null $revision
	 * @return array
	 */
	public function getPendingUsers( PageIdentity $page, ?RevisionRecord $revision = null ): array {
		if ( !$revision ) {
			$revisionId = $this->getRequestedRevisionId( $page );
			if ( !$revisionId ) {
				return [];
			}
			$revision = $this->revisionLookup->getRevisionById( $revisionId );
			if ( !$revision ) {
				return [];
			}
		}
		$assignments = $this->assignmentStore->getAssignees( $page );
		return array_filter( $assignments, function ( UserIdentity $assignee ) use ( $revision ) {
			return $this->mustRead( $assignee, $revision );
		} );
	}

	/**
	 * @param Title $title
	 * @param Authority $actor
	 * @return array list of notified users
	 * @throws Exception
	 */
	public function sendRemindersForPendingUsers( Title $title, Authority $actor ): array {
		$this->assertActorCan( 'remind', $title, $actor );
		return $this->notifyPending( $title );
	}

	/**
	 * @param PageIdentity $page
	 * @return int|null
	 */
	public function getRequestedRevisionId( PageIdentity $page ): ?int {
		return $this->assignmentStore->getRequestedRevisionId( $page );
	}

	/**
	 * @param PageIdentity $page
	 * @return array|null
	 */
	public function getRequestInfo( PageIdentity $page ): ?array {
		$requestedRev = $this->getRequestedRevisionId( $page );
		if ( !$requestedRev ) {
			return null;
		}
		$requestedRev = $this->revisionLookup->getRevisionById( $requestedRev );
		if ( !$requestedRev ) {
			return null;
		}
		$assignees = $this->assignmentStore->getAssignees( $page );
		$pending = $read = 0;
		foreach ( $assignees as $assignee ) {
			$confirmation = $this->getConfirmation( $assignee, $requestedRev );
			if ( $confirmation && $confirmation->revision->getId() === $requestedRev->getId() ) {
				$read++;
			} else {
				$pending++;
			}
		}
		$revisionTimestamp = $this->language->timeanddate( $requestedRev->getTimestamp() );
		$linkQuery = $requestedRev->isCurrent() ? [] : [ 'oldid' => $requestedRev->getId() ];
		$data = [
			'revision' => $requestedRev->getId(),
			'version_link' => [
				'text' => $revisionTimestamp,
				'query' => $linkQuery,
				'anchor' => $this->linkRenderer->makeKnownLink( $page, $revisionTimestamp, [], $linkQuery ),
			],
			'pending' => $pending,
			'read' => $read,
			'is_current' => $requestedRev->isCurrent()
		];

		$this->hookContainer->run( 'PageReadConfirmationGetRequestInfo', [ $page, &$data ] );
		return $data;
	}

	/**
	 * @param PageIdentity $page
	 * @return bool
	 */
	public function isEnabled( PageIdentity $page ): bool {
		$enabledNamespaces = $this->config->get( 'PageReadConfirmationsEnabledNamespaces' ) ?? [];
		return in_array( $page->getNamespace(), $enabledNamespaces, true );
	}

	/**
	 * @param string $action
	 * @param PageIdentity $page
	 * @param Authority $actor
	 * @return void
	 * @throws Exception
	 */
	private function assertActorCan( string $action, PageIdentity $page, Authority $actor ): void {
		if ( $actor->getUser() instanceof User && $actor->getUser()->isSystemUser() ) {
			return;
		}
		$can = false;
		switch ( $action ) {
			case 'assign':
			case 'deleteRequest':
			case 'removeConfirmation':
				$can = $this->permissionManager->userCan( 'edit', $actor, $page );
				break;
			case 'request':
			case 'remind':
				$can = $actor->isRegistered() && $this->permissionManager->userCan( 'read', $actor, $page );
				break;
		}

		if ( !$can ) {
			throw new Exception( Message::newFromKey( 'page-read-confirmations-no-permission' )->text() );
		}
	}

	/**
	 * @param array $users
	 * @param PageIdentity $page
	 * @param Authority $actor
	 * @return void
	 * @throws Exception
	 */
	private function notifyUsers( array $users, PageIdentity $page, Authority $actor ) {
		$users = $this->assignmentStore->resolveAssigneeArray( $users );
		$event = new PageReadConfirmationReminderEvent(
			$actor->getUser(), $page, $this->getRequestedRevisionId( $page ), $users
		);
		$this->notifier->emit( $event );
	}

	/**
	 * @param PageIdentity $page
	 * @param Authority $actor
	 * @param RevisionRecord|null $revision
	 * @return array|string[]
	 * @throws Exception
	 */
	private function notifyPending( PageIdentity $page, Authority $actor, ?RevisionRecord $revision = null ): array {
		$pending = $this->getPendingUsers( $page, $revision );
		$event = new PageReadConfirmationReminderEvent(
			$actor->getUser(), $page, $this->getRequestedRevisionId( $page ), $pending
		);
		$this->notifier->emit( $event );

		return array_map(
			static function ( UserIdentity $user ) {
				return $user->getName();
			},
			$pending
		);
	}
}
