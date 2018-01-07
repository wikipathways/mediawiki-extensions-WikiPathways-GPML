if (window.hasOwnProperty("XrefPanel")) {
	XrefPanel.show = function(elm, id, datasource, species, symbol) {
		jqelm = $(elm);
		if(XrefPanel.currentTriggerDialog) {
			XrefPanel.currentTriggerDialog.dialog("close");
			XrefPanel.currentTriggerDialog.dialog("destroy");
		}
		jqcontent = XrefPanel.create(id, datasource, species, symbol);
		var x = jqelm.offset().left - $(window).scrollLeft();
		var y = jqelm.offset().top - $(window).scrollTop();
		jqdialog = jqcontent.dialog({
			position: [x,y]
		});
		XrefPanel.currentTriggerDialog = jqdialog;
	}
}

var pvjsInput = $jsonData;
pvjsInput.onReady = function() {};
window.addEventListener('load', function() {
	pvjs.Pvjs(".Container", pvjsInput);
});
