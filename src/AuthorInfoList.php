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
 * @author Thomas Kelder <thomaskelder@gmail.com>
 * @author Alexander Pico <apico@gladstone.ucsf.edu>
 * @author Mark A. Hershberger <mah@nichework.com>
 */
namespace WikiPathways\GPML;

use AjaxResponse;
use DOMDocument;
use Title;
use User;

class AuthorInfoList {
	private $title;
	private $limit;
	private $showBots;

	private $authors;

	/**
	 * Constructor
	 *
	 * @param Title $title the title being checked
	 * @param int $limit how many to show
	 * @param bool $showBots or not
	 */
	public function __construct( Title $title, $limit = 0, $showBots = false ) {
		$this->title = $title;
		if ( $limit ) {
			$this->limit = $limit + 1;
		}
		$this->showBots = $showBots;
		$this->authors = [];
		$this->load();
	}

	private function load() {
		$dbr = wfGetDB( DB_SLAVE );
		$limit = '';
		if ( $this->limit ) {
			$limit = "LIMIT 0, {$this->limit}";
		}

		// Get users for page
		$page_id = $this->title->getArticleId();

		// $query = "SELECT DISTINCT(rev_user) FROM revision WHERE " .
		// "rev_page = {$page_id} $limit";

		$res = $dbr->select(
			"revision", "rev_user", [ "rev_page" => $page_id ], __METHOD__,
			[ "DISTINCT", "OFFSET" => 0, "LIMIT" => $this->limit ]
		);
		foreach ( $res as $row ) {
			$user = User::newFromId( $row->rev_user );
			if ( $user->isAnon() ) {
				// Skip anonymous users
				continue;
			}
			if ( !$user->isAllowed( 'bot' ) || $this->showBots ) {
				$this->authors[] = new AuthorInfo( $user, $this->title );
			}
		}

		// Sort the authors by editCount
		usort( $this->authors, [ 'WikiPathways\\GPML\\AuthorInfo', "compareByEdits" ] );

		// Place original author in first position
		$this->originalAuthorFirst();
	}

	/**
	 * Place original author in first position
	 * @return ordered author list
	 */
	public function originalAuthorFirst() {
		$orderArray = [];
		foreach ( $this->authors as $a ) {
			array_push( $orderArray, $a->getFirstEdit() );
		}
		$firstAuthor = $this->authors[array_search( min( $orderArray ), $orderArray )];

		$key = array_search( $firstAuthor, $this->authors );
		if ( $key !== false ) {
			unset( $this->authors[$key] );
		}
		array_unshift( $this->authors, $firstAuthor );
	}

	/**
	 * NOT USED. RENDERING DONE IN JS.
	 *
	 * Render the author list.
	 * @return A HTML snipped containing the author list
	 */
	public function renderAuthorList() {
		$html = '';
		foreach ( $this->authors as $a ) {
			$html .= $a->renderAuthor() . ", ";
		}
		return substr( $html, 0, -2 );
	}

	/**
	 * Get an XML document containing the author info
	 * @return DOMDocument
	 */
	public function getXml() {
		$doc = new DOMDocument();
		$root = $doc->createElement( "AuthorList" );
		$doc->appendChild( $root );

		foreach ( $this->authors as $a ) {
			$a->addXml( $doc, $root );
		}
		return $doc;
	}

	/**
	 * Called from javascript to get the author list.
	 * @param int $pageId The id of the page to get the authors for.
	 * @param int $limit Limit the number of authors to query. Leave
	 *                   empty to get all authors.
	 * @param bool $includeBots Whether to include users marked as bot.
	 * @return An xml document containing all authors for the given page
	 */
	public static function jsGetAuthors( $pageId, $limit = '', $includeBots = false ) {
		$title = Title::newFromId( $pageId );
		if ( $includeBots === 'false' ) {
			$includeBots = false;
		}
		$authorList = new AuthorInfoList( $title, $limit, $includeBots );
		$doc = $authorList->getXml();
		$resp = new AjaxResponse( $doc->saveXML() );
		$resp->setContentType( "text/xml" );
		return $resp;
	}

	/**
	 * Main entry point
	 *
	 * @param string $input input
	 * @param array $argv arguments passed to this parser function
	 * @param Parser $parser object
	 *
	 * @return string
	 */
	public static function render( $input, $argv, $parser ) {
		$parser->disableCache();

		if ( isset( $argv["limit"] ) ) {
			$limit = htmlentities( $argv["limit"] );
		} else {
			$limit = 0;
		}
		if ( isset( $argv["bots"] ) ) {
			$bots = htmlentities( $argv["bots"] );
		} else {
			$bots = false;
		}
		$parser->getOutput()->addModules( "wpi.AuthorInfo" );

		$id = $parser->getTitle()->getArticleId();
		return "<div id='authorInfoContainer'></div><script type='text/javascript'>"
			   . "AuthorInfo.init('authorInfoContainer', '$id', '$limit', '$bots');"
			   . "</script>";
	}

}
