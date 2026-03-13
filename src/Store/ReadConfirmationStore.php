<?php

namespace MediaWiki\Extension\PageReadConfirmations\Store;

use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationEntity;
use MediaWiki\Extension\PageReadConfirmations\Util\ReadConfirmationQueryBuilder;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\ILoadBalancer;

class ReadConfirmationStore {

	/**
	 * @param ILoadBalancer $lb
	 */
	public function __construct(
		private readonly ILoadBalancer $lb
	) {
	}

	/**
	 * @return ReadConfirmationQueryBuilder
	 */
	public function newQueryBuilder(): ReadConfirmationQueryBuilder {
		return new ReadConfirmationQueryBuilder( $this->lb );
	}

	/**
	 * @param ReadConfirmationEntity $entity
	 * @return void
	 */
	public function store( ReadConfirmationEntity $entity ): void {
		$this->remove( $entity );

		$db = $this->lb->getConnectionRef( DB_PRIMARY );
		$row = [
			'prc_user' => $entity->assignee->getId(),
			'prc_rev' => $entity->revision->getId(),
			'prc_wiki_id' => WikiMap::getCurrentWikiId(),
			'prc_page' => $entity->revision->getPageId(),
			'prc_read_at' => $db->timestamp( $entity->readAt?->getTimestamp() ),
		];
		$db->newInsertQueryBuilder()
			->insert( 'page_read_confirmations' )
			->row( $row )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param ReadConfirmationEntity $entity
	 * @return void
	 */
	public function remove( ReadConfirmationEntity $entity ): void {
		$db = $this->lb->getConnectionRef( DB_PRIMARY );
		$db->newDeleteQueryBuilder()
			->delete( 'page_read_confirmations' )
			->where( [
				'prc_user' => $entity->assignee->getId(),
				'prc_rev' => $entity->revision->getId(),
				'prc_wiki_id' => $entity->wikiId,
			] )
			->caller( __METHOD__ )
			->execute();
	}
}
