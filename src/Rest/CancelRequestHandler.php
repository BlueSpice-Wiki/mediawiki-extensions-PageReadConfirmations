<?php

namespace MediaWiki\Extension\PageReadConfirmations\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Message\Message;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class CancelRequestHandler extends SimpleHandler {

	/**
	 * @param ReadConfirmationManager $confirmationManager
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		private readonly ReadConfirmationManager $confirmationManager,
		private readonly TitleFactory $titleFactory
	) {
	}

	/**
	 * @return true
	 */
	public function needsReadAccess() {
		return true;
	}

	public function needsWriteAccess() {
		return true;
	}

	/**
	 * @return \MediaWiki\Rest\Response|mixed
	 * @throws \Exception
	 */
	public function execute() {
		$user = RequestContext::getMain()->getUser();
		$title = $this->titleFactory->newFromText( $this->getValidatedBody()['page'] );
		if ( !$title || !$title->exists() ) {
			throw new \InvalidArgumentException(
				Message::newFromKey( 'page-read-confirmations-invalid-revision-id' )->text()
			);
		}

		$this->confirmationManager->deleteRequest( $title, $user );
		return [
			'success' => true
		];
	}

	/**
	 * @return array[]
	 */
	public function getBodyParamSettings(): array {
		return [
			'page' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

}
