<?php
/**
 * 秒杀
 */
// namespace app\mobile\controller;
namespace app\shop\controller;

use think\Db;
use app\common\model\WxNews;
 
class Seckill extends MobileBase
{
    /**
     * 秒杀 
     */
    public function index(){
        $flash_salelist = M('flash_sale')->alias('FS')->join('goods G','FS.goods_id=G.goods_id','left')->field('FS.id,FS.title,FS.goods_id,FS.item_id,FS.price,FS.goods_num,FS.buy_num,FS.order_num,G.market_price,FS.goods_name,G.original_img,FS.start_time,FS.end_time')->where(['FS.end_time'=>['gt',time()],'FS.is_end'=>0,'FS.start_time'=>['lt',time()]])->order('FS.start_time desc')->select();
        $time = time(); 
        foreach($flash_salelist as $k=>$v){
            $flash_salelist[$k]['rate'] = !$v['order_num'] ? 100 : 100-intval(($v['order_num']/$v['goods_num'])*100);
        }
        //查时间段
        $time_arr = M('flash_sale')->alias('FS')->join('goods G','FS.goods_id=G.goods_id','left')->field('FS.start_time,FS.end_time')->where(['FS.end_time'=>['gt',time()],'FS.is_end'=>0])->order('FS.start_time asc')->group('FS.start_time')->limit(5)->select();
        foreach($time_arr as $key=>$val){
            $time_arr[$key]['start_time_msg'] = date('H:i',$val['start_time']);
            if($time < $val['start_time']){
                $time_arr[$key]['time_msg'] = '即将开抢';
            }else if($time > $val['start_time'] && $time < $val['end_time']){
                $time_arr[$key]['time_msg'] = '抢购中';
            }else{
                $time_arr[$key]['time_msg'] = '抢购结束';
            }
        } 

        foreach($flash_salelist as $k=>$v){
            $flash_salelist[$k]['start'] = (($v['start_time'] < time()) && ($v['end_time'] > time())) ? 1 : 0;
        }

        $this->assign('time_arr',$time_arr);             
        $this->assign('flash_salelist',$flash_salelist);             
        return $this->fetch();
    }
    
    //ajax获取某个时间段的秒杀
    public function ajaxSeckill()
    {
        $start_time = input('post.start_time','');
        $flash_salelist = M('flash_sale')->alias('FS')->join('goods G','FS.goods_id=G.goods_id','left')->field('FS.id,FS.title,FS.goods_id,FS.item_id,FS.price,FS.goods_num,FS.buy_num,FS.order_num,G.market_price,FS.goods_name,G.original_img,FS.start_time,FS.end_time')->where(['FS.start_time'=>$start_time,'FS.is_end'=>0])->order('FS.start_time desc')->select();
        foreach($flash_salelist as $k=>$v){
            $flash_salelist[$k]['rate'] = !$v['order_num'] ? 100 : 100-intval(($v['order_num']/$v['goods_num'])*100);
            $flash_salelist[$k]['start'] = (($v['start_time'] < time()) && ($v['end_time'] > time())) ? 1 : 0;
        }
        $this->assign('flash_salelist',$flash_salelist);             
        return $this->fetch();
    }
    // 秒杀详情
    public function details(){
        $shareid = I('shareid');
        if(!empty($shareid) && !session('?user'))
        {
          $user = M('users')->where("user_id", $shareid)->find();
          $shareid =  $user['user_id'];
          Session::set('shareid',$shareid);
        }else{
            $user = session('user');
        }
        C('TOKEN_ON', true);
        $goodsLogic = new \app\common\logic\GoodsLogic();
        $goods_id = I("get.goods_id/d",0);
        $id = I("get.id/d",0);
        $goodsModel = new \app\common\model\Goods();
        $goods = $goodsModel::get($goods_id);
        if (empty($goods) || ($goods['is_on_sale'] == 0)) {
            $this->error('此商品不存在或者已下架');
        }
        if(($goods['is_virtual'] == 1 && $goods['virtual_indate'] <= time())){
            $goods->save(['is_on_sale' => 0]);
            $this->error('此商品不存在或者已下架');
        }
        $user_id = cookie('user_id');
        if ($user_id) {
            $goodsLogic->add_visit_log($user_id, $goods);
            $collect = db('goods_collect')->where(array("goods_id" => $goods_id, "user_id" => $user_id))->count(); //当前用户收藏
            $this->assign('collect', $collect);
        }

        $recommend_goods = M('goods')->where("is_recommend=1 and is_on_sale=1 and cat_id = {$goods['cat_id']}")->cache(7200)->limit(9)->field("goods_id, goods_name, shop_price")->select();

        //等级价格
        if($user['level'] > 0){
            $price = M('goods_level_price')->where('goods_id',$goods_id)->where('level',$user['level'])->value('price');
            $price = $price?$price:$goods['market_price'];
        }else{
            $price = $goods['market_price'];
        }

        $prominfo = M('flash_sale')->find($id);
        $prominfo['rate'] = !$prominfo['order_num'] ? 100 : 100-intval(($prominfo['order_num']/$prominfo['goods_num'])*100);
        $this->assign('price', $price); 
        //dump($goods);exit;
        $this->assign('recommend_goods', $recommend_goods);
        $this->assign('goods', $goods);
        $this->assign('user', $user);    
        $this->assign('prominfo', $prominfo);     
        return $this->fetch();
    }

    // 秒杀详情
    public function rule(){
        return $this->fetch();
    }

    public function index_zp()
    {        
        return $this->fetch();
    }

    /**
     * 秒杀 倒计时    
     * 秒杀 结束
     */
    public function detail()
    {
       
        return $this->fetch();
    }

    /**
     * 秒杀 提交 订单
     */
    public function submit_order()
    {
       
        $time = "2019,3,8";
        $this->assign('time',$time);


        return $this->fetch();
    }

    /**
     * 秒杀 填写 订单
     */
    public function add()
    {
       
        return $this->fetch();
    }
     public function lj_fill()
    {
       
        return $this->fetch();
    }
         public function wtf_submit()
    {
       
        return $this->fetch();
    }
    

}