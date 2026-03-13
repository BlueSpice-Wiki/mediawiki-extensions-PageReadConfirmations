<?php

namespace MediaWiki\Extension\PageReadConfirmations\Util;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use stdClass;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\SelectQueryBuilder;

class ReadConfirmationQueryBuilder {

	/** @var array */
	private array $conditions = [];

	/** @var array|null */
	private ?array $orderBy = null;

	/**
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct(
		private readonly ILoadBalancer $loadBalancer
	) {
	}

	/**
	 * @param PageIdentity $page
	 * @return $this
	 */
	public function forPage( PageIdentity $page ): static {
		$this->conditions['prc_page'] = $page->getId();
		return $this;
	}

	/**
	 * @param UserIdentity $user
	 * @return $this
	 */
	public function forUser( UserIdentity $user ): static {
		$this->conditions['prc_user'] = $user->getId();
		return $this;
	}

	/**
	 * @param RevisionRecord $revision
	 * @return $this
	 */
	public function forRevision( RevisionRecord $revision ): static {
		$this->conditions['prc_rev'] = $revision->getId();
		return $this;
	}

	/**
	 * @param string|array $wikiIds
	 * @return $this
	 */
	public function forWikiId( string|array $wikiIds ): static {
		$this->conditions['prc_wiki_id'] = $wikiIds;
		return $this;
	}

	/**
	 * @param array $conds
	 * @return $this
	 */
	public function conds( array $conds ): static {
		$this->conditions = array_merge( $this->conditions, $conds );
		return $this;
	}

	/**
	 * @param array $fields
	 * @param string $dir
	 * @return static
	 */
	public function setOrderBy( array $fields, string $dir ): static {
		$this->orderBy = [ $fields, $dir ];
		return $this;
	}

	/**
	 * @return bool
	 */
	public function hasRows(): bool {
		$this->assertWikIdCondition();
		$query = $this->getQuery( [ '1' ] );
		return (bool)$query->fetchRowCount();
	}

	/**
	 * @return IResultWrapper
	 */
	public function fetch(): IResultWrapper {
		$this->assertWikIdCondition();

		$q = $this->getSelectAllQuery()->getSQL();
		return $this->getSelectAllQuery()->fetchResultSet();
	}

	/**
	 * @return stdClass|null
	 */
	public function fetchOne(): ?stdClass {
		$this->assertWikIdCondition();
		return $this->getSelectAllQuery()->fetchRow() ?: null;
	}

	/**
	 * @return SelectQueryBuilder
	 */
	private function getSelectAllQuery(): SelectQueryBuilder {
		return $this->getQuery( [ 'prc_user', 'user_name', 'prc_rev', 'prc_wiki_id', 'prc_page', 'prc_read_at' ] );
	}

	/**
	 * @param array $fields
	 * @return SelectQueryBuilder
	 */
	private function getQuery( array $fields ): SelectQueryBuilder {
		$query = $this->loadBalancer->getConnection( DB_REPLICA )
			->newSelectQueryBuilder()
			->table( 'page_read_confirmations', 'prc' )
			->table( 'user', 'u' )
			->select( $fields )
			->conds( $this->conditions )
			->join( 'user', 'u', [ 'u.user_id = prc.prc_user' ] )
			->caller( __METHOD__ );
		if ( $this->orderBy ) {
			$query->orderBy( $this->orderBy[0], $this->orderBy[1] );
		}
		return $query;
	}

	/**
	 * @return void
	 */
	private function assertWikIdCondition(): void {
		if ( !isset( $this->conditions['prc_wiki_id'] ) ) {
			$this->conditions['prc_wiki_id'] = WikiMap::getCurrentWikiId();
		}
	}
}
