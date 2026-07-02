<?php

namespace MediaWiki\Extension\PageReadConfirmations\Integration\Hook;

use MediaWiki\Config\Config;
use MediaWiki\Extension\StandardDialogs\Hook\StandardDialogsRegisterPageInfoPanelModules;
use MediaWiki\ResourceLoader\Context as ResourceLoaderContext;

class SetupPageInfoPanelModule implements StandardDialogsRegisterPageInfoPanelModules {

	/**
	 * @inheritDoc
	 */
	public function onStandardDialogsRegisterPageInfoPanelModules(
		&$modules, ResourceLoaderContext $context, Config $config
	): void {
		$modules[] = "ext.pageReadConfirmations.standardDialogs.confirmationPage";
	}
}
