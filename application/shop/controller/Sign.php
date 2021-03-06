<?php
/**
 * 签到.
 */
namespace app\shop\controller;
use app\common\model\Users;
use app\common\model\Config;
use think\Db;

class Sign extends MobileBase
{
    public $user_id = 0;
    public $user = array();

    /*
    * 初始化操作
    */
    public function _initialize()
    {
        parent::_initialize();
        if (!session('user')) {
            header('location:'.U('Mobile/User/login'));
            exit;
        }

        $user = session('user');
        $user = M('users')->where("user_id", $user['user_id'])->find();
        session('user', $user);  //覆盖session 中的 user
        $this->user = $user;
        $this->user_id = $user['user_id'];
    }

    public function index()
    {

        $model = new Config();
        $config['sign_rule'] = $model->where(['name'=>'sign_rule'])->value('value');
        $user_id = session('user.user_id');
        $this->assign('config', $config);
        $this->assign('user', $this->user);

        return $this->fetch();
    }

    // 签到规则
    public function sign_rule()
    {

        return $this->fetch();
    }

    public function ajaxReturn($data){
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($data,JSON_UNESCAPED_UNICODE));
    }

    /**
     * 签到.
     */
    public function sign()
    {
        $user_model = new Users();
        $user_id = I('user_id');

        if (!$user_id) {
            return $this->ajaxReturn(['status' => -1, 'msg' => '签到user_id不能为空']);
        }
        $con['sign_day'] = array('like', date('Y-m-d', time()).'%');
        $cunzai = M('sign_log')->where(['user_id' => $user_id])->where($con)->find();
        $date = $this->deal_time(date('Y-m-d H:i:s', time()));
        if ($cunzai) {
            return $this->ajaxReturn(['status' => 1, 'msg' => '今日已签到', 'date' => $date]);
        }

        Db::startTrans();
        try{

            $signID = Db::name('sign_log')->insertGetId(['user_id' => $user_id, 'sign_day' => date('Y-m-d H:i:s')]);
            $user = $user_model->where(['user_id' => $user_id])->field('level,is_agent,super_nsign,is_distribut')->find();

            //查询签到送积分是否开启
            $sign_on_off = M('config')->where(['name' => 'sign_on_off'])->value('value');

            if ($sign_on_off == 1) {

                //赠送积分
                $agent_free_num = $user_model->where(['user_id' => $user_id])->setInc('pay_points', $user['userLevel']['get_integral'] ? $user['userLevel']['get_integral'] : 0);

                //积分变动日志
                $accountLogData = [
                    'user_id' => $user_id,
                    'pay_points' => $user['userLevel']['get_integral'] ? $user['userLevel']['get_integral'] : 0,
                    'change_time' => time(),
                    'desc' => '签到赠送',
                    'order_sn'=>'',
                    'order_id'=>$signID,
                    'log_type'=>8,
                ];
                Db::name('account_log')->insert($accountLogData);

            }

            // 提交事务
            Db::commit();
            return $this->ajaxReturn(['status' => 1, 'msg' => '今日签到成功', 'date' => $date,'a'=> $agent_free_num,'d'=>$distribut_free_num,'add_points'=>$accountLogData['pay_points']]);

        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->ajaxReturn(['status' => -1, 'msg' => '签到失败', 'date' => $date]);

        }

    }


    /**
     * 获取签到的日期列表.
     */
    public function get_sign_day()
    {
        $user_id = I('user_id');
        if (!$user_id) {
            return $this->ajaxReturn(['status' => -1, 'msg' => 'user_id不能为空', 'data' => '']);
        }
        $list = M('sign_log')->where(['user_id' => $user_id])->field('sign_day')->select();
        foreach ($list as $k => $v) {
            $data[$k] = $this->deal_time($v['sign_day']);
        }

        $con['sign_day'] = array('like', date('Y-m-d', time()).'%');
        $cunzai = M('sign_log')->where(['user_id' => $user_id])->where($con)->find();

        if ($cunzai) {
            $today_sign = true;
        } else {
            $today_sign = false;
        }

        //当前积分
        $points = M('users')->where(['user_id' => $user_id])->value('pay_points');
        //连续签到几天
        $continue_sign = continue_sign($user_id);

        //签到积分
        $add_point = (int) M('config')->where(['name' => 'sign_integral'])->value('value');

        //签到规则

        $rule = M('config')->where(['name' => 'sign_rule'])->value('value');

        //拢共签到几天
        $accumulate_day = count($data);

        //检查权限
        $auth = $this->check_auth($user_id);

        return $this->ajaxReturn(
            ['status' => 1,
                'msg' => '获取成功',
                'data' => $data,
                'today_sign' => $today_sign,
                'points' => $points,
                'add_point' => $add_point,
                'continue_sign' => $continue_sign,
                'accumulate_day' => $accumulate_day,
                'note' => $rule,
                'auth' => $auth,
            ]);
    }

    /**
     * 检查签到权限.
     */
    private function check_auth($user_id)
    {
        //检查身份
        $is_ok = M('config')->where(['name' => '1sign_require_level'])->field('value')->find();
        if ($this->user_id >= $is_ok ) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * 处理时间.
     */
    private function deal_time($time)
    {
        //
        $m=date('m',strtotime($time));
        $y=date('Y',strtotime($time));
        $d=date('d',strtotime($time));
        $newtime="$y-$m-$d";
//        $time = strtotime("$time -1 month");
        //前端要求  减去 1个月
//        $time = date('Y-m-d', $time);

        return str_replace('-', '/', $newtime);
    }

    //仅供生成连续签到奖品次数用的获取连续签到数
    public function goods_continue_sign($user_id, $sign_mark)
    {
        //定义时间戳
        date_default_timezone_set('Asia/Shanghai');
        //先看一下今天有没有签到
        $con['sign_day'] = array('like', date('Y-m-d', time()).'%');
        $cunzai = M('sign_log')->where(['user_id' => $user_id])->where($con)->find();
        if ($cunzai) {
            $todaySign = 1;
        } else {
            $todaySign = 0;
        }
        //再看之前的签到时间
        //只查询签到标志为0的记录
        $list = M('sign_log')->where(['user_id' => $user_id, "$sign_mark" => 0])->order('sign_day desc')->field('sign_day')->select();
        //对所有的签到时间进行时间戳然后倒序排序
        $array = array();
        foreach ($list as $key => $value) {
            $array[] = strtotime($value['sign_day']);
        }

        //定义连续签到次数
        $countSign = $todaySign;
        //依次判断所有的时间戳是否在指定范围内，例如第一个应该在昨天00:00:00-23:59:59之前，如果在则$countSign+1,否则跳出循环
        //定义昨天的时间戳范围
        $begintime = strtotime(date('Y-m-d 00:00:00', time() - 86400));
        $endtime = strtotime(date('Y-m-d 23:59:59', time() - 86400));
        if ($todaySign == 1) {
            for ($i = 1; $i < count($array);) {
                //                echo $begintime."------".$array[$i]."---------".$endtime."+++++";
                if ($array[$i] >= $begintime && $array[$i] <= $endtime) {
                    ++$countSign;
                    $begintime -= 86400;
                    $endtime -= 86400;
                } else {
                    break;
                }
                ++$i;
            }
        } else {
            for ($k = 0; $k < count($array);) {
                if ($array[$k] >= $begintime && $array[$k] <= $endtime) {
                    ++$countSign;
                    $begintime -= 86400;
                    $endtime -= 86400;
                } else {
                    break;
                }
                ++$k;
            }
        }

        return $countSign;
    }
    
}
