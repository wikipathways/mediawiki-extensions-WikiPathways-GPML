<?php
/**
 * Copyright (C) 2016  Mark A. Hershberger
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
 * @file
 * @ingroup Extensions
 * @author Mark A. Hershberger
 */

namespace WikiPathways\GPML;

use Title;

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

		// FIXME this shim is in the global context, see
		// WikiPathways.php
		// FIX would be to write an API call (or just
		// fit the information this produces into GPML).
		global $wgAjaxExportList;
		$wgAjaxExportList[] = "jsGetAuthors";
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
	 *     know what to do with the model given.
	 *
	 * @return bool false when GPML is given
	 */
	static function onContentHandlerForModelID( $modelName, &$handler ) {
		if ( $modelName === CONTENT_MODEL_GPML
			 # FIXME Temporary backcompat
			 || $modelName === "pathway" ) {
			$handler = new ContentHandler;
			return false;
		}
	}
}
