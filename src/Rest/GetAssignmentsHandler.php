<?php

namespace MediaWiki\Extension\PageReadConfirmations\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PageReadConfirmations\Store\ReadConfirmationAssignmentStore;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class GetAssignmentsHandler extends SimpleHandler {

	/**
	 * @param TitleFactory $titleFactory
	 * @param ReadConfirmationAssignmentStore $assignmentStore
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly ReadConfirmationAssignmentStore $assignmentStore,
		private readonly PermissionManager $permissionManager
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
	 * @throws HttpException
	 */
	public function execute() {
		$params = $this->getValidatedParams();
		$title = $this->titleFactory->newFromID( $params['page'] );
		if ( !$title ) {
			throw new \InvalidArgumentException(
				Message::newFromKey( 'page-read-confirmations-invalid-page-id' )->text()
			);
		}
		if ( !$this->permissionManager->userCan( 'read', RequestContext::getMain()->getUser(), $title ) ) {
			throw new HttpException( 'permissiondenied', 403 );
		}

		return $this->getResponseFactory()->createJson(
			$this->assignmentStore->getAssignmentsForPage( $title )
		);
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
