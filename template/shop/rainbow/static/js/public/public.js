// 返回
function returnFun(){
	/*返回上一页*/
	if($('.header .backBtn').attr('data-num') == 1 || $('.header .backBtn').attr('data-num') == undefined ){
		window.history.back();
		console.log("返回上一页");
	}else {
		console.log("规定路径跳转!", $('.header .backBtn').attr('data-num'));
		/*页面跳转*/
		window.location.href = $('.header .backBtn').attr('data-num');
	}
	return false;
}

/*页面跳转*/
function pageJump(_url){
	/*页面跳转*/
    window.location.href = _url;
}


/**
 * 详情页收藏
 */
$(".action-bar .fav").click(function(){
	var that = $(this);
	if(that.hasClass("faved")){
			// 已收藏 -> 取消收藏
		$(this).removeClass("faved");  
		$(".fav-tips").show().html("<em class='fav-animation'>取消收藏</em>");        
	}else{
		// 未收藏 -> 收藏
		$(this).addClass("faved");
		$(".fav-tips").show().html("<em class='fav-animation'>收藏成功</em>");    
	}
});

/**
 * 详情-规格选择
 */
function skuShow(){
	$(".mask").show();
	$(".sku-content").animate({'bottom':'0'});
	stopSliding(".wrapper");//禁止body滑动
}

function skuHide(){
	$(".mask").hide();
	$(".sku-content").animate({'bottom':'-100%'});
	resumingSlip(".wrapper");//恢复body滑动
}

/**
 * 取消冒泡事件
 */
function cancelTap(event){
	var e = window.event || event;
	if(e.stopPropagation){
		e.stopPropagation();
	}else{
		e.cancelBubble = true;
	}
}

/**
 * 
 * 禁止body滑动
 */
function stopSliding(name){
	var $name = $(name);
	var scrollTop = document.body.scrollTop;//设置背景元素的位置
	$name.attr('data-top',scrollTop);
	$name.css("position","fixed");//设置背景元素的position属性为‘fixed'
	$name.css("top","-" + scrollTop + "px");
}

/**
 * 
 * 恢复body滑动
 */
function resumingSlip(name){
	var $name = $(name);
	var scrollTop = $name.attr('data-top');//设置背景元素的位置
	$name.css("top","0px");//恢复背景元素的初始位置
	$name.css("position","static");//恢复背景元素的position属性
	$(document).scrollTop(scrollTop);//scrollTop,设置滚动条的位置
}
