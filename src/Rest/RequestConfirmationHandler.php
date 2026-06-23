<?php

namespace MediaWiki\Extension\PageReadConfirmations\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Message\Message;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class RequestConfirmationHandler extends SimpleHandler {

	/**
	 * @param RevisionLookup $revisionLookup
	 * @param ReadConfirmationManager $confirmationManager
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		private readonly RevisionLookup $revisionLookup,
		private readonly ReadConfirmationManager $confirmationManager,
		private readonly TitleFactory $titleFactory
	) {
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function execute() {
		$params = $this->getValidatedParams();

		$title = $this->titleFactory->newFromID( $params['page'] );
		if ( !$title ) {
			throw new \InvalidArgumentException(
				Message::newFromKey( 'page-read-confirmations-invalid-page-id'
				)->text() );
		}
		$revision = $this->revisionLookup->getRevisionByTitle( $title );
		if ( !$revision ) {
			throw new \InvalidArgumentException(
				Message::newFromKey( 'page-read-confirmations-no-revision-found-for-page' )->text()
			);
		}
		$this->confirmationManager->requestRevisionConfirmation(
			$title, $revision, RequestContext::getMain()->getUser()
		);

		return [
			'success' => true,
		];
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings(): array {
		return [
			'page' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}
}
