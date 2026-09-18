<?php

namespace MediaWiki\Extension\PageReadConfirmations\Integration\WikiAutomations;

use Exception;
use MediaWiki\Extension\PageReadConfirmations\Util\AutomaticAssigner;
use MediaWiki\Extension\WikiAutomations\Action\GenericAutomationAction;
use MediaWiki\Extension\WikiAutomations\IPageScopedAutomationAction;
use MediaWiki\Extension\WikiAutomations\Util\WikitextExpressionParser;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MWStake\MediaWiki\Component\FormEngine\IFormSpecification;
use MWStake\MediaWiki\Component\FormEngine\StandaloneFormSpecification;

class TriggerReadConfirmation extends GenericAutomationAction implements IPageScopedAutomationAction {

	/** @var PageIdentity|null */
	private ?PageIdentity $page = null;

	/**
	 * @param AutomaticAssigner $automaticAssigner
	 * @param WikitextExpressionParser $expressionParser
	 */
	public function __construct(
		private readonly AutomaticAssigner $automaticAssigner,
		private readonly WikitextExpressionParser $expressionParser
	) {
	}

	/**
	 * @return IFormSpecification
	 */
	public function getLayout(): IFormSpecification {
		$spec = new StandaloneFormSpecification();
		$spec->setItems( [
			[
				'type' => 'textarea',
				'name' => 'audience_users',
				'label' => Message::newFromKey(
					'page-read-confirmations-inspector-activity-trigger-audience-users'
				)->text(),
				'help' => Message::newFromKey(
					'page-read-confirmations-inspector-activity-trigger-audience-users-help'
				)->text(),
				'helpInline' => true,
				'labelAlign' => 'top',
				'widget_$overlay' => true,
			],
			[
				'type' => 'textarea',
				'name' => 'audience_groups',
				'label' => Message::newFromKey(
					'page-read-confirmations-inspector-activity-trigger-audience-groups'
				)->text(),
				'help' => Message::newFromKey(
					'page-read-confirmations-inspector-activity-trigger-audience-groups-help'
				)->text(),
				'helpInline' => true,
				'labelAlign' => 'top',
				'widget_$overlay' => true,
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
			if ( is_array( $audienceUsers ) ) {
				// B/C - so it doesnt break
				$audienceUsers = '';
			}
			$displayData[] = [
				'key' => Message::newFromKey(
					'page-read-confirmations-inspector-activity-trigger-audience-users'
				)->text(),
				'value' => trim( $audienceUsers ),
			];
		}
		if ( !empty( $audienceGroups ) ) {
			$displayData[] = [
				'key' => Message::newFromKey(
					'page-read-confirmations-inspector-activity-trigger-audience-groups'
				)->text(),
				'value' => trim( $audienceGroups ),
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
		$data = $this->getData();
		if ( is_array( $data['assigned_users'] ) ) {
			// B/C - so it doesnt break
			$data['assigned_users'] = '';
		}
		$audienceUsers = $data['audience_users'] ?? '';
		if ( $audienceUsers ) {
			$data['assigned_users'] = $this->expressionParser->processUsers(
				$audienceUsers,
				User::newSystemUser( 'MediaWiki default', [ 'steal' => true ] ),
				$this->page
			);
		}
		try {
			$this->automaticAssigner->assignFromData( $this->page, $data );
			return Status::newGood();
		} catch ( Exception $ex ) {
			return Status::newFatal( $ex->getMessage() );
		}
	}
}
