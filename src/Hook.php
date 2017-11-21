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
    static function onCodeEditorGetPageLanguage(
        Title $title, &$lang, $model = "", $format = ""
    ) {
        global $wgCodeEditorEnableCore;


        if ( $wgCodeEditorEnableCore ) {
            if ( $title->hasContentModel( CONTENT_MODEL_GPML ) ) {
                $lang = CONTENT_FORMAT_GPML;
                return false;
            }
        }

        return true;
    }

    static function onRegistration() {
        if ( !defined( 'CONTENT_MODEL_GPML' ) ) {
            define( 'CONTENT_MODEL_GPML', 'gpml' );
        }
        if ( !defined( 'CONTENT_FORMAT_GPML' ) ) {
            define( 'CONTENT_FORMAT_GPML', 'gpml' );
        }
        global $wgContentHanders;
        $wgContentHanders[CONTENT_MODEL_GPML] = 'WikiPathways\GPML\ContentHandler';

        # Temporary back compat
        $wgContentHanders["pathway"] = 'WikiPathways\GPML\ContentHandler';
    }

    static function onContentHandlerForModelID( $modeName, &$handler ) {
        if ( $modeName === CONTENT_MODEL_GPML
             || $modeName === "pathway" ) { # Temporary backcompat
            $handler = 'WikiPathways\GPML\ContentHandler';
            return false;
        }
        return true;
    }
}