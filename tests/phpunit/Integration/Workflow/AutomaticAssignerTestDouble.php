<?php

namespace MediaWiki\Extension\PageReadConfirmations\Tests\Integration\Workflow;

use MediaWiki\Extension\PageReadConfirmations\Util\AutomaticAssigner;
use MediaWiki\Title\Title;

/**
 * Test double for AutomaticAssigner. AutomaticAssigner is declared readonly,
 * so it cannot be mocked by PHPUnit. This readonly subclass delegates
 * assignFromData() to a configurable static callback.
 */
readonly class AutomaticAssignerTestDouble extends AutomaticAssigner {
	public function assignFromData( Title $title, array $data ): void {
		if ( AutomaticAssignerCallbackStore::$callback !== null ) {
			( AutomaticAssignerCallbackStore::$callback )( $title, $data );
		}
	}
}
