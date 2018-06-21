window.addEventListener('load', function() {
	var theme;
	var jsonData = JSON.parse( mw.config.get( "pvjsString" ) );
	if ( !!window.URLSearchParams ) {
		var urlParams = new URLSearchParams( window.location.search );
		if ( !!urlParams && urlParams.get && urlParams.get( 'theme' ) ) {
			theme = urlParams.get( 'theme' ).replace( /[^a-zA-Z0-9]/, '' );
		}
	}
	new Pvjs( ".Container", { theme: theme || 'plain',
							  pathway: jsonData.pathway,
							  entitiesById: jsonData.entitiesById,
							  onReady: function() {}
							} );
});
