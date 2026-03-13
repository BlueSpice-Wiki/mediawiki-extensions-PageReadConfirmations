<?php

namespace MediaWiki\Extension\PageReadConfirmations\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\PageReadConfirmations\Data\Store;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Language\Language;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\CommonWebAPIs\Rest\QueryStore;
use MWStake\MediaWiki\Component\DataStore\IStore;
use Wikimedia\ParamValidator\ParamValidator;

class ConfirmationStore extends QueryStore {

	/**
	 * @param HookContainer $hookContainer
	 * @param TitleFactory $titleFactory
	 * @param ReadConfirmationManager $confirmationManager
	 * @param PermissionManager $permissionManager
	 * @param Language $language
	 * @param LinkRenderer $linkRenderer
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct(
		HookContainer $hookContainer,
		private readonly TitleFactory $titleFactory,
		private readonly ReadConfirmationManager $confirmationManager,
		private readonly PermissionManager $permissionManager,
		private readonly Language $language,
		private readonly LinkRenderer $linkRenderer,
		private readonly RevisionLookup $revisionLookup
	) {
		parent::__construct( $hookContainer );
	}

	public function needsReadAccess() {
		return true;
	}

	/**
	 * @return Store
	 * @throws HttpException
	 */
	protected function getStore(): IStore {
		$user = RequestContext::getMain()->getUser();
		$title = $this->titleFactory->newFromID( $this->getValidatedParams()['page'] );
		if ( !$title || !$title->exists() ) {
			throw new HttpException( 'Page not found', 404 );
		}
		if ( !$this->permissionManager->userCan( 'read', $user, $title ) ) {
			throw new HttpException( 'permissiondenied', 403 );
		}
		return new Store(
			$title, $user, $this->confirmationManager, $this->language,
			$this->linkRenderer, $this->revisionLookup
		);
	}

	/**
	 * @return array[]
	 */
	protected function getStoreSpecificParams(): array {
		return [
			'page' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}
}
