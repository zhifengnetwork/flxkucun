<?php

namespace app\shop\controller;

class Video extends MobileBase{
    // 跳转到视频播放页
    public function video_play(){
        return $this->fetch();
    }

    // 跳转到添加视频
    public function addvideo(){
        return $this->fetch();
    }

    // 跳转到视频列表
    public function video_list(){
        return $this->fetch();
    }

}