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
	private $prom_type;//活动类型
	private $prom_id;//活动ID

	public function __construct($userId,  $goodId, $goodNum, $orderSn, $orderId, $prom_type=0, $prom_id=0)
	{	
		$this->userId = $userId;
		$this->goodId = $goodId;
		$this->goodNum = $goodNum;
		$this->orderSn = $orderSn;
		$this->orderId = $orderId;
		$this->prom_type = $prom_type;
		$this->prom_id = $prom_id;
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
		$good = M('goods')->where('goods_id', $this->goodId)->field('is_distribut,is_agent')->find();
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

		if($this->prom_type == 1)
		{
			return $this->flash_sale_commission(); //秒杀返利
		}


		if($this->goodId==$this->tgoodsid )//是否特殊产品
		{ return true;
			$this->addhostmoney($user_info['user_id'],$parent_info);//店主推荐店主
			$this->upzdmoney($user_info['first_leader']);//大区，董事无限代
		}
		else
    { return true;
      //不是特产品按照佣金比例反给用户 ，自购返利

			if ($user_info['level']>1) {
					$distribut_level = M('distribut_level')->where('level_id', 1)->field('rate1')->find();
					//计算返利金额
					$goods = $this->goods();
					$commission = $goods['shop_price'] * ($distribut_level['rate1'] / 100) * $this->goodNum;
					//计算佣金
					//按上一级等级各自比例分享返利
			}
      // 购买商品返利给上一级
			if (empty($rebase)||$rebase[$parent_info['level']]<=0) { //计算返利比列
							$fanli = M('user_level')->where('level', $parent_info['level'])->field('rate')->find();
			} else {
					$fanli['rate'] = $rebase[$parent_info['level']];
			}
			//查询会员等级返利数据
			if ($parent_info['level']!=1 && !empty($parent_info)) { //上一级是普通会员则不反钱
				//计算返利金额
				$goods = $this->goods();
					$commission = $goods['shop_price'] * ($fanli['rate'] / 100) * $this->goodNum;
					//计算佣金
					//按上一级等级各自比例分享返利
					$bool = M('users')->where('user_id', $user_info['first_leader'])->setInc('user_money', $commission);
					if (($bool !== false) && $commission) {
							$desc = "分享返利";
							$log = $this->writeLog($user_info['first_leader'], $commission, $desc, 1); //写入日志
							//检查返利管理津贴
						//  $this->jintie($user_info['first_leader'],$commission);
							//return true;
					} else {
							return false;
					}
			} else {
					return false;
			}     
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
		if( $order['pay_status']==1 && $user_info['level']==1)//自动升级vip
		{
              $res = M('users')->where(['user_id'=>$user_id])->update(['level'=>2]);
              	$desc = "购买产品成为vip";
	        	$log = $this->writeLog_ug($user_info['user_id'],'',$desc,2); //写入日志
		}
		else if($this->goodId==$this->tgoodsid  && $order['pay_status']==1 && $user_info['level']<3)//自动升级店主
		{return;
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
		//获得管理津贴
	public  function jintienew($user_id,$fanli_money)
	{
      //只有总监和大区获得管理津贴
		//查询上上级信息
		$user_info = M('users')->where('user_id',$user_id)->field('level,user_id')->find();

			$fanli = M('user_level')->where('level',$user_info['level'])->field('lead_reward')->find();
			 $commission = $fanli_money * ($fanli['lead_reward'] / 100);

	          //按上一级等级各自比例分享返利
	       $bool = M('users')->where('user_id',$user_info['user_id'])->setInc('user_money',$commission);
	       	$desc = "平级领导奖";
	        $log = $this->writeLog($user_info['user_id'],$commission,$desc,5); //写入日志
		

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
			 $fanli = M('user_level')->where('level',$p_parent_info['level'])->field('y_reward')->find();
			 $commission = $fanli['y_reward']; //计算金额
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
			  $fanli = M('user_level')->where('level',$p_p_parent_info['level'])->field('k_reward')->find();
			 $commission = $fanli['k_reward']; //计算金额
	          //按上一级等级各自比例分享返利
	        $bool = M('users')->where('user_id',$p_p_parent_info['user_id'])->setInc('user_money',$commission);
	       	$desc = "大区直属总监的店主邀店主获得金额";
	        $log = $this->writeLog($p_p_parent_info['user_id'],$commission,$desc,6); //写入日志
		}


	}
	//记录日志
	public function writeLog($userId,$money,$desc,$states,$frozen_money=0)
	{
		if(!$userId)return;
		$data = array(
			'user_id'=>$userId,
			'user_money'=>$money,
			'frozen_money' => $frozen_money,
			'change_time'=>time(),
			'desc'=>$desc,
			'order_sn'=>$this->orderSn,
			'order_id'=>$this->orderId,
			'log_type'=>$states
		);

		$bool = M('account_log')->insert($data);


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
		//总监，大区无限代返利
	public function upzdmoney($user_id)
	{ return;
		$three =0;
	    $zongjing =0;
	    $four = 0;
	    $pingji_4 =0;
	    $pingji_5 =0;
		//查询上级信息
		$parent_info = M('users')->where('user_id',$user_id)->field('level,user_id,first_leader')->find();
		//查询上上级信息
		$p_parent_info = M('users')->where('user_id',$parent_info['first_leader'])->field('level,user_id,first_leader')->find();
		if($parent_info['level']==4 && $p_parent_info['level']==5)
		{
			 $fanli = M('user_level')->where('level',$p_parent_info['level'])->field('s_reward')->find();
			 $commission_n = $fanli['s_reward']; //计算金额
	          //按上一级等级各自比例分享返利
	        $bool = M('users')->where('user_id',$p_parent_info['user_id'])->setInc('user_money',$commission_n);
	       	$desc = "大区直属总监邀店主获得金额";
	        $log = $this->writeLog($p_parent_info['user_id'],$commission_n,$desc,6); //写入日志
	        $first_leader = $this->newgetAllUps($user_id);
	        foreach($first_leader as $k=>$v)
	        {
	          if($v['level']==5 &&  $p_parent_info['user_id']!=$v['user_id'])   //处理总监返利
			  {
			  	if($k>=1)
			  	{
			  		  $fanli = M('user_level')->where('level',$v['level'])->field('s_reward')->find();
						 $commission = 50; //计算金额
				          //按上一级等级各自比例分享返利
				         $bool = M('users')->where('user_id',$v['user_id'])->setInc('user_money',$commission);
				       	 $desc = "大区平级奖";
				         $log = $this->writeLog($v['user_id'],$commission,$desc,6); //写入日志
                         $this->jintienew($v['user_id'],$commission_n);//平级领导奖
			  	     return false;
                     break;

			  	}
			
			  }

	        }

		}
		else
		{ 
           //循环无限代
			if(!empty($user_id))
			{
				$first_leader = $this->newgetAllUps($user_id);
			  foreach($first_leader as $ke=>$ye)
			 {
			 //	if($ke>=1) //从上上级开始
			 	//{
			 $next_k =$ke+1 ;
			 //特殊情况 上级就是总监，跳到到总监程序
			 if($parent_info['level']==4)
			 {
			 	$three =1;
			 } 

			  if($ye['level']==4 && $three<2 && $error!=1)   //处理总监返利
			  {

				  if($first_leader[$ke]['level']==$first_leader[$next_k]['level'] && $pingji_4!=1&&$three!=1) //处理评级奖
				     {
                     
					     $fanli = M('user_level')->where('level',$first_leader[$next_k]['level'])->field('s_reward')->find();
				     	
				        $commission = 30; //计算金额
				          //按上一级等级各自比例分享返利
				        $bool = M('users')->where('user_id',$first_leader[$next_k]['user_id'])->setInc('user_money',$commission);
				        //$pingji_user =$first_leader[$ke+1];
				       	$desc = "总监平级奖";
				        $log = $this->writeLog($first_leader[$next_k]['user_id'],$commission,$desc,6); //写入日志

				         $three =$three+1;

                        if($parent_info['level']!=4) 
                        {
					         $fanli = M('user_level')->where('level',$ye['level'])->field('y_reward')->find();
					        $commission = $fanli['y_reward']; //计算金额
				          //按上一级等级各自比例分享返利
				        	$bool = M('users')->where('user_id',$ye['user_id'])->setInc('user_money',$commission);
				       		$desc = "总监直属店主邀店主获得金额";
				        	$log = $this->writeLog($ye['user_id'],$commission,$desc,6); //写入日志
				        	$three =$three+1;
                        }
				         $pingji_4 =1; //记录已经拿了平级奖
				         $three =$three+1;

				     }
				    elseif($three==1 && $pingji_4!=1)//评级奖
				     {
				     	if($ke>=1)
				     	{
					     $fanli = M('user_level')->where('level',$ye['level'])->field('s_reward')->find();
				     	
				        $commission = 30; //计算金额
				          //按上一级等级各自比例分享返利
				        $bool = M('users')->where('user_id',$ye['user_id'])->setInc('user_money',$commission);
				       	$desc = "总监平级奖";
				        $log = $this->writeLog($ye['user_id'],$commission,$desc,6); //写入日志
				        $pingji_4 =1;
				        $three =$three+1;
				        }
		
				     }
				     elseif($parent_info['level']!=4 && $pingji_4!=1)
				     {
				     	if($ke>=1)
				     	{
					     $fanli = M('user_level')->where('level',$ye['level'])->field('y_reward')->find();
					 	$commission = $fanli['y_reward']; //计算金额
			          //按上一级等级各自比例分享返利
			        	$bool = M('users')->where('user_id',$ye['user_id'])->setInc('user_money',$commission);
			       		$desc = "总监直属店主邀店主获得金额";
			        	$log = $this->writeLog($ye['user_id'],$commission,$desc,6); //写入日志
			        	$three =$three+1;
			            }
				     }
				   // if($ke>=1)
				    //{
				   	    $zongjing =1;
						//$three =$three+1;
				    //}
				
				}
				if($ye['level']==5 && $four<2)  //处理大区返利
				{
					 if($parent_info['level']==5)
					 {
	
					 	$four =1;
					 } 
					$f_1 =$first_leader[$ke]['level'];
					$f_2 =$first_leader[$next_k]['level'];
					if($f_1>$f_2)
					{
                         $error =1;
					}
			     
			     if($first_leader[$ke]['level']==$first_leader[$next_k]['level'] && $pingji_5!=1 &&$four!=1)
				   {
				   	
					     $fanli = M('user_level')->where('level',$ye['level'])->field('s_reward')->find();
						 $commission = 50; //计算金额
				          //按上一级等级各自比例分享返利
				         $bool = M('users')->where('user_id',$first_leader[$next_k]['user_id'])->setInc('user_money',$commission);
				       	 $desc = "大区平级奖";
				         $log = $this->writeLog($first_leader[$next_k]['user_id'],$commission,$desc,6); //写入日志
				          $four =$four+1;
				         if($parent_info['level']!=5)
				         {
						    if($zongjing==1)//证明这条线有总监和大区
							{
							
							 $fanli = M('user_level')->where('level',$ye['level'])->field('k_reward')->find();
							 $commission_z = $fanli['k_reward']; //计算金额
					          //按上一级等级各自比例分享返利
					         $bool = M('users')->where('user_id',$ye['user_id'])->setInc('user_money',$commission_z);
					       	 $desc = "大区直属总监的店主邀店主获得金额";
					       	 $log = $this->writeLog($ye['user_id'],$commission_z,$desc,6); //写入日志
					       	  $four =$four+1;
					       	   
							}else //只有大区
							{
								 $fanli = M('user_level')->where('level',$ye['level'])->field('y_reward')->find();
							 	 $commission_z = $fanli['y_reward']; //计算金额
					             //按上一级等级各自比例分享返利
					       		 $bool = M('users')->where('user_id',$ye['user_id'])->setInc('user_money',$commission_z);
					       		 $desc = "大区直属店主邀店主获得金额";
					       		 $log = $this->writeLog($ye['user_id'],$commission_z,$desc,6); //写入日志
					       		 $four =$four+1;
							}

				         }
				         if(!empty($commission_z))
		             	{
		             	   //平级领导奖
			 			   $this->jintienew($first_leader[$next_k]['user_id'],$commission_z);//平级领导奖
		             	}
				         $pingji_5 =1;
				       				      
				   }elseif($four==1 && $pingji_5!=1)//评级奖
				   {
				      if($ke>=1)
				      {
					     $fanli = M('user_level')->where('level',$ye['level'])->field('s_reward')->find();
						 $commission = 50; //计算金额
				          //按上一级等级各自比例分享返利
				         $bool = M('users')->where('user_id',$ye['user_id'])->setInc('user_money',$commission);
				       	 $desc = "大区平级奖";
				         $log = $this->writeLog($ye['user_id'],$commission,$desc,6); //写入日志
				         if(!empty($commission_z))
		             	{
		             	  //平级领导奖
			 			  $this->jintienew($ye['user_id'],$commission_z);//平级领导奖

		             	}
	
				         $pingji_5 =1;
				         $four =$four+1;
				      }
				
				   }
				   elseif($parent_info['level']!=5 && $pingji_5!=1)
				   {

				     if($zongjing==1)//证明这条线有总监和大区
					{
						if($ke>=1)
				      {

					 	$fanli = M('user_level')->where('level',$ye['level'])->field('k_reward')->find();
					 	$commission_z = $fanli['k_reward']; //计算金额
			          //按上一级等级各自比例分享返利
			        	$bool = M('users')->where('user_id',$ye['user_id'])->setInc('user_money',$commission_z);
			       		$desc = "大区直属总监的店主邀店主获得金额";
			       		 $log = $this->writeLog($ye['user_id'],$commission_z,$desc,6); //写入日志
			       		 $four =$four+1;
			       	   }

					}else //只有大区
					{
					    if($ke>=1)
				        {
						 $fanli = M('user_level')->where('level',$ye['level'])->field('y_reward')->find();
					 	 $commission_z = $fanli['y_reward']; //计算金额
			             //按上一级等级各自比例分享返利
			       		 $bool = M('users')->where('user_id',$ye['user_id'])->setInc('user_money',$commission_z);
			       		 $desc = "大区直属店主邀店主获得金额";
			       		 $log = $this->writeLog($ye['user_id'],$commission_z,$desc,6); //写入日志
			       		 $four =$four+1;
			       		}

					}

				   }
		             if($parent_info['level']==5)
		             {
		             	if($ke>=1)
		             	{
		             	//平级领导奖
				        $fanli = M('user_level')->where('level',$parent_info['level'])->field('reward')->find();
			 			$commission = $fanli['reward']; //计算金额
			 			$this->jintienew($ye['user_id'],$commission);//平级领导奖

		             	}
		             
		             }
				}

			 	//}

			  }
			}
		}

	}

	public function pingji($user_id)
	{
			//查询上级信息
		$parent_info = M('users')->where('user_id',$user_id)->field('level,user_id,first_leader')->find();
		//查询上上级信息
		$p_parent_info = M('users')->where('user_id',$parent_info['first_leader'])->field('level,user_id,first_leader')->find();
		if($parent_info['level']==4 && $p_parent_info['level']==4) //总监评级奖励
		{
             $fanli = M('user_level')->where('level',$p_parent_info['level'])->field('s_reward')->find();
			 $commission = 30; //计算金额
	          //按上一级等级各自比例分享返利
	        $bool = M('users')->where('user_id',$p_parent_info['user_id'])->setInc('user_money',$commission);
	       	$desc = "总监平级奖";
	        $log = $this->writeLog($p_parent_info['user_id'],$commission,$desc,6); //写入日志
		}
		elseif($parent_info['level']==5 && $p_parent_info['level']==5) //总监评级奖励
		{
             $fanli = M('user_level')->where('level',$p_parent_info['level'])->field('s_reward')->find();
			 $commission = 50; //计算金额
	          //按上一级等级各自比例分享返利
	        $bool = M('users')->where('user_id',$p_parent_info['user_id'])->setInc('user_money',$commission);
	       	$desc = "大区平级奖";
	        $log = $this->writeLog($p_parent_info['user_id'],$commission,$desc,6); //写入日志
		}
	}

 /*
 * 获取所有上级
 */
   public function newgetAllUps($invite_id,&$userList=array())
  {           
      $field  = "user_id,first_leader,mobile,level";
      $UpInfo = M('users')->field($field)->where(['user_id'=>$invite_id])->find();
      if($UpInfo)  //有上级
      {
          $userList[] = $UpInfo;
          $this->newgetAllUps($UpInfo['first_leader'],$userList);

      }
      
      return $userList;     
      
	}

	/**
	 * 秒杀返利
	 */
	private function set_flash_sale_commission($user_id,$commissioninfo,$status){

		if(!$commissioninfo)return;
		$desc = "下级秒杀".$this->goodNum.'件返利';
		if($this->goodNum == 1){
			$commission = $commissioninfo['one_commission'];
			$not_money  = $commissioninfo['not_money1'];
		}elseif($this->goodNum == 2){
			$commission = $commissioninfo['tow_commission'];
			$not_money  = $commissioninfo['not_money2'];
		}
		if(!$commission && !($commission-$not_money) && !$not_money)return;
		$Users = M('Users');
		$log = $this->writeLog($user_id,($commission-$not_money),$desc,$status,$not_money); 
		$Users->where(['user_id'=>$user_id])->setInc('user_money',$commission-$not_money);
		$Users->where(['user_id'=>$user_id])->setInc('frozen_money',$not_money);		
	}

	//多返一级
	private function next_lev_commission($commissioninfo,$leader,$status,$level){
			//多返一级
			/*
			$first_leader = M('Users')->where(['user_id'=>$leader['user_id']])->value('first_leader');
			$level = $first_leader ? M('Users')->where(['user_id'=>$first_leader])->value('level') : 0;	
			$leader5 = ['user_id'=>($first_leader ? $first_leader : 0),'level'=>$level];
			*/
			$UsersLogic = new \app\common\logic\UsersLogic();
			$leader5 = $UsersLogic->getUserLevTop($leader['user_id'],$level);
			if($leader5['user_id']){
				$this->set_flash_sale_commission($leader5['user_id'],$commissioninfo,$status);
				return true;
			}		
			return false;
	}
	

	/**
	 * 秒杀返利
	 */
	private function flash_sale_commission(){
	
		//判断是否已经返利了
		$con['log_type'] = array('egt',90);
		$is_exits = M('account_log')->where(['order_id'=>$this->orderId])->where($con)->find();
		if($is_exits){
			//已经返利了
			// dump('already');
			// exit;
        	return json_encode(['status' => 0, 'msg' => '已经返利了' ],JSON_UNESCAPED_UNICODE );
		}
		
		//获取下单用户上级的级别
		$UsersLogic = new \app\common\logic\UsersLogic();
		$leader = $UsersLogic->getUserLevTop($this->userId,3);
		$FlashSaleCommission = M('flash_sale_commission');
		
		if(!$leader['user_id']){
			$leader = $UsersLogic->getUserLevTop($this->userId,4);	
			if(!$leader['user_id']){
				$leader = $UsersLogic->getUserLevTop($this->userId,5);	
				if(!$leader['user_id']){
					$leader = $UsersLogic->getUserLevTop($this->userId,6);	
					if(!$leader['user_id']){
						$first_leader = M('Users')->where(['user_id'=>$this->userId])->value('first_leader');	
						$level = $first_leader ? M('Users')->where(['user_id'=>$first_leader])->value('level') : 0;	
						$leader = ['user_id'=>($first_leader ? $first_leader : 0),'level'=>$level];
					}
				}
			}
		}

		//  dump($leader);exit;
		//  array(2) {
		// 		["user_id"] => int(3915)
		// 		["level"] => int(1)
		//   }

		if(!$leader['user_id'])return;
		
		$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>$leader['level']])->find();
		if($leader['level'] == 3){
			$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,90);
			$leader = $UsersLogic->getUserLevTop($this->userId,4);
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>4])->find();
			$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,94);

			$leader = $UsersLogic->getUserLevTop($this->userId,5); 
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>5])->find();
			$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,95);
			//多返一级
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>-5])->find();	
			$nfl = $this->next_lev_commission($commissioninfo,$leader,96,5);	

			$leader = $UsersLogic->getUserLevTop($this->userId,6);
			if(!$nfl)$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,96);
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>6])->find();
			$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,95);

			//多返一级
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>-6])->find();
			$this->next_lev_commission($commissioninfo,$leader,96,6);			
		}elseif($leader['level'] == 4){
			$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,90);
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>3])->find();
			$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,91);

			$leader = $UsersLogic->getUserLevTop($this->userId,5);
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>5])->find();
			$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,95);
			//多返一级
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>-5])->find();
			$nfl = $this->next_lev_commission($commissioninfo,$leader,96,5);

			$leader = $UsersLogic->getUserLevTop($this->userId,6); 
			if(!$nfl)$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,96);
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>6])->find();
			$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,95);

			//多返一级
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>-6])->find();
			$this->next_lev_commission($commissioninfo,$leader,96,6);		
		}elseif($leader['level'] == 5){
			$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,90);

			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>4])->find();
			$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,92);
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>3])->find();
			$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,91);	
			//多返一级
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>-5])->find();
			$nfl = $this->next_lev_commission($commissioninfo,$leader,96,5);

			$leader = $UsersLogic->getUserLevTop($this->userId,6); 
			if(!$nfl)$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,96);
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>6])->find();
			$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,95);			
			//多返一级
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>-6])->find();
			$this->next_lev_commission($commissioninfo,$leader,93,6);	
		}elseif($leader['level'] == 6){
			$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,90);

			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>5])->find();
			$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,93);
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>-5])->find();
			$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,96);
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>4])->find();
			$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,92);
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>3])->find();
			$this->set_flash_sale_commission($leader['user_id'],$commissioninfo,91);	
	
			//多返一级
			$commissioninfo = $FlashSaleCommission->where(['flash_sale_id'=>$this->prom_id,'level'=>-6])->find();
			$this->next_lev_commission($commissioninfo,$leader,93,6);	
		}
		

		if($leader['level'] <= 2){
        	return json_encode(['status' => 0, 'msg' => '只返大于总代或总代以上级别的人' ],JSON_UNESCAPED_UNICODE );
		}

	}

	
}