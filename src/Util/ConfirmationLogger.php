<?php

namespace MediaWiki\Extension\PageReadConfirmations\Util;

use ManualLogEntry;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationEntity;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;

class ConfirmationLogger {

	/**
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		private readonly LoggerInterface $logger
	) {
	}

	/**
	 * @param UserIdentity $actor
	 * @param RevisionRecord $revision
	 * @return void
	 */
	public function logConfirm( UserIdentity $actor, RevisionRecord $revision ): void {
		$this->addEntry(
			'confirm',
			$revision->getPage(),
			$actor,
			[
				'4::revision' => $revision->getId()
			]
		);
		$this->logger->info(
			'User {user} confirmed revision {revisionId} of page {pageTitle}',
			[
				'user' => $actor->getName(),
				'revisionId' => $revision->getId(),
				'pageTitle' => $revision->getPage()->getDBkey()
			]
		);
	}

	/**
	 * @param UserIdentity $actor
	 * @param ReadConfirmationEntity $confirmation
	 * @return void
	 */
	public function logRemoveConfirmation( UserIdentity $actor, ReadConfirmationEntity $confirmation ): void {
		$this->addEntry(
			'remove-confirmation',
			$confirmation->revision->getPage(),
			$actor,
			[
				'4::revision' => $confirmation->revision->getId(),
				'5::user' => $confirmation->assignee->getName()
			]
		);
		$this->logger->info(
			'User {user} removed confirmation for revision {revisionId} of page {pageTitle} assigned to {assignee}',
			[
				'user' => $actor->getName(),
				'revisionId' => $confirmation->revision->getId(),
				'pageTitle' => $confirmation->revision->getPage()->getDBkey(),
				'assignee' => $confirmation->assignee->getName()
			]
		);
	}

	/**
	 * @param UserIdentity $actor
	 * @param PageIdentity $page
	 * @param array $assignment
	 * @return void
	 */
	public function logAssign( UserIdentity $actor, PageIdentity $page, array $assignment ): void {
		$this->addEntry(
			'assign',
			$page,
			$actor,
			[
				'4::key' => $assignment['key'],
				'5::type' => $assignment['type']
			]
		);
		$this->logger->info(
			'User {user} assigned a read confirmation for page {pageTitle} with key {key} and type {type}',
			[
				'user' => $actor->getName(),
				'pageTitle' => $page->getDBkey(),
				'key' => $assignment['key'],
				'type' => $assignment['type']
			]
		);
	}

	/**
	 * @param UserIdentity $actor
	 * @param PageIdentity $page
	 * @param array $assignment
	 * @return void
	 */
	public function logUnassign( UserIdentity $actor, PageIdentity $page, array $assignment ): void {
		$this->addEntry(
			'unassign',
			$page,
			$actor,
			[
				'4::key' => $assignment['key'],
				'5::type' => $assignment['type']
			]
		);
		$this->logger->info(
			'User {user} unassigned a read confirmation for page {pageTitle} with key {key} and type {type}',
			[
				'user' => $actor->getName(),
				'pageTitle' => $page->getDBkey(),
				'key' => $assignment['key'],
				'type' => $assignment['type']
			]
		);
	}

	/**
	 * @param UserIdentity $actor
	 * @param RevisionRecord $revision
	 * @return void
	 */
	public function logRequest( UserIdentity $actor, RevisionRecord $revision ): void {
		$this->addEntry(
			'request',
			$revision->getPage(),
			$actor,
			[
				'4::revision' => $revision->getId()
			]
		);
		$this->logger->info(
			'User {user} requested a read confirmation for revision {revisionId} of page {pageTitle}',
			[
				'user' => $actor->getName(),
				'revisionId' => $revision->getId(),
				'pageTitle' => $revision->getPage()->getDBkey()
			]
		);
	}

	/**
	 * @param UserIdentity $actor
	 * @param PageIdentity $page
	 * @return void
	 */
	public function logRemoveRequest( UserIdentity $actor, PageIdentity $page ): void {
		$this->addEntry(
			'remove-request',
			$page,
			$actor
		);
		$this->logger->info(
			'User {user} removed a read confirmation request for page {pageTitle}',
			[
				'user' => $actor->getName(),
				'pageTitle' => $page->getDBkey()
			]
		);
	}

	/**
	 * @param string $action
	 * @param PageIdentity $page
	 * @param User $actor
	 * @param array $params
	 * @return void
	 */
	private function addEntry( string $action, PageIdentity $page, UserIdentity $actor, array $params = [] ) {
		$logEntry = new ManualLogEntry( 'ext-page-read-confirmations', $action );
		$logEntry->setPerformer( $actor );
		$logEntry->setTarget( $page );

		$logEntry->setParameters( $params );

		$logId = $logEntry->insert();

		$logEntry->publish( $logId );
	}
}
