/*
 * debouncedresize: special jQuery event that happens once after a window resize
 *
 * latest version and complete README available on Github:
 * https://github.com/louisremi/jquery-smartresize
 *
 * Copyright 2012 @louis_remi
 * Licensed under the MIT license.
 *
 * This saved you an hour of work? 
 * Send me music http://www.amazon.co.uk/wishlist/HNTU0468LQON
 */(function(e){var t,n=e.event,r=n.special.debouncedresize={setup:function(){e(this).on("resize",r.handler)},teardown:function(){e(this).off("resize",r.handler)},handler:function(e,i){var s=this,o=arguments,u=function(){e.type="debouncedresize";n.dispatch.apply(s,o)};t&&clearTimeout(t);i?u():t=setTimeout(u,r.threshold)},threshold:150}})(jQuery);
 
(function($){

	$('.pt-flickrembed button.loader').click(function(){
		button = $(this);
		$('div.images',button.parent()).hide();
		button.hide(function(){
			$('img[data-src]',button.parent()).each(function(){
				$(this).attr('src',$(this).attr('data-src'));
			});
			$('div.images',button.parent()).slideDown();
			gallery_shunt();
			//$(window).on('debouncedresize',pt_flickrembed_shunt);
		});
	});
	
	$(document).ready(function(){
		$('.pt-flickrembed button.loader').trigger('click');
	});
	
	$(window).load(gallery_shunt);

	
	function gallery_shunt(){
		var $container = $('.module.gallery');
		$container.imagesLoaded( function() {
			$container.masonry({
				itemSelector: 'figure'
			}).addClass('loaded');
		});
	}
	gallery_shunt();

})(jQuery);