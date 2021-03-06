<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/13 0013
 * Time: 10:20
 */

namespace app\cron\controller;

use think\Controller;
use think\Db;
use app\common\util\Exception;

class Team extends Controller{
    /**
     * 执行方法
     */
    public function run()
    {
        $this->change_group_buy_is_end();  
        $this->change_flash_sale_is_end();  
        //M('A')->add(['msg'=>date('Y-m-d H:i:s',time())]);      
    }

    public function change_flash_sale_is_end(){
        //取结束时间十分钟内大于等于当前时间的秒杀
        $flashSale = M('flash_sale');
        $list = $flashSale->field('id,goods_id,is_end')->where(['end_time'=>['between',[time()-600,time()]]])->select();
        $Goods = M('Goods');
        foreach($list as $v){
            if(!$v['is_end'])$flashSale->where(['id'=>$v['id']])->update(['is_end'=>1]);
            $goods_info = $Goods->field('prom_type,prom_id')->find($v['goods_id']);
            if(($goods_info['prom_type'] == 1) && ($goods_info['prom_id'] == $v['id']))
                $Goods->where(['goods_id'=>$v['goods_id']])->update(['prom_type'=>0,'prom_id'=>0]);
        }
    }

    public function change_group_buy_is_end(){
        //取结束时间大于等于当前时间且未结束的团购
        $GroupBuy = M('group_buy');
        $list = $GroupBuy->field('id,buy_num,min_mduser_num')->where(['end_time'=>['elt',time()],'is_end'=>0])->select();
        
        $list1 = $GroupBuy->field('id,goods_id')->where(['end_time'=>['elt',time()],'is_end'=>0])->select();
        $GroupBuy->where(['end_time'=>['elt',time()],'is_end'=>0])->update(['is_end'=>1]);

        $Goods = M('Goods');
        foreach($list1 as $v){
            $goods_info = $Goods->field('prom_type,prom_id')->find($v['goods_id']);
            if(($goods_info['prom_type'] == 2) && ($goods_info['prom_id'] == $v['id']))
                $Goods->where(['goods_id'=>$v['goods_id']])->update(['prom_type'=>0,'prom_id'=>0]);
        }

        $Order = M('Order');
        $Users = M('Users');
        $AccountLog = M('account_log');
        foreach($list as $v){ //订单中source_uid最多的用户免单
            if($v['buy_num'] < $v['min_mduser_num'])continue;
            $info = $Order->field('source_uid,count(source_uid) as num')->where(['prom_id'=>$v['id'],'prom_type'=>2,'source_uid'=>['neq',0]])->group('source_uid')->order('num desc,pay_time desc')->limit(1)->find();
            if($info){
                $orderinfo = $Order->field('order_id,order_sn,shipping_price,total_amount')->where(['user_id'=>$info['source_uid'],'pay_status'=>1,'prom_id'=>$v['id'],'prom_type'=>2])->find();
                $Users->where(['user_id'=>$info['source_uid']])->setInc('user_money',$orderinfo['total_amount']);
                $AccountLog->add(['user_id'=>$info['source_uid'],'user_money'=>$orderinfo['total_amount'],'change_time'=>time(),'desc'=>'团购分享最多获得免单','order_sn'=>$orderinfo['order_sn'],'order_id'=>$orderinfo['order_id'],'log_type'=>80]);
            }
        }

    }

    public function getphpinfo(){
        phpinfo();
    }

    protected function sql()
    {
        try {
            Db::startTrans();





            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            return $e->getData();
        }
    }

}