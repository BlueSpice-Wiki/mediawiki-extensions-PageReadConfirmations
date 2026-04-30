<?php

namespace MediaWiki\Extension\PageReadConfirmations\Integration\WikiAutomations;

use Exception;
use MediaWiki\Extension\PageReadConfirmations\Util\AutomaticAssigner;
use MediaWiki\Extension\WikiAutomations\Action\GenericAutomationAction;
use MediaWiki\Extension\WikiAutomations\IPageScopedAutomationAction;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Status\Status;
use MWStake\MediaWiki\Component\FormEngine\IFormSpecification;
use MWStake\MediaWiki\Component\FormEngine\StandaloneFormSpecification;

class TriggerReadConfirmation extends GenericAutomationAction implements IPageScopedAutomationAction {

	/** @var PageIdentity|null */
	private ?PageIdentity $page = null;

	/**
	 * @param AutomaticAssigner $automaticAssigner
	 */
	public function __construct(
		private readonly AutomaticAssigner $automaticAssigner
	) {
	}

	/**
	 * @return IFormSpecification
	 */
	public function getLayout(): IFormSpecification {
		$spec = new StandaloneFormSpecification();
		$spec->setItems( [
			[
				'type' => 'user_group_multiselect',
				'name' => 'audience_users',
				'label' => Message::newFromKey(
					'page-read-confirmations-inspector-activity-trigger-audience-header'
				)->text(),
				'labelAlign' => 'top',
				'widget_$overlay' => true,
				'widget_placeholder' => Message::newFromKey(
					'page-read-confirmations-assign-placeholder'
				)->text(),
			],
		] );
		return $spec;
	}

	/**
	 * @return array
	 */
	public function getDisplayData(): array {
		$audienceUsers = $this->getData()['audience_users'] ?? '';
		$audienceGroups = $this->getData()['audience_groups'] ?? '';

		$displayData = [];
		if ( !empty( $audienceUsers ) ) {
			$displayData[] = [
				'key' => Message::newFromKey(
					'page-read-confirmations-inspector-activity-trigger-audience-users'
				)->text(),
				'value' => $audienceUsers,
			];
		}
		if ( !empty( $audienceGroups ) ) {
			$displayData[] = [
				'key' => Message::newFromKey(
					'page-read-confirmations-inspector-activity-trigger-audience-groups'
				)->text(),
				'value' => $audienceGroups,
			];
		}
		return $displayData;
	}

	/**
	 * @param PageIdentity $page
	 * @return Status
	 * @throws \Exception
	 */
	public function executeForPage( PageIdentity $page ): Status {
		$this->page = $page;
		return $this->execute();
	}

	/**
	 * @return Status
	 * @throws \Exception
	 */
	public function execute(): Status {
		if ( !$this->page ) {
			return Status::newFatal( 'missingpage' );
		}
		try {
			$this->automaticAssigner->assignFromData( $this->page, $this->getData() );
			return Status::newGood();
		} catch ( Exception $ex ) {
			return Status::newFatal( $ex->getMessage() );
		}
	}
}
