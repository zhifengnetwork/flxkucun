$(function(){

    /*
    * 秒杀模块倒计时
    */
    function downTime(endTime){
        //1.1获取当前时间戳
        var currentTime = new Date().getTime();
        //1.2剩下时间戳
        var allTime = endTime - currentTime;
        //1.3把剩下的时间戳转为秒,取整
        var allSecond = parseInt(allTime / 1000);

        // 1.4判断时间是否截止
        if(allSecond <= 0){
            $(".downTime").html("活动已结束");
        }else{
            //1.4.1转化
            var h = checkTime(parseInt(allSecond / 3600 % 24));
            var m = checkTime(parseInt(allSecond / 60 % 60));
            var s = checkTime(parseInt(allSecond % 60));
            //1.4.2注入时间
            $("#downTime-h").html(h);
            $("#downTime-m").html(m);
            $("#downTime-s").html(s);
        }

   
    }

    function setendTime(){
        //自定义结束时间戳
        var endTime = new Date('2019/04/01 23:59:00').getTime();
        var text = downTime(endTime);
    }
     // 倒计时补零操作
    function checkTime(i){
        return i >= 10 ? i : "0" + i ;
    }

    setInterval(setendTime,1000);

    /*
    * 点击搜索跳转页面
    */
    $(".search").click(function () {
        location.href = '/shop/Goods/ajaxSearch'
    })

})