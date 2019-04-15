<?php


namespace app\admin\controller;

use app\admin\logic\OrderLogic;
use app\common\model\UserLabel;
use think\AjaxPage;
use think\console\command\make\Model;
use think\Page;
use think\Verify;
use think\Db;
use app\admin\logic\UsersLogic;
use app\common\logic\MessageTemplateLogic;
use app\common\logic\GoodsLogic;
use app\common\logic\MessageFactory;
use app\common\model\Withdrawals;
use app\common\model\Users;
use app\common\model\AgentInfo;
use think\Loader;

class Preform extends Base {

  //  protected $year = $this->c_year();

    public function index(){
        return $this->fetch();
    }
    public function c_year()
    {
      return date('Y');
    }

    
    public function preform(){
      //$return = $this->upzdmoney(17725621);
     // print_R($return);exit;
      $where = [
            'u.level'=> ['IN','4,5'],
            
          ];
      $count = Db::name('users')->alias('u')->field('u.user_id')
             //->join('order od','u.first_leader=od.user_id','LEFT')
             ->where($where)->count();

       $Page = new Page($count,10);
        $users = Db::name('users')->alias('u')
             ->field('u.user_id,u.realname,u.mobile,u.nickname')
             ->order('user_id desc')
             ->where($where)
             ->limit($Page->firstRow,$Page->listRows)->select();
          $user_ids =array();
          $user_idcc =array();
         $year = date('Y');
         $season_1 = $this->getQuarterDate($year,1);//第一季度
         $season_2 = $this->getQuarterDate($year,2);//第一季度
         $season_3 = $this->getQuarterDate($year,3);//第一季度
         $season_4 = $this->getQuarterDate($year,4);//第一季度
         foreach($users as $k=>$v)
         {
            $datas =$this->getAlldp_p($v['user_id']);
            //print_r($datas);exit;
            foreach($datas as $kd=>$yd)
            {
              $userdata[]=$yd['user_id'];
            }
           // $userdata = Db::name('users')->where('first_leader='.$v['user_id'])->column('user_id');
          //  $v['team_par'] = $return;
            if(!empty($userdata)) {
                $user_ids = implode(',',$userdata);
            }else
            {
              $user_ids='';
            }
            $userdata =array();
             $total_1 = $this->jisuanyeji($user_ids,$season_1['st'],$season_1['et']); //
             $total_2 = $this->jisuanyeji($user_ids,$season_2['st'],$season_2['et']); //
             $total_3 = $this->jisuanyeji($user_ids,$season_3['st'],$season_3['et']); //
             $total_4 = $this->jisuanyeji($user_ids,$season_4['st'],$season_4['et']); //
            $return['first'] = !empty($total_1)?$total_1[0]['sale_amount']:0;

            $return['two'] = !empty($total_2)?$total_2[0]['sale_amount']:0;;//第二季度
            $return['thiree'] = !empty($total_3)?$total_3[0]['sale_amount']:0;;//第三季度
            $return['four'] = !empty($total_4)?$total_4[0]['sale_amount']:0;;//第四季度
            $return['k'] =$k;
            $return['nickname'] =$v['nickname'];
            $return['user_id'] =$v['user_id'];
            $return['mobile'] =$v['mobile'];
            $new_list[]=$return;

           
         }
        $this->assign('new_list',$new_list);
        $this->assign('pager',$Page);
        $this->assign('p',I('p/d',1));
        $this->assign('page_size',$this->page_size);
   
        return $this->fetch();
    }
     /*
 * 获取所有上级
 */
   public function newgetAllUp($invite_id,&$userList=array())
  {           
      $field  = "user_id,first_leader,mobile,level";
      $UpInfo = M('users')->field($field)->where(['user_id'=>$invite_id])->find();
      if($UpInfo)  //有上级
      {
          $userList[] = $UpInfo;
          $this->newgetAllUp($UpInfo['first_leader'],$userList);

      }
      
      return $userList;     
      
  }
    //计算团队业绩--订单计算
    public function jisuanyeji($user_s=array(),$start_time,$end_time)
    {
          if(empty($user_s)) {
            return '';
          }
           $where_goods = [

            'od.order_status'=> ['notIN','3,5'],
           // 'og.is_send'    => 1,
            'og.prom_type' =>0,//只有普通订单才算业绩
            //'u.first_leader'=>$v['user_id'],
            //"og.goods_num" =>'>1',
            'od.user_id'=> ['IN',$user_s],
            'od.pay_status'=>1,
            'od.pay_time'    => ['Between',$start_time.','.$end_time],
            
          ];
         $order_goods = Db::name('order_goods')->alias('og')
             ->field('u.first_leader,sum(og.goods_num*og.goods_price) as sale_amount')
             ->where($where_goods)
              ->order('goods_id DESC')
              ->join('order od','od.order_id=og.order_id','LEFT')
              ->join('users u','u.user_id=od.user_id','LEFT')
            // ->limit($Page->firstRow,$Page->listRows)
             ->select();

         return $order_goods;

    }

    public function checklog()
    {
        $start_time = strtotime(0);
        $end_time = time();
        if(IS_POST){
            $start_time = strtotime(I('start_time'));
            $end_time = strtotime(I('end_time'));
        }
        $count = M('account_log')->alias('acount')->join('users', 'users.user_id = acount.user_id')
                    ->whereTime('acount.change_time', 'between', [$start_time, $end_time])
                   // ->where("acount.states = 101 or acount.states = 102")
                    ->count();
        $page = new Page($count, 10);
        $log = M('account_log')->alias('acount')->join('users', 'users.user_id = acount.user_id')
                               ->field('users.nickname, acount.*')->order('log_id DESC')
                               ->whereTime('acount.change_time', 'between', [$start_time, $end_time])
                               //->where("acount.states = 101 or acount.states = 102")
                               ->limit($page->firstRow, $page->listRows)
                               ->select();
        
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
        $this->assign('pager', $page);
        $this->assign('log',$log);
        return $this->fetch();
    }
    //触发发送奖金
    public function send_reward()
    {
       
      $jidu = I('seaon');
      $year = date('Y');
      $season = $this->getQuarterDate($year,$jidu);//第一季度
      $reward_log = Db::name('reward_log')->where(['year'=>$year,'quarter'=>$jidu])->find(); //分红列表
    //print_r(Db::name('reward_log')->getlastsql());exit;
      $time =time();
      
      if($time<$season['et'])
      {
         $this->ajaxReturn(['status' => 0,'msg'   => '没到时间发放']);
      }
      if($reward_log)
      {
         $this->ajaxReturn(['status' => 0,'msg'   => '第'.$jidu.'季度奖金已发放']);
      }

       $where_goods = [
            'od.order_status'=> ['notIN','3,5'],
            'od.pay_time'    => ['Between',$season['st'].','.$season['et']],
           // 'og.is_send'    => 1,
            'og.prom_type' =>0,//只有普通订单才算业绩
            //'u.first_leader'=>$v['user_id'],
            //"og.goods_num" =>'>1',
           // 'od.user_id'=> ['IN',$user_s],
             'od.pay_status'=>1,
            
          ];
         $order_goods = Db::name('order_goods')->alias('og')
             ->field('u.first_leader,u.user_id,og.order_id,og.goods_num*og.goods_price as sale_amount')
             ->where($where_goods)->order('user_id DESC')
              ->join('order od','od.order_id=og.order_id','LEFT')
              ->join('users u','u.user_id=od.user_id','LEFT')
            // ->limit($Page->firstRow,$Page->listRows)
             ->select();
           // print_r(Db::name('order_goods')->getlastsql());exit;
          $parent =array();
          $p_y  =array();
          $s_y  =array();
          
          foreach($order_goods as $k=>$v)
          {
            /*
            if(!empty($v['first_leader']))  //统计订单用户上级业绩
            {
            // echo $v['sale_amount'];
              $p_y[$v['first_leader']]['team_par']+= $v['sale_amount'];
             
              $p_parent = Db::name('users')->alias('u')
              ->where('user_id='.$v['first_leader'])
              ->field('u.user_id,u.first_leader')
              ->find();

              if(!empty($p_parent['first_leader']))
              {
                 // $s_y[$p_parent['first_leader']][$v['first_leader']][$v['user_id']]['team_par'][]= $v['sale_amount'];
                $s_y[$p_parent['first_leader']]['team_par']+= $v['sale_amount'];
                
              }
              
            }*/

            //无限找上上级
            if(!empty($v['first_leader']))  //统计订单用户上级业绩
            {
            $user_data = $this->getAllUp_p($v['user_id']);
            if(!empty($user_data) && count($user_data)>1)
            {
              unset($user_data[0]);
              foreach($user_data as $kk=>$yy)
            {
              $p_y[$yy['user_id']]+=$v['sale_amount'];

            }
            }
       
            }
          }
        if(empty($p_y))
        {
          $this->ajaxReturn(['status' => 0,'msg'   => '第'.$jidu.'季度没有业绩分红']);exit;
        }
        //var_dump($p_y);exit;
        $result=$this->count_userreward_new($p_y,$jidu);
        if($result){
            //设置成功后跳转页面的地址，默认的返回页面是$_SERVER['HTTP_REFERER']
                $this->ajaxReturn(['status'=>1,'msg'=>'发放成功','result'=>'']);
        } else {
            //错误页面的默认跳转页面是返回前一页，通常不需要设置
            $this->ajaxReturn(['status' => 0,'msg'   => '发放失败']);
        }
       

         //return $order_goods;
    }
        /*计算每个用户应该发的奖金*/
   public function count_userreward_new($p_y,$jidu){
     $list = Db::name('share')->order('grade','desc')->select(); //分红列表
      $commission='';
      if(!empty($p_y))
      {
        foreach($p_y as $k=>$v)
        {
         $user_data =Db::name('users')->where('first_leader='.$k)->column('user_id');//查出没下级
         
         if(!empty($user_data))
         {
                foreach($p_y as $py=>$ss)
                {

                   if(in_array($py,$user_data))// 证明有下级
                   {
                     
                      $steam_p_new+=$ss;

                   }
                }
                if($steam_p_new>0)
                  {
                    foreach($list as $kk=>$vv)
                      {
  
                      if($p_y[$k]>=$vv['lower'] && $p_y[$k]<$vv['upper'])
                      {
                        $team_p = $p_y[$k]* ($vv['rate'] / 100);//上上级业绩
                     
                      }
                      if($steam_p_new>=$vv['lower'] && $steam_p_new<$vv['upper'])
                      {
                         $steam_p =$steam_p_new* ($vv['rate'] / 100);
                      }
                       $commission = $team_p -$steam_p;
               
                     }
                     //发放业绩分红，记录
                      if(!empty($commission) && $commission>0)
                      {
                        $bool = M('users')->where('user_id',$k)->setInc('user_money',$commission);
                         if ($bool !== false) {
                         $desc = "第".$jidu."季度业绩分红";
                        $log = $this->writeLog($k,$commission,$desc,$jidu,$this->c_year(),$team_p,$steam_p); //写入日志
                       $log = $this->writeLog_user($k,$commission,$desc,-1); //写入日志
                       } else {
                        return false;
                       }

                     }

                  }else
                  {
                     foreach($list as $kk=>$vv)
                  {
                      if($p_y[$k]>=$vv['lower'] && $p_y[$k]<$vv['upper'])
                      {
                        $team_p = $p_y[$k]* ($vv['rate'] / 100);//上上级业绩
                     
                      }
                
                  }
                        $commission = $team_p -0;
                       //发放业绩分红，记录
                      if(!empty($commission) && $commission>0)
                      {
                        $bool = M('users')->where('user_id',$k)->setInc('user_money',$commission);
                         if ($bool !== false) {
                         $desc = "第".$jidu."季度业绩分红";
                        $log = $this->writeLog($k,$commission,$desc,$jidu,$this->c_year(),$team_p,0); //写入日志
                       $log = $this->writeLog_user($k,$commission,$desc,-1); //写入日志
                       } else {
                        return false;
                       }
                     }

                  }
                  $steam_p_new =0;
                      
         }else
         {

              foreach($list as $kk=>$vv)
                  {
                      if($p_y[$k]>=$vv['lower'] && $p_y[$k]<$vv['upper'])
                      {
                        $team_p = $p_y[$k]* ($vv['rate'] / 100);//上上级业绩
                     
                      }
                      $commission = $team_p -0;
            
                  }
                             //发放业绩分红，记录
                      if(!empty($commission) && $commission>0)
                      {

                        $bool = M('users')->where('user_id',$k)->setInc('user_money',$commission);
                      
                         if ($bool !== false) {
                         $desc = "第".$jidu."季度业绩分红";
                        $log = $this->writeLog($k,$commission,$desc,$jidu,$this->c_year(),$team_p,0); //写入日志
                       $log = $this->writeLog_user($k,$commission,$desc,10); //写入日志
                       } else {
                        return false;
                       }
                     }
         }
             
       }
       return true;
      }

   }
    /*计算每个用户应该发的奖金*/
    public function count_userreward($p_y,$s_y,$jidu){

      $list = Db::name('share')->order('grade','desc')->select(); //分红列表
      $commission='';
      if(!empty($p_y))
      {

        foreach($p_y as $k=>$v)
        {

          //判断下下级是否有业绩，减去下下级金额

          if(!empty($s_y[$k]) && $p_y[$k]['team_par']>$s_y[$k]['team_par'])
          {
            foreach($list as $kk=>$vv)
            {

              if($v['team_par']>0)
              {
                if($v['team_par']>=$vv['lower'] && $v['team_par']<=$vv['upper'])
                {
                   $team_p = $v['team_par']* ($vv['rate'] / 100);//上上级业绩
                   
                }
                if($s_y[$k]['team_par']>=$vv['lower'] && $s_y[$k]['team_par']<=$vv['upper'])
                {
                   $steam_p =$s_y[$k]['team_par']* ($vv['rate'] / 100);
                }

                $commission = $team_p -$steam_p;

              }
                //发放业绩分红，记录
              if(!empty($commission) && $commission>0)
              {
                    $bool = M('users')->where('user_id',$k)->setInc('user_money',$commission);
                  if ($bool !== false) {
                     $desc = "第".$jidu."季度业绩分红1";
                    $log = $this->writeLog($k,$commission,$desc,$jidu,$this->c_year(),$team_p,$steam_p); //写入日志
                   $log = $this->writeLog_user($k,$commission,$desc,10); //写入日志
                   } else {
                    return false;
                   }

              }
            }
          }else //下下没有业绩
          {

             foreach($p_y as $k=>$v)
             {
              foreach($list as $kk=>$vv)
              {
                if($v['team_par']>0)
                {

                 if($v['team_par']>=$vv['lower'] && $v['team_par']<=$vv['upper'])
                  {
                    $commission = $v['team_par']* ($vv['rate'] / 100);//上上级业绩
                   
                  }

                }
              }

              //发放业绩分红，记录
              if(!empty($commission))
              {
                    $bool = M('users')->where('user_id',$k)->setInc('user_money',$commission);
                  if ($bool !== false) {
                    $desc = "第".$jidu."季度业绩分红";
                    $log = $this->writeLog($k,$commission,$desc,$jidu,$this->c_year()); //写入日志
                    $log = $this->writeLog_user($k,$commission,$desc,10); //写入日志
                   } else {
                    return false;
                   }

              }

             }

          }
          return true;
        }

      }else
      {
        return false;
      }


    }

   //查看发放的分红
    public function checklog_reward()
    {
      $id = I('id');
        $start_time = strtotime(0);
        $end_time = time();
        if(IS_POST){
            $start_time = strtotime(I('start_time'));
            $end_time = strtotime(I('end_time'));
        }
        $count = M('account_log')->alias('acount')->join('users', 'users.user_id = acount.user_id')
                    ->where("acount.log_type=-1 and acount.user_id=".$id)
                   // ->where("acount.states = 101 or acount.states = 102")
                    ->count();
        $page = new Page($count, 10);
        $log = M('account_log')->alias('acount')->join('users', 'users.user_id = acount.user_id')
                               ->field('users.nickname, acount.*')->order('log_id DESC')
                               ->whereTime('acount.change_time', 'between', [$start_time, $end_time])
                               ->where("acount.log_type=-1 and acount.user_id=".$id)
                               ->limit($page->firstRow, $page->listRows)
                               ->select();
        
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
        $this->assign('pager', $page);
        $this->assign('log',$log);
        return $this->fetch();
    }

/*
 * 取某个季度的开始和结束时间
 *   $year 年份，如2014
 *   $season 季度，1、2、3、4
 */
  public function getQuarterDate($year,$season){
    $times = array();
    //$times['st'] = date('Y-m-d H:i:s', mktime(0, 0, 0,$season*3-3+1,1,$year));
    //$times['et'] = date('Y-m-d H:i:s', mktime(23,59,59,$season*3,date('t',mktime(0, 0 , 0,$season*3,1,$year)),$year));
    $times['st'] = mktime(0, 0, 0,$season*3-3+1,1,$year);
    $times['et'] =mktime(23,59,59,$season*3,date('t',mktime(0, 0 , 0,$season*3,1,$year)),$year);
    return $times;
  }

    //记录日志
  public function writeLog($userId,$money,$desc,$quarter,$year,$team_p=0,$steam_p=0)
  {
    $data = array(
      'user_id'=>$userId,
      'money'=>$money,
      'time'=>time(),
      'desc'=>$desc,
      'quarter'=>$quarter,
      'year'=>$year,
      'team_p'=>$team_p,
      'steam_p'=>$steam_p,
    );

    $bool = M('reward_log')->insert($data);
    
    return $bool;
  }
    //记录日志
  public function writeLog_user($userId,$money,$desc,$states)
  {
    $data = array(
      'user_id'=>$userId,
      'user_money'=>$money,
      'change_time'=>time(),
      'desc'=>$desc,
      //'order_sn'=>$this->orderSn,
      //'order_id'=>$this->orderId,
      'log_type'=>$states
    );

    $bool = M('account_log')->insert($data);



    
    return $bool;
  }

   /*
   * 获取所有上级
   */
   public function getAllUp_p($invite_id,&$userList=array())
  {           
      $field  = "user_id,first_leader";
      $UpInfo = M('users')->field($field)->where(['user_id'=>$invite_id])->find();
      if($UpInfo)  //有上级
      {
          $userList[] = $UpInfo;                                      
          $this->getAllUp_p($UpInfo['first_leader'],$userList);
      }
      //unset($userList[0]);
      return $userList;     
      
  }
     /*
   * 获取所有下级
   */
   public function getAlldp_p($invite_id,&$userList=array())
  {           
      $field  = "user_id";
      $UpInfo = M('users')->field($field)->where(['first_leader'=>$invite_id])->select();
     // if($UpInfo)  //有上级
      //{
         // $userList[] = $UpInfo;
         // $                                      
          //$this->getAlldp_p($UpInfo['user_id'],$userList);
      //}
      if($UpInfo)
      {
        foreach ($UpInfo as $key => $value) {
          $userList[] = $value;
          $this->getAlldp_p($value['user_id'],$userList);
        }
  
      }
      
      return $userList;
      
  }

      //获取用户的所有下级ID
   public  function get_downline($data,$mid,$level=0){
        $arr=array();
        foreach ($data as $key => $v) {
            if($v['first_leader']==$mid){  //pid为0的是顶级分类
                $v['level'] = $level+1;
                $arr[]=$v;
                $arr = array_merge($arr,$this->get_downline($data,$v['user_id'],$level+1));
            }
        }
        return $arr;
    }
    
}