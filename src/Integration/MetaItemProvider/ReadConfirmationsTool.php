<?php

namespace MediaWiki\Extension\PageReadConfirmations\Integration\MetaItemProvider;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Message\Message;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\Literal;
use OOUI\ButtonWidget;

class ReadConfirmationsTool extends Literal {

	/** @var \DateTime|null */
	private ?\DateTime $readAt = null;
	/** @var bool */
	private bool $mustReadThisRevision = false;
	/** @var RevisionRecord|null */
	private ?RevisionRecord $mustReadAnother = null;
	/** @var Title|null */
	private ?Title $title = null;

	/**
	 *
	 */
	public function __construct(
		private readonly ReadConfirmationManager $manager,
		private readonly RevisionLookup $revisionLookup
	) {
		parent::__construct( 'read-confirmations-tool', '' );
	}

	/**
	 *
	 * @param IContextSource $context
	 * @return bool
	 */
	public function shouldRender( $context ): bool {
		$title = $context->getTitle();
		if ( !$title || !$title->exists() || !$title->canExist() ) {
			return false;
		}
		$action = $context->getRequest()->getVal( 'action', 'view' );
		if ( $action !== 'view' ) {
			return false;
		}
		$revId = $context->getOutput()->getRevisionId();
		$revision = $this->revisionLookup->getRevisionById( $revId );
		if ( !$revision ) {
			return false;
		}
		$this->title = $title;

		$this->readAt = $this->manager->getReadAt( $context->getUser(), $revision );
		$mustReadRevision = $this->manager->getMustReadRevisionId( $context->getUser(), $title );
		$this->mustReadThisRevision = $mustReadRevision && $mustReadRevision->getId() === $revId;
		$this->mustReadAnother = $mustReadRevision;

		if ( $this->readAt || $this->mustReadThisRevision || $this->mustReadAnother ) {
			$context->getOutput()->enableOOUI();
			return true;
		}
		return false;
	}

	/**
	 *
	 * @return string
	 */
	public function getHtml(): string {
		if ( $this->mustReadThisRevision ) {
			// We are on the right revision
			if ( $this->readAt ) {
				// Already read
				return \Html::element( 'span', [
					'class' => 'page-read-confirmations-confirmed-label'
				], Message::newFromKey( 'page-read-assignments-has-confirmed-label' )->text() );
			} else {
				// Must confirm
				return ( new ButtonWidget( [
					'label' => Message::newFromKey( 'page-read-assignments-do-confirm-label' )->text(),
					'icon' => 'add',
					'flags' => [ 'progressive' ],
					'framed' => false,
					'classes' => [ 'page-read-confirmations-confirm-button' ],
				] ) )->toString();
			}
		} elseif ( $this->mustReadAnother ) {
			$message = Message::newFromKey( 'page-read-assignments-has-another-request-label' )
				->params(
					$this->title->getPrefixedText(),
					$this->mustReadAnother->getId()
				)->parse();
			// There is a revision user must read, but not this one
			return \Html::rawElement( 'span', [
				'class' => 'page-read-confirmations-requested-another-label'
			], $message );
		}

		if ( $this->mustReadThisRevision && !$this->readAt ) {

		} elseif ( $this->readAt ) {

		}
		return '';
	}
}
