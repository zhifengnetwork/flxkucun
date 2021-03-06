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
        $user_id = session('user.user_id');
        // $goods_id = input('id');
        // $goodsInfo = Db::table('tp_goods')->where('goods_id',$goods_id)->find();
        // $videoImg=explode('.',$goodsInfo['video']);
        // $goodsInfo["video_img"]=$videoImg[0].".jpg";
        // $addtime=strtotime(date("Y-m-d"));
        // $res = Db::name('video_favor')->where(['addtime'=>$addtime,'user_id'=>$user_id,'video_id'=>$goods_id,'type'=>1])->find();
        
        $id = input('id');
        $video = Db::name('video')->where(array('id'=>$id))->find();
        $res = Db::name('video_favor')->where(array('video_id'=>$id))->find();
        //总点赞数
        $count = Db::name('video_favor')->where(['video_id'=>$video['id']])->count();
        if($video['goods_id']){
            $goodsInfo = Db::name('goods')->where('goods_id',$video['goods_id'])->find();
            $level = session('user.level');
            if($level){
                $goodsInfo['shop_price'] = Db::name('goods_level_price')->where('goods_id',$video['goods_id'])->where('level',$level)->value('price');
                $goodsInfo['shop_price'] = $goodsInfo['shop_price']?$goodsInfo['shop_price']:$goodsInfo['market_price'];
            }
        }
        if($res){
            $favor=true;
        }else{
            $favor=false;
        }
        return $this->fetch('',[
            'goodsInfo'=>$goodsInfo,
            'favor'=>$favor,
            'video'=>$video,
            'count' => $count,
        ]);

    }

    //跳转到用户视频播放页
    public function user_video_play(){
        $video_id = input('id');
        $user_id = session('user.user_id');
        $video = Db::table('tp_video')->where('id',$video_id)->find();
        $pople_id = Db::table('tp_video')->where('id',$video_id)->value('user_id');
        $pople = Db::table('tp_users')->where('user_id',$pople_id)->value('nickname');
        $addtime=strtotime(date("Y-m-d"));
        $res = Db::name('video_favor')->where(['addtime'=>$addtime,'user_id'=>$user_id,'video_id'=>$video_id,'type'=>2])->find();
        if($res){
           $favor=true;
        }else{
            $favor=false;
        }
        return $this->fetch('',[
            'video'=>$video,
            'pople' =>$pople,
            'favor'=>$favor
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
            $this->error('视频上传失败，请重试');
           }
        };
       
        return $this->fetch();
    }

    //添加视频
    public function add_video()
    {
        if(request()->isPost()){
            $user_id = session('user.user_id');
            $nickname = Db::table('tp_users')->where('user_id',$user_id)->value('nickname');
            $title = input('title');
            $content = input('content');
            $category = input('category');
            $video_url = input('video_url');
            $video_img = input('video_img');
            $time = time();
            if(!empty($user_id)){
                if(empty($video_url)){
                    $this->error('请选择您要上传的视频');
                };
                if(empty($title)){
                    $this->error('视频标题不能为空');
                };
                if(empty($content)){
                    $this->error('请输入您想说的话');
                };
            };
            $data = [
                'user_id' => $user_id,
                'title' => $title,
                'content' => $content,
                'video_url' => $video_url,
                'video_img' => $video_img,
                'update_time' => $time,
                'category' =>$category,
                'nickname' =>$nickname
            ];
            $id = input('post.id');
            if($id){
                $result = Db::name('video')->where('id',$id)->update($data);
            }else{
                $result = Db::name('video')->insert($data);
            }
            if($result){
                $this->success('提交成功',url("/shop/video/video_list"));
            }else{
                $this->error('提交失败，请重试');
            }
        }
    }

    //上传公共方法
    public function upload(){
        $uploadDir = './public/uploads/video/';
        $path = '';
        $file = request()->file('video');
        $info = $file->validate(['size' =>1024*1024*10,'ext'=>'avi,mp4,flw,mov'])->move($uploadDir);
        if($info){
            $path = str_replace("\\","/",$info->getSaveName());
        }else{
            $this->error($file->getError());
        }
        return ltrim($uploadDir.$path,'.');
    }

    //ajax上传视频
    public function ajaxUpload()
    {
        $uploadDir = './public/uploads/video/';
        $path = '';
        $file = request()->file('video');
        $info = $file->validate(['size' =>1024*1024*10,'ext'=>'avi,mp4,flw,mov'])->move($uploadDir);
        if($info){
            $path = str_replace("\\","/",$info->getSaveName());
        }else{
            $result['msg'] = $file->getError();
            $result['status'] = 2;
            return json($result);
        }
        
        //获取封面图
        $video_img='';
        $this->setVideoImg($video);
        if($video){     
            $video = $video;
            $videoImg=explode('.',$video);
            if(!empty($videoImg)){
                $video_img=$videoImg[0].".jpg";
            }
        };
        $result['video_url'] = ltrim($uploadDir.$path,'.');
        $result['video_img'] = $video_img;
        $result['status'] = 1;
        return json($result);
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

    //视频点赞
    public function video_favor(){

        $type = input('type');
        $video_id = input('video_id');
        $user_id = session('user.user_id');
        if($user_id){
            if($type&&$video_id){
                $addtime=strtotime(date("Y-m-d"));
                $res = Db::name('video_favor')->where(['addtime'=>$addtime,'user_id'=>$user_id,'type'=>$type,'video_id'=>$video_id])->find();
                if($res){
                    $result=array('status'=>-3,'msg'=>'该视频已经点赞过','result'=>array());
                    $this->ajaxReturn($result);
                }else{
                    $res = Db::name('video_favor')->where(['addtime'=>$addtime,'user_id'=>$user_id])->find();
                    if($res){
                        $result=array('status'=>-3,'msg'=>'每天只能点赞一次','result'=>array());
                        $this->ajaxReturn($result);
                    }else{

                        $data=['type'=>$type,'video_id'=>$video_id,'user_id'=>$user_id,'addtime'=>$addtime];
                        $res =Db::name('video_favor')->insert($data);
                        if($res){
                            $result=array('status'=>1,'msg'=>'点赞成功','result'=>array());
                            $this->ajaxReturn($result);
                        }else{
                            $result=array('status'=>-3,'msg'=>'点赞失败，请重新操作','result'=>array());
                            $this->ajaxReturn($result);
                        }
                    }
                }
            }else{
                $result=array('status'=>-3,'msg'=>'点赞失败，请重新操作','result'=>array());
                $this->ajaxReturn($result);
            }
        }else{
            $result=array('status'=>-3,'msg'=>'请登录后操作','result'=>array());
            $this->ajaxReturn($result);
        }


    }

    //图片上传
    public function upload_image(){
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('image');
        // 移动到框架应用根目录/public/uploads/ 目录下
        if($file){
            $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'video');
            if($info){
                $result['url'] = '/public/uploads/video/'.$info->getSaveName();
                $result['status'] = 1;
                return json($result);
            }else{
                // 上传失败获取错误信息
                $result['msg'] = $file->getError();
                $result['status'] = 2;
                return json($result);
            }
        }
    }

}