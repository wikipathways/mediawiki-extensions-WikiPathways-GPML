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

use Parser;
use StripState;

class PathwayPage {
	private $pathway;
	private $data;

	/**
	 * Called on ParserBeforeStrip hook which is intended to be used
	 * to process raw wiki code before the MW processor. May be deprecated.
	 *
	 * @param Parser $parser object
	 * @param string &$text being parsed
	 * @param StripState $stripState object
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserBeforeStrip
	 */
	public static function render( Parser $parser, &$text, StripState $stripState ) {
		global $wgRequest;

		$title = $parser->getTitle();
		$oldId = $wgRequest->getVal( "oldid" );
		if ( $title && $title->getNamespace() == NS_PATHWAY &&
			preg_match( "/^\s*\<\?xml/", $text ) ) {
			$parser->disableCache();

			try {
				$pathway = Pathway::newFromTitle( $title );
				if ( $oldId ) {
					$pathway->setActiveRevision( $oldId );
				}
				$pathway->updateCache( FILETYPE_IMG );
				// In case the image page is removed
				$page = new PathwayPage( $pathway );
				$text = $page->getContent();
			} catch ( Exception $e ) {
				// Return error message on any exception
				$text = wfMessage( "pathwaypage-render-error", $e );
			}
		}
	}

	/**
	 * Construct me!
	 *
	 * @param Pathway $pathway to get the page for.
	 */
	public function __construct( $pathway ) {
		$this->pathway = $pathway;
		$this->data = $pathway->getPathwayData();
	}

	/**
	 * Unused function to get the basic layout
	 * @return string
	 */
	public function getContent() {
		$text = wfMessage(
			"wp-gpml-page-layout", $this->titleEditor(), $this->privateWarning(), $this->descriptionText(),
			$this->curatinTags(), $this->ontologyTags(), $this->bibliographyText()
		);
		return $text;
	}

	public function titleEditor() {
		$title = $this->pathway->getName();
		return "<pageEditor id='pageTitle' type='title'>$title</pageEditor>";
	}

	public function privateWarning() {
		global $wgLang;

		$warn = '';
		if ( !$this->pathway->isPublic() ) {
			$url = SITE_URL;
			$msg = wfMessage( 'pathwaypage-private-warning' )->plain();

			$pp = $this->pathway->getPermissionManager()->getPermissions();
			$expdate = $pp->getExpires();
			$expdate = $wgLang->date( $expdate, true );
			$msg = str_replace( '$DATE', $expdate, $msg );
			$warn = "<div class='private_warn'>$msg</div>";
		}
		return $warn;
	}

	public function curationTags() {
		$tags = "== Quality Tags ==\n" .
			"<CurationTags></CurationTags>";
		return $tags;
	}

	public function descriptionText() {
		// Get WikiPathways description
		$content = $this->data->getWikiDescription();

		$description = $content;
		if ( !$description ) {
			$description = "<I>No description</I>";
		}
		$description = "== Description ==\n<div id='descr'>"
			 . $description . "</div>";

		$description .= "<pageEditor id='descr' type='description'>$content</pageEditor>\n";

		// Get additional comments
		$comments = '';
		foreach ( $this->data->getGpml()->Comment as $comment ) {
			if ( $comment['Source'] == COMMENT_WP_DESCRIPTION ||
				$comment['Source'] == COMMENT_WP_CATEGORY ) {
				continue; // Skip description and category comments
			}
			$text = (string)$comment;
			$text = html_entity_decode( $text );
			$text = nl2br( $text );
			$text = self::formatPubMed( $text );
			if ( !$text ) { continue;   }			$comments .= "; " . $comment['Source'] . " : " . $text . "\n";
		}
		if ( $comments ) {
			$description .= "\n=== Comments ===\n<div id='comments'>\n$comments<div>";
		}
		return $description;
	}

	public function ontologyTags() {
		global $wpiEnableOtag;
		if ( $wpiEnableOtag ) {
			$otags = "== Ontology Terms ==\n" .
				"<OntologyTags></OntologyTags>";
			return $otags;
		}
	}

	public function bibliographyText() {
		global $wgUser;

		$out = "<pathwayBibliography></pathwayBibliography>";
		// No edit button for now, show help on how to add bibliography instead
		// $button = $this->editButton('javascript:;', 'Edit bibliography', 'bibEdit');
		# &$parser, $idClick = 'direct', $idReplace = 'pwThumb', $new = '', $pwTitle = '', $type = 'editor'
		$help = '';
		if ( $wgUser->isLoggedIn() ) {
			$help = "{{Template:Help:LiteratureReferences}}";
		}
		return "== Bibliography ==\n$out\n$help";
			// "<div id='bibliography'><div style='float:right'>$button</div>\n" .
			// "$out</div>\n{{#editApplet:bibEdit|bibliography|0||bibliography|0|250px}}";
	}


	public static function formatPubMed( $text ) {
		$link = "http://www.ncbi.nlm.nih.gov/entrez/query.fcgi?db=pubmed&cmd=Retrieve&dopt=AbstractPlus&list_uids=";
		if ( preg_match_all( "/PMID: ([0-9]+)/", $text, $ids ) ) {
			foreach ( $ids[1] as $id ) {
				$text = str_replace( $id, "[$link$id $id]", $text );
			}
		}
		return $text;
	}
}
