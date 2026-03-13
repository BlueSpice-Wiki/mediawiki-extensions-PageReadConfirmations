<?php

namespace MediaWiki\Extension\PageReadConfirmations\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Message\Message;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Revision\RevisionLookup;
use Wikimedia\ParamValidator\ParamValidator;

class ConfirmationHandler extends SimpleHandler {

	/**
	 * @param ReadConfirmationManager $confirmationManager
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct(
		private readonly ReadConfirmationManager $confirmationManager,
		private readonly RevisionLookup $revisionLookup
	) {
	}

	/**
	 * @return true
	 */
	public function needsReadAccess() {
		return true;
	}

	/**
	 * @return \MediaWiki\Rest\Response|mixed
	 */
	public function execute() {
		$user = RequestContext::getMain()->getUser();
		$revision = $this->revisionLookup->getRevisionById( $this->getValidatedBody()['revisionId'] );
		if ( !$revision ) {
			throw new \InvalidArgumentException(
				Message::newFromKey( 'page-read-confirmations-invalid-revision-id' )->text()
			);
		}

		$this->confirmationManager->confirm( $user, $revision );
		return [
			'success' => true
		];
	}

	/**
	 * @return array[]
	 */
	public function getBodyParamSettings(): array {
		return [
			'revisionId' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

}
