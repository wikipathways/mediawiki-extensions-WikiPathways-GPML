window.addEventListener('load', function() {
	var theme;
	var jsonData = JSON.parse( mw.config.get( "pvjsString" ) );
	if ( !!window.URLSearchParams ) {
		var urlParams = new URLSearchParams( window.location.search );
		if ( !!urlParams && urlParams.get && urlParams.get( 'theme' ) ) {
			theme = urlParams.get( 'theme' ).replace( /[^a-zA-Z0-9]/, '' );
		}
	}
        theme = theme || 'plain';
	new Pvjs( ".Container", { theme: theme,
				  pathway: jsonData.pathway,
				  entitiesById: jsonData.entitiesById,
				  onReady: function() {}
				} );
  var containerBackground = theme === 'dark' ? '#3d3d3d' : 'fefefe';
  // TODO: this is a klukge. Fix it in pvjs.
  document.querySelectorAll(".Container").forEach(function(el) {
    el.style.setProperty('background-color', containerBackground);
  });
  document.querySelectorAll(".diagram-container").forEach(function(el) {
    el.style.setProperty('background', containerBackground);
  });
  if (theme === "dark") {
    var metaboliteStyle = _.map(document.querySelectorAll('.DataNode.Metabolite'))
    .filter(function(el) {
      return el.hasAttribute("typeof");
    })
    .map(function(el) {
      return el.getAttribute('typeof')
        .split(' ')
        .filter(function(elType) {
          return elType.match(/^wikidata:*/);
        });
    })
    .reduce(function(acc, wikidataTypes) {
      return _.union(acc, wikidataTypes);
    }, [])
    .map(function(wikidataType) {
      return wikidataType.replace(/wikidata:/, '')
    })
    .reduce(function(acc, wikidataIdentifier) {
      console.log('wikidataIdentifier:');
      console.log(wikidataIdentifier);
      var patternId = 'Pattern' + wikidataIdentifier;
      //var myStyle = '.DataNode.Metabolite[typeof~="wikidata:' + wikidataIdentifier + '"]:hover > .Icon { fill: url(/Pathway:WP2868#Pattern' + wikidataIdentifier + '); }';
      var myStyle = '.DataNode.Metabolite[typeof~="wikidata:' + wikidataIdentifier + '"]:hover > .Icon { fill: url(#Pattern' + wikidataIdentifier + '); }';
      return acc + '\n' + myStyle;
    }, '');
    var sheet = document.createElement('style')
    sheet.innerHTML = metaboliteStyle;
    document.body.appendChild(sheet);
  }
});
