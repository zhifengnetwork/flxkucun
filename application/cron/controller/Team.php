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
    }

    public function change_group_buy_is_end(){
        //取结束时间大于等于当前时间且未结束的团购
        $GroupBuy = M('group_buy');
        $list = $GroupBuy->field('id,buy_num,min_mduser_num')->where(['end_time'=>['egt',time()],'is_end'=>0])->select();

        $list1 = $GroupBuy->field('goods_id')->where(['end_time'=>['elt',time()],'is_end'=>0])->select();
        $GroupBuy->where(['end_time'=>['elt',time()],'is_end'=>0])->update(['is_end'=>1]);

        $Goods = M('Goods');
        foreach($list1 as $v){
            $Goods->where(['goods_id'=>$v['goods_id']])->update(['prom_type'=>0,'prom_id'=>0]);
        }

        $Order = M('Order');
        $Users = M('Users');
        $AccountLog = M('account_log');
        foreach($list as $v){ //订单中source_uid最多的用户免单
            if($v['buy_num'] < $v['min_mduser_num'])continue;
            $info = $Order->field('source_uid,count(source_uid) as num')->where(['prom_id'=>$v['id'],'prom_type'=>2,'source_uid'=>['neq',0]])->group('source_uid')->order('num desc,pay_time desc')->limit(1)->select();
            if($info){
                $orderinfo = $Order->field('order_id,order_sn,shipping_price,total_amount')->where(['user_id'=>$info['source_uid'],'pay_status'=>1,'prom_id'=>$v['id'],'prom_type'=>2])->find();
                $Users->where(['user_id'=>$info['source_uid']])->setInc('user_money',$orderinfo['total_amount']);    
                $AccountLog->add(['user_id'=>$info['source_uid'],'user_money'=>$orderinfo['total_amount'],'change_time'=>time(),'desc'=>'团购分享最多获得免单','order_sn'=>$orderinfo['order_sn'],'order_id'=>$orderinfo['order_id'],'log_type'=>80]);
            }
        }

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