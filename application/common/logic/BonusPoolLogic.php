<?php

namespace app\common\logic;

use think\Exception;
use think\Model;
use think\Db;

/**
 * 活动逻辑类
 */
class BonusPoolLogic extends Model
{

	//判断商品是否为领取类目的产品
	public function is_receive($order)
	{
		//订单中的所有产品
		$orderGoods = M('order_goods')->where('order_id', $order['order_id'])->field('goods_num,is_bonus')->select();
        //计算产品数量
        $nums = 0;
        foreach ($orderGoods as $key => $value) {
            //参加活动的产品
            if($value['is_bonus'] == 1){
                $nums = $nums + $value['goods_num'];
            }
        }
		if($nums <= 0) return false;

		//领取产品的用户
		$user = M('users')->where('user_id', $order['user_id'])->field('user_id, nickname, first_leader')
			  ->find();
		if(!$user) return false;

		//开启事务
		Db::startTrans();
		try{
			//将邮费放进奖金池
			$money = $this->put_in($order['shipping_price']);

			//记录下级领取的日志
			$this->write_log($nums, $money, $user, $order['order_id']);

			//没有上级则不记录上级排名
			if($user['first_leader']){
				$leader = M('users')->where('user_id', $user['first_leader'])->find();
				if($leader){
					//累计上级排名
					$result = $this->write_ranking($nums, $money, $user);
				}
			}
			
			// 提交事务
			Db::commit();  
	        return true;
		}catch(\Exception $e){
			// 回滚事务
            Db::rollback();
            return false;
		}
	}

	/*
     * 将邮费放进奖金池
     * shipping 运费
     */
	public function put_in($shipping)
	{
		//获每个产品抽取的邮费
        $bonus_pool = $this->getDate('bonus_pool');
		$bonus_total = $this->getDate('bonus_total');
        
		//抽取邮费的百分比
		$money = round($shipping * ((int)$bonus_pool['bonus_pool']/100), 2);

        // 增加奖金池
        $result = Db::name('config')->where('name', 'bonus_total')->update(['value' => $money + (real)$bonus_total['bonus_total']]);
		if($result){
			return $money;
		}else{
			return false;
		}
	}

	//记录领取日志
	public function write_log($nums, $money, $user, $order_id)
	{
		$data = array(
			'user_id' => $user['user_id'],
			'user_name' => $user['nickname'],
			'leader_id' => $user['first_leader'],
			'order_id' => $order_id,
			'nums' => $nums,
			'money' => $money,
			'create_time' => time(),
			'desc' => '领取'.$nums.'个商品',
		);
		$result = Db::name('bonus_receive_log')->insert($data);
		return $result;	
	}

	//记录上级排名
	public function write_ranking($nums, $money, $user)
	{
		//结算排名的时间戳
		$tb_time = $this->getDate('bonus_time');
		$tb_time['bonus_time'] = (int)$tb_time['bonus_time'];
		if($tb_time['bonus_time']){
			$bonus_time['create_time'] = ['>', $tb_time['bonus_time']];
		}else{
			$bonus_time = array();
		}
	
		//查询用户是否已经存在排名
		$users = M('bonus_rank')->where($bonus_time)->where('user_id', $user['first_leader'])
			   ->where('status', 0)->find();

		//用户已存在排名则更新数据, 否则插入一条新记录
		if($users){
			$data = array(
				'nums' => $users['nums'] + $nums,
				'money' => $users['money'] + $money,
				'update_time' => time(), 
			);
			$result = Db::name('bonus_rank')->where('user_id', $users['user_id'])->update($data);
			return $result;
		}else{
			$data = array(
				'user_id' => $user['first_leader'],
				'nums' => $nums,
				'money' => $money,
				'create_time' => time(),
				'update_time' => time(),
			);
			$result = Db::name('bonus_rank')->insert($data);
			return $result;
		}
	}

	//奖金池奖励
	public function bonus_reward()
	{
		//判断是否满足奖励的条件
		$satisfy = $this->is_satisfy();
		if(!$satisfy) return false;

		//获取后台设置的奖励信息
		$data = array('bonus_total', 'bonus_time', 'ranking1', 'ranking2', 'ranking3');
		$data = $this->getDate($data);
	
		//截止时间,用于修改排名表时不更新后面新增的数据
		$bonus_time = time();
		$last_time = (int)$data['bonus_time'];
		$result = $this->getUser($last_time, $bonus_time);
		if(!$result) return false;

		//按数量取最大的前3条记录
		Db::startTrans();
		try{
			//记录奖励的总额
			$count = 0;
			$log = array();
			foreach ($result as $key => $value) {
				// dump($value);
				//奖励总金额
				$num = $key + 1;
				$money = ($data['ranking'. $num] / 100) * $data['bonus_total'];
				$count = $count + $money;
				$user_money = $money + $value['user_money'];
				// dump($money);
				// dump($user_money);
				Db::name('users')->where('user_id', $value['user_id'])->update(['user_money' => $user_money]);
				
				$log[$key]['user_id'] = $value['user_id'];
				$log[$key]['money'] = $money;
				$log[$key]['ranking'] = $num;
				$log[$key]['bonus_total'] = $data['bonus_total'];
				$log[$key]['create_time'] = $bonus_time;
				$log[$key]['status'] = 1;
			}

			//记录奖励日志,修改排名记录为过期
			Db::name('bonus_log')->insertAll($log);
			Db::name('bonus_rank')->whereTime('create_time', '<=', $bonus_time)
								  ->where('status', 0)->update(['status'=>1]);
			//剩余金额
			$remanent = round(($data['bonus_total'] - $count), 2);
			dump($remanent);
			Db::name('config')->where('name', 'bonus_total')->update(['value'=>$remanent]);
			Db::commit();
			return true;
		}catch (\Exception $e) {
		    // 回滚事务
		    Db::rollback();
		    $this->fail_reward($bonus_time);
		    return false;
		}
	}

	//获取奖励用户
	public function getUser($last_time, $bonus_time)
	{
		M('config')->where('name', 'bonus_time')->update(['value'=>$bonus_time]);
		$condition['rank.status'] = 0;
		if($last_time){
			$condition['rank.create_time'] = ['between', [$last_time, $bonus_time]];
		}else{
			$condition['rank.create_time'] = ['<', $bonus_time];
		}

		$result = Db::name('bonus_rank')->alias('rank')->join('users', 'rank.user_id = users.user_id')
				->field('rank.*, users.user_money')->where($condition)
				->order('rank.nums DESC, rank.create_time ASC')->limit(3)->select();
		return $result;
	}

	//奖励失败时插入记录
	public function fail_reward($data)
	{
		$data = array(
	    	$log['user_id'] = 0,
			$log['money'] = 0,
			$log['ranking'] = 0,
			$log['bonus_total'] = 0,
			$log['create_time'] = $data,
			$log['status'] = 0,
	    );
	    M('bonus_log')->insert($data);
	}

    //获取奖励设置信息
    public function getDate($data = '')
    {
        if(is_array($data)){
            $condition['name'] = ['in', $data];
        }else if($data != ''){
            $condition['name'] = $data;
        }else{
            $condition = array();
        }

        $result = M('config')->where($condition)
                ->where('inc_type', 'bonus')
                ->column('name, value');
        return $result;
    }

	//判断是否满足条件
	public function is_satisfy()
	{
		//判断是否达到后台设定的奖励日期
		//默认晚上3点结算奖励
		$time     = 0;
		$pre_day  = date('d');
		$pre_time = date('H');
		$day = $this->getDate('day');
		if(($pre_day < $day['day']) or ($pre_time < $time)) return false;

		//查询排名奖励表是否已经奖励
		$is_reward = M('bonus_log')->whereTime('create_time','month')->find();
		if($is_reward) return false;
		
		return true;
	}


}