<?php
namespace app\shop\controller;

use think\Db;
class Find extends MobileBase{
    // 跳转到发现页面
    public function find(){

    	//平台商品视频
        $goodsVideoList = Db::table('tp_goods')->where(['is_on_sale'=>1,'is_video'=>1,'is_recommend'=>1])->field('goods_name,goods_id,original_img,video')->order("sort DESC")->limit(4)->select();
        foreach ($goodsVideoList as $key => $value){
                 $videoImg=explode('.',$value['video']);
                 $goodsVideoList[$key]["original_img"]=$videoImg[0].".jpg";
        }
        $this->assign('goodsVideo', $goodsVideoList);

    	//平台推荐用户视频
    	$userVideoList = Db::table('tp_video')->where(['status'=>1,'is_on_sale'=>1,'is_recommend'=>1])->field('id,title,nickname,video_img,user_id')->order("sort DESC")->limit(4)->select();
    	foreach ($userVideoList as $key => $value){
    	    $userImg=Db::table('tp_users')->where(['user_id'=>$value['user_id']])->field('head_pic')->find();
            $userVideoList[$key]['head_pic']=$userImg['head_pic'];
        }
    	$this->assign('userVideo',$userVideoList);
        return $this -> fetch();
    }

    //商品视频查找更多

    public function find_more(){

        //平台商品视频
        $goodsVideoList = Db::table('tp_goods')->where(['is_on_sale'=>1,'is_video'=>1,'is_recommend'=>1])->field('goods_name,goods_id,original_img,video')->order("sort DESC")->limit(20)->select();
        foreach ($goodsVideoList as $key => $value){
            $videoImg=explode('.',$value['video']);
            $goodsVideoList[$key]["original_img"]=$videoImg[0].".jpg";
        }
        $this->assign('goodsVideo', $goodsVideoList);
        return $this -> fetch();
    }


}