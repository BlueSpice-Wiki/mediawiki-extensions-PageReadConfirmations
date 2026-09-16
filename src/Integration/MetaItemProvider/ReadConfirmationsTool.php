<?php

namespace MediaWiki\Extension\PageReadConfirmations\Integration\MetaItemProvider;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\Literal;
use OOUI\ButtonWidget;
use OOUI\HorizontalLayout;
use OOUI\LabelWidget;

class ReadConfirmationsTool extends Literal {

	/** @var \DateTime|null */
	private ?\DateTime $readAt = null;
	/** @var bool */
	private bool $mustReadThisRevision = false;
	/** @var RevisionRecord|null */
	private ?RevisionRecord $mustReadAnother = null;
	/** @var Title|null */
	private ?Title $title = null;
	/** @var OutputPage */
	private OutputPage $output;

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
		if ( !$this->manager->isEnabled( $title ) ) {
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
			$this->output = $context->getOutput();
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
			// Must confirm
			return ( new ButtonWidget( [
				'label' => Message::newFromKey( 'page-read-assignments-do-confirm-label' )->text(),
				'icon' => 'add',
				'framed' => false,
				'flags' => [ 'progressive' ],
				'classes' => [ 'page-read-confirmations-confirm-button' ],
			] ) )->toString();
		} else {
			$html = new HorizontalLayout();
			$mustReadAnotherButton = new ButtonWidget( [
				'icon' => 'alert',
				'title' => Message::newFromKey( 'page-read-assignments-read-another-version-label' )->text(),
				'framed' => false,
				'infusable' => true,
				'classes' => [ 'page-read-confirmations-another-version-button' ],
				'data' => json_encode( [ 'mustRead' => $this->mustReadAnother?->getId() ] )
			] );
			if ( $this->readAt ) {
				$html->appendContent(
					new LabelWidget( [
						'label' => Message::newFromKey( 'page-read-assignments-has-confirmed-label' )->text(),
						'classes' => [ 'page-read-confirmations-confirmed-label' ]
					] )
				);
				if ( $this->mustReadAnother ) {
					$this->output->addModules( [ 'ext.pageReadConfirmations.anotherRequestedPopup' ] );
					$html->appendContent( $mustReadAnotherButton );
				}
			} elseif ( $this->mustReadAnother ) {
				$this->output->addModules( [ 'ext.pageReadConfirmations.anotherRequestedPopup' ] );
				$mustReadAnotherButton->setLabel(
					Message::newFromKey( 'page-read-assignments-read-another-version-label' )->text()
				);
				$html->appendContent( $mustReadAnotherButton );
			}
			return $html->toString();
		}
	}
}
