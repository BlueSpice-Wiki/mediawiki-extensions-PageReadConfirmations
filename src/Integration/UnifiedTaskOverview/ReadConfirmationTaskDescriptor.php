<?php

namespace MediaWiki\Extension\PageReadConfirmations\Integration\UnifiedTaskOverview;

use MediaWiki\Extension\UnifiedTaskOverview\ITaskDescriptor;
use MediaWiki\Language\RawMessage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use stdClass;

class ReadConfirmationTaskDescriptor implements ITaskDescriptor {

	private Title $title;
	private RevisionRecord $revisionToConfirm;

	public function __construct( Title $title, RevisionRecord $revisionToConfirm ) {
		$this->title = $title;
		$this->revisionToConfirm = $revisionToConfirm;
	}

	/** @inheritDoc */
	public static function newFromTaskRow( stdClass $row ): ?static {
		$services = MediaWikiServices::getInstance();
		$title = $services->getTitleFactory()->newFromID( (int)$row->uto_page_id );
		if ( !$title ) {
			return null;
		}
		$revision = $services->getRevisionLookup()->getRevisionByTitle( $title );
		if ( !$revision ) {
			return null;
		}
		return new static( $title, $revision );
	}

	/** @inheritDoc */
	public function getUniqueKey(): string {
		return $this->title->getArticleID() . ':' . ( $this->revisionToConfirm->getId() ?? 0 );
	}

	/** @inheritDoc */
	public function getTitle(): Title {
		return $this->title;
	}

	/** @inheritDoc */
	public function getType(): string {
		return 'page-read-confirmation';
	}

	/** @inheritDoc */
	public function getURL(): string {
		$query = [];
		if ( !$this->revisionToConfirm->isCurrent() ) {
			$query = [ 'oldid' => $this->revisionToConfirm->getId() ];
		}
		return $this->title->getFullURL( $query );
	}

	/** @inheritDoc */
	public function getHeader(): Message {
		$services = MediaWikiServices::getInstance();
		$displayTitleProperties = $services->getPageProps()->getProperties( $this->title, 'displaytitle' );
		if ( count( $displayTitleProperties ) === 1 ) {
			$displayTitle = $displayTitleProperties[$this->title->getArticleID()];
		}
		return new RawMessage( $displayTitle ?? $this->title->getSubpageText() );
	}

	/** @inheritDoc */
	public function getSubHeader(): Message {
		return Message::newFromKey( 'page-read-confirmations-uto-task-header' );
	}

	/** @inheritDoc */
	public function getBody(): Message {
		return new RawMessage( '' );
	}

	/** @inheritDoc */
	public function getSortKey(): int {
		return 20;
	}

	/** @inheritDoc */
	public function getRLModules(): array {
		return [];
	}

}
