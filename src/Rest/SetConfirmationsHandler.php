<?php

namespace MediaWiki\Extension\PageReadConfirmations\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Message\Message;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class SetConfirmationsHandler extends SimpleHandler {

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
		$body = $this->getValidatedBody();

		$title = $this->titleFactory->newFromText( $body['page'] );
		if ( !$title ) {
			throw new \InvalidArgumentException(
				Message::newFromKey( 'page-read-confirmations-invalid-page-id'
				)->text() );
		}
		$assignments = json_decode( $body['assignments'], true );
		$this->confirmationManager->storeAssignments( $title, $assignments, RequestContext::getMain()->getUser() );

		$requestRevision = $body['requestRevision'] ?? null;
		$revision = null;
		if ( $requestRevision ) {
			$revision = $this->revisionLookup->getRevisionById( $requestRevision );
			if ( !$revision ) {
				throw new \InvalidArgumentException(
					Message::newFromKey( 'page-read-confirmations-no-revision-found-for-page' )->text()
				);
			}
		}
		if ( !$requestRevision && $body['requestCurrentRevision'] ?? false ) {
			$latestRevision = $this->revisionLookup->getRevisionByTitle( $title );
			if ( !$latestRevision ) {
				throw new \InvalidArgumentException(
					Message::newFromKey( 'page-read-confirmations-no-revision-found-for-page' )->text()
				);
			}
			$revision = $latestRevision;
		}

		if ( $revision ) {
			$this->confirmationManager->requestRevisionConfirmation(
				$title, $revision, RequestContext::getMain()->getUser()
			);
		}

		return [
			'success' => true,
			'requestedRevision' => $revision?->getId(),
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
			'assignments' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'requestCurrentRevision' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false
			],
			'requestRevision' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false
			]
		];
	}

}
