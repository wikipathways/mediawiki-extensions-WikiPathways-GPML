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
 */
namespace WikiPathways\GPML;

use AjaxResponse;
use RequestContext;
use Title;

class AuthorInfo {

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

	/**
	 * Called from javascript to get the author list.
	 * @param $pageId The id of the page to get the authors for.
	 * @param $limit Limit the number of authors to query. Leave empty to get all authors.
	 * @param $includeBots Whether to include users marked as bot.
	 * @return An xml document containing all authors for the given page
	 */
	public static function jsGetAuthors( $pageId, $limit = '', $includeBots = false ) {
		$title = Title::newFromId( $pageId );
		if ( $includeBots === 'false' ) { $includeBots = false;
		}
		$authorList = new AuthorInfoList( $title, $limit, $includeBots );
		$doc = $authorList->getXml();
		$resp = new AjaxResponse( $doc->saveXML() );
		$resp->setContentType( "text/xml" );
		return $resp;
	}

	private $title;
	private $user;
	private $editCount;
	private $firstEdit;

	public function __construct( $user, $title ) {
		$this->title = $title;
		$this->user = $user;
		$this->load();
	}

	public function getEditCount() {
		return $this->editCount;
	}

	public function getFirstEdit() {
		return $this->firstEdit;
	}

	private function load() {
		$dbr = wfGetDB( DB_SLAVE );
		$query = "SELECT COUNT(rev_user) AS editCount, MIN(rev_timestamp) AS firstEdit FROM revision " .
			"WHERE rev_user={$this->user->getId()} " .
			"AND rev_page={$this->title->getArticleId()}";
		$res = $dbr->query( $query );
		$row = $dbr->fetchObject( $res );
		$this->editCount = $row->editCount;
		$this->firstEdit = $row->firstEdit;
		$dbr->freeResult( $res );
	}

	public function getDisplayName() {
		$name = $this->user->getRealName();

		// Filter out email addresses
		if ( preg_match( "/^[-_a-z0-9\'+*$^&%=~!?{}]++(?:\.[-_a-z0-9\'+*$^&%=~!?{}]+)*+@(?:(?![-.])[-a-z0-9.]+(?<![-.])\.[a-z]{2,6}|\d{1,3}(?:\.\d{1,3}){3})(?::\d++)?$/iD", $name ) ) {
			$name = ''; // use username instead
		}
		if ( !$name ) { $name = $this->user->getName();
		}
		return $name;
	}

	private function getAuthorLink() {
		global $wgScriptPath;
		$title = Title::newFromText( 'User:' . $this->user->getTitleKey() );
		$href = $title->getFullUrl();
		return $href;
	}

	public function getInfo( $type ) {
	// if($type==="ORCID")
	}

	/**
	 * Creates the HTML code to display a single
	 * author
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
	 */
	public function addXml( $doc, $node ) {
		$e = $doc->createElement( "Author" );
		$e->setAttribute( "Name", $this->getDisplayName() );
		$e->setAttribute( "EditCount", $this->editCount );
		$e->setAttribute( "Url", $this->getAuthorLink() );
		$node->appendChild( $e );
	}

	public static function compareByEdits( $a1, $a2 ) {
		$c = $a2->getEditCount() - $a1->getEditCount();
		if ( $c == 0 ) { // If equal edits, compare by realname
			$c = strcasecmp( $a1->getDisplayName(), $a2->getDisplayName() );
		}
		return $c;
	}
}
