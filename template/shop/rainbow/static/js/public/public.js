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

