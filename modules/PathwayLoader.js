window.addEventListener('load', function() {
	var theme;
	var kaavioHighlights = JSON.parse( mw.config.get( "kaavioHighlights" ) ) || [];
	// TODO: this is a kludge, and it only handles a single label, e.g.:
	// https://vm1.wikipathways.org/Pathway:WP528?action=widget&label=PEMT&colors=green
	// goes to:
	// https://vm1.wikipathways.org/Pathway:WP528?action=widget&label=PEMT&colors=green&green=PEMT
	if (kaavioHighlights.length > 0) {
		var searchOriginal = location.search; 
		var searchParams = new URLSearchParams(searchOriginal); 
		kaavioHighlights.forEach(function(kaavioHighlight) {
					var selector = kaavioHighlight.selector;
					var color = kaavioHighlight.backgroundColor;
					searchParams.set(color, selector);
				});
		var searchUpdated = searchParams.toString();
		if (('?' + searchUpdated) !== searchOriginal) {
			location.search = searchUpdated;
		}
	}
//	var newState = kaavioHighlights.reduce(function(acc, kaavioHighlight) {
//				var selector = kaavioHighlight.selector;
//				var color = kaavioHighlight.backgroundColor;
//				acc[color] = selector;
//				return acc;
//			}, {});
//	history.replaceState(newState);
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
				  // TODO this isn't working yet below, so we're using the kludge above:
				  //highlighted: kaavioHighlights,
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
      /*
      console.log('wikidataIdentifier:');
      console.log(wikidataIdentifier);
      //*/
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
