$(document).ready( function() {
	var sfEls = document.getElementById("gpml-pathwaynav").getElementsByTagName("LI");
	for (var i=0; i<sfEls.length; i++) {
		sfEls[i].onmouseover =
			function() {
				this.className+=" sfhover";
			};
		sfEls[i].onmouseout =
			function() {
				this.className=this.className.replace(" sfhover", "");
			};
	}
} );
