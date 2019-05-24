<?php
/**
 * DC环球直供网络
 * ============================================================================
 *   分销、代理
 */

namespace app\common\logic;

use app\common\logic\LevelLogic;
use think\Model;
use think\Db;
use think\Session;
/**
 * 返利类
 */
class FanliLogic extends Model
{

	private $userId;//用户id
	private $goodId;//商品id
	private $goodNum;//商品数量
	private $orderSn;//订单编号
	private $orderId;//订单id

	public function __construct($userId,  $goodId, $goodNum, $orderSn, $orderId)
	{	
		$this->userId = $userId;
		$this->goodId = $goodId;
		$this->goodNum = $goodNum;
		$this->orderSn = $orderSn;
		$this->orderId = $orderId;
		$this->tgoodsid = $this->catgoods();
	}
	//获取返利数据
	public function getconfing()
	{
         $goods_info = M('goods')->where(['goods_id'=>$this->goodId])->field('rebate')->find();
         return unserialize($goods_info['rebate']);
	}
	//获取用户购买特殊产品数量
	public function getproductnum()
	{
        $num=M('order_goods')->alias('og')
        ->join('order o', 'og.order_id=o.order_id')
        ->where(['o.user_id'=>$this->userId,'og.goods_id'=>$this->tgoodsid,'o.pay_status'=>1])
        ->count();
        return $num;
	}
    //会员返利
	public function fanliModel()
	{

		$price = M('goods')->where(['goods_id'=>$this->goodId])->value('shop_price');
		//判断商品是否是活动商品
		$good = M('goods')
				->where('goods_id', $this->goodId)
				->field('is_distribut,is_agent')
                ->find();
           //获取每个产品返利数据
         $rebase = $this->getconfing();
        //查询会员当前等级
		$user_info = M('users')->where('user_id',$this->userId)->field('first_leader,level,user_id')->find();
		//查询上一级信息
		$parent_info = M('users')->where('user_id',$user_info['first_leader'])->field('level')->find();
        //判断是否特殊产品成为店主，则不走返利流程
        //用户购买后检查升级
		$this->checkuserlevel($this->userId,$this->orderId);
		$pro_num = $this->getproductnum();
		//echo $this->goodId.'-'.$this->tgoodsid.'-'.$user_info['level'];exit;
        if($this->goodId==$this->tgoodsid )//是否特殊产品
        {
        	 //if($pro_num<=1) //是否有买过特殊产品
        	 //{
		      if(empty($rebase)||$rebase[$parent_info['level']]<=0) //计算返利比列
		       {
                   $fanli = M('user_level')->where('level',$parent_info['level'])->field('rate')->find();
		       }else
		       {
		           $fanli['rate'] = $rebase[$parent_info['level']];
		       }

	          //查询会员等级返利数据
		       if($parent_info['level']!=1 && !empty($parent_info)){ //上一级是普通会员则不反钱
		         //计算返利金额
		          $goods = $this->goods();
		          $commission = $goods['shop_price'] * ($fanli['rate'] / 100) * $this->goodNum;
		           //计算佣金
		          //按上一级等级各自比例分享返利
		          $bool = M('users')->where('user_id',$user_info['first_leader'])->setInc('user_money',$commission);


			         if ($bool !== false) {
			        	$desc = "分享返利";
			        	$log = $this->writeLog($user_info['first_leader'],$commission,$desc,1); //写入日志
			            //检查返利管理津贴
			            $this->jintie($user_info['first_leader'],$commission);
			        	//return true;
			         } else {
			        	return false;
			         }
			     }else{
			     	return false;
			     }

        	 //}
        	
        	if($user_info['level']<=2)
        	{
        		$this->addhostmoney($user_info['user_id'],$parent_info);
        		$this->ppInvitation($user_info['first_leader']);//总监下线推荐店主金额
                $this->ccInvitation($user_info['first_leader']);//大区下线推荐店主金额
        	}
          
        }
        else
        {
        	//不是特产品按照佣金比例反给用户 ，自购返利

            if($user_info['level']>1)
            {
               $distribut_level = M('distribut_level')->where('level_id',1)->field('rate1')->find();
                 //计算返利金额
		        $goods = $this->goods();
		        $commission = $goods['shop_price'] * ($distribut_level['rate1'] / 100) * $this->goodNum;
		           //计算佣金
		          //按上一级等级各自比例分享返利
		        $bool = M('users')->where('user_id',$user_info['user_id'])->setInc('user_money',$commission);
		         if ($bool !== false) {
			        	$desc = "自购返利";
			        	$log = $this->writeLog($user_info['user_id'],$commission,$desc,7); 
			        	//return true;
			         } else {
			        	return false;
			         }
            }
            // 购买商品返利给上一级
            if(empty($rebase)||$rebase[$parent_info['level']]<=0) //计算返利比列
		       {
                   $fanli = M('user_level')->where('level',$parent_info['level'])->field('rate')->find();
		       }else
		       {
		           $fanli['rate'] = $rebase[$parent_info['level']];
		       }
	          //查询会员等级返利数据
		       if($parent_info['level']!=1 && !empty($parent_info)){ //上一级是普通会员则不反钱
		         //计算返利金额
		          $goods = $this->goods();
		          $commission = $goods['shop_price'] * ($fanli['rate'] / 100) * $this->goodNum;
		           //计算佣金
		          //按上一级等级各自比例分享返利
		          $bool = M('users')->where('user_id',$user_info['first_leader'])->setInc('user_money',$commission);
			      if ($bool !== false) {
			        	$desc = "分享返利";
			        	$log = $this->writeLog($user_info['first_leader'],$commission,$desc,1); //写入日志
			            //检查返利管理津贴
			            $this->jintie($user_info['first_leader'],$commission);
			        	//return true;
			         } else {
			        	return false;
			         }
			     }else{
			     	return false;
			     }
        	 

            /*
		    elseif($user_info['level']>=4) //是复购
		    {
	            if(empty($rebase)||$rebase[$user_info['level']]<=0) //
		         {
                    $fanli = M('user_level')->where('level',$user_info['level'])->field('rate')->find();
                    
		         }else
		         {
		          	 $fanli['rate'] = $rebase[$user_info['level']];
		          	 
		         }
	          if(!empty($fanli['rate']))
	          {


	          //计算返利金额
	          $goods = $this->goods();
	          $commission = $goods['shop_price'] * ($fanli['rate'] / 100) * $this->goodNum; //计算佣金
	          //按上一级等级各自比例分享返利
	          $bool = M('users')->where('user_id',$user_info['user_id'])->setInc('user_money',$commission);

	         if ($bool !== false) {
	        	$desc = "复购返利";
	        	$log = $this->writeLog($user_info['user_id'],$commission,$desc,1); //写入日志
	          }
	      

	        	//return true;
	         } else {
	        	return false;
	         }
	        }*/
		
	    }

	}
	//会员升级
	public function checkuserlevel($user_id,$order_id)
	{
         //自动升级为vip，店主，总监，大区董事自动申请
		
		 //扫码登陆

		 $order = M('order')->where(['order_id'=>$this->orderId])->find();
		 $user_info = M('users')->where('user_id',$user_id)->field('first_leader,level,is_code,user_id')->find();
        $goodid = $this->goodId;
        $tgoodsid =$this->tgoodsid;
		if( $order['pay_status']==1 && $user_info['level']==1 && $goodid!=$tgoodsid)//自动升级vip
		{
              $res = M('users')->where(['user_id'=>$user_id])->update(['level'=>2]);
              	$desc = "购买产品成为vip";
	        	$log = $this->writeLog_ug($user_info['user_id'],'',$desc,2); //写入日志
		}
		else if($this->goodId==$this->tgoodsid  && $order['pay_status']==1 && $user_info['level']<3)//自动升级店主
		{
			$res_s = M('users')->where(['user_id'=>$user_id])->update(['level'=>3]);
			$desc = "购买指定产品获得店主";
	        $log = $this->writeLog_ug($user_info['user_id'],'398',$desc,2); //写入日志

	        if($res_s)
	        {
	        	//$this->addhostmoney2($user_info['user_id']);//产生店主获得金额和津贴
	          //自动升级总监
			    $parent_info = M('users')->where('user_id',$user_info['first_leader'])->field('first_leader,level,is_code,user_id')->find();

				$num=M('users')->where(['first_leader'=>$user_info['first_leader'],'level'=>3])->count();
				$fanli = M('user_level')->where('level',4)->field('tui_num')->find();
	             if($num>=$fanli['tui_num'] && !empty($fanli['tui_num']) && $parent_info['level']==3)
	             {
	                  $res = M('users')->where(['user_id'=>$user_info['first_leader']])->update(['level'=>4]);
	                  $desc = "直推店主".$fanli['tui_num']."个成为总监";
		        	  $log = $this->writeLog_ug($user_info['first_leader'],'',$desc,2); //写入日志
	             }
	        }
		}

	}
    //推荐店主获得金额
	public function addhostmoney($user_id,$parent_info)
	{
		$user_info = M('users')->where('user_id',$user_id)->field('first_leader,level')->find();
       //只有店主，总监，大区董事推荐店主才能的到店主推荐金额
		 if($parent_info['level']==3 || $parent_info['level']==4 || $parent_info['level']==5)//
		 {
		//计算返利金额
		  $fanli = M('user_level')->where('level',$parent_info['level'])->field('rate','reward')->find();
          $goods = $this->goods();
          $commission = $fanli['reward']; //计算金额
          
         // print_R($goods['shop_price'].'-'.$this->goodNum.'-'.$fanli['rate']);exit;
          //按上一级等级各自比例分享返利
          $bool = M('users')->where('user_id',$user_info['first_leader'])->setInc('user_money',$commission);

	         if ($bool !== false) {
	        	$desc = "推荐店主获得金额";
	        	$log = $this->writeLog($user_info['first_leader'],$commission,$desc,3); //写入日志

	        	return true;
	         } else {
	        	return false;
	         }
	     }else
	     {
            return false;
	     }

	}
	//获得管理津贴
	public  function jintie($user_leader,$fanli_money)
	{
      //只有总监和大区获得管理津贴
		//查询上上级信息
		$parent_info = M('users')->where('first_leader',$user_leader)->field('level,user_id')->find();
		if($parent_info['level']==4 || $parent_info['level']==5)
		{
			$fanli = M('user_level')->where('level',$parent_info['level'])->field('jintie')->find();
			 $commission = $fanli_money * ($fanli['jintie'] / 100);

	          //按上一级等级各自比例分享返利
	       $bool = M('users')->where('user_id',$parent_info['user_id'])->setInc('user_money',$commission);
	       	$desc = "获得管理津贴";
	        $log = $this->writeLog($parent_info['user_id'],$commission,$desc,5); //写入日志
		}

	}
	 //总监，大区董事产生一个店主的金额和管理津贴
	public function addhostmoney2($user_id)
	{
		//查询会员当前等级
		$user_info = M('users')->where('user_id',$this->userId)->field('first_leader,level')->find();
		//查询上一级信息
		$parent_info = M('users')->where('user_id',$user_info['first_leader'])->field('level')->find();
		if($parent_info['level']==4 || $parent_info['level']==5)
		{
           $fanli = M('user_level')->where('level',$parent_info['level'])->field('chan')->find();
	         //计算返利金额
	       $commission = $fanli['chan']; //计算佣金
	          //按上一级等级各自比例分享返利
	       $bool = M('users')->where('user_id',$user_info['user_id'])->setInc('user_money',$commission);
	       	$desc = "团队产生店主获得金额";
	        $log = $this->writeLog($user_info['first_leader'],$commission,$desc,4); //写入日志
	        return true;
		}

	}
	//总监直属店主邀店主获得金额
	public function ppInvitation($user_leader)
	{
		//判断上上级是否是总监，是总监就获得对应金额
		//查询上级信息
		$parent_info = M('users')->where('user_id',$user_leader)->field('level,user_id,first_leader')->find();
		//查询上上级信息
		$p_parent_info = M('users')->where('user_id',$parent_info['first_leader'])->field('level,user_id')->find();
		if($p_parent_info['level']==4 && $parent_info['level']==3)
		{
			 $fanli = M('user_level')->where('level',$p_parent_info['level'])->field('y_reward')->find();
			 $commission = $fanli['y_reward']; //计算金额
	          //按上一级等级各自比例分享返利
	        $bool = M('users')->where('user_id',$p_parent_info['user_id'])->setInc('user_money',$commission);
	       	$desc = "总监直属店主邀店主获得金额";
	        $log = $this->writeLog($p_parent_info['user_id'],$commission,$desc,6); //写入日志
		}


	}
	//大区直属店主邀店主,直属总监邀店主,直属店主邀店主,获得金额
	public function ccInvitation($user_leader)
	{
		
		//查询上级信息
		$parent_info = M('users')->where('user_id',$user_leader)->field('level,user_id,first_leader')->find();
		//查询上上级信息
		$p_parent_info = M('users')->where('user_id',$parent_info['first_leader'])->field('level,user_id,first_leader')->find();
		//查询上上上级信息
		$p_p_parent_info = M('users')->where('user_id',$p_parent_info['first_leader'])->field('level,user_id,first_leader')->find();
		//直属店主邀店主
		if($p_parent_info['level']==5 && $parent_info['level']==3)
		{
			 $fanli = M('user_level')->where('level',$p_parent_info['level'])->field('k_reward')->find();
			 $commission = $fanli['k_reward']; //计算金额
	          //按上一级等级各自比例分享返利
	        $bool = M('users')->where('user_id',$p_parent_info['user_id'])->setInc('user_money',$commission);
	       	$desc = "大区直属店主邀店主获得金额";
	        $log = $this->writeLog($p_parent_info['user_id'],$commission,$desc,6); //写入日志
		}
		//直属总监邀店主
		elseif($p_parent_info['level']==5 && $parent_info['level']==4)
		{
		    $fanli = M('user_level')->where('level',$p_parent_info['level'])->field('s_reward')->find();
			 $commission = $fanli['s_reward']; //计算金额
	          //按上一级等级各自比例分享返利
	        $bool = M('users')->where('user_id',$p_parent_info['user_id'])->setInc('user_money',$commission);
	       	$desc = "大区直属总监邀店主获得金额";
	        $log = $this->writeLog($p_parent_info['user_id'],$commission,$desc,6); //写入日志
		}
	    //直属总监的店主邀店主
		elseif($p_p_parent_info['level']==5 && $p_parent_info['level']==4 && $parent_info['level']==3)
		{
			  $fanli = M('user_level')->where('level',$p_p_parent_info['level'])->field('y_reward')->find();
			 $commission = $fanli['y_reward']; //计算金额
	          //按上一级等级各自比例分享返利
	        $bool = M('users')->where('user_id',$p_p_parent_info['user_id'])->setInc('user_money',$commission);
	       	$desc = "大区直属总监的店主邀店主获得金额";
	        $log = $this->writeLog($p_p_parent_info['user_id'],$commission,$desc,6); //写入日志
		}


	}
	//记录日志
	public function writeLog($userId,$money,$desc,$states)
	{
		$data = array(
			'user_id'=>$userId,
			'user_money'=>$money,
			'change_time'=>time(),
			'desc'=>$desc,
			'order_sn'=>$this->orderSn,
			'order_id'=>$this->orderId,
			'log_type'=>$states
		);

		$bool = M('fan_log')->insert($data);


         if(empty($money))
         {
         	$money =0; 
         }
		if($bool){

			//分钱记录
			$data = array(
				'order_id'=>$this->orderId,
				'user_id'=>$userId,
				'status'=>1,
				'goods_id'=>$this->goodId,
				'money'=>$money
			);
			M('order_divide')->add($data);
		
		}
		
		return $bool;
	}
		//升级记录
	public function writeLog_ug($userId,$money,$desc,$states)
	{
		$data = array(
			'user_id'=>$userId,
			'user_money'=>$money,
			'change_time'=>time(),
			'desc'=>$desc,
			'order_sn'=>$this->orderSn,
			'order_id'=>$this->orderId,
			'log_type'=>$states
		);

		$bool = M('upgrade_log')->insert($data);
		
		return $bool;
	}
	public function goods(){
		$goods = M('goods')->field("shop_price,cat_id")->where(['goods_id'=>$this->goodId])->find();
		return $goods;
	}
	//特殊产品
	public function catgoods()
	{
		$goods = M('goods')->field("goods_id")->where(['cat_id'=>8])->find();
		if(!$goods) return 0;
		return $goods['goods_id'];
	}

	
}