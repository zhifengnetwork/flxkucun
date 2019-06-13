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
        //对过期的拼团订单进行取消,在服务器上由定时器任务执行
        $Tf = M('team_found');
        $time = time() - 3600 * 48; //只取两天以内的
        $list = $Tf->field('f.found_id')->alias('f')->join('tp_team_activity t','f.team_id=t.team_id','left')->where('f.found_time between ' . $time . " and (unix_timestamp(now())-t.time_limit*3600) and f.need>0")->select();
        $Tf->alias('f')->join('tp_team_activity t','f.team_id=t.team_id','left')->where('f.found_time between ' . $time . " and (unix_timestamp(now())-t.time_limit*3600) and f.need>0")->update(['f.status'=>3]);

        $Tfw = M('team_follow');
        $Order = M('Order');
        $Users = M('users');
        $AccountLog = M('account_log');
        foreach($list as $v){ 
            $Tfw->where(['found_id'=>$v['found_id']])->update(['status'=>3]); 
            $oflist = $Order->field('order_id,order_sn,user_id,integral_money,total_amount')->where(['order_prom_id'=>$v['found_id'],'pay_status'=>1,'order_status'=>0])->select();

            foreach($oflist as $v1){
                if($v1['total_amount']){
                    $Users->where(['user_id'=>$v1['user_id']])->setInc('credit2',$v1['total_amount']);     
                    $AccountLog->add(['user_id'=>$v1['user_id'],'user_money'=>$v1['total_amount'],'pay_points'=>$v1['integral_money'],'change_time'=>time(),'desc'=>'拼团失败返回','order_sn'=>$v1['order_sn'],'order_id'=>$v1['order_id'],'states'=>103]);
                }
                if($v1['integral_money'])
                    $Users->where(['user_id'=>$v1['user_id']])->setInc('pay_points',$v1['integral_money']);      
            }
            $Order->where(['order_prom_id'=>$v['found_id']])->update(['order_status'=>3]);
        }

        //竞拍未成功的人返回保证金
        $Auction = M('Auction');
        $AuctionDeposit = M('Auction_deposit');
        $AuctionPprice = M('Auction_price');
        $alist = $Auction->field('id,deposit,payment_time,end_time')->where(['start_time'=>['lt',(time()-24*3600)],'is_end'=>1])->select();
        foreach($alist as $v2){
            $aplist = $AuctionPprice->field('user_id,pay_status')->where(['is_out'=>['neq',2],'auction_id'=>$v2['id']])->select();    
            foreach($aplist as $v3){
                $order_sn = $AuctionDeposit->where(['user_id'=>$v3['user_id'],'auction_id'=>$v2['id'],'is_back'=>0])->value('order_sn');
                if(!$order_sn)continue;
                $AuctionDeposit->where(['user_id'=>$v3['user_id'],'auction_id'=>$v2['id']])->update(['is_back'=>1]);    
                $Users->where(['user_id'=>$v3['user_id']])->setInc('pay_points',$v2['deposit']);  
                $AccountLog->add(['user_id'=>$v3['user_id'],'user_money'=>$v2['deposit'],'change_time'=>time(),'desc'=>'竞拍失败保证金返回','states'=>104]);  
            }
            //$apinfo = $AuctionPprice->field('user_id')->where(['is_out'=>2,'auction_id'=>$v2['id'],'pay_status'=>['neq',1]])->find(); 
            //if($apinfo && (time() > ($v2['end_time']+($v2['payment_time']*60))))
        }
               
    }

    public function change_group_buy_is_end(){
        //取结束时间大于等于当前时间且未结束的团购
        $GroupBuy = M('group_buy');
        $list = $GroupBuy->field('id,buy_num,min_mduser_num')->where(['end_time'=>['egt',time()],'is_end'=>0])->select();
        $GroupBuy->where(['end_time'=>['egt',time()],'is_end'=>0])->update(['is_end'=>1]);
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