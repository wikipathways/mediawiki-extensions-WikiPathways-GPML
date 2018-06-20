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
 * @author  Mark A. Hershberger
 */

namespace WikiPathways\GPML;

use DOMDocument;
use Exception;
use Html;
use Message;
use ParserOptions;
use ParserOutput;
use RequestContext;
use SimpleXMLElement;
use SpecialPage;
use TextContent;
use Title;
use User;
use WikiPathways\Organism;
use WikiPathways\Pathway;
use WikiPathways\PathwayCache\Factory;
use WikiTextContent;

/**
 * Content for Pathway pages
 */
class Content extends TextContent {

	private $redirectTarget = false;
	private $parsed;
	private $title;
	private $revId;
	private $request;
	private $user;
	private $validationErrors = [];
	private $wikitext;

	/**
	 * @param string $text GPML code.
	 * @param string $modelId the content model name
	 */
	public function __construct( $text, $modelId = CONTENT_MODEL_GPML ) {
		parent::__construct( $text, $modelId );
		$this->output = RequestContext::getMain()->getOutput();
		$this->request = RequestContext::getMain()->getRequest();
		$this->user = RequestContext::getMain()->getUser();
	}

	/**
	 * Get the first of any validation errors.
	 * @return string
	 */
	public function getValidationError() {
		if ( count( $this->validationErrors[0] ) ) {
			return $this->validationErrors[0];
		}
	}

	/**
	 * Validates the GPML code and returns the error if it's invalid
	 *
	 * @return bool
	 */
	public function isValid() {
		$gpml = $this->getTextForSearchIndex();
		// First, check if species is supported
		$msg = $this->checkGpmlSpecies( $gpml );
		if ( $msg ) {
			$this->validationErrors[] = $msg;
			return false;
		}

		// Second, validate GPML to schema
		$xml = new DOMDocument();
		$parsed = $xml->loadXML( $gpml );
		if ( !$parsed ) {
			$this->validationErrors[] = "Error: no valid XML provided\n$gpml";
			return false;
		}

		if ( !method_exists( $xml->firstChild, "getAttribute" ) ) {
			$this->validationErrors[] = "Not valid GPML!";
			return false;
		}

		$xmlNs = $xml->firstChild->getAttribute( 'xmlns' );
		$schema = Pathway::getSchema( $xmlNs );
		if ( !$schema ) {
			$this->validationErrors[] = "Error: no xsd found for $xmlNs\n$gpml";
			return false;
		}

		if ( !$xml->schemaValidate( WPI_SCRIPT_PATH . "/bin/$schema" ) ) {
			$error = libxml_get_last_error();
			$this->validationErrors[] = $gpml[$error->line - 1] . "\n";
			$this->validationErrors[] = str_repeat( '-', $error->column ) . "^\n";

			switch ( $error->level ) {
			case LIBXML_ERR_WARNING:
				$this->validationErrors[] = "Warning {$error->code}: ";
				break;
			case LIBXML_ERR_ERROR:
				$this->validationErrors[] = "Error {$error->code}: ";
				break;
			case LIBXML_ERR_FATAL:
				$this->validationErrors[] = "Fatal Error {$error->code}: ";
				break;
			}

			$this->validationErrors[] = sprintf(
				"%s %s (Line: %d Column: %d)", trim( $error->message ),
				$error->file, $error->line, $error->column
			);
			return false;
		}
		return true;
	}

	private function checkGpmlSpecies( $gpml ) {
		$gpml = utf8_encode( $gpml );
		// preg_match can fail on very long strings, so first try to
		// find the <Pathway ...> part with strpos
		$startTag = strpos( $gpml, "<Pathway" );
		if ( !$startTag ) {
			return "Unable to find start of '<Pathway ...>' tag.";
		}
		$endTag = strpos( $gpml, ">", $startTag );
		if ( !$endTag ) {
			return "Unable to find end of '<Pathway ...>' tag.";
		}

		if (
			preg_match( "/<Pathway.*Organism=\"(.*?)\"/us",
						substr( $gpml, $startTag, $endTag - $startTag ),
						$match )
		) {
			$species = $match[1];
			$organisms = array_keys( Organism::listOrganisms() );
			if ( !in_array( $species, $organisms ) ) {
				return "The organism '$species' for the pathway is not supported.";
			}
		} else {
			return "The pathway doesn't have an organism attribute.";
		}
	}

	/**
	 * Returns content with pre-save transformations applied
	 * If you need to mangle the GPML, here is the place to do it.
	 *
	 * @param Title $title of the page
	 * @param User $user who is editing
	 * @param ParserOptions $popts for parsing
	 *
	 * @return WikiPathways\ContentHandler\Content
	 */
	public function preSaveTransform( Title $title, User $user, ParserOptions $popts ) {
		return $this;
	}

	/**
	 * @param string $format format to get (ignored for now)
	 *
	 * @return string
	 *
	 * @see Content::serialize
	 */
	public function serialize( $format = null ) {
		return $this->getNativeData();
	}

	public function getParserOutput(
		Title $title, $revId = null, ParserOptions $options = null, $generateHtml = true
	) {
		$po = null;
		try {
			\MediaWiki\suppressWarnings();
			$this->parsed = new SimpleXMLElement( $this->getNativeData() );
			\MediaWiki\restoreWarnings();
			$this->parsed->registerXPathNamespace( "gpml", "http://pathvisio.org/GPML/2013a" );
		} catch ( Exception $e ) {
			// Not XML, use Wikitext
			\MediaWiki\restoreWarnings();
			$this->wikitext = new WikiTextContent( $this->getNativeData() );
			$po = $this->wikitext->getParserOutput( $title, $revId, $options, $generateHtml );
		}
		if ( $po === null ) {
			$po = parent::getParserOutput( $title, $revId, $options, $generateHtml );
		}

		return $po;
	}

	/**
	 * Set the HTML and add the appropriate styles
	 *
	 * @param Title $title Context title for parsing
	 * @param int|null $revId Revision ID (for {{REVISIONID}})
	 * @param ParserOptions $options Parser options
	 * @param bool $generateHtml Whether or not to generate HTML
	 * @param ParserOutput &$output The output object to fill (reference).
	 */
	protected function fillParserOutput(
		Title $title, $revId, ParserOptions $options, $generateHtml,
		ParserOutput &$output
	) {
		if ( $this->wikitext ) {
			return $this->wikitext->fillParserOutput(
				$title, $revId, $options, $generateHtml, $output
			);
		}
		$this->title = $title;
		$this->revId = $revId;
		$this->pathway = Pathway::newFromTitle( $this->title );

		if ( !$options ) {
			// NOTE: use canonical options per default to produce
			// cacheable output
			$options = $this->getContentHandler()
				->makeParserOptions( 'canonical' );
		}

		if ( $generateHtml ) {
			$html = $this->getHtml();
		} else {
			$html = '';
		}

		// These should be closer to where they modules that they go with are invoked.
		$output->addModules(
			[ "wpi.AuthorInfo", "wpi.Pathway", "wpi.toggleButton", "wpi.PageEditor" ]
		);

		$output->setTitleText( $this->getTitle() );
		$output->setText( $html );
	}

	/**
	 * @return Message Page for GPML
	 */
	protected function getHtml() {
		if ( $this->wikitext ) {
			return $this->wikitext->getHtml();
		}
		return wfMessage( "wp-gpml-page-layout" )->params( $this->getSections() )->text();
	}

	/**
	 * @return array of possible blocks
	 */
	private function getSections() {
		return [
			$this->renderPrivateWarning(),
			$this->renderTitle(), $this->renderAuthorInfo(),
			$this->renderDiagram(), $this->renderDiagramFooter(),
			$this->renderDescription(), $this->renderQualityTags(),
			$this->renderOntologyTags(), $this->renderBibliography(),
			$this->renderHistory(), $this->renderXrefs(),
			$this->renderLinkToFullPathwayPage()
		];
	}

	/**
	 * @return method name
	 */
	private function renderPrivateWarning() {
		global $wgLang;

		$warn = '';
		if ( !$this->pathway->isPublic() ) {
			$perm = $this->pathway->getPermissionManager()->getPermissions();
			$expDate = "<b class='error'>Could not get "
			. "permissions for this pathway</b>";
			if ( $perm ) {
				$expDate = $wgLang->date( $perm->getExpires(), true );
			}

			$warn = wfMessage( 'wp-gpml-private-warning' )
			->params( $expDate )->parse();
		}
		return $warn;
	}

	/**
	 * @return method name
	 */
	private function renderTitle() {
		return wfMessage( 'wp-gpml-title' )->params( $this->pathway->getName() )
		->parse();
	}

	/**
	 * @return method name
	 */
	private function renderDiagram() {
		$pathway = $this->pathway;
		$json = Factory::getCache( "JSON", $pathway );
		if ( !$json->isCached() ) {
			$png = Factory::getCache( "PNG", $pathway );

			return wfMessage(
				"wp-gpml-diagram-no-json"
			)->params( $png->getURL() )->plain();
		}

		$this->output->addJsConfigVars( "pvjsString", $json->render() );
		$this->output->addModuleStyles( [ "wpi.PathwayLoader.css" ] );
		$this->output->addModules( [ "wpi.PathwayLoader.js" ] );

		$svg = Factory::getCache( "SVG", $pathway );
		if ( $svg->isCached() ) {
			return wfMessage( "wp-gpml-diagram" )->params( $svg->fetchText() )
				->plain();
		}
	}

	private function renderLoggedInEditButton() {
		$this->output->addJsConfigVars(
			[
				'identifier' => $this->pathway->getIdentifier(),
				'version' => $this->pathway->getLatestRevision()
			]
		);
		return Html::rawElement(
			"div", [ "id" => "edit-button", "style" => "float: left" ],
			Html::rawElement(
				"a", [ "id" => "download-from-page", "href" => "#",
					   "class" => "button" ],
				Html::rawElement( "span", [], wfMessage( "wp-gpml-launch-editor" ) )
			)
		);
	}

	private function renderEditButton() {
		$this->output->addModules( [ "wpi.openInPathVisio" ] );
		// Create edit button
		if ( $this->user->isLoggedIn() && $this->pathway->getTitleObject()->userCan( 'edit' ) ) {
			return $this->renderLoggedInEditButton();
		} else {
			return $this->renderCannotEditButton();
		}
	}

	private function renderCannotEditButton() {
		if ( !$this->user->isLoggedIn() ) {
			$href = SpecialPage::getTitleFor( 'Userlogin' )->getFullURL( [
				'returnto' => $this->pathway->getTitleObject()->getPrefixedURL()
			] );
			$label = "Log in to edit pathway";
		} elseif ( wfReadOnly() ) {
			$href = "";
			$label = "Database locked";
		} elseif ( !$this->pathway->getTitleObject()->userCan( 'edit' ) ) {
			$href = "";
			$label = "Editing is disabled";
		}
		return Html::rawElement(
			"div", [ "id" => "edit-button", "style" => "float: left" ],
			Html::rawElement(
				"a", [ "class" => "button", "href" => $href, "title" => $label ],
				Html::rawElement( "span", [], $label )
			)
		);
	}

	/**
	 * @return string
	 */
	private function renderDiagramFooter() {
		return Html::rawElement(
			"div", [ 'id' => 'diagram-footer' ],
			$this->renderEditButton()
			. wfMessage( "wp-gpml-diagram-help-link" )->parse()
			. $this->renderDownloadDropdown()
		);
	}

	/**
	 * @return method name
	 */
	private function renderQualityTags() {
		return wfMessage( "wp-gpml-quality-tags" )->parse();
	}

	/**
	 * @return method name
	 */
	private function renderOntologyTags() {
		return wfMessage( "wp-gpml-ontology-tags" )->parse();
	}

	/**
	 * @return method name
	 */
	private function renderBibliography() {
		$out = wfMessage( "wp-gpml-bibliography" );

		$param = "";
		if ( $this->user->isLoggedIn() ) {
			$param = wfMessage( "wp-gpml-bibliography-help" );
		}
		return $out->params( $param )->parse();
	}

	/**
	 * @return string
	 */
	private function renderHistory() {
		return wfMessage( "wp-gpml-history" )->parse();
	}

	/**
	 * @return string
	 */
	private function renderXrefs() {
		return wfMessage( "wp-gpml-xrefs" )->parse();
	}
	/**
	 * @return method name
	 */
	private function renderLinkToFullPathwayPage() {
		return wfMessage( "wp-gpml-link-to-full-page" )
		->params( $this->pathway->getFullUrl() )->parse();
	}
	/**
	 * Provide rendered html for author info
	 *
	 * @return string
	 */
	private function renderAuthorInfo() {
		$msg = wfMessage( "wp-gpml-authorinfo" );
		$revId = $this->request->getInt( 'oldid' );
		if ( !$revId ) {
			$revId = $this->title->getLatestRevID();
		}
		return $msg->params(
			$this->title->getArticleID(), $revId, 5, false
		)->plain();
	}

	/**
	 * Dropdown widget
	 *
	 * @return string
	 */
	public function renderDownloadDropdown() {
		$type = [
			'Pathway' => 'gpml',
			'Scalable Vector Graphics' => 'svg',
			'Gene list' => 'txt',
			'Png image' => 'png'
		];
		/* 'Biopax level 3' => 'owl', */
		/* 'Eu.Gene' => 'pwf', */
		/* 'Acrobat => 'pdf', */

		$this->output->addModules( [ "wpi.Dropdown" ] );
		$downloadLinks = "";
		foreach ( $type as $name => $format ) {
			$downloadLinks .= Html::rawElement(
				"li", [], Html::rawElement(
					"a", [ "href" => self::getDownloadURL( $this->pathway, $format ) ],
					wfMessage( "wp-gpml-download-link-text" )->params( $name, $format )
				)
			);
		}
		return Html::rawElement(
			"div", [ "id" => "download-button" ],
			Html::rawElement(
				"div", [ "style" => "float: right" ],
				Html::rawElement(
					"ul", [ "id" => "gpml-pathwaynav", "name" => "nav" ],
					Html::rawElement(
						"li", [],
						Html::rawElement(
							"a", [ "href" => "#nogo2", "class" => "button buttondown" ],
							Html::rawElement( "span", [], wfMessage( "wp-gpml-download" ) )
						) . Html::rawElement( "ul", [], $downloadLinks )
					)
				)
			)
		);
	}

	/**
	 * Get a properly formatted download url
	 *
	 * @param string $pathway to get url for
	 * @param string $type of download url
	 * @return string
	 */
	public static function getDownloadURL( $pathway, $type ) {
		$arg['action'] = 'downloadFile';
		$arg['type'] = $type;
		$arg['pwTitle'] = $pathway->getTitleObject()->getFullText();
		if ( $pathway->getActiveRevision() ) {
			$arg['oldid'] = $pathway->getActiveRevision();
		}

		return WPI_SCRIPT_URL . "?" . wfArrayToCgi( $arg );
	}

	/**
	 * Convenience function for extracting the text content of a single element.
	 *
	 * @param string $path xpath to which "/text()[1]" will be added.
	 * @return string
	 */
	private function xpathContent( $path ) {
		return (string)current( $this->parsed->xpath( "$path/text()[1]" ) );
	}

	/**
	 * Convenience function for extracting the text content of an attribute
	 *
	 * @param string $path xpath to the attribute
	 * @return string
	 */
	private function xpathAttribute( $path ) {
		return (string)current( $this->parsed->xpath( $path ) );
	}

	/**
	 * @return string the description from the gpml.
	 */
	private function renderDescription() {
		$msg = wfMessage( "wp-gpml-description" );
		return $msg->params(
			$this->xpathContent(
				"/gpml:Pathway/gpml:Comment[@Source='WikiPathways-description']"
			)
		)->toString( Message::FORMAT_PARSE );
	}

	/**
	 * Get the title text
	 *
	 * @return string the title of this pathway
	 */
	private function getTitle() {
		return sprintf(
			"%s (%s)", $this->xpathAttribute( "/gpml:Pathway/@Name" ),
			$this->xpathAttribute( "/gpml:Pathway/@Organism" )
		);
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
