<?php

namespace MediaWiki\Extension\PageReadConfirmations\Hook;

use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Title\Title;

class AddContentAction implements SkinTemplateNavigation__UniversalHook, BeforePageDisplayHook {

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->pageSupported( $out->getTitle() ) ) {
			return;
		}
		$out->addModules( [ 'ext.pageReadConfirmations.bootstrap' ] );
	}

	/**
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( !$this->pageSupported( $sktemplate->getTitle() ) ) {
			return;
		}
		$links['actions']['readConfirmationAssign'] = [
			"class" => '',
			"text" => $sktemplate->msg( 'page-read-confirmations-label' )->text(),
			"href" => "#",
			'position' => 30,
		];
	}

	/**
	 * @param Title $page
	 * @return bool
	 */
	private function pageSupported( Title $page ): bool {
		// Check for content page here?
		return $page->exists();
	}
}
