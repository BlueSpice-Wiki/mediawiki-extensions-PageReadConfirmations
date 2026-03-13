<?php

namespace MediaWiki\Extension\PageReadConfirmations\Tests;

use Exception;
use InvalidArgumentException;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Extension\PageReadConfirmations\Store\ReadConfirmationAssignmentStore;
use MediaWiki\Extension\PageReadConfirmations\Store\ReadConfirmationStore;
use MediaWiki\Extension\PageReadConfirmations\Util\ConfirmationLogger;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Language\Language;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Events\Notifier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager
 */
class ReadConfirmationManagerTest extends TestCase {

	/** @var ReadConfirmationStore|MockObject */
	private $confirmationStore;

	/** @var ReadConfirmationAssignmentStore|MockObject */
	private $assignmentStore;

	/** @var PermissionManager|MockObject */
	private $permissionManager;

	/** @var ReadConfirmationManager */
	private $manager;

	protected function setUp(): void {
		$this->confirmationStore = $this->createMock( ReadConfirmationStore::class );
		$this->assignmentStore = $this->createMock( ReadConfirmationAssignmentStore::class );
		$this->permissionManager = $this->createMock( PermissionManager::class );

		$this->manager = new ReadConfirmationManager(
			$this->confirmationStore,
			$this->assignmentStore,
			$this->permissionManager,
			$this->createMock( RevisionLookup::class ),
			$this->createMock( HookContainer::class ),
			$this->createMock( Language::class ),
			$this->createMock( LinkRenderer::class ),
			$this->createMock( ConfirmationLogger::class ),
			$this->createMock( Notifier::class )
		);
	}

	// confirm()

	/**
	 * @covers \MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager::confirm
	 */
	public function testConfirmThrowsForAnonymousUser(): void {
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'isRegistered' )->willReturn( false );

		$this->expectException( InvalidArgumentException::class );
		$this->manager->confirm( $user, $this->createMock( RevisionRecord::class ) );
	}

	/**
	 * @covers \MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager::confirm
	 */
	public function testConfirmThrowsWhenUserNotAssigned(): void {
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'isRegistered' )->willReturn( true );

		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPage' )->willReturn( $this->createMock( PageIdentity::class ) );

		$this->assignmentStore->method( 'isAssigned' )->willReturn( false );

		$this->expectException( InvalidArgumentException::class );
		$this->manager->confirm( $user, $revision );
	}

	/**
	 * @covers \MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager::confirm
	 */
	public function testConfirmThrowsWhenRevisionNotRequested(): void {
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'isRegistered' )->willReturn( true );

		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPage' )->willReturn( $this->createMock( PageIdentity::class ) );
		$revision->method( 'getId' )->willReturn( 1 );

		// isAssigned returns true but requested revision ID differs from the revision being confirmed
		$this->assignmentStore->method( 'isAssigned' )->willReturn( true );
		$this->assignmentStore->method( 'getRequestedRevisionId' )->willReturn( 2 );

		$this->expectException( InvalidArgumentException::class );
		$this->manager->confirm( $user, $revision );
	}

	// storeAssignments()

	/**
	 * @covers \MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager::storeAssignments
	 */
	public function testStoreAssignmentsThrowsOnMissingAssignmentKeys(): void {
		$actor = $this->createMock( User::class );
		$actor->method( 'isSystemUser' )->willReturn( true );
		$actor->method( 'getUser' )->willReturnSelf();

		$this->expectException( InvalidArgumentException::class );
		$this->manager->storeAssignments(
			$this->createMock( PageIdentity::class ),
			[ [ 'no_key' => 'val' ] ],
			$actor
		);
	}

	/**
	 * @covers \MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager::storeAssignments
	 */
	public function testStoreAssignmentsThrowsOnInvalidAssignmentType(): void {
		$actor = $this->createMock( User::class );
		$actor->method( 'isSystemUser' )->willReturn( true );
		$actor->method( 'getUser' )->willReturnSelf();

		$this->expectException( InvalidArgumentException::class );
		$this->manager->storeAssignments(
			$this->createMock( PageIdentity::class ),
			[ [ 'key' => 'someuser', 'type' => 'invalid_type' ] ],
			$actor
		);
	}

	/**
	 * @covers \MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager::storeAssignments
	 */
	public function testStoreAssignmentsThrowsWhenActorLacksPermission(): void {
		$actor = $this->createMock( User::class );
		$actor->method( 'isSystemUser' )->willReturn( false );
		$actor->method( 'getUser' )->willReturnSelf();
		$this->permissionManager->method( 'userCan' )->willReturn( false );

		$this->expectException( Exception::class );
		$this->manager->storeAssignments(
			$this->createMock( Title::class ),
			[],
			$actor
		);
	}

	// requestRevisionConfirmation()

	/**
	 * @covers \MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager::requestRevisionConfirmation
	 */
	public function testRequestRevisionConfirmationThrowsWhenRevisionBelongsToDifferentPage(): void {
		$page = $this->createMock( PageIdentity::class );
		$page->method( 'getId' )->willReturn( 1 );

		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPageId' )->willReturn( 2 );

		$this->expectException( InvalidArgumentException::class );
		$this->manager->requestRevisionConfirmation(
			$page,
			$revision,
			$this->createMock( Authority::class )
		);
	}

	/**
	 * @covers \MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager::requestRevisionConfirmation
	 */
	public function testRequestRevisionConfirmationThrowsWhenRevisionIsOlderThanCurrent(): void {
		$page = $this->createMock( PageIdentity::class );
		$page->method( 'getId' )->willReturn( 1 );

		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPageId' )->willReturn( 1 );
		$revision->method( 'getId' )->willReturn( 5 );

		$actor = $this->createMock( User::class );
		$actor->method( 'isSystemUser' )->willReturn( true );
		$actor->method( 'getUser' )->willReturnSelf();

		$this->assignmentStore->method( 'getRequestedRevisionId' )->willReturn( 10 );

		$this->expectException( InvalidArgumentException::class );
		$this->manager->requestRevisionConfirmation( $page, $revision, $actor );
	}

	// deleteRequest()

	/**
	 * @covers \MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager::deleteRequest
	 */
	public function testDeleteRequestThrowsWhenActorLacksPermission(): void {
		$actor = $this->createMock( User::class );
		$actor->method( 'isSystemUser' )->willReturn( false );
		$actor->method( 'getUser' )->willReturnSelf();
		$this->permissionManager->method( 'userCan' )->willReturn( false );

		$this->expectException( Exception::class );
		$this->manager->deleteRequest(
			$this->createMock( Title::class ),
			$actor
		);
	}

	// removeConfirmation()

	/**
	 * @covers \MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager::removeConfirmation
	 */
	public function testRemoveConfirmationThrowsWhenActorLacksPermission(): void {
		$actor = $this->createMock( User::class );
		$actor->method( 'isSystemUser' )->willReturn( false );
		$actor->method( 'getUser' )->willReturnSelf();
		$this->permissionManager->method( 'userCan' )->willReturn( false );

		$this->expectException( Exception::class );
		$this->manager->removeConfirmation(
			$this->createMock( UserIdentity::class ),
			$this->createMock( Title::class ),
			$actor
		);
	}
}
