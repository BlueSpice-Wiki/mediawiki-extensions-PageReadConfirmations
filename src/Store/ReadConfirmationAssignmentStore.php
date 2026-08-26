<?php

namespace MediaWiki\Extension\PageReadConfirmations\Store;

use MediaWiki\Block\BlockManager;
use MediaWiki\Config\Config;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\ILoadBalancer;

class ReadConfirmationAssignmentStore {

	/**
	 * @param ILoadBalancer $lb
	 * @param UserGroupManager $userGroupManager
	 * @param UserFactory $userFactory
	 * @param PermissionManager $permissionManager
	 * @param Config $config
	 * @param BlockManager $blockManager
	 */
	public function __construct(
		private readonly ILoadBalancer $lb,
		private readonly UserGroupManager $userGroupManager,
		private readonly UserFactory $userFactory,
		private readonly PermissionManager $permissionManager,
		private readonly Config $config,
		private readonly BlockManager $blockManager
	) {
	}

	public function isAssigned( UserIdentity $user, PageIdentity $page ): bool {
		if ( !$this->isValidAssignee( $user, $page ) || !$page->getId() ) {
			return false;
		}
		$db = $this->lb->getConnection( DB_REPLICA );
		$userGroups = $this->userGroupManager->getUserGroups( $user );
		$groupConds = [
			// Explicit assignment to the user
			$db->makeList( [
				'prca_key' => $user->getName(),
				'prca_type' => 'user',
			], LIST_AND ),
			// "everyone" => "user" group
			$db->makeList( [
				'prca_key' => 'user',
				'prca_type' => 'group',
			], LIST_AND ),
		];
		foreach ( $userGroups as $group ) {
			$groupConds[] = $db->makeList( [
				'prca_key' => $group,
				'prca_type' => 'group',
			], LIST_AND );
		}
		$conds = [
			'prca_page' => $page->getId(),
			'prca_wiki_id' => WikiMap::getCurrentWikiId(),
		];
		$conds[] = $db->makeList( $groupConds, LIST_OR );

		return $db->newSelectQueryBuilder()
			->select( [ 'prca_key' ] )
			->from( 'page_read_confirmations_assignments' )
			->where( $conds )
			->caller( __METHOD__ )
			->fetchRowCount() > 0;
	}

	/**
	 * @param PageIdentity $page
	 * @return array
	 */
	public function getAssignees( PageIdentity $page ): array {
		$raw = $this->lb->getConnection( DB_REPLICA )->newSelectQueryBuilder()
			->select( [ 'prca_key', 'prca_type', 'prca_wiki_id' ] )
			->table( 'page_read_confirmations_assignments' )
			->where( [
				'prca_page' => $page->getId(),
				'prca_wiki_id' => WikiMap::getCurrentWikiId(),
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$assigneeArray = [];
		foreach ( $raw as $row ) {
			$assigneeArray[] = [
				'type' => $row->prca_type,
				'key' => $row->prca_key,
			];
		}
		$validAssignees = [];
		$assignees = $this->resolveAssigneeArray( $assigneeArray );
		foreach ( $assignees as $user ) {
			if ( $this->isValidAssignee( $user, $page ) ) {
				$validAssignees[] = $user;
			}
		}

		return $validAssignees;
	}

	/**
	 * @param array $assigneeArray
	 * @return array
	 */
	public function resolveAssigneeArray( array $assigneeArray ): array {
		$assignees = [];
		foreach ( $assigneeArray as $row ) {
			if ( $row['type'] === 'user' ) {
				$user = $this->userFactory->newFromName( $row['key'] );
				$assignees[$user->getId()] = $user;
			} elseif ( $row['type'] === 'group' ) {
				foreach ( $this->resolveGroup( $row['key'] ) as $user ) {
					$assignees[$user->getId()] = $user;
				}
			}
		}
		return array_values( $assignees );
	}

	/**
	 * @param PageIdentity $page
	 * @return array
	 */
	public function getAssignmentsForPage( PageIdentity $page ): array {
		return $this->getAssignmentsForPageId( $page->getId() );
	}

	/**
	 * @param int $page
	 * @return array
	 */
	private function getAssignmentsForPageId( int $page ) {
		$res = $this->lb->getConnection( DB_REPLICA )->select(
			'page_read_confirmations_assignments',
			[ 'prca_key', 'prca_type' ],
			[
				'prca_page' => $page,
				'prca_wiki_id' => WikiMap::getCurrentWikiId(),
			],
			__METHOD__
		);

		$assignments = [];
		foreach ( $res as $row ) {
			$assignments[] = [
				'key' => $row->prca_key,
				'type' => $row->prca_type,
				'_key' => $row->prca_key . ':' . $row->prca_type,
			];
		}
		return $assignments;
	}

	/**
	 * @param int $page
	 * @param array $assignments
	 * @return array [ $added, $removed ]
	 */
	public function storeAssignments( int $page, array $assignments ): array {
		$db = $this->lb->getConnection( DB_PRIMARY );
		foreach ( $assignments as &$assignment ) {
			$key = $assignment['key'] . ':' . $assignment['type'];
			$assignment['_key'] = $key;
		}

		$old = $this->getAssignmentsForPageId( $page );
		$oldKeys = array_column( $old, '_key' );
		$newKeys = array_column( $assignments, '_key' );
		$addedKeys = array_diff( $newKeys, $oldKeys );
		$removedKeys = array_diff( $oldKeys, $newKeys );

		$removed = [];
		$added = [];
		foreach ( $old as $oldAssignment ) {
			if ( in_array( $oldAssignment['_key'], $removedKeys ) ) {
				$this->removeAssignment( $page, $oldAssignment );
				$removed[] = $oldAssignment;
			}
		}

		if ( empty( $addedKeys ) ) {
			return [ [], $removed ];
		}

		$rows = [];
		foreach ( $assignments as $a ) {
			if ( !in_array( $a['_key'], $addedKeys ) ) {
				continue;
			}
			$rows[] = [
				'prca_page' => $page,
				'prca_key' => $a['key'],
				'prca_type' => $a['type'],
				'prca_timestamp' => $db->timestamp(),
				'prca_wiki_id' => WikiMap::getCurrentWikiId(),
			];
			$added[] = $a;
		}

		$db->newInsertQueryBuilder()
			->table( 'page_read_confirmations_assignments' )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();

		return [ $added, $removed ];
	}

	/**
	 * @param int $page
	 * @return void
	 */
	public function clearAssignments( int $page ): void {
		$this->lb->getConnection( DB_PRIMARY )->newDeleteQueryBuilder()
			->deleteFrom( 'page_read_confirmations_assignments' )
			->where( [
				'prca_page' => $page,
				'prca_wiki_id' => WikiMap::getCurrentWikiId(),
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Set revision to read
	 *
	 * @param int $page
	 * @param int $revisionToRead
	 * @return void
	 */
	public function updateRequestedRevision( int $page, int $revisionToRead ): void {
		$db = $this->lb->getConnection( DB_PRIMARY );
		$db->newUpdateQueryBuilder()
			->update( 'page_read_confirmations_requests' )
			->set( [ 'prcr_revision' => $revisionToRead, 'prcr_timestamp' => $db->timestamp() ] )
			->caller( __METHOD__ )
			->where( [
				'prcr_page' => $page,
				'prcr_wiki_id' => WikiMap::getCurrentWikiId(),
			] )
			->execute();
	}

	/**
	 * @param int $page
	 * @param int $revisionToRead
	 * @return void
	 */
	public function insertRequestedRevision( int $page, int $revisionToRead ): void {
		$db = $this->lb->getConnection( DB_PRIMARY );
		$db->newInsertQueryBuilder()
			->insertInto( 'page_read_confirmations_requests' )
			->row( [
				'prcr_page' => $page,
				'prcr_wiki_id' => WikiMap::getCurrentWikiId(),
				'prcr_revision' => $revisionToRead,
				'prcr_timestamp' => $db->timestamp(),
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param PageIdentity $page
	 * @return int|null
	 */
	public function getRequestedRevisionId( PageIdentity $page ): ?int {
		$revision = $this->lb->getConnection( DB_REPLICA )->newSelectQueryBuilder()
			->select( [ 'prcr_revision' ] )
			->from( 'page_read_confirmations_requests' )
			->where( [
				'prcr_page' => $page->getId(),
				'prcr_wiki_id' => WikiMap::getCurrentWikiId(),
			] )
			->limit( 1 )
			->caller( __METHOD__ )
			->fetchField();

		if ( !$revision ) {
			return null;
		}
		return (int)$revision;
	}

	/**
	 * @param int $pageId
	 * @return void
	 */
	public function deleteRequestForPage( int $pageId ): void {
		$this->lb->getConnection( DB_PRIMARY )->newDeleteQueryBuilder()
			->deleteFrom( 'page_read_confirmations_requests' )
			->where( [
				'prcr_page' => $pageId,
				'prcr_wiki_id' => WikiMap::getCurrentWikiId(),
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param int $page
	 * @param array $assignment
	 * @return void
	 */
	private function removeAssignment( int $page, array $assignment ) {
		$db = $this->lb->getConnection( DB_PRIMARY );
		$db->newDeleteQueryBuilder()
			->table( 'page_read_confirmations_assignments' )
			->where( [
				'prca_page' => $page,
				'prca_key' => $assignment['key'],
				'prca_type' => $assignment['type'],
				'prca_wiki_id' => WikiMap::getCurrentWikiId(),
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param string $groupName
	 * @return UserIdentity[]
	 */
	private function resolveGroup( string $groupName ): array {
		$membersQuery = $this->lb->getConnection( DB_REPLICA )->newSelectQueryBuilder()
			->select( [ 'user_id', 'user_name' ] )
			->from( 'user' )
			->caller( __METHOD__ );
		if ( $groupName !== 'user' ) {
			$membersQuery = $membersQuery
				->from( 'user_groups', 'ug' )
				->where( [ 'ug_group' => $groupName ] )
				->join( 'user_groups', 'ug', [ 'user_id = ug_user' ] );
		}
		$membersRes = $membersQuery->fetchResultSet();

		$members = [];
		foreach ( $membersRes as $memberRow ) {
			$user = $this->userFactory->newFromName( $memberRow->user_name );
			if ( $this->blockManager->getBlock( $user, null ) ) {
				continue;
			}
			if ( $user ) {
				$members[] = $user;
			}
		}
		return $members;
	}

	/**
	 * @param UserIdentity $user
	 * @param PageIdentity $page
	 * @return bool
	 */
	private function isValidAssignee( UserIdentity $user, PageIdentity $page ): bool {
		if ( !( $user instanceof User ) ) {
			$user = $this->userFactory->newFromUserIdentity( $user );
		}
		$reservedUsernames = $this->config->get( 'ReservedUsernames' ) ?? [];
		if ( in_array( $user->getName(), $reservedUsernames ) ) {
			return false;
		}
		if ( !$user->isRegistered() || $user->isSystemUser() || $user->getBlock() ) {
			return false;
		}
		if ( $user->getToken() !== $user->getToken() ) {
			// Some system users are not actually system users, but if token changes from call to call,
			// its not a real user
			return false;
		}
		// Check if the user has read access to the page
		return $this->permissionManager->quickUserCan( 'read', $user, $page );
	}
}
