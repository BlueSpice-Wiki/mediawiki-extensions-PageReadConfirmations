<?php

namespace MediaWiki\Extension\PageReadConfirmations\Data;

class Record extends \MWStake\MediaWiki\Component\DataStore\Record {
	public const USER_ID = 'prc_user';
	public const USER_NAME = 'user_name';
	public const READ_AT = 'prc_read_at';
	public const READ_AT_FOR_USER = 'read_at_for_user';
	public const READ_REVISION = 'prc_rev';
	public const READ_REVISION_LINK = 'revision_link';
	public const HAS_CONFIRMED = 'has_confirmed';
}
