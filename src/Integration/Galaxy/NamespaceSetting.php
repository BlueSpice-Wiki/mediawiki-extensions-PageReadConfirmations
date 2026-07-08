<?php

namespace MediaWiki\Extension\PageReadConfirmations\Integration\Galaxy;

use BlueSpice\GalaxyDistributionConnector\NamespaceSettings\INamespaceSetting;
use MediaWiki\Message\Message;

class NamespaceSetting implements INamespaceSetting {

	/**
	 * @return Message
	 */
	public function getLabel(): Message {
		return Message::newFromKey( 'page-read-confirmations-ns-setting-label' );
	}

	/**
	 * @return Message
	 */
	public function getDescription(): Message {
		return Message::newFromKey( 'page-read-confirmations-ns-setting-help' );
	}

	/**
	 * @param int $namespace
	 * @param mixed $value
	 * @return void
	 */
	public function apply( int $namespace, mixed $value ): void {
		$GLOBALS['wgPageReadConfirmationsEnabledNamespaces'] =
			$GLOBALS['wgPageReadConfirmationsEnabledNamespaces'] ?? [];
		if ( !$value && in_array( $namespace, $GLOBALS['wgPageReadConfirmationsEnabledNamespaces'] ) ) {
			$GLOBALS['wgPageReadConfirmationsEnabledNamespaces'] = array_diff(
				$GLOBALS['wgPageReadConfirmationsEnabledNamespaces'],
				[ $namespace ]
			);
		} elseif ( $value && !in_array( $namespace, $GLOBALS['wgPageReadConfirmationsEnabledNamespaces'] ) ) {
			$GLOBALS['wgPageReadConfirmationsEnabledNamespaces'][] = $namespace;
		}
	}
}
