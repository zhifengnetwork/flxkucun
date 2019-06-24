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



$(function(){
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

})

