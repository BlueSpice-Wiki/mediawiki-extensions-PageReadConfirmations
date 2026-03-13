<?php

use MediaWiki\Extension\PageReadConfirmations\ReadConfirmationManager;
use MediaWiki\Extension\PageReadConfirmations\Store\ReadConfirmationAssignmentStore;
use MediaWiki\Extension\PageReadConfirmations\Store\ReadConfirmationStore;
use MediaWiki\Extension\PageReadConfirmations\Util\AutomaticAssigner;
use MediaWiki\Extension\PageReadConfirmations\Util\ConfirmationLogger;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	'PageReadConfirmations.ConfirmationStore' => static function ( MediaWikiServices $services ) {
		return new ReadConfirmationStore(
			$services->getDBLoadBalancer()
		);
	},
	'PageReadConfirmations.AssignmentStore' => static function ( MediaWikiServices $services ) {
		return new ReadConfirmationAssignmentStore(
			$services->getDBLoadBalancer(),
			$services->getUserGroupManager(),
			$services->getUserFactory()
		);
	},
	'PageReadConfirmations.Manager' => static function ( MediaWikiServices $services ) {
		return new ReadConfirmationManager(
			$services->getService( 'PageReadConfirmations.ConfirmationStore' ),
			$services->getService( 'PageReadConfirmations.AssignmentStore' ),
			$services->getPermissionManager(),
			$services->getRevisionLookup(),
			$services->getHookContainer(),
			$services->getContentLanguage(),
			$services->getLinkRenderer(),
			new ConfirmationLogger( LoggerFactory::getInstance( 'PageReadConfirmations' ) ),
			$services->getService( 'MWStake.Notifier' )
		);
	},
	'PageReadConfirmations._AutomaticAssigner' => static function ( MediaWikiServices $services ) {
		return new AutomaticAssigner(
			$services->getService( 'PageReadConfirmations.Manager' ),
			$services->getRevisionLookup(),
			$services->getUserFactory()
		);
	},
];
