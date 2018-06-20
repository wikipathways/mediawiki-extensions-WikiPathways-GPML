var pvjsInput = mw.config.get( "pvjsString" );
pvjsInput.onReady = function() {};
window.addEventListener('load', function() {
	pvjs.Pvjs(".Container", pvjsInput);
});
