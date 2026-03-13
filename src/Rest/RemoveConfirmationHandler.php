<?php

namespace MediaWiki\Extension\PageReadConfirmations\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Message\Message;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserFactory;
use Wikimedia\ParamValidator\ParamValidator;

class RemoveConfirmationHandler extends SimpleHandler {

	/**
	 * @param ReadConfirmationManager $confirmationManager
	 * @param TitleFactory $titleFactory
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		private readonly ReadConfirmationManager $confirmationManager,
		private readonly TitleFactory $titleFactory,
		private readonly UserFactory $userFactory
	) {
	}

	/**
	 * @return true
	 */
	public function needsReadAccess() {
		return true;
	}

	/**
	 * @return true
	 */
	public function needsWriteAccess() {
		return true;
	}

	/**
	 * @return \MediaWiki\Rest\Response|mixed
	 * @throws \Exception
	 */
	public function execute() {
		$actor = RequestContext::getMain()->getUser();
		$title = $this->titleFactory->newFromText( $this->getValidatedBody()['page'] );
		if ( !$title || !$title->exists() ) {
			throw new \InvalidArgumentException(
				Message::newFromKey( 'page-read-confirmations-invalid-page' )->text()
			);
		}
		$user = $this->userFactory->newFromName( $this->getValidatedBody()['user'] );
		if ( !$user || !$user->isRegistered() ) {
			throw new \InvalidArgumentException(
				Message::newFromKey( 'page-read-confirmations-invalid-user' )->text()
			);
		}

		$this->confirmationManager->removeConfirmation( $user, $title, $actor );
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
			],
			'user' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

}
