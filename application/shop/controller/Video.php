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


     // 跳转到商品视频播放页
    public function video_play(){
        $goods_id = input('id');
        $goodsInfo = Db::table('tp_goods')->where('goods_id',$goods_id)->find();
        $videoImg=explode('.',$goodsInfo['video']);
        $goodsInfo["video_img"]=$videoImg[0].".jpg";
        return $this->fetch('',[
            'goodsInfo'=>$goodsInfo,
        ]);

    }

    //跳转到用户视频播放页
    public function user_video_play(){
        $video_id = input('id');
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
            $nickname = Db::table('tp_users')->where('user_id',$user_id)->value('nickname');
            $file = request()->file('video');
            $title = input('title');
            $content = input('content');
            $category = input('category');
            $time = time();
            if(!empty($user_id)){

                if(empty($file)){
                    $this->error('请选择您要上传的视频');
                };
                if(empty($title)){
                    $this->error('视频标题不能为空');
                };
                if(empty($content)){
                    $this->error('请输入您想说的话');
                };
            };
            
            $video = $this -> upload();
            $this->setVideoImg($video);
            $video_img='';
            if($video){     
               $video = $video;
               $videoImg=explode('.',$video);
               if(!empty($videoImg)){
                   $video_img=$videoImg[0].".jpg";
               }
            };

            $data = [
                'user_id' => $user_id,
                'title' => $title,
                'content' => $content,
                'video_url' => $video,
                'video_img' => $video_img,
                'update_time' => $time,
                'category' =>$category,
                'nickname' =>$nickname
            ];

           $result = Db::name('video')->insert($data);
           if($result){
             //$this->ajaxReturn(['status'=>1,'msg'=>'操作成功']);
             $this->success('视频上传成功',url("/shop/video/video_list"));
           }else{
            $this->erro('视频上传失败，请重试');
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
                    @unlink('.'.$v['video_img']);
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
       if(request()->isPost()){
            $videoId =  I('post.video_id');
            $title = input('title');
            $video_url = input('video_url');
            $content = input('content');
            $time = time();
            $file = request()->file('video');
            if(!empty($file)){
                $video = $this -> upload();
                $this->setVideoImg($video);
                $video_path = $video;
                $videoImg=explode('.',$video_path);
                if(!empty($videoImg)){
                    $video_img=$videoImg[0].".jpg";
                }
            }else{
                $video_path = $video_url;
                $video_img =input('video_img');
            };
            $data = [
                'title' => $title,
                'content' => $content,
                'video_url' =>$video_path,
                'video_img' =>$video_img,
                'status' => 0,
                'reason' => null,
                'update_time' => $time,
            ];
            $result = M('video')->where(['id'=>$videoId])->update($data);
            if($result){
                @unlink('.'.$video_url);
                @unlink('.'.input('video_img'));
                $this->success('修改成功',url("/shop/video/video_list"));
            }else{
              $this->error('修改失败，请重试!');
            }

        };
        $this->assign('info', $info);
        return $this->fetch();
    }

    //截取视频封面
    public function setVideoImg($file){
        $pre = dirname(dirname(dirname(__DIR__)));
        if(IS_WIN) {
            $ffmpeg = $pre . '/public/plugins/ffmpeg/bin/ffmpeg.exe';
            if(!file_exists($ffmpeg))	return $ffmpeg.' /no ffmpeg';
        }else{
            $ffmpeg = '/monchickey/ffmpeg/bin/ffmpeg';

            if(!file_exists($ffmpeg)){
                //$ffmpeg = '/usr/bin/ffmpeg';
                $ffmpeg = 'ffmpeg';
            }
        }
        //if(!file_exists($ffmpeg))	return $ffmpeg.' /no ffmpeg';
        $arr = explode('.', $file);
        $jpg = $pre . $arr[0] . '.jpg';
        $path = $pre . $file;
        if(file_exists($path)){
            // exec system
            exec("$ffmpeg -i $path -ss 2 -vframes 1 $jpg",$re);
            return $re;
        }else{
            return $path.' /no path';
        }
    }

}