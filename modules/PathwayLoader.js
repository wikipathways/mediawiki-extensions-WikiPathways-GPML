var pvjsInput = JSON.parse( mw.config.get( "pvjsString" ) );
pvjsInput.onReady = function() {};
window.addEventListener('load', function() {
	pvjs.Pvjs(".Container", pvjsInput);
});
