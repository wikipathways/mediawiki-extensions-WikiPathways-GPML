// From: https://www.mediawiki.org/wiki/OOUI/Windows/Dialogs

function jnlpDialog( config ) {
	jnlpDialog.super.call( this, config );
}
OO.inheritClass( jnlpDialog, OO.ui.Dialog );

// Specify a name for .addWindows()
jnlpDialog.static.name = 'downloadDialog';
// Specify a title statically (or, alternatively, with data passed to the opening() method).
jnlpDialog.static.title = 'JNLP dialog';

// Override the click-block so that we can click out of the dialog.
jnlpDialog.prototype.onMouseDown = function( e ) {
	if ( e.target === this.$element[ 0 ] ) {
		this.close();
	}
};

// Customize the initialize() function: This is where to add content to the dialog body and set up event handlers.
jnlpDialog.prototype.initialize = function () {
	// Call the parent method
	jnlpDialog.super.prototype.initialize.call( this );
	// Create and append a layout and some content.
	this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );
	this.content.$element.append( '<div style="margin-left: 2em"><img src="/skins/wikipathways/jnlp-instructions.png"></div>' );
	this.$body.append( this.content.$element );
};

// Make the window.
var downloadDialog = new jnlpDialog( {
	size: 'large'
} );

// Create and append a window manager, which will open and close the window.
var windowManager = new OO.ui.WindowManager();
$( 'body' ).append( windowManager.$element );

// Add the window to the window manager using the addWindows() method.
windowManager.addWindows( [ downloadDialog ] );

$( '#download-from-page' ).click( function() {
	// Open the window!
	windowManager.openWindow( downloadDialog );
	window.location = '/extensions/WikiPathways/PathwayViewer/pathway-jnlp.php?identifier=' + mw.config.get( "identifier" ) + "&version=" + mw.config.get( "version" );
} );
