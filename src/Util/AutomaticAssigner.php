<?php

namespace MediaWiki\Extension\PageReadConfirmations\Util;

use Exception;
use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Message\Message;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;

readonly class AutomaticAssigner {

	/**
	 * @param ReadConfirmationManager $confirmationManager
	 * @param RevisionLookup $revisionLookup
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		private ReadConfirmationManager $confirmationManager,
		private RevisionLookup $revisionLookup,
		private UserFactory $userFactory
	) {
	}

	/**
	 * @param Title $title
	 * @param array $data
	 * @return void
	 * @throws Exception
	 */
	public function assignFromData( Title $title, array $data ): void {
		$revId = !empty( $data['revision'] ) ? $data['revision'] : $title->getLatestRevID();
		$revision = $this->revisionLookup->getRevisionById( $revId );
		if ( !$revision ) {
			throw new Exception(
				Message::newFromKey( 'page-read-confirmations-no-revision-for-title' )->text()
			);
		}

		$audienceUsers = $this->getAudienceUsers( $data );
		$audienceGroups = $this->getAudienceGroups( $data );
		$assignments = [];
		foreach ( $audienceUsers as $user ) {
			$assignments[] = [
				'type' => 'user',
				'key' => $user->getName(),
			];
		}
		foreach ( $audienceGroups as $group ) {
			$assignments[] = [
				'type' => 'group',
				'key' => $group,
			];
		}
		if ( !empty( $assignments ) ) {
			$this->confirmationManager->storeAssignments(
				$title,
				$assignments,
				User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] )
			);
		}

		$this->confirmationManager->requestRevisionConfirmation(
			$title,
			$revision,
			User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] )
		);
	}

	/**
	 * @param array $data
	 * @return array
	 */
	private function getAudienceUsers( array $data ): array {
		$audience = explode( ',', $data['audience_users'] ?? '' );
		$audience = array_map( 'trim', $audience );

		$users = array_map(
			fn ( $username ) => $this->userFactory->newFromName( $username ),
			$audience
		);
		return array_filter( $users, fn ( $user ) => $user && $user->isRegistered() );
	}

	/**
	 * @param array $data
	 * @return array
	 */
	private function getAudienceGroups( array $data ): array {
		$audience = explode( ',', $data['audience_groups'] ?? '' );
		return array_filter( array_map( 'trim', $audience ) );
	}

}
