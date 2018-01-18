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

use ParserOptions;
use ParserOutput;
use RequestContext;
use SimpleXMLElement;
use TextContent;
use Title;
use User;
use WikiPathways\Pathway;

/**
 * Content for Pathway pages
 */
class Content extends TextContent {

	private $redirectTarget = false;
	protected $parsed;
	protected $title;
	protected $revId;
	protected $request;

	/**
	 * @param string $text GPML code.
	 * @param string $modelId the content model name
	 */
	public function __construct( $text, $modelId = CONTENT_MODEL_GPML ) {
		parent::__construct( $text, $modelId );
		$this->output = RequestContext::getMain()->getOutput();
		$this->request = RequestContext::getMain()->getRequest();
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
		$this->title = $title;
		$this->revId = $revId;
		$this->pathway = Pathway::newFromTitle( $this->title );

		$this->parsed = new SimpleXMLElement( $this->getNativeData() );
		$this->parsed->registerXPathNamespace( "gpml", "http://pathvisio.org/GPML/2013a" );

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

		$output->addModules( [ "wpi.AuthorInfo", "wpi.Pathway" ] );

		$output->setTitleText( $this->getTitle() );
		$output->setText( $html );
	}

	/**
	 * @return Message Page for GPML
	 */
	protected function getHtml() {
		return wfMessage( "wp-gpml-page-layout" )->params( $this->getSections() )->text();
	}

	/**
	 * @return array of possible blocks
	 */
	protected function getSections() {
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
	protected function renderPrivateWarning() {
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
	protected function renderTitle() {
		return wfMessage( 'wp-gpml-title' )->params( $this->pathway->getName() )
			->parse();
	}

	/**
	 * @return method name
	 */
	protected function renderDiagram() {
		$pathway = $this->pathway;
		$jsonData = $pathway->getPvjson();
		if ( !$jsonData ) {
			$pngPath = $pathway->getFileURL( FILETYPE_PNG, false );

			return wfMessage( "wp-gpml-diagram-no-json" )->params( $pngPath )
				->plain();
		}

		$this->getOutput()->addJsConfigVars( "pathwayJsonData", $jsonData );
		$this->getOutput()->addModule( [ "wpi.pvjs", "wpi.PathwayLoader" ] );
		return wfMessage( "wp-gpml-diagram" )->params( $pathway->getSvg(), $jsonData )
			->parse();
	}
	/**
	 * @return method name
	 */
	protected function renderDiagramFooter() {
		$pathway = $this->pathway;

		// Create dropdown action menu
		$download = [
			'PathVisio (.gpml)' => self::getDownloadURL( $pathway, 'gpml' ),
			'Scalable Vector Graphics (.svg)' => self::getDownloadURL( $pathway, 'svg' ),
			'Gene list (.txt)' => self::getDownloadURL( $pathway, 'txt' ),
			'Biopax level 3 (.owl)' => self::getDownloadURL( $pathway, 'owl' ),
			'Eu.Gene (.pwf)' => self::getDownloadURL( $pathway, 'pwf' ),
			'Png image (.png)' => self::getDownloadURL( $pathway, 'png' ),
			'Acrobat (.pdf)' => self::getDownloadURL( $pathway, 'pdf' ),
		];
		$downloadlist = '';
		foreach ( array_keys( $download ) as $key ) {
			$downloadlist .= '<li><a href="' . $download[$key] . '">' . $key . '</a></li>';
		}

		return wfMessage( "wp-gpml-diagram-footer" )->params( $downloadlist )->plain();

		// Create edit button
		$pathwayURL = $pathway->getTitleObject()->getPrefixedURL();
		// AP20070918
		$helpUrl = Title::newFromText("Help:Known_problems")->getFullUrl();
		$helpLink = '<div style="float:left;"><a href="' . $helpUrl . '"> not working?</a></div>';
		if ($wgUser->isLoggedIn() && $pathway->getTitleObject()->userCan('edit')) {
			$identifier = $pathway->getIdentifier();
			$version = $pathway->getLatestRevision();
			// see http://www.ericmmartin.com/projects/simplemodal/
			$wgOut->addScript('<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/simplemodal/1.4.4/jquery.simplemodal.min.js"></script>');
			//*
			// this should just be a button, but the button class only works for "a" elements with text inside.
			$openInPathVisioScript = <<<SCRIPT
<script type="text/javascript">
window.addEventListener('DOMContentLoaded', function() {
	document.querySelector('#edit-button').innerHTML = '<a id="download-from-page" href="#" onclick="return false;" class="button"><span>Launch Editor</span></a>{$helpLink}';
	$("#download-from-page").click(function() {
		$.modal('<div id="jnlp-instructions" style="width: 610px; height:616px; cursor:pointer;" onClick="$.modal.close()"><img id="jnlp-instructions-diagram" src="/skins/wikipathways/jnlp-instructions.png" alt="The JNLP will download to your default folder. Right-click the JNLP file and select Open."> </div>',
		{
			overlayClose: true,
			overlayCss: {backgroundColor: "gray"},
			opacity: 50
		});
		// We need the kludge below, because the image doesn't display in FF otherwise.
		window.setTimeout(function() {
			$('#jnlp-instructions-diagram').attr('src', '/skins/wikipathways/jnlp-instructions.png');
		}, 10);
		// server must set Content-Disposition: attachment
		// TODO why do the ampersand symbols below get parsed as HTML entities? Disabling this line and using the minimal line below for now, but we shouldn't have to do this..
		//window.location = "{$SITE_URL}/wpi/extensions/PathwayViewer/pathway-jnlp.php?identifier={$identifier}&version={$version}&filename=WikiPathwaysEditor";
		window.location = "{$SITE_URL}/wpi/extensions/PathwayViewer/pathway-jnlp.php?identifier={$identifier}";
	});
});
</script>
SCRIPT;
			$wgOut->addScript($openInPathVisioScript);

		} else {
			if(!$wgUser->isLoggedIn()) {
				$hrefbtn = SITE_URL . "/index.php?title=Special:Userlogin&returnto=$pathwayURL";
				$label = "Log in to edit pathway";
			} else if(wfReadOnly()) {
				$hrefbtn = "";
				$label = "Database locked";
			} else if(!$pathway->getTitleObject()->userCan('edit')) {
				$hrefbtn = "";
				$label = "Editing is disabled";
			}
			$script = <<<SCRIPT
<script type="text/javascript">
window.addEventListener('DOMContentLoaded', function() {
	document.querySelector('#edit-button').innerHTML = '<a href="{$hrefbtn}" title="{$label}" id="edit" class="button"><span>{$label}</span></a>{$helpLink}';
});
</script>
SCRIPT;
			$wgOut->addScript($script);
		}
	}
	/**
	 * @return method name
	 */
	protected function renderQualityTags() {
		return __METHOD__;
	}
	/**
	 * @return method name
	 */
	protected function renderOntologyTags() {
		return __METHOD__;
	}
	/**
	 * @return method name
	 */
	protected function renderBibliography() {
		return __METHOD__;
	}

	/**
	 * @return string
	 */
	protected function renderHistory() {
		return wfMessage( "wp-gpml-history" )->parse();
	}

	/**
	 * @return string
	 */
	protected function renderXrefs() {
		return wfMessage( "wp-gpml-xrefs" )->parse();
	}
	/**
	 * @return method name
	 */
	protected function renderLinkToFullPathwayPage() {
		return wfMessage( "wp-gpml-link-to-full-page" )
			->params( $this->pathway->getFullUrl() )->parse();
	}
	/**
	 * Provide rendered html for author info
	 * @return string
	 */
	protected function renderAuthorInfo() {
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
	 * Rendered HTML for the pathway display
	 * @return string
	 */
	protected function  renderPathway() {
		global $wgContLang, $wgUser;
		$editorState = 'disabled';

		$gpml = $this->pathway->getFileURL( FILETYPE_GPML );
		$imgURL = $this->pathway->getImage()->getURL();

		$identifier = $this->pathway->getIdentifier();
		$version = $this->pathway->getLatestRevision();

		$textalign = $wgContLang->isRTL() ? ' style="text-align:right"' : '';
		$align = "center";
		$thumbUrl = $this->pathway->getImage()->getViewURL();
		$label = $this->getLabel();
		$alt = "ALT-TEXT";
		$id = "thumb";

		return wfMessage( "wp-gpml-pathway" )->params(
			$wgUser->mName, $id, $align, $identifier, $version, $gpml,
			$editorState, $alt, $imgURL, $textalign, $label
		)->plain();
	}

	/**
	 * Return HTML for a labeled button
	 *
	 * @return string
	 */
	protected function getLabel() {
		global $wgUser;

		// Create edit button
		$pathwayURL = $this->pathway->getTitleObject()->getPrefixedURL();
		// AP20070918
		$editButton = '';
		if ( $wgUser->isLoggedIn() && $this->pathway->getTitleObject()->userCan( 'edit' ) ) {
			$identifier = $this->pathway->getIdentifier();
			$version = $this->pathway->getLatestRevision();
			$editButton = '<div style="float:left;">' .
						// see http://www.ericmmartin.com/projects/simplemodal/
						'<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/simplemodal/1.4.4/jquery.simplemodal.min.js"></script>'.
						// this should just be a button, but the button class only works for "a" elements with text inside.
						'<a id="download-from-page" href="#" onclick="return false;" class="button"><span>Launch Editor</span></a>' .
						'<script type="text/javascript">' .
						" $('#download-from-page').click(function() { " .
						" $.modal('<div id=\"jnlp-instructions\" style=\"width: 610px; height:616px; cursor:pointer;\" onClick=\"$.modal.close()\"><img id=\"jnlp-instructions-diagram\" src=\"/skins/wikipathways/jnlp-instructions.png\" alt=\"The JNLP will download to your default folder. Right-click the JNLP file and select Open.\"> </div>', {overlayClose:true, overlayCss: {backgroundColor: \"gray\"}, opacity: 50}); " .
						// We need the kludge below, because the image doesn't display in FF otherwise.
						" window.setTimeout(function() { " .
						" $('#jnlp-instructions-diagram').attr('src', '/skins/wikipathways/jnlp-instructions.png'); " .
						"}, 10);" .
						// server must set Content-Disposition: attachment
						// TODO why do the ampersand symbols below get parsed as HTML entities? Disabling this line and using the minimal line below for now, but we shouldn't have to do this..
						//" window.location = '" . SITE_URL . "/wpi/extensions/PathwayViewer/pathway-jnlp.php?identifier=" . $identifier . "&version=" . $version . "&filename=WikiPathwaysEditor'; " .
						" window.location = '" . SITE_URL . "/wpi/extensions/PathwayViewer/pathway-jnlp.php?identifier=" . $identifier . "'; " .
						" }); " .
						'</script>' .
						'</div>';
		} else {
			if ( !$wgUser->isLoggedIn() ) {
				$hrefbtn = SITE_URL . "/index.php?title=Special:Userlogin&returnto=$pathwayURL";
				$label = "Log in to edit pathway";
			} elseif ( wfReadOnly() ) {
				$hrefbtn = "";
				$label = "Database locked";
			} elseif ( !$this->pathway->getTitleObject()->userCan( 'edit' ) ) {
				$hrefbtn = "";
				$label = "Editing is disabled";
			}

			$editButton = "<a href='$hrefbtn' title='$label' id='edit' " .
						"class='button'><span>$label</span></a>";
		}

		$helpUrl = Title::newFromText( "Help:Known_problems" )->getFullUrl();
		$helpLink = "<div style='float:left;'><a href='$helpUrl'> not working?</a></div>";

		// Create dropdown action menu
		$pwTitle = $this->pathway->getTitleObject()->getFullText();

		// disable dropdown for now
		$drop = self::editDropDown( $this->pathway );

		return $editButton . $helpLink . $drop;
	}

	/**
	 * Dropdown widget
	 *
	 * @param string $pathway to get widget for
	 * @return string
	 */
	public static function editDropDown( $pathway ) {
		global $wgOut;

		$download = [
			'PathVisio (.gpml)' => self::getDownloadURL( $pathway, 'gpml' ),
			'Scalable Vector Graphics (.svg)' => self::getDownloadURL( $pathway, 'svg' ),
			'Gene list (.txt)' => self::getDownloadURL( $pathway, 'txt' ),
			'Biopax level 3 (.owl)' => self::getDownloadURL( $pathway, 'owl' ),
			'Eu.Gene (.pwf)' => self::getDownloadURL( $pathway, 'pwf' ),
			'Png image (.png)' => self::getDownloadURL( $pathway, 'png' ),
			'Acrobat (.pdf)' => self::getDownloadURL( $pathway, 'pdf' ),
		];
		$downloadlist = '';
		foreach ( array_keys( $download ) as $key ) {
			$downloadlist .= "<li><a href='{$download[$key]}'>$key</a></li>";
		}

		$dropdown = <<<DROPDOWN
<div style="float:right">
<ul id="gpml-pathwaynav" name="nav">
<li><a href="#nogo2" class="button buttondown"><span>Download</span></a>
		<ul>
			$downloadlist
		</ul>
</li>
</ul>
</div>
DROPDOWN;

		$wgOut->addModules( [ "wpi.Dropdown" ] );
		return $dropdown;
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
			$args['oldid'] = $pathway->getActiveRevision();
		}

		return WPI_SCRIPT_URL . "?" . wfArrayToCgi( $arg );
	}

	/**
	 * Convenience function for extracting the text content of a single element.
	 *
	 * @param string $path xpath to which "/text()[1]" will be added.
	 * @return string
	 */
	protected function xpathContent( $path ) {
		return (string)current( $this->parsed->xpath( "$path/text()[1]" ) );
	}

	/**
	 * Convenience function for extracting the text content of an attribute
	 *
	 * @param string $path xpath to the attribute
	 * @return string
	 */
	protected function xpathAttribute( $path ) {
		return (string)current( $this->parsed->xpath( $path ) );
	}

	/**
	 * @return string the description from the gpml.
	 */
	protected function renderDescription() {
		$msg = new \RawMessage( "$1" );
		return $msg->params( $this->xpathContent(
			"/gpml:Pathway/gpml:Comment[@Source='WikiPathways-description']"
		) );
	}

	/**
	 * Get the title text
	 * @return string the title of this pathway
	 */
	protected function getTitle() {
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
