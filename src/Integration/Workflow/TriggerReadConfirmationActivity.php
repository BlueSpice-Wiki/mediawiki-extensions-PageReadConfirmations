<?php

namespace MediaWiki\Extension\PageReadConfirmations\Integration\Workflow;

use MediaWiki\Extension\PageReadConfirmations\Util\AutomaticAssigner;
use MediaWiki\Extension\Workflows\Activity\ExecutionStatus;
use MediaWiki\Extension\Workflows\Activity\GenericActivity;
use MediaWiki\Extension\Workflows\Definition\ITask;
use MediaWiki\Extension\Workflows\Exception\WorkflowExecutionException;
use MediaWiki\Extension\Workflows\WorkflowContext;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;

/**
 * Workflow activity - trigger_read_confirmation
 * Assigns users and triggers read confirmation for latest or provided revision
 *
 * Params:
 * - pageId or pagename - Title to operate on
 * - revision (optional) - revision ID to trigger confirmation for, if not provided latest revision will be used
 * - audience_users (optional) - comma separated list of users to assign to confirm reading
 * - audience_groups (optional) - comma separated list of groups to assign to confirm reading
 *
 * If no audience is provided, a blank request will be inserted, which will either be valid for already
 * assigned users, or for future assignments
 */
class TriggerReadConfirmationActivity extends GenericActivity {

	/**
	 * @param TitleFactory $titleFactory
	 * @param AutomaticAssigner $automaticAssigner
	 * @param ITask $task
	 */
	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly AutomaticAssigner $automaticAssigner,
		ITask $task
	) {
		parent::__construct( $task );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $data, WorkflowContext $context ): ExecutionStatus {
		$title = $this->getAffectedTitle( $data, $context );
		if ( !$title instanceof Title ) {
			throw new WorkflowExecutionException(
				Message::newFromKey( 'page-read-confirmations-no-valid-title' )->text(), $this->getTask()
			);
		}

		try {
			$this->automaticAssigner->assignFromData( $title, $data );
			return new ExecutionStatus( static::STATUS_COMPLETE, $data );
		} catch ( \Exception $exception ) {
			throw new WorkflowExecutionException( $exception->getMessage(), $this->getTask() );
		}
	}

	/**
	 * @param array $data
	 * @param WorkflowContext $context
	 * @return Title|null
	 */
	private function getAffectedTitle( $data, WorkflowContext $context ) {
		if ( !empty( $data['pageId'] ) ) {
			return $this->titleFactory->newFromID( $data['pageId'] );
		}
		if ( !empty( $data['pagename'] ) ) {
			return $this->titleFactory->newFromText( $data['pagename'] );
		}

		return $context->getContextPage();
	}

}
