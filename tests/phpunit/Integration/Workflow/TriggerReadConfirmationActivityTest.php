<?php

namespace MediaWiki\Extension\PageReadConfirmations\Tests\Integration\Workflow;

use MediaWiki\Extension\PageReadConfirmations\Integration\Workflow\TriggerReadConfirmationActivity;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Extension\PageReadConfirmations\Util\AutomaticAssigner;
use MediaWiki\Extension\Workflows\Definition\ITask;
use MediaWiki\Extension\Workflows\Exception\WorkflowExecutionException;
use MediaWiki\Extension\Workflows\WorkflowContext;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserFactory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageReadConfirmations\Integration\Workflow\TriggerReadConfirmationActivity
 */
class TriggerReadConfirmationActivityTest extends TestCase {

	/** @var TitleFactory */
	private $titleFactory;

	/** @var AutomaticAssigner */
	private $automaticAssigner;

	/** @var ITask */
	private $task;

	protected function setUp(): void {
		$this->titleFactory = $this->createMock( TitleFactory::class );
		$this->automaticAssigner = new AutomaticAssignerTestDouble(
			$this->createMock( ReadConfirmationManager::class ),
			$this->createMock( RevisionLookup::class ),
			$this->createMock( UserFactory::class )
		);
		$this->task = $this->createMock( ITask::class );
		AutomaticAssignerCallbackStore::$callback = null;
	}

	protected function tearDown(): void {
		AutomaticAssignerCallbackStore::$callback = null;
	}

	private function makeActivity(): TriggerReadConfirmationActivity {
		return new TriggerReadConfirmationActivity(
			$this->titleFactory,
			$this->automaticAssigner,
			$this->task
		);
	}

	/**
	 * @covers \MediaWiki\Extension\PageReadConfirmations\Integration\Workflow\TriggerReadConfirmationActivity::execute
	 */
	public function testThrowsWhenNoValidTitleCanBeConstructed(): void {
		$this->titleFactory->method( 'newFromText' )->willReturn( null );

		$context = $this->createMock( WorkflowContext::class );
		$context->method( 'getContextPage' )->willReturn( null );

		$this->expectException( WorkflowExecutionException::class );
		$this->makeActivity()->execute( [ 'pagename' => 'SomePage' ], $context );
	}

	/**
	 * @covers \MediaWiki\Extension\PageReadConfirmations\Integration\Workflow\TriggerReadConfirmationActivity::execute
	 */
	public function testThrowsWhenNoRevisionFoundForTitle(): void {
		$title = $this->createMock( Title::class );
		$this->titleFactory->method( 'newFromText' )->willReturn( $title );
		AutomaticAssignerCallbackStore::$callback = static function () {
			throw new \Exception( 'No revision found for title' );
		};

		$context = $this->createMock( WorkflowContext::class );

		$this->expectException( WorkflowExecutionException::class );
		$this->makeActivity()->execute( [ 'pagename' => 'SomePage' ], $context );
	}

	/**
	 * @covers \MediaWiki\Extension\PageReadConfirmations\Integration\Workflow\TriggerReadConfirmationActivity::execute
	 */
	public function testWrapsManagerExceptionInWorkflowExecutionException(): void {
		$title = $this->createMock( Title::class );
		$this->titleFactory->method( 'newFromText' )->willReturn( $title );
		AutomaticAssignerCallbackStore::$callback = static function () {
			throw new \Exception( 'Some internal error' );
		};

		$context = $this->createMock( WorkflowContext::class );

		$this->expectException( WorkflowExecutionException::class );
		$this->makeActivity()->execute( [ 'pagename' => 'SomePage' ], $context );
	}

	/**
	 * @covers \MediaWiki\Extension\PageReadConfirmations\Integration\Workflow\TriggerReadConfirmationActivity::execute
	 */
	public function testSuccessfulExecution(): void {
		$title = $this->createMock( Title::class );
		$this->titleFactory->method( 'newFromText' )->willReturn( $title );
		$called = false;
		AutomaticAssignerCallbackStore::$callback = static function () use ( &$called ) {
			$called = true;
		};

		$context = $this->createMock( WorkflowContext::class );
		$data = [ 'pagename' => 'SomePage' ];

		$result = $this->makeActivity()->execute( $data, $context );

		$this->assertTrue( $called );
		$this->assertSame( TriggerReadConfirmationActivity::STATUS_COMPLETE, $result->getStatus() );
	}
}
