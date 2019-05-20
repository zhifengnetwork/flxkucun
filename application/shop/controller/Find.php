<?php
namespace app\shop\controller;

use think\Db;
class Find extends MobileBase{
    // 跳转到发现页面
    public function find(){
    	$id = input('video_id');
    	$videos = Db::table('tp_video')->select();
        return $this -> fetch('',$videos);
    } 

}