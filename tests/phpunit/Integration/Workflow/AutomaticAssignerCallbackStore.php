<?php

namespace MediaWiki\Extension\PageReadConfirmations\Tests\Integration\Workflow;

/**
 * Holds a mutable callback for AutomaticAssignerTestDouble.
 * Needed because readonly classes cannot have non-readonly instance properties.
 */
class AutomaticAssignerCallbackStore {
	public static ?\Closure $callback = null;
}
