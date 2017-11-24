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
use DOMElement;
use RequestContext;
use Title;
use User;

class AuthorInfo {
	private $title;
	private $user;
	private $editCount;
	private $firstEdit;

	/**
	 * Constructor
	 * @param User $user who is looking
	 * @param Title $title to check
	 */
	public function __construct( User $user, Title $title ) {
		$this->title = $title;
		$this->user = $user;
		$this->load();
	}

	/**
	 * Get the number of edits this editor made
	 * @return int
	 */
	public function getEditCount() {
		return $this->editCount;
	}

	/**
	 * Get the timestamp of their first edit
	 * @return int
	 */
	public function getFirstEdit() {
		return $this->firstEdit;
	}

	private function load() {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			"revision",
			[ 'COUNT(rev_user) AS editCount', 'MIN(rev_timestamp) AS firstEdit' ],
			[
				'rev_user' => $this->user->getId(),
				'rev_page' => $this->title->getArticleId()
			], __METHOD__
		);
		$row = $dbr->fetchObject( $res );

		$this->editCount = $row->editCount;
		$this->firstEdit = $row->firstEdit;
	}

	/**
	 * See if this looks like an email
	 * @param string $name to check
	 * @return bool
	 */
	protected function isEmail( $name ) {
		// See if this is an email.  Maybe replace with something like
		// https://packagist.org/packages/egulias/email-validator

		if ( preg_match( "/^[-_a-z0-9\'+*$^&%=~!?{}]++(?:\.[-_a-z0-9\'+*$^&%=~!?{}]+)*+@(?:(?![-.])[-a-z0-9.]+(?<![-.])\.[a-z]{2,6}|\d{1,3}(?:\.\d{1,3}){3})(?::\d++)?$/iD", $name ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Get a name to display for this author.
	 * @return string
	 */
	public function getDisplayName() {
		$name = $this->user->getRealName();

		// Filter out email addresses and empties
		if ( !$name || $this->isEmail( $name ) ) {
			$name = $this->user->getName();
		}
		return $name;
	}

	private function getAuthorLink() {
		$title = Title::newFromText( 'User:' . $this->user->getTitleKey() );
		$href = $title->getFullUrl();
		return $href;
	}

	/**
	 * Creates the HTML code to display a single
	 * author
	 *
	 * @return string
	 */
	public function renderAuthor() {
		$name = $this->getDisplayName();
		$href = $this->getAuthorLink();
		$link = "<A href=\"$href\" title=\"Number of edits: {$this->editCount}\">" .
			htmlspecialchars( $name ) . "</A>";
		return $link;
	}

	/**
	 * Add an XML node for this author to the
	 * given node.
	 *
	 * @param DOMDocument $doc to add to
	 * @param DOMElement $node location to add the author info to
	 */
	public function addXml( DOMDocument $doc, DOMElement $node ) {
		$e = $doc->createElement( "Author" );
		$e->setAttribute( "Name", $this->getDisplayName() );
		$e->setAttribute( "EditCount", $this->editCount );
		$e->setAttribute( "Url", $this->getAuthorLink() );
		$node->appendChild( $e );
	}

	/**
	 * Compare two authors by edits and then usernames
	 *
	 * @param AuthorInfo $a1 First author
	 * @param AuthorInfo $a2 Second author
	 * @return int
	 */
	public static function compareByEdits( AuthorInfo $a1, AuthorInfo $a2 ) {
		$c = $a2->getEditCount() - $a1->getEditCount();

		// If equal edits, compare by realname
		if ( $c == 0 ) {
			$c = strcasecmp( $a1->getDisplayName(), $a2->getDisplayName() );
		}
		return $c;
	}
}
