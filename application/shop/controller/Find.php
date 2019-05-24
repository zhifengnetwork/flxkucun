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
    	$userVideoList = Db::table('tp_video')->where(['status'=>1,'is_on_sale'=>1,'is_recommend'=>1])->field('id,title,nickname,video_img,user_id')->order("sort DESC")->limit(8)->select();
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
        $goodsVideoList = Db::table('tp_goods')->where(['is_on_sale'=>1,'is_video'=>1,'is_recommend'=>1])->field('goods_name,goods_id,original_img,video')->order("sort DESC")->limit(10)->select();
        foreach ($goodsVideoList as $key => $value){
            $videoImg=explode('.',$value['video']);
            $goodsVideoList[$key]["original_img"]=$videoImg[0].".jpg";
        }
        $this->assign('goodsVideo', $goodsVideoList);
        return $this -> fetch();
    }
   //ajax加载商品视频
    public function ajaxGoodsList(){

        $page =  I('get.page');
        $pageSize=10;
        $pageOffset=($page-1)*$pageSize;
        //平台商品视频
        $goodsVideoList = Db::table('tp_goods')->where(['is_on_sale'=>1,'is_video'=>1,'is_recommend'=>1])->field('goods_name,goods_id,original_img,video')->order("sort DESC")->limit($pageOffset,$pageSize)->select();
        foreach ($goodsVideoList as $key => $value){
            $videoImg=explode('.',$value['video']);
            $goodsVideoList[$key]["original_img"]=$videoImg[0].".jpg";
        }
        $data='';
        foreach ($goodsVideoList as $value){
            $data.="<li class=\"item\">
			<a href=\"{:U('shop/video/video_play',array('id'=>'".$value['goods_id']."'))}\">
				<div class=\"img_wrap\">
					<img src=".$value["original_img"]." height=\"246.41\" />				
								<span class=\"playBtn_sm\">
									<img src=\"__STATIC__/images/home_zp/playBtn-sm.png\" />
								</span>
							</div>
							<p>".$value["goods_name"]."</p>
						</a>
			</li>";
        }
        echo $data;
    }
  //ajax加载用户视频
    public function ajaxUserVideo(){

        $page =  I('get.page');
        $pageSize=8;
        $pageOffset=($page-1)*$pageSize;
        //平台推荐用户视频
        $userVideoList = Db::table('tp_video')->where(['status'=>1,'is_on_sale'=>1,'is_recommend'=>1])->field('id,title,nickname,video_img,user_id')->order("sort DESC")->limit($pageOffset,$pageSize)->select();
        foreach ($userVideoList as $key => $value){
            $userImg=Db::table('tp_users')->where(['user_id'=>$value['user_id']])->field('head_pic')->find();
            $userVideoList[$key]['head_pic']=$userImg['head_pic'];
            if(empty($value['video_img'])){
                $userVideoList[$key]['video_img']="/template/shop/rainbow/static/images/home_zp/00product-img01.png";
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
						<p>".$value["title"]."</p>
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