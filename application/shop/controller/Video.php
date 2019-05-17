<?php

namespace app\shop\controller;
use app\common\model\Users;
use think\Db;

class Video extends MobileBase{
   
    public $user_id = 0;
    public $user = array();

    /*
    * 初始化操作
    */
    public function _initialize()
    {
        parent::_initialize();
        if (!session('user')) {
            header('location:'.U('Mobile/User/login'));
            exit;
        }

        $user = session('user');
        session('user', $user);  //覆盖session 中的 user
        $this->user = $user;
        $this->user_id = $user['user_id'];
    }


     // 跳转到视频播放页
    public function video_play(){
        $video_id = input('video_id');
        $video = Db::table('tp_video')->where('id',$video_id)->find();
        $pople_id = Db::table('tp_video')->where('id',$video_id)->value('user_id');
        $pople = Db::table('tp_users')->where('user_id',$pople_id)->value('nickname');
        return $this->fetch('',[
            'video'=>$video,
            'pople' =>$pople
        ]);

    }
    // 添加视频
    public function addvideo(){
        if(request()->isPost()){
            $user_id = session('user.user_id');
            $this->assign('user_id', $user_id);
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
        $uploadDir = './public/uploads/video/';
        $path = '';
        $file = request()->file('video');
        $info = $file->validate(['size' =>1024*1024*5,'ext'=>'avi,mp4,flw'])->move($uploadDir);
        if($info){
            $path = str_replace("\\","/",$info->getSaveName());
        }else{
            echo $file->getError();
        }
        return ltrim($uploadDir.$path,'.');
    }

    // 跳转到视频列表
    public function video_list(){
        
        $user_id = session('user.user_id');
        $this->assign('user_id', $user_id);
        $video_data = Db::table('tp_video')->where('user_id',$user_id)->select();
        return $this->fetch('',['video' =>$video_data]);
    }

    //视频删除
    public function video_del(){ 
        $video_id = request()->post('video_id/a'); 
        if(!empty($video_id)){
            $video_info = Db::table('tp_video')->where('id','in',$video_id)->select(); 
            $result = Db::table('tp_video')->delete($video_id);
            if($result){
                foreach ($video_info as $v){
                    @unlink('.'.$v['video_url']); 
                }
                $this->success('删除成功！');
            }else{
                $this->error('删除失败，请重试');
            }
        }else{
            $this->error('请选择您要删除的视频!');
        }
    }

    //视频修改
    public function video_update(){
        $id = input('video_id');
        if(empty($id)){
            return $this->error('请选择您要更新的视频！');
        };

        $video = M("video");
        $info = $video->where(['id'=>$id])->find();
        $olde_path = Db::table('tp_video')->where(['id'=>$id])->value('video_url'); 
       if(request()->isPost()){ 
            $file = request()->file('video');
            $title = input('title');
            $describe = input('describe');
            $time = time();
            $data = [
                'video_url' => $file,
                'title' => $title,
                'describe' => $describe,
                'update_time' => $time,
            ];

            $data2 = [
                'title' => $title,
                'describe' => $describe,
                'update_time' => $time,
            ];

            if(!empty($file)){
                
                $file = $this->upload();
                if($file){
                    
                   $result = $video->where(['id'=>$id])->save($data);
                   if($result){
                        $this->success('修改成功',url("/shop/video/video_list"));
                       
                        // @unlink('.'.$olde_path);
                   }else{
                     $this->error('修改失败，请重试!');
                   }
                }  
            }else{
                $result = $video->where(['id'=>$id])->save($data2);
                if($result){
                     $this->success('修改成功',url("/shop/video/video_list"));
                }else{
                  $this->error('修改失败，请重试!');
                }
            }
            
        };
        $this->assign('info', $info);
        return $this->fetch();
    }

}