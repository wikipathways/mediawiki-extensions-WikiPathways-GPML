document.AuthorInfo = {};
/**
 * Create an author list for the given page and add it to the
 * document in the given div element
 */
document.AuthorInfo.init = function(toDiv) {
	var authorEL = document.getElementById(toDiv);
	document.AuthorInfo.pageId = authorEL.dataset.pageid;
	document.AuthorInfo.showBots = authorEL.dataset.showbots;

	var parentElm = document.getElementById(toDiv);

	//The top container
	var contentDiv = document.createElement("div");
	contentDiv.id = "AuthorInfo_" + authorEL.pageId;
	document.AuthorInfo.contentDiv = contentDiv;
	parentElm.appendChild(contentDiv);

	var authorDiv = document.createElement("div");
	document.AuthorInfo.contentDiv.appendChild(authorDiv);
	document.AuthorInfo.authorDiv = authorDiv;

	//Overlay div to show errors
	document.AuthorInfo.errorDiv = document.createElement("div");
	document.AuthorInfo.errorDiv.className = "authorerror";
	document.AuthorInfo.contentDiv.appendChild(document.AuthorInfo.errorDiv);

	document.AuthorInfo.loadAuthors(authorEL.dataset.limit);
};

document.AuthorInfo.loadAuthors = function(limit) {
	document.AuthorInfo.lastLimit = limit;
	if(limit == 0) {
		limit = -1;
	}

	$.ajax(
		mw.util.wikiScript() + '?' + $.param( {
			action: 'ajax',
			rs: 'WikiPathways\\GPML\\AuthorInfoList::jsGetAuthors',
			rsargs: [document.AuthorInfo.pageId, parseInt(limit) + 1, true] //true=includeBots
		} ), {
			complete: document.AuthorInfo.loadAuthorsCallback,
			dataType: "xml"
		} );
};

document.AuthorInfo.loadAuthorsCallback = function(xhr) {
	if(document.AuthorInfo.checkResponse(xhr)) {
		var xml = xhr.responseXML;
		var elements = xml.getElementsByTagName("Author");

		var showAll = document.AuthorInfo.lastLimit <= 0 ||
			elements.length <= document.AuthorInfo.lastLimit;

		var html = "<span class='author'>";
		var end = showAll ? elements.length : elements.length - 1;
		for(var i=0;i<end;i++) {
			var elm = elements[i];
			var nm = elm.getAttribute("Name");
			var title = nm + " edited this page " + elm.getAttribute("EditCount") + " times";
			if(i==0){
				title += " and is the original author";
			}
			if(nm.indexOf("Maintenance bot") != -1){continue;} //skip listing bot, in any position
			html += "<A href='" + elm.getAttribute("Url") + "' title='" + title + "'>" + nm + "</A>";
			if(i != end - 1) {
								if (i == end - 2){
										if (elements[i+1].getAttribute("Name").indexOf("Maintenance bot") != -1) { continue;} //skip comma of last author is bot
								}
				html += ", ";
			}
		}
		if(!showAll && elements.length > document.AuthorInfo.lastLimit) {
			html += ", <a href='javascript:document.AuthorInfo.showAllAuthors()' " +
				"title='Click to show all authors'>et al.</a>";
		}
		document.AuthorInfo.authorDiv.innerHTML = html + "</span>";
	}
};

document.AuthorInfo.showAllAuthors = function() {
	document.AuthorInfo.loadAuthors(0);
};

document.AuthorInfo.checkResponse = function(xhr) {
	if (xhr.readyState == 4){
		if (xhr.status != 200) {
			document.AuthorInfo.showError(xhr.statusText);
		}
	} else if ( xhr.readyState != "complete" ) {
		document.AuthorInfo.showError(xhr.statusText);
	}
	return true;
};

document.AuthorInfo.showError = function(msg) {
	document.AuthorInfo.errorDiv.style.display = "block";
	document.AuthorInfo.errorDiv.innerHTML = "<p class='authorerror'>Error loading authors: " + msg +
		" - <a href='javascript:document.AuthorInfo.hideError();'>close</a></p>";
};

document.AuthorInfo.hideError = function() {
	document.AuthorInfo.errorDiv.style.display = "none";
	document.AuthorInfo.errorDiv.innerHTML = "";
};

$(document).ready(function() {document.AuthorInfo.init("authorInfoContainer");});
