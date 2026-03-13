<?php

namespace MediaWiki\Extension\PageReadConfirmations\Data;

use MediaWiki\Language\Language;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\DataStore\ISecondaryDataProvider;

class SecondaryDataProvider implements ISecondaryDataProvider {

	/**
	 *
	 * @param PageIdentity $forPage
	 * @param UserIdentity $forUser
	 * @param Language $language
	 * @param LinkRenderer $linkRenderer
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct(
		private readonly PageIdentity $forPage,
		private readonly UserIdentity $forUser,
		private readonly Language $language,
		private readonly LinkRenderer $linkRenderer,
		private readonly RevisionLookup $revisionLookup
	) {
	}

	/**
	 * @param array $dataSets
	 * @return array
	 */
	public function extend( $dataSets ) {
		foreach ( $dataSets as $dataSet ) {
			$readAt = $dataSet->get( Record::READ_AT );
			if ( $readAt ) {
				$dataSet->set( Record::READ_AT_FOR_USER, $this->language->userTimeAndDate( $readAt, $this->forUser ) );
			}

			$revisionId = $dataSet->get( Record::READ_REVISION );
			if ( $revisionId ) {
				$revision = $this->revisionLookup->getRevisionById( $revisionId );
				if ( $revision ) {
					$dataSet->set(
						Record::READ_REVISION_LINK,
						$this->linkRenderer->makeKnownLink(
							$this->forPage,
							$this->language->timeanddate( $revision->getTimestamp() ),
							[],
							[ 'oldid' => $revisionId ]
						)
					);
				}

			}
		}
		return $dataSets;
	}
}
