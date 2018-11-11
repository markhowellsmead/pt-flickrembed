(function($){
	$(document).ready(function(){
		$('.pt-flickrembed').removeWhitespace().collagePlus({'targetHeight':320});
	});
	$(window).resize(function(){
		$('.pt-flickrembed').removeWhitespace().collagePlus({'targetHeight':320});
	});
})(jQuery);