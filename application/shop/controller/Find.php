<?php
namespace app\shop\controller;

use think\Db;
class Find extends MobileBase{
    // 跳转到发现页面
    public function find(){
        $user_id = session('user.user_id');
    	//平台商品视频
        // $goodsVideoList = Db::table('tp_goods')->where(['is_on_sale'=>1,'is_video'=>1,'is_recommend'=>1])->field('goods_name,goods_id,original_img,video')->order("sort DESC")->limit(4)->select();
        $goodsVideoList = Db::table('tp_video')->alias('v')->join('tp_goods g','g.goods_id=v.goods_id','LEFT')->where(['v.is_on_sale'=>1,'v.user_id'=>0])->field('g.goods_name,v.goods_id,g.original_img,v.video_url,v.video_img,v.title,v.id')->order("v.is_recommend DESC,v.sort DESC")->group('v.id desc')->limit(4)->select();
        // $addtime=strtotime(date("Y-m-d"));
        // foreach ($goodsVideoList as $key => $value){
        //     $res = Db::name('video_favor')->where(['addtime'=>$addtime,'user_id'=>$user_id,'type'=>1,'video_id'=>$value['goods_id']])->find();
        //       if($res){
        //           $goodsVideoList[$key]["favor"]="red";
        //       }else{
        //           $goodsVideoList[$key]["favor"]="";
        //       }
        // }
        $this->assign('goodsVideo', $goodsVideoList);
 
    	//平台推荐用户视频
    	$userVideoList = Db::table('tp_video')->where(['status'=>1,'is_on_sale'=>1,'user_id'=>array('gt',0)])->field('id,title,nickname,video_img,user_id')->order("is_recommend desc,sort DESC")->limit(8)->select();
    	foreach ($userVideoList as $key => $value){
    	    $userImg=Db::table('tp_users')->where(['user_id'=>$value['user_id']])->field('head_pic')->find();
            $res = Db::name('video_favor')->where(['addtime'=>$addtime,'user_id'=>$user_id,'type'=>2,'video_id'=>$value['id']])->find();
            if($res){
                $userVideoList[$key]["favor"]="red";
            }else{
                $userVideoList[$key]["favor"]="";
            }
            $userVideoList[$key]['head_pic']=$userImg['head_pic'];
        }
        $this->assign('userVideo',$userVideoList);
        //视频封面
        $dynamic = Db::name('config')->where('inc_type','dynamic')->column('name,value');
        $this->assign('dynamic',$dynamic);
        return $this -> fetch();
    }

    //商品视频查找更多

    public function find_more(){
        $user_id = session('user.user_id');
        //平台商品视频
        // $goodsVideoList = Db::table('tp_goods')->where(['is_on_sale'=>1,'is_video'=>1,'is_recommend'=>1])->field('goods_name,goods_id,original_img,video')->order("sort DESC")->limit(10)->select();

        $goodsVideoList = Db::table('tp_video')->alias('v')->join('tp_goods g','g.goods_id=v.goods_id','LEFT')->where(['v.is_on_sale'=>1,'v.user_id'=>0])->field('g.goods_name,v.goods_id,g.original_img,v.video_url,v.video_img,v.title,v.id')->order("v.is_recommend desc,v.sort DESC")->group('v.id desc')->limit(10)->select();
        $addtime=strtotime(date("Y-m-d"));
        // foreach ($goodsVideoList as $key => $value){
        //     $videoImg=explode('.',$value['video']);
        //     $goodsVideoList[$key]["original_img"]=$videoImg[0].".jpg";
        //     $res = Db::name('video_favor')->where(['addtime'=>$addtime,'user_id'=>$user_id,'type'=>1,'video_id'=>$value['goods_id']])->find();
        //     if($res){
        //         $goodsVideoList[$key]["favor"]="red";
        //     }else{
        //         $goodsVideoList[$key]["favor"]="";
        //     }
        // }
        $this->assign('goodsVideo', $goodsVideoList);
        return $this -> fetch();
    }
   //ajax加载商品视频
    public function ajaxGoodsList(){
        $user_id = session('user.user_id');
        $page =  I('get.page');
        $pageSize=10;
        $pageOffset=($page-1)*$pageSize;
        //平台商品视频
        // $goodsVideoList = Db::table('tp_goods')->where(['is_on_sale'=>1,'is_video'=>1])->field('goods_name,goods_id,original_img,video')->order("is_recommend desc,sort DESC")->limit($pageOffset,$pageSize)->select();
        $goodsVideoList = Db::table('tp_video')->alias('v')->join('tp_goods g','g.goods_id=v.goods_id','LEFT')->where(['v.is_on_sale'=>1,'v.user_id'=>0])->field('g.goods_name,v.goods_id,g.original_img,v.video_url,v.video_img,v.title,v.id')->order("v.is_recommend desc,v.sort DESC")->group('v.id desc')->limit(10)->select();
        $addtime=strtotime(date("Y-m-d"));
        // foreach ($goodsVideoList as $key => $value){
        //     $videoImg=explode('.',$value['video']);
        //     $goodsVideoList[$key]["original_img"]=$videoImg[0].".jpg";
        //     $res = Db::name('video_favor')->where(['addtime'=>$addtime,'user_id'=>$user_id,'type'=>1,'video_id'=>$value['goods_id']])->find();
        //     if($res){
        //         $goodsVideoList[$key]["favor"]="red";
        //     }else{
        //         $goodsVideoList[$key]["favor"]="";
        //     }
        // }
        $data='';
        foreach ($goodsVideoList as $value){
            $data.="<li class=\"item\">
			<a href=\"{:U('shop/video/video_play',array('id'=>'".$value['id']."'))}\">
				<div class=\"img_wrap\">
					<img src=".$value["video_url"]." height=\"246.41\" />				
								<span class=\"playBtn_sm\">
									<img src=\"__STATIC__/images/home_zp/playBtn-sm.png\" />
								</span>
							</div>
							<p>".$value["title"]." <i class=\"video_favor ".$value["favor"]."\"></i></p>
						</a>
			</li>";
        }
        echo $data;
    }
  //ajax加载用户视频
    public function ajaxUserVideo(){
        $user_id = session('user.user_id');
        $page =  I('get.page');
        $pageSize=8;
        $pageOffset=($page-1)*$pageSize;
        //平台推荐用户视频
        $userVideoList = Db::table('tp_video')->where(['status'=>1,'is_on_sale'=>1])->field('id,title,nickname,video_img,user_id')->order("is_recommend desc,sort DESC")->limit($pageOffset,$pageSize)->select();
        $addtime=strtotime(date("Y-m-d"));
        foreach ($userVideoList as $key => $value){
            $userImg=Db::table('tp_users')->where(['user_id'=>$value['user_id']])->field('head_pic')->find();
            $userVideoList[$key]['head_pic']=$userImg['head_pic'];
            if(empty($value['video_img'])){
                $userVideoList[$key]['video_img']="/template/shop/rainbow/static/images/home_zp/00product-img01.png";
            }
            $res = Db::name('video_favor')->where(['addtime'=>$addtime,'user_id'=>$user_id,'type'=>2,'video_id'=>$value['id']])->find();
            if($res){
                $userVideoList[$key]["favor"]="red";
            }else{
                $userVideoList[$key]["favor"]="";
            }
        }
        $data='';
        foreach ( $userVideoList as $value){

          $data.="<li class=\"item\">
			<a href=\"{:U('shop/video/user_video_play',array('id'=>'".$value['id']."'))}\">
				<div class=\"img_wrap\">
						<img src=".$value["video_img"]." height=\"246.41\" />
							<span class=\"playBtn_sm\">
								<img src=\"__STATIC__/images/home_zp/playBtn-sm.png\" />
							</span>
						</div>
						<p>".$value["title"]."<i class=\"video_favor ".$value["favor"]."\"></i></p>
						<div class=\"userInfo\">
						<span class=\"acatav\"><img src=".$value["head_pic"]."></span>
							<span class=\"name\">".$value["nickname"]."</span>
						</div>
						</a>
					</li>" ;
        }
        echo $data;
    }

}