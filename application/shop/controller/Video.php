<?php

namespace app\shop\controller;
use think\Db;
class Video extends MobileBase{
    // 跳转到视频播放页
    public function video_play(){

        return $this->fetch();
    }

    // 添加视频
    public function addvideo(){
        if(request()->isPost()){
            $user_id = input('user_id');
            $file = request()->file('video');
            $title = input('title');
            $describe = input('describe');
            $time = time();
        
            if(!empty($user_id)){

                if(empty($file)){
                    $this->error('请选择您要上传的视频');
                };
                if(empty($title)){
                    $this->error('视频标题不能为空');
                };
                if(empty($describe)){
                    $this->error('请输入您想说的话');
                };
            };
            
            $video = $this -> upload();

            if($video){     
               $video = $video;
            };

            $data = [
                'user_id' => $user_id,
                'title' => $title,
                'describe' => $describe,
                'video_url' => $video,
                'update_time' => $time,
            ];

           $result = Db::name('video')->insert($data);
           if($result){
             $this->success('视频上传成功',url("/shop/video/video_list"));
           }else{
            $this->erro('视频上传视频，请重试');
           }
        };
       
        return $this->fetch();
    }

    //上传公共方法
    public function upload(){
        $uploadDir = './public/uploads/';
        $path = '';
        $file = request()->file('video');
        $info = $file->validate(['size' =>1024*1024*5,'ext'=>'avi,mp4,flw'])->move($uploadDir);
        if($info){
            $path = $info ->getSaveName();
        }else{
            echo $file->getError();
        }
        return $uploadDir.$path;
    }



    // 跳转到视频列表
    public function video_list(){
        $user_id = input('user_id');
        $video_data = Db::table('tp_video')->where('user_id',$user_id)->select();
        return $this->fetch('',['video' =>$video_data]);
    }

}