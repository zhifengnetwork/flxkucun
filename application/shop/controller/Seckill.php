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
        $flash_salelist = M('flash_sale')->alias('FS')->join('goods G','FS.goods_id=G.goods_id','left')->field('FS.id,FS.title,FS.goods_id,FS.item_id,FS.price,FS.goods_num,FS.buy_num,FS.order_num,G.market_price,FS.goods_name,G.original_img,FS.start_time,FS.end_time')->where(['FS.end_time'=>['gt',time()],'FS.is_end'=>0])->order('FS.start_time desc')->select();
        $time = time(); 
        foreach($flash_salelist as $k=>$v){
            $flash_salelist[$k]['rate'] = !$v['order_num'] ? 100 : 100-intval(($v['order_num']/$v['goods_num'])*100);
            $flash_salelist[$k]['start_time_msg'] = date('H:i',$v['start_time']);
            if($time < $v['start_time']){
                $flash_salelist[$k]['time_msg'] = '即将开抢';
            }else if($time > $v['start_time'] && $time < $v['end_time']){
                $flash_salelist[$k]['time_msg'] = '抢购中';
            }else{
                $flash_salelist[$k]['time_msg'] = '抢购结束';
            }
        }

        $this->assign('flash_salelist',$flash_salelist);             
        return $this->fetch();
    }
    
    // 秒杀详情
    public function details(){
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