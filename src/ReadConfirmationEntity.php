<?php

namespace MediaWiki\Extension\PageReadConfirmations;

use DateTime;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;

final class ReadConfirmationEntity {

	/**
	 * @param UserIdentity $assignee
	 * @param RevisionRecord $revision
	 * @param string $wikiId
	 * @param DateTime|null $readAt
	 */
	public function __construct(
		public readonly UserIdentity $assignee,
		public readonly RevisionRecord $revision,
		public readonly string $wikiId,
		public readonly ?DateTime $readAt
	) {
	}
}
