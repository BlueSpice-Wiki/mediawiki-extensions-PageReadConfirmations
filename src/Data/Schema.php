<?php

namespace MediaWiki\Extension\PageReadConfirmations\Data;

use MWStake\MediaWiki\Component\DataStore\FieldType;

class Schema extends \MWStake\MediaWiki\Component\DataStore\Schema {
	public function __construct() {
		parent::__construct( [
			Record::USER_ID => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::INT
			],
			Record::USER_NAME => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::STRING
			],
			Record::READ_AT => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			],
			Record::READ_AT_FOR_USER => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::STRING
			],
			Record::READ_REVISION => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::INT
			],
			Record::READ_REVISION_LINK => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::STRING
			],
			Record::HAS_CONFIRMED => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::BOOLEAN
			],
		] );
	}
}
