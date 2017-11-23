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
 * @author
 * @author Mark A. Hershberger <mah@nichework.com>
 */
namespace WikiPathways\GPML;

use DOMDocument;
use User;

class AuthorInfoList {
	private $title;
	private $limit;
	private $showBots;

	private $authors;

	public function __construct( $title, $limit = '', $showBots = false ) {
		$this->title = $title;
		if ( $limit ) { $this->limit = $limit + 1;
		}
		$this->showBots = $showBots;
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
		$query = "SELECT DISTINCT(rev_user) FROM revision WHERE " .
			"rev_page = {$page_id} $limit";

		$res = $dbr->query( $query );
		$this->authors = [];
		while ( $row = $dbr->fetchObject( $res ) ) {
			$user = User::newFromId( $row->rev_user );
			if ( $user->isAnon() ) { continue; // Skip anonymous users
			}
			if ( !$user->isAllowed( 'bot' ) || $this->showBots ) {
				$this->authors[] = new AuthorInfo( $user, $this->title );
			}
		}

		// Sort the authors by editCount
		usort( $this->authors, "WikiPathways\\GPML\\AuthorInfo::compareByEdits" );
		$dbr->freeResult( $res );

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

						if ( ( $key = array_search( $firstAuthor, $this->authors ) ) !== false ) {
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
}
