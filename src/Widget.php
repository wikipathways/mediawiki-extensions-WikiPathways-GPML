<?php
/**
 * Entry point for a pathway viewer widget that can be included in
 * other pages.
 *
 * <iframe src ="http://www.wikipathways.org/wpi/PathwayWidget.php?id=WP4"
 *     width="500" height="500" style="overflow:hidden;"></iframe>
 *
 * Copyright (C) 2018  J. David Glaststone Institutes
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
 * This page will display the interactive pathway viewer for a given
 * pathway. It takes the following parameters:
 *
 * - identifier: the pathway identifier (e.g. WP4)
 *
 * - version: the version (revision) number of a specific version of
 *  the pathway (optional, leave out to display the newest version)
 *
 */
namespace WikiPathways\GPML;

use DerivativeContext;
use ErrorPageError;
use Hooks;
use IContextSource;
use FormlessAction;
use Page;
use RequestContext;
use Revision;
use SkinFactory;
use Title;
use WikiPathways\Pathway;
use WikiPathways\PathwayCache\Factory;
use WikiPathways\PathwayViewer;

$last = error_reporting( 0 );
$oldVal = ini_set( 'display_errors', '0' ); // Hide E_NOTICE from multiple MEDIAWIKI defs
require getenv( "MW_INSTALL_PATH" ) . '/includes/WebStart.php';
ini_set( 'display_errors', $oldVal );
error_reporting( $last );

class Widget extends FormlessAction {
	private $svg;

	public function __construct(
		Page $page, IContextSource $context = null
	) {
		parent::__construct( $page, $context );

#		Hooks::register(
#			'RequestContextCreateSkin',
#			function ( $req, &$skin ) {
#				$skin = SkinFactory::getDefaultInstance()
#					  ->makeSkin( 'widgetoutput' );
#		} );
		$this->svg = $svg;
	}

	public function requiresWrite() {
		return false;
	}

	public function getName() {
		return "widget";
	}

	public function onView() {
		header( "X-XSS-Protection: 0" );
		$out = $this->getOutput();
		$req = $this->getRequest();
		#$out->setArticleBodyOnly( true );
		$out->clearSubtitle();
		$out->addModules( [ "wpi.widget" ] );

		$version = $req->getVal( 'rev', 0 );
		$label = $req->getVal( 'label' );
		$xref = $req->getVal( 'xref' );
		$colors = $req->getVal( 'colors' );

		$highlights = " ";
		if (
			( !is_null( $label ) || !is_null( $xref ) )
			&& !is_null( $colors )
		) {
			$highlights = "[";
			$selectors = [];
			if ( !is_null( $label ) ) {
				if ( is_array( $label ) ) {
					foreach ( $label as $l ) {
						array_push(
							$selectors, '{"selector":"' . $l . '",'
						);
					}
				} else {
					array_push(
						$selectors, '{"selector":"' . $label . '",'
					);
				}
			}
			if ( !is_null( $xref ) ) {
				if ( is_array( $xref ) ) {
					foreach ( $xref as $x ) {
						$xParts = explode( ",", $x );
						array_push(
							$selectors, '{"selector":"xref:id:'
							. $xParts[0] . "," . $xParts[1] . '",'
						);
					}
				} else {
					$xrefParts = explode( ",", $xref );
					array_push(
						$selectors, '{"selector":"xref:id:'
						. $xrefParts[0] . "," . $xrefParts[1] . '",'
					);
				}
			}

			$colorArray = explode( ",", $colors );
			$firstColor = $colorArray[0];

			// if color list doesn't match selector list, then just
			// use first color
			if ( count( $selectors ) != count( $colorArray ) ) {
				for ( $i = 0; $i < count( $selectors ); $i++ ) {
					$colorArray[$i] = $firstColor;
				}
			}

			$selectorCount = count($selectors);
			// if highlight params received
			for ( $i = 0; $i < $selectorCount - 1; $i++ ) {
				$highlights .= $selectors[$i] . '"backgroundColor":"'
							. $colorArray[$i] . '","borderColor":"'
							. $colorArray[$i] . '"},';
			}
			# TODO: use join instead of handling the last one as a special case
			if ( $selectorCount > 0 ) {
				$highlights .= $selectors[$selectorCount - 1] . '"backgroundColor":"'
						. $colorArray[$selectorCount - 1] . '","borderColor":"'
						. $colorArray[$selectorCount - 1] . '"}';
			}
			$highlights .= "]";
		}

		if (
			!isset( $highlights ) || empty( $highlights )
			|| $highlights == " "
		) {
			$highlights = "[]";
		}

#		$pathway = Pathway::newFromTitle( $this->getTitle() );
#		if ( $version ) {
#			$pathway->setActiveRevision( $version );
#		}

		$out->addJsConfigVars( "kaavioHighlights", $highlights );

		return $this->svg;
	}

	public static function main() {
		$context = RequestContext::getMain();
		$req = $context->getRequest();
		$out = $context->getOutput();
		$title = $context->getTitle();
		$wpid = $title->getText();
		$revid = $title->getLatestRevID();
		$pathway = new Pathway($wpid, $revid);
		#var_dump(get_class_methods($pathway));
		$out->addModuleStyles( [ "wpi.PathwayLoader.css" ] );
		$svg = Factory::getCache( "PVJSSVG", $pathway );
		$out->addHTML( '<div class="Container" style="height: 600px; width: 100%;">' . $svg->render() . '</div>' );

		$json = Factory::getCache( "JSON", $pathway );
		$out->addJsConfigVars( "pvjsString", $json->render() );

		$out->addModules( [ "wpi.PathwayLoader.js" ] );
	}
}

Widget::main();
