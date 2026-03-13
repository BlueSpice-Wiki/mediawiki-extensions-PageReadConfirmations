<?php

namespace MediaWiki\Extension\PageReadConfirmations\Integration\Event;

use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\TitleEvent;

class PageReadConfirmationReminderEvent extends TitleEvent {

	public function __construct(
		UserIdentity $agent,
		PageIdentity $title,
		private readonly ?int $revisionToRead = null,
		private readonly array $targetUsers = []
	) {
		parent::__construct( $agent, $title );
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'page-read-confirmation-reminder';
	}

	/**
	 * @return string
	 */
	protected function getMessageKey(): string {
		// page-read-confirmation-reminder-event-message-bot will be triggered from here as well
		return 'page-read-confirmation-reminder-event-message';
	}

	/**
	 * @return Message
	 */
	public function getKeyMessage(): Message {
		return Message::newFromKey( 'page-read-confirmation-reminder-event' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getTitleAnchor( Title $title, IChannel $forChannel, ?string $label = null ): string {
		if ( !$this->revisionToRead ) {
			return parent::getTitleAnchor( $title, $forChannel, $label );
		}

		$url = $title->getFullURL( [ 'oldid' => $this->revisionToRead ] );
		$label = $this->getTitleDisplayText( $title );
		return "[$url $label]";
	}

	/**
	 * @return array
	 */
	public function getTargetUsers(): array {
		return $this->targetUsers;
	}

	/**
	 * @inheritDoc
	 */
	public static function getArgsForTesting(
		UserIdentity $agent, MediaWikiServices $services, array $extra = []
	): array {
		$params = parent::getArgsForTesting( $agent, $services, $extra );
		$params[] = 123;
		$params[] = [ $extra['targetUser'] ];

		return $params;
	}
}
