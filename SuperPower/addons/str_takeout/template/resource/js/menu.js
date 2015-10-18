
$(function(){
	$('#icoMenu').click(function(){
		$('#sideNav').toggle();
	});

	$('#menuWrap .add').each(function(){
		$(this).amount(0, $.amountCb());
		for(var i = 0, num = parseInt($(this).data('num')); i < num; i++){
			$(this).click();
		}
	});
	
	$(document).on('click', '#menuWrap .price_wrap', function(e){
		e.stopPropagation();
	});


	$('#detailBtn').click(function(){
		if(!$(this).hasClass('detail')){
			dialogTarget.find('.add ').click();
		}
	});
});
