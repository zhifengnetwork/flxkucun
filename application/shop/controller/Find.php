<?php
namespace app\shop\controller;

use think\Db;
class Find extends MobileBase{
    // 跳转到发现页面
    public function find(){

    	//平台商品视频
        $goodsVideoList = Db::table('tp_goods')->where(['is_on_sale'=>1,'is_video'=>1,'is_recommend'=>1])->field('goods_name,goods_id,original_img')->order("sort DESC")->limit(4)->select();
        $this->assign('userVideoList', $goodsVideoList);
    	//平台推荐用户视频
    	$userVideoList = Db::table('tp_video')->where(['status'=>1,'is_on_sale'=>1,'is_recommend'=>1])->order("sort DESC")->limit(4)->select();
    	$this->assign('userVideoList',$userVideoList);
        return $this -> fetch();
    } 

}