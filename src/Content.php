<?php
/**
 * Copyright (C) 2017  J. David Gladstone Institutes
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

use TextContent;
use ParserOptions;
use Title;
use User;

/**
 * Content for Pathway pages
 */
class Content extends TextContent {

	/**
	 * @var bool|Title|null
	 */
	private $redirectTarget = false;

	/**
	 * @param string $text GPML code.
	 * @param string $modelId the content model name
	 */
	public function __construct( $text, $modelId = CONTENT_MODEL_GPML ) {
		parent::__construct( $text, $modelId );
	}

	/**
	 * Returns a Content object with pre-save transformations applied using
	 * Parser::preSaveTransform().
	 *
	 * @param Title $title of the page
	 * @param User $user who is editing
	 * @param ParserOptions $popts for parsing
	 *
	 * @return WikiPathways\ContentHandler\Content
	 */
	public function preSaveTransform( Title $title, User $user, ParserOptions $popts ) {
		global $wgParser;
		// @todo Make pre-save transformation optional for script pages
		// See bug #32858

		$text = $this->getNativeData();
		$pst = $wgParser->preSaveTransform( $text, $title, $user, $popts );

		return new static( $pst );
	}

	/**
	 * @return string GPML wrapped in a <pre> tag.
	 */
	protected function getHtml() {
		$html = "";
		$html .= "<pre class=\"mw-code mw-gpml\" dir=\"ltr\">\n";
		$html .= htmlspecialchars( $this->getNativeData() );
		$html .= "\n</pre>\n";

		return $html;
	}

	/**
	 * If this page is a redirect, return the content
	 * if it should redirect to $target instead
	 *
	 * @param Title $target to update
	 * @return WikiPathways\ContentHandler\Content
	 */
	public function updateRedirect( Title $target ) {
		if ( !$this->isRedirect() ) {
			return $this;
		}

		return $this->getContentHandler()->makeRedirectContent( $target );
	}

	/**
	 * @return Title|null
	 */
	public function getRedirectTarget() {
		if ( $this->redirectTarget !== false ) {
			return $this->redirectTarget;
		}
		$this->redirectTarget = null;
		$text = $this->getNativeData();
		if ( strpos( $text, '/* #REDIRECT */' ) === 0 ) {
			// Extract the title from the url
			preg_match( '/title=(.*?)\\\\u0026action=raw/', $text, $matches );
			if ( isset( $matches[1] ) ) {
				$title = \Title::newFromText( $matches[1] );
				if ( $title ) {
					// Have a title, check that the current content equals what
					// the redirect content should be
					if ( $this->equals( $this->getContentHandler()->makeRedirectContent( $title ) ) ) {
						$this->redirectTarget = $title;
					}
				}
			}
		}

		return $this->redirectTarget;
	}

}
