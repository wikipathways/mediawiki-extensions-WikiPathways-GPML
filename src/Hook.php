<?php
/**
 * Copyright (C) 2016  J. David Gladstone Institutes
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author  Mark A. Hershberger
 * @file
 * @ingroup Extensions
 */

namespace WikiPathways\GPML;

use Content;
use Revision;
use Status;
use Title;
use User;
use WikiPage;
use WikiPathways\Pathway;

class Hook {
	/**
	 * Take care of initializing stuff for this extension
	 */
	static function onRegistration() {
		if ( !defined( 'CONTENT_MODEL_GPML' ) ) {
			define( 'CONTENT_MODEL_GPML', 'gpml' );
		}
		if ( !defined( 'CONTENT_FORMAT_GPML' ) ) {
			define( 'CONTENT_FORMAT_GPML', 'gpml' );
		}

		global $wgAjaxExportList;
		$wgAjaxExportList[] = "WikiPathways\\GPML\\AuthorInfoList::jsGetAuthors";
	}

	/**
	 * Set up parsing functions and the like
	 *
	 * @param Parser &$parser object
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		$parser->setHook(
			"AuthorInfo", "WikiPathways\\GPML\\AuthorInfoList::render"
		);
	}

	/**
	 * Hook to tell CodeEditor what this page should be edited with.
	 *
	 * @param Title $title of page to examine
	 * @param string &$lang to use for page
	 * @param string $model ContentModel of page
	 * @param string $format ContentFormat for page
	 *
	 * @return bool false when GPML is given.
	 */
	static function onCodeEditorGetPageLanguage(
		Title $title, &$lang, $model = "", $format = ""
	) {
		if ( $model === CONTENT_MODEL_GPML ) {
			$lang = "xml";
			return false;
		}
	}

	/**
	 * Provide a content handler for this content model
	 *
	 * @param string $modelName the content model we have
	 * @param ContentHandler &$handler the handler we provide if we
	 *                                  know what to do with the
	 *                                  model given.
	 *
	 * @return bool false when GPML is given
	 */
	static function onContentHandlerForModelID( $modelName, &$handler ) {
		if ( $modelName === CONTENT_MODEL_GPML
			// FIXME Temporary backcompat
			|| $modelName === "pathway"
		) {
			$handler = new ContentHandler;
			return false;
		}
	}

	/**
	 * Check GPML on the PageContentSave hook
	 *
	 * @param WikiPage $wikiPage being saved
	 * @param User $user saving the article
	 * @param Content $content new article content
	 * @param string $summary the article summary (comment)
	 * @param bool $isMinor minor flag
	 * @param null $isWatch watch flag (not used)
	 * @param null $section section number (not used)
	 * @param int &$flags see WikiPage::doEditContent documentation
	 * @param Status $status Status
	 * @return string|null
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageContentSave
	 */
	public static function checkGpml(
		WikiPage $wikiPage, User $user, Content $content, $summary,
		$isMinor, $isWatch, $section, &$flags, Status $status
	) {
		// Flag that can be set to disable validation
		global $wpiDisableValidation;

		if ( !$wpiDisableValidation
			&& $wikiPage->getTitle()->getNamespace() == NS_PATHWAY
		) {
			$text = $content->getNativeData();
			$error = Pathway::validateGpml( $text );
			if ( $error ) {
				return "<h1>Invalid GPML</h1><p><code>$error</code>";
			}
		}
	}

	/**
	 * @param WikiPage $wikiPage modified
	 * @param User $user performing the modification
	 * @param Content $content New content
	 * @param string $summary Edit summary/comment
	 * @param bool $isMinor edit was marked as minor
	 * @param null $isWatch (No longer used)
	 * @param null $section (No longer used)
	 * @param int &$flags passed to WikiPage::doEditContent()
	 * @param Revision $revision of the saved content. If the save did
	 *                             not result in the creation of a new
	 *                             revision (e.g. the submission was
	 *                             equal to the latest revision), this
	 *                             parameter may be null (null edits, or
	 *                             "no-op"). However, there are reports
	 *                             (see phab:T128838) that it's instead
	 *                             set to that latest revision.
	 * @param Status $status Status object about to
	 *                             be returned by
	 *                             doEditContent()
	 * @param int $baseRevId the rev ID (or false) this edit was based on
	 * @param int $undidRevId the rev ID (or 0) this edit undid
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageContentSaveComplete
	 */
	public static function wfUpdateAfterSave(
		WikiPage $wikiPage, User $user, Content $content, $summary, $isMinor,
		$isWatch, $section, &$flags, Revision $revision, Status $status,
		$baseRevId, $undidRevId
	) {
		if ( $wikiPage->getTitle()->getNamespace() == NS_PATHWAY ) {
			$pw = Pathway::newFromTitle( $wikiPage->getTitle() );
		}
	}
}
