<?php

namespace app\shop\controller;

use app\common\logic\Address;
use app\common\logic\CartLogic;
use app\common\logic\DistributLogic;
use app\common\logic\Message;
use app\common\logic\OrderLogic;
use app\common\logic\ShareLogic;
use app\common\logic\UserApply;
use app\common\logic\UsersLogic;
use app\common\model\UserAddress;
use app\common\model\UserMessage;
use app\common\model\Users as UserModel;
use app\common\model\UserStock;
use app\common\util\TpshopException;
use think\Cache;
use think\db;
use think\Image;
use think\Loader;
use think\Page;
use think\Verify;
use think\session;

class User extends MobileBase
{

    public $user_id = 0;
    public $user = array();

    /*
     * 初始化操作
     */
    public function _initialize()
    {

        parent::_initialize();
        if (session('?user')) {
            $User = new UserModel();
            $session_user = session('user');
            $this->user = $User->where('user_id', $session_user['user_id'])->find();
            if (!empty($this->user->auth_users)) {
                $session_user = array_merge($this->user->toArray(), $this->user->auth_users[0]);
                session('user', $session_user); //覆盖session 中的 user
            }
            $this->user_id = $this->user['user_id'];
            $this->assign('user', $this->user); //存储用户信息0
        }
        $nologin = array(
            'login', 'pop_login', 'do_login', 'logout', 'verify', 'set_pwd', 'finished',
            'verifyHandle', 'reg', 'send_sms_reg_code', 'find_pwd', 'check_validate_code',
            'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code', 'express', 'bind_guide', 'bind_account', 'bind_reg',
        );
        $is_bind_account = tpCache('basic.is_bind_account');
        if (!$this->user_id && !in_array(ACTION_NAME, $nologin)) {
            if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') && $is_bind_account) {
                header("location:" . U('Shop/User/bind_guide')); //微信浏览器, 调到绑定账号引导页面
            } else {
                header("location:" . U('Shop/User/login'));
            }
            exit;
        }

        $order_status_coment = array(
            'WAITPAY' => '待付款 ', //订单查询状态 待支付
            'WAITSEND' => '待发货', //订单查询状态 待发货
            'WAITRECEIVE' => '待收货', //订单查询状态 待收货
            'WAITCCOMMENT' => '待评价', //订单查询状态 待评价
        );
        $this->assign('order_status_coment', $order_status_coment);
    }

    public function index()
    {
        $map['user_id'] = $this->user_id;

        $this->user['visit_count'] = M('goods_visit')->where($map)->count();
        $this->user['collect_count'] = M('goods_collect')->where($map)->count();

        $level = Db::query('select level_name from tp_users as a,tp_user_level as b where a.level = b.level and a.user_id = ' . $this->user_id);
        $this->assign('level', $level);

        $leader = get_uper_users($this->user['first_leader']);
        $this->assign('leader', $leader);

        /*未读消息 */
        $message_logic = new Message();
        $message_logic->checkPublicMessage();

        $where = array(
            'user_id'   => $this->user_id,
            'deleted'   => 0,
            'category'  => 0,
            //'is_see'    => 1,
        );
        $userMessage = new UserMessage();
        $count = $userMessage->where($where)->count();
        $page = new Page($count, 5);

        $rec_id = $userMessage->where($where)->LIMIT($page->firstRow . ',' . $page->listRows)->order('rec_id desc')->column('rec_id');
        $msglists = $message_logic->sortMessageListBySendTime($rec_id, $type);  
        $this->assign('msglists', $msglists);   
        /*未读消息 */   

        //客服电话
        $phone = M('config')->where('inc_type','shop_info')->where('name','phone')->value('value');

        //获取团队借鉴团队数据里面的获取止推下级的方法
        $zhitui_sub = getAlldp($this->user_id);
        $zhitui_num = count($zhitui_sub);
        
        //当前登录用户信息
        $logic = new UsersLogic();
        $user_info = $logic->get_info($this->user_id);
        $order_info['belowWaitSend'] = $user_info['result']['belowWaitSend']; //下级待发货数
        $this->assign('order_info', $order_info);

        $this->assign('phone',$phone);
        $this->assign('zhitui_num',$zhitui_num);

        repair_leader();//修补上级ID

        return $this->fetch();
    }

    // 仓库管理
    public function store_manage()
    {   
        $third_leader = I('get.third_leader/d',0);
        //读取会员仓库信息
        if($third_leader)
            $kucun = user_kucun($third_leader);
        else
            $kucun = user_kucun($this->user_id);

        $GoodsLevelPrice = M('goods_level_price');
        foreach($kucun as $k=>$v){
            $price = $GoodsLevelPrice->where(['goods_id'=>$v['goods_id'],'level'=>$this->user['level']])->value('price');
            $price && ($kucun[$k]['shop_price'] = $price);
        }           

        $this->assign('kucun', $kucun);
        $this->assign('third_leader', $third_leader);
        return $this->fetch();
    }
    // 团队数据
    public function team_data()
    {
        /******我的上级*****/
        //直推上级
        $first_leader = M('users')->where('user_id', $this->user['first_leader'])->find();
        //平级上级
        $pinji_leader_first = find_prepareuserinfo($this->user['user_id'],2);
        //$pinji_leader_first = M('users')->field($field)->where(['user_id' => $this->user['balance_leader']])->find();
        //配货上级
        $peihuo_leader_first = find_prepareuserinfo($this->user['user_id']);
        //$peihuo_leader_first = M('users')->field($field)->where(['user_id' => $this->user['third_leader']])->find();
        /******我的配货下级*****/

        //$peihuo_sub = getAlldp1($this->user['user_id'], $this->user['level'],'third_leader');
        //$peihuo_sub = getAlldp($this->user['user_id'], $this->user['level']);
        $UsersLogic = new UsersLogic();
        $arr = $peihuo_sub = [];  
        $arr = $UsersLogic->getUserLevBotAll($this->user['user_id'],$this->user['level'],$arr);
        $Users = M('Users');
        foreach($arr as $k=>$v){
            $leader = find_prepareuserinfoID($v);
            if($leader == $this->user['user_id']){
                $peihuo_sub[] = $Users->field('user_id,level,nickname,mobile,head_pic,mobile')->find($v);
            }
        }
        /******直推下级*****/
        $zhitui_sub = getAlldp($this->user_id);
        //print_r($peihuo_sub);exit;

        $this->assign('user_info', $this->user); //用户信息
        $this->assign('pinji_leader_first', $pinji_leader_first);
        $this->assign('peihuo_leader_first', $peihuo_leader_first);
        $this->assign('first_leader', $first_leader);
        $this->assign('zhitui_sub', $zhitui_sub);
        $this->assign('peihuo_sub', $peihuo_sub);
        $this->assign('user_info', $this->user); //用户信息
        $this->assign('level', M('user_level')->getField('level,level_name')); //等级
        return $this->fetch();
    }
    // 邀请代理
    public function vite_agent()
    {   
        //if(!file_exists("public/upload/zhengshu/users/{$this->user_id}.jpg")){
            \think\Image::open('public/upload/zhengshu/zhengshu.jpg')->text($this->user['nickname'],'hgzb.ttf',32,'#000000',[505,540])->save("public/upload/zhengshu/users/{$this->user_id}.jpg"); 

            $level_name = M('User_level')->where(['level'=>$this->user['level']])->value('level_name');
            \think\Image::open("public/upload/zhengshu/users/{$this->user_id}.jpg")->text($level_name,'hgzb.ttf',30,'#000000',[430,1120])->save("public/upload/zhengshu/users/{$this->user_id}.jpg");
            
            if($this->user['realname'])
                \think\Image::open("public/upload/zhengshu/users/{$this->user_id}.jpg")->text($this->user['realname'],'hgzb.ttf',30,'#000000',[420,1185])->save("public/upload/zhengshu/users/{$this->user_id}.jpg");

            if($this->user['mobile'])
            \think\Image::open("public/upload/zhengshu/users/{$this->user_id}.jpg")->text($this->user['mobile'],'hgzb.ttf',30,'#000000',[530,1250])->save("public/upload/zhengshu/users/{$this->user_id}.jpg");    
        //}

        $levlist = M('user_level')->field('id,level,level_name')->where([ 'level' => ['gt', 2] ])->where([ 'level' => ['elt', $this->user['level']] ])->select();
        $this->assign('levlist', $levlist);
        $this->assign('pic', "/public/upload/zhengshu/users/{$this->user_id}.jpg?".rand(0,9999));
        return $this->fetch();
    }

    // 申请等级
    public function apply_grade()
    {
        $levlist = M('user_level')->field('id,level,level_name')->where(['level' => ['gt', $this->user['level']]])->select();
        $this->assign('levlist', $levlist);

        //收到的邀请数
        $num = M('apply')->where(['uid' => $this->user_id])->count();
        $this->assign('num', $num);

        return $this->fetch();
    }
    // 下级订单
    public function sub_order()
    {
        $user_id = input('user_id',10);
        $p = 1;
        $num = 50;
        if(!$user_id){
            $this->error('用户不存在');
        }
        $start_time = input('start_time');
        $end_time = input('end_time');
        if($start_time){
            $where['o.add_time'] = array('gt',strtotime($start_time));
        }
        if($end_time){
            $where['o.add_time'] = array('lt',strtotime($end_time));
        }
        $where['o.kucun_type'] = 1;
        $where['o.pay_status'] = 1;
        $where['o.user_id'] = $user_id;
        // {$good[goods_id]|goods_thum_images=100,100}
        $order_list = Db::name('order')->alias('o')->join('tp_order_goods og','og.order_id=o.order_id')->where($where)->field('o.order_id,o.order_sn,o.total_amount,o.add_time,og.goods_id,og.goods_price,og.goods_num,og.goods_name')->order('add_time desc')->page($p,$num)->select();
        //组建数组
        $new_order = array();
        foreach($order_list as $key=>$val){
            $new_order[$val['order_id']]['list'][] = $val;
            $new_order[$val['order_id']]['order_sn'] = $val['order_sn'];
            $new_order[$val['order_id']]['add_time'] = $val['add_time'];
            $new_order[$val['order_id']]['total_amount'] = $val['total_amount'];
            $new_order[$val['order_id']]['goods_num'] += $val['goods_num'];
            $new_order[$val['order_id']]['order_sn'] = $val['order_sn'];
        }
        $this->assign('new_order',$new_order);
        return $this->fetch();
    }

    //ajax获取下级订单的分页数据
    public function ajax_sub_order()
    {
        $user_id = input('user_id',0);
        $p = 1;
        $num = 50;
        if(!$user_id){
            $this->error('用户不存在');
        }
        $start_time = input('start_time');
        $end_time = input('end_time');
        if($start_time){
            $where['o.add_time'] = array('gt',strtotime($start_time));
        }
        if($end_time){
            $where['o.add_time'] = array('lt',strtotime($end_time));
        }
        $where['o.kucun_type'] = 1;
        $where['o.pay_status'] = 1;
        $where['o.user_id'] = $user_id;
        // {$good[goods_id]|goods_thum_images=100,100}
        $order_list = Db::name('order')->alias('o')->join('tp_order_goods og','og.order_id=o.order_id')->where($where)->field('o.order_id,o.order_sn,o.total_amount,o.add_time,og.goods_id,og.goods_price,og.goods_num,og.goods_name')->order('add_time desc')->page($p,$num)->select();
        //组建数组
        $new_order = array();
        foreach($order_list as $key=>$val){
            $new_order[$val['order_id']]['list'][] = $val;
            $new_order[$val['order_id']]['order_sn'] = $val['order_sn'];
            $new_order[$val['order_id']]['add_time'] = $val['add_time'];
            $new_order[$val['order_id']]['total_amount'] = $val['total_amount'];
            $new_order[$val['order_id']]['goods_num'] += $val['goods_num'];
            $new_order[$val['order_id']]['order_sn'] = $val['order_sn'];
        }
        $this->assign('new_order',$new_order);
        return $this->fetch();
    }

    // 订单发货
    public function order_send()
    {
        return $this->fetch();
    }



	
    // 上级仓库
    public function superior_store()
    {
        $logic = new UsersLogic();
        $data = $logic->get_info($this->user_id);
        $user = $data['result'];
        if ($user['mobile'] == '' && $user['email'] == '') {
            //$this->error('请先绑定手机号码', U('Shop/User/setMobile'));
        }
		$this->user['third_leader'] = findthird_leader($this->user_id, $this->user['level']);

        $goods_id = I('get.goods_id/d',0);
        $type = I('get.type/d',1);
        //if(!$goods_id)$this->error('参数错误');

        if($type == 1){
            header('Content-Type: text/html; charset=utf-8');
            exit("进货功能已关闭");
        }

        // 找配货上级
        $new_kucun = array();
        //$pei_parent = find_prepareuserinfo($this->user_id);
        if(($this->user['level'] <= 2) && !$goods_id && !$type){
            $this->redirect(U('User/store_manage',['third_leader'=>$this->user['third_leader']])); return;
        }
        /* if($goods_id && ($this->user['level'] <= 2)){
            $pei_parent = getThird_leader($this->user_id, $this->user['level'], $goods_id);
        }else  */
        $pei_parent = getThird_leader1($this->user_id, $this->user['level']);

        (($this->user['level'] > 2) && ($type == 2)) && $pei_parent = $this->user_id;
        if($pei_parent == $this->user_id){
            $title = '我的仓库';
        }elseif($pei_parent > 0){
            $title = '上级仓库';
        }else{
            $title = '系统仓库';
        }
       if(!$pei_parent)
        {
            $kucun = M("goods")->alias('g')
                ->field('g.store_count as nums,g.goods_name,g.goods_id,g.market_price as shop_price,g.original_img')
            //->join('users u','g.user_id=u.user_id','LEFT')
                ->where("is_on_sale=1 and prom_type <> 1")->select();
        } else { 
            $kucun = user_kucun($pei_parent);
        }

        $GoodsLevelPrice = M('goods_level_price');
        foreach($kucun as $k=>$v){
            $price = $GoodsLevelPrice->where(['goods_id'=>$v['goods_id'],'level'=>$this->user['level']])->value('price');
            $price && ($kucun[$k]['shop_price'] = $price);
        }

        //读取会员仓库信息

        //dump($kucun);exit;
        $this->assign('pei_parent', $pei_parent);
        $this->assign('kucun', $kucun);
        $this->assign('type', $type);
        $this->assign('title', $title);
        $this->assign('third_leader', I('get.third_leader/d',0) ? I('get.third_leader/d',0) : $pei_parent);
        return $this->fetch();
    }

    // 指定用户仓库
    public function user_store()
    {
        $applyid = I('get.applyid/d',0);
        $type = I('get.type/d',1);
        $model = ($type == 1) ? M('Apply') :  M('Apply_for');
        $applyinfo = $model->find($applyid);	
        if($applyinfo['uid'] != $this->user_id)$this->error('您无权限进入此仓库');

        $logic = new UsersLogic();
        $data = $logic->get_info($this->user_id);
        $user = $data['result'];
        if ($user['mobile'] == '' && $user['email'] == '') {
            //$this->error('请先绑定手机号码', U('Shop/User/setMobile'));
        }

        // 存找配货上级
        if(!$applyinfo['leaderid'])
        {
            $kucun = M("goods")->alias('g')
                ->field('g.store_count as nums,g.goods_name,g.goods_id,g.shop_price,g.original_img')
            //->join('users u','g.user_id=u.user_id','LEFT')
                ->where("is_on_sale=1 and g.prom_type=0")->select();
        } else { 
            $kucun = user_kucun($applyinfo['leaderid']);
        }        

        $GoodsLevelPrice = M('goods_level_price');
        foreach($kucun as $k=>$v){
            $price = $GoodsLevelPrice->where(['goods_id'=>$v['goods_id'],'level'=>$applyinfo['level']])->value('price');
            $price && ($kucun[$k]['shop_price'] = $price);
        }

        //读取会员仓库信息

        //dump($kucun);exit;
        $this->assign('pei_parent', $applyinfo['leaderid']);
        $this->assign('kucun', $kucun);
        $this->assign('applyid', $applyid);
        $this->assign('type', $type);
        return $this->fetch('superior_store');
    }

    // 我的佣金
    public function mommission()
    {
        $y = I('get.y/d',date('Y'));
        $m = I('get.m/d',date('m'));
        $day = 0;

        if(($y > 2000) && ($y < 2100) && ($m > 0) && ($m < 13)){
            //$day = cal_days_in_month(CAL_GREGORIAN, $m, $y);
            $day = date("t",strtotime("$y-$m"));
        }

        $Users = M('Users');
        //$tjxiajilist = $Users->field('user_id,nickname,level,mobile,third_leader,head_pic')->where(['first_leader'=>$this->user_id])->select();
        //$phxiajilist = $Users->field('user_id,nickname,level,mobile,third_leader,head_pic')->where(['third_leader'=>$this->user_id])->select();

        $Order = M('Order');
        $UserLevel = M('User_level');
        $num = $num1 = 0;
        $where = ['o.seller_id'=>$this->user_id,'o.order_status'=>['not in',[3,5]],'o.pay_status'=>1,'o.kucun_type'=>1];
        if($day)$where['o.add_time'] = ['between',[strtotime("$y-$m-01"),strtotime("$y-$m-01")+$day*24*3600]];

        $tjxiajilist = M('Order')->alias('o')->join('users u','o.user_id=u.user_id','left')->field('u.user_id,u.nickname,u.level,u.mobile,u.third_leader,u.head_pic')->where($where)->group('o.user_id')->select();
 
        foreach($tjxiajilist as $k=>$v){
            $where['o.user_id'] = $v['user_id'];
            $tjxiajilist[$k]['level_name'] = $UserLevel->where(['level'=>$v['level']])->value('level_name');
            $tjxiajilist[$k]['total_amount'] = M('Order')->alias('o')->where($where)->sum('total_amount');   
            $tjxiajilist[$k]['goods_num'] = M('Order')->alias('o')->join('order_goods og','o.order_id=og.order_id','left')->where($where)->sum('goods_num');   
            $num += $tjxiajilist[$k]['total_amount'];
        }/*
        foreach($phxiajilist as $k=>$v){
            $where['user_id'] = $v['user_id'];
            $phxiajilist[$k]['level_name'] = $UserLevel->where(['level'=>$v['level']])->value('level_name');
            $phxiajilist[$k]['total_amount'] = M('Order')->where($where)->sum('total_amount');   
            $num1 += $phxiajilist[$k]['total_amount'];
        }*/
        $this->assign('tjxiajilist',$tjxiajilist);
        //$this->assign('phxiajilist',$phxiajilist);
        $this->assign('num',$num);
        $this->assign('num1',$num1);
        $this->assign('ym',$y.'-'.$m);
        return $this->fetch();
    }

    // 授权vip
    public function empower_vip()
    {
        return $this->fetch();
    }

    //zp
    public function welfare_zp()
    {
        return $this->fetch();
    }

    public function wtf_Goodson()
    {
        return $this->fetch();
    }

    // 购物余额
    public function shopping_balance()
    {
        $this->assign('user', $this->user);
        return $this->fetch();
    }

    //余额转账明细
    public function money_exchange()
    {
        if($_GET['change_time']){
            $userid=$this->user_id;
            $account_log = M('account_log')->where(['log_type'=>6,'user_id'=>$userid,'change_time'=>$_GET['change_time']])
                ->limit(1)
                ->select();
                $this->assign('countList',$account_log);   
            return $this->fetch();
        }else{
            $userid=$this->user_id;
            $count = M('account_log')->where(['log_type'=>6,'user_id'=>$userid])->count();
            $Page = new Page($count,15);
            $account_log = M('account_log')->where(['log_type'=>6,'user_id'=>$userid])
                ->order('change_time desc')
                ->limit($Page->firstRow.','.$Page->listRows)
                ->select();
                $this->assign('countList',$account_log);   
            if ($_GET['is_ajax']) {
                return $this->fetch('ajax_money_exchange');
            }
            return $this->fetch();
        }
    }

    public function foerachuser(){
        $p = I('get.p/d',1);
        $num = I('get.num/d',200);
        $list = M('Users')->field('user_id,level')->limit(($p*$num) . ',' . $num)->select();
        foreach($list as $v){
            $balance_leader = findbalance_leader($v['user_id'],$v['level']);
            $third_leader = findthird_leader($v['user_id'],$v['level']);
            $res = M('users')->where(['user_id'=>$v['user_id']])->update(['balance_leader'=>$balance_leader,'third_leader'=>$third_leader]);
        }
        if(count($list) < $num)die('已到尾页');
    }

    // 购物余额转账
    public function transferAccounts()
    {

        $data = I('post.');

        if (!$this->user_id) {
            $this->ajaxReturn(['status' => 0, 'msg' => '请先登录', 'data' => null]);
        }

        if (encrypt($data['pay_pwd']) != $this->user['paypwd']) {

            $this->ajaxReturn(['status' => 0, 'msg' => '支付密码错误']);

        }

        if ($data['uid'] != $this->user['first_leader']) {
            if ($data['uid'] != $this->user['balance_leader']) {
                if ($data['uid'] != $this->user['third_leader']) {

                    // $this->ajaxReturn(['status' => 0, 'msg' => '非上级ID无法转账']);
                }
            }
        }

        if ($data['money'] <= 0) {

            $this->ajaxReturn(['status' => 0, 'msg' => '转账额度必须大于0']);

        }

        if ($this->user['frozen_money'] < $data['money']) {

            $this->ajaxReturn(['status' => 0, 'msg' => "当前余额不足,无法转账"]);

        }

        $Users = M('Users');

        $reduce = $Users->where(['user_id' => $this->user_id])->setDec('frozen_money', $data['money']);
        $plus = $Users->where(['user_id' => $data['uid']])->setInc('frozen_money', $data['money']);

        $accountLogData = [
            [
                'user_id' => $this->user_id,
                'pay_points' => 0,
                'frozen_money' => -$data['money'],
                'change_time' => time(),
                'desc' => '转账给ID:' . $data['uid'],
                'log_type'=>6,
            ],
            [
                'user_id' => $data['uid'],
                'pay_points' => 0,
                'frozen_money' => $data['money'],
                'change_time' => time(),
                'desc' => '收到ID:' . $this->user_id . '转账',
                'log_type'=>6,
            ],
        ];

        Db::name('account_log')->insertAll($accountLogData);

        if ($plus) {

            $user_info = $Users->where('user_id', $data['uid'])->find();
            if ($user_info['openid']) {
                $url = SITE_URL . "/Shop/apply/invitation_agent?id=" . $applyid;
                $wx_content = $this->user['nickname'] . "向您转账" . $data['money'] . "元！\n\n<a href='{$url}'>点击查看</a>";
                $wechat = new \app\common\logic\wechat\WechatUtil();
                $wechat->sendMsg($user_info['openid'], 'text', $wx_content);
            }

            //发送站内消息
            $msid = M('message_notice')->add(['message_title' => '转账通知', 'message_content' => $this->user['nickname'] . "向您转账！", 'send_time' => time(), 'mmt_code' => "/Shop/apply/invitation_agent?id=" . $applyid, 'type' => 6]);
            if ($msid) {
                M('user_message')->add(['user_id' => $data['uid'], 'message_id' => $msid]);
            }

            $this->ajaxReturn(['status' => 1, 'msg' => '转账成功!', 'data' => $msid]);

        } else {

            $this->ajaxReturn(['status' => 0, 'msg' => '转账失败!', 'data' => null]);

        }

    }

    //计算团队业绩--订单计算
    public function jisuanyeji($user_id, $start_time, $end_time)
    {

        $where_goods = [
            'od.order_status' => ['notIN', '3,5'],
            // 'og.is_send'    => 1,
            'og.prom_type' => 0, //只有普通订单才算业绩
            //'u.first_leader'=>$v['user_id'],
            //"og.goods_num" =>'>1',
            //'od.user_id'=> ['IN',$user_s],
            'u.first_leader' => $user_id,

        ];
        $order_goods = Db::name('order_goods')->alias('og')
            ->field('u.user_id,sum(og.goods_num*og.goods_price) as sale_amount')
            ->where($where_goods)->order('goods_id DESC')
            ->join('order od', 'od.order_id=og.order_id', 'LEFT')
            ->join('users u', 'u.user_id=od.user_id', 'LEFT')
        // ->limit($Page->firstRow,$Page->listRows)
            ->select();
        // print_r(Db::name('order_goods')->getlastsql());exit;
        return $order_goods;

    }

    public function wallet()
    {
        //获取账户资金记录
        $logic = new UsersLogic();
        $data = $logic->get_account_log($this->user_id, I('get.type'));
        $account_log = $data['result'];

        $this->assign('user', $this->user);
        $this->assign('account_log', $account_log);
        $this->assign('page', $data['show']);

        // if ($_GET['is_ajax']) {
        //     return $this->fetch('ajax_account_list');
        //     exit;
        // }

        return $this->fetch();
    }

    public function personal()
    {

        $user_id = session('user.user_id');
        $user = M('users')->where(['user_id' => $user_id])->find();

        $this->assign('user', $user);

        return $this->fetch();
    }

    private function child_agent($user_id)
    {
        $performance = M('agent_performance')->where(['user_id' => $user_id])->find();
        if (empty($performance)) {
            return false;
        }

        return $performance;
    }

    /**
     * 分销
     */
    public function member()
    {
        $user = session('user');
        $field = "user_id,first_leader,is_distribut,is_agent";
        $users = M('users')->where(['first_leader' => $user['user_id']])->field($field)->select();
        if ($users) {
            if (empty($users)) {
                return false;
            }

            $money_array = [];
            foreach ($users as $key => $val) {
                $get_child_agent = $this->child_agent($val['user_id']);
                $money_array[] = $get_child_agent['agent_per'];
                // dump($get_child_agent['agent_per']);
                // $$money_array[] = $get_child_agent['agent_per'];
            }
            if (empty($money_array)) {
                return false;
            };
            $moneys = array_filter($money_array);
            rsort($moneys);
            //最大业绩用户
            if (count($moneys) >= 2) {
                $max_moneys = max($moneys);
            } else {
                $max_moneys = $moneys[0];
            }
            array_shift($moneys);
            //去掉最大业绩之和
            $moneys = array_sum($moneys);
            $agent = $this->child_agent($user['user_id']);
            $money_total1 = $agent['ind_per'] + $agent['agent_per'];
            $money_total = array(
                'money_total' => $money_total1,
                'max_moneys' => $max_moneys,
                'moneys' => $money_total1 - $max_moneys,
            );
            $this->assign('money_total', $money_total);
        }

        //上级用户信息
        $leader_id = M('users')->where('user_id', $user['user_id'])->value('first_leader');
        if ($leader_id) {
            $leader = M('users')->where('user_id', $leader_id)->field('user_id, nickname')->find();
            if ($leader) {
                $this->assign('leader', $leader);
            }
        }

        $this->assign('user_id', $user['user_id']);
        //$underling_number = M('users')->where(['user_id'=>$user['user_id']])->value('underling_number');
        $underling_number = M('users')->where(['first_leader' => $user['user_id']])->count();
        $this->assign('underling_number', $underling_number);

        return $this->fetch();
    }

    public function p_details()
    {

        $userLogic = new UsersLogic();
        if (IS_POST) {
            if ($_FILES['head_pic']['tmp_name']) {
                $file = $this->request->file('head_pic');
                $image_upload_limit_size = config('image_upload_limit_size');
                $validate = ['size' => $image_upload_limit_size, 'ext' => 'jpg,png,gif,jpeg'];
                $dir = UPLOAD_PATH . 'head_pic/';
                if (!($_exists = file_exists($dir))) {
                    $isMk = mkdir($dir);
                }
                $parentDir = date('Ymd');
                $info = $file->validate($validate)->move($dir, true);
                if ($info) {
                    $post['head_pic'] = '/' . $dir . $parentDir . '/' . $info->getFilename();
                } else {
                    $this->error($file->getError()); //上传错误提示错误信息
                }
            }

            if (!$userLogic->update_info($this->user_id, $post)) {
                $this->error("保存失败");
            }

            setcookie('uname', urlencode($post['nickname']), null, '/');
            $this->redirect(U('User/p_details'));
            exit;
        }

        $this->assign('sex', C('SEX'));

        $user_id = session('user.user_id');
        $user = M('users')->where(['user_id' => $user_id])->find();
        $user['birthday'] = $user['birthday'] ? date('Y-m-d', $user['birthday']) : '无';

        $this->assign('user', $user);

        return $this->fetch();
    }

    public function modify()
    {
        $name = I('get.name/s','nickname');
        $user_id = I('get.user_id/d',0);
        if(!$user_id || !in_array($name,['nickname','realname']))$this->error('参数错误！');
        $name1 = M('Users')->where(['user_id'=>$user_id])->value($name);
        $this->assign('user_id', $user_id);
        $this->assign('name', $name);
        $this->assign('name1', $name1);
        return $this->fetch();
    }

    public function update_realname(){
        $user_id = I('get.user_id/d',0); 
        $realname = I('get.realname/s','');
        if(!$user_id || !$realname || ($user_id != $_SESSION['think']['user']['user_id'])){
            echo json_encode(['msg'=>'参数错误','status'=>-1]);
        }
        $res = M('Users')->update(['user_id'=>$user_id,'realname'=>$realname]);
        if(false !== $res)
            echo json_encode(['msg'=>'操作成功','status'=>1]);
        else
            echo json_encode(['msg'=>'操作失败','status'=>-1]);
    }

    public function edit_personal()
    {
        return $this->fetch();
    }
    public function lj_address()
    {
        return $this->fetch();
    }
    public function edit_Consignee()
    {
        return $this->fetch();
    }

    public function myinterest()
    {
        return $this->fetch();
    }

    public function logout()
    {
        header("Location:" . U('Home/User/logout'));
        exit();
        Session::set('user','');
        session_unset();
        session_destroy();
        setcookie('uname', '', time() - 3600, '/');
        setcookie('cn', '', time() - 3600, '/');
        setcookie('user_id', '', time() - 3600, '/');
        setcookie('PHPSESSID', '', time() - 3600, '/');
        //$this->success("退出成功",U('Mobile/Index/index'));
        exit;
    }

    /*
     * 账户资金
     */
    public function account()
    {
        $user = session('user');
        //获取账户资金记录
        $logic = new UsersLogic();
        $data = $logic->get_account_log($this->user_id, I('get.type'));
        $account_log = $data['result'];

        $this->assign('user', $user);
        $this->assign('account_log', $account_log);
        $this->assign('page', $data['show']);

        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_account_list');
            exit;
        }
        return $this->fetch();
    }

    public function account_list()
    {
        $type = I('type', 'all');
        $usersLogic = new UsersLogic;
        $result = $usersLogic->account($this->user_id, $type);

        $this->assign('type', $type);
        $this->assign('account_log', $result['account_log']);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_account_list');
        }
        return $this->fetch();
    }

    public function account_detail()
    {
        $log_id = I('log_id/d', 0);
        $detail = Db::name('account_log')->where(['log_id' => $log_id])->find();
        $this->assign('detail', $detail);
        return $this->fetch();
    }

    //余额消费明细
    public function account_detail2()
    {
        $log_id = I('log_id/d', 0);
        $detail = Db::name('account_log')->where(['log_id' => $log_id])->find();
        $this->assign('detail', $detail);
        return $this->fetch();
    }

    public function frozen_list()
    {
        $type = I('type', 'all');
        $usersLogic = new UsersLogic;
        $result = $usersLogic->frozen($this->user_id, $type);

        $this->assign('type', $type);
        $this->assign('account_log', $result['account_log']);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_account_list');
        }
        return $this->fetch();
    }
    /**
     * 优惠券
     */
    public function coupon()
    {
        $logic = new UsersLogic();
        $data = $logic->get_coupon($this->user_id, input('type'));
        foreach ($data['result'] as $k => $v) {
            $user_type = $v['use_type'];
            $data['result'][$k]['use_scope'] = C('COUPON_USER_TYPE')["$user_type"];
            if ($user_type == 1) { //指定商品
                $data['result'][$k]['goods_id'] = M('goods_coupon')->field('goods_id')->where(['coupon_id' => $v['cid']])->getField('goods_id');
            }
            if ($user_type == 2) { //指定分类
                $data['result'][$k]['category_id'] = Db::name('goods_coupon')->where(['coupon_id' => $v['cid']])->getField('goods_category_id');
            }
        }
        $coupon_list = $data['result'];
        $this->assign('coupon_list', $coupon_list);
        $this->assign('page', $data['show']);
        if (input('is_ajax')) {
            return $this->fetch('ajax_coupon_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     *  登录
     */
    public function login()
    {
        if ($this->user_id > 0) {
//            header("Location: " . U('Mobile/User/index'));
            $this->redirect('Shop/User/index');
        }
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Shop/User/index");
        $this->assign('referurl', $referurl);
        // 新版支付宝跳转链接
        $this->assign('alipay_url', urlencode(SITE_URL . U("Shop/LoginApi/login", ['oauth' => 'alipaynew'])));
        return $this->fetch();
    }

    /**
     * 登录
     */
    public function do_login()
    {
        $username = trim(I('post.username'));
        $password = trim(I('post.password'));
        //验证码验证
        if (isset($_POST['verify_code'])) {
            $verify_code = I('post.verify_code');
            $verify = new Verify();
            if (!$verify->check($verify_code, 'user_login')) {
                $res = array('status' => 0, 'msg' => '验证码错误');
                exit(json_encode($res));
            }
        }

        $logic = new UsersLogic();
        $res = $logic->login($username, $password);
        if ($res['status'] == 1) {
            $res['url'] = htmlspecialchars_decode(I('post.referurl'));
            session('user', $res['result']);
            setcookie('user_id', $res['result']['user_id'], null, '/');
            setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname', urlencode($nickname), null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($res['result']['user_id']);
            $cartLogic->doUserLoginHandle(); // 用户登录后 需要对购物车 一些操作
            $orderLogic = new OrderLogic();
            $orderLogic->setUserId($res['result']['user_id']); //登录后将超时未支付订单给取消掉
            $orderLogic->abolishOrder();

            //多重保险
            $openid = $tempuser['openid'];
            if ($openid) {
                $uuuuid = session('user.user_id');
                $xianzai_openid = M('users')->where(['user_id' => $res['result']['user_id']])->value('openid');
                if ($openid != $xianzai_openid) {
                    //以 新的 为准
                    M('users')->where(['user_id' => $res['result']['user_id']])->update(['openid' => $openid, 'old_openid' => $xianzai_openid]);
                }
            }

        }
        exit(json_encode($res));
    }

    /**
     *  注册
     */
    public function reg()
    {

        if ($this->user_id > 0) {
            $this->redirect(U('Shop/User/index'));
        }
        $reg_sms_enable = tpCache('sms.regis_sms_enable');
        $reg_smtp_enable = tpCache('sms.regis_smtp_enable');

        if (IS_POST) {
            $logic = new UsersLogic();
            //验证码检验
            //$this->verifyHandle('user_reg');
            $nickname = I('post.useriphone', '');
            $username = I('post.useriphone', '');
            $password = I('post.password', '');
            $password2 = I('post.password2', '');
            $is_bind_account = tpCache('basic.is_bind_account');
            //是否开启注册验证码机制
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 1);

            $session_id = session_id();

            //是否开启注册验证码机制
            if (check_mobile($username)) {
                if ($reg_sms_enable) {
                    //手机功能没关闭
                    $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                    if ($check_code['status'] != 1) {
                        $this->ajaxReturn($check_code);
                    }
                }
            }
            //是否开启注册邮箱验证码机制
            if (check_email($username)) {
                if ($reg_smtp_enable) {
                    //邮件功能未关闭
                    $check_code = $logic->check_validate_code($code, $username);
                    if ($check_code['status'] != 1) {
                        $this->ajaxReturn($check_code);
                    }
                }
            }

            $invite = I('invite');
            $u = I('u');
            $is_code = I('is_code');
            if (!empty($u) && !empty($is_code)) {
                $new_u['uid'] = $u;
                $new_u['is_code'] = 1;
            }
            if (!empty($invite)) {
                $invite = get_user_info($invite, 2); //根据手机号查找邀请人
                if (empty($invite)) {
                    $this->ajaxReturn(['status' => -1, 'msg' => '推荐人不存在', 'result' => '']);
                }
            } else {
                $invite = array();
            }
            if ($is_bind_account && session("third_oauth")) { //绑定第三方账号
                $thirdUser = session("third_oauth");
                $head_pic = $thirdUser['head_pic'];
                $data = $logic->reg($username, $password, $password2, 0, $invite, $nickname, $head_pic, $new_u);
                //用户注册成功后, 绑定第三方账号
                $userLogic = new UsersLogic();
                $data = $userLogic->oauth_bind_new($data['result']);
            } else {
                $data = $logic->reg($username, $password, $password2, 0, $invite);
            }

            if ($data['status'] != 1) {
                $this->ajaxReturn($data);
            }

            //获取公众号openid,并保持到session的user中
            $oauth_users = M('OauthUsers')->where(['user_id' => $data['result']['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
            $oauth_users && $data['result']['open_id'] = $oauth_users['open_id'];

            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($data['result']['user_id']);
            $cartLogic->doUserLoginHandle(); // 用户登录后 需要对购物车 一些操作
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('regis_sms_enable', $reg_sms_enable); // 注册启用短信：
        $this->assign('regis_smtp_enable', $reg_smtp_enable); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out') > 0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        return $this->fetch();
    }

    public function bind_guide()
    {
        $data = session('third_oauth');

        //没有第三方登录的话就跳到登录页
        if (empty($data)) {
            $this->redirect('User/login');
        }
        $first_leader = Cache::get($data['openid']);
        if ($first_leader) {
            //拿关注传时候过来来的上级id
            setcookie('first_leader', $first_leader);
        }
        $this->assign("nickname", $data['nickname']);
        $this->assign("oauth", $data['oauth']);
        $this->assign("head_pic", $data['head_pic']);
        $this->assign('store_name', tpCache('shop_info.store_name'));
        return $this->fetch();
    }

    /**
     * 绑定已有账号
     * @return \think\mixed
     */
    public function bind_account()
    {
        $mobile = input('mobile/s');
        $verify_code = input('verify_code/s');
        //发送短信验证码
        $logic = new UsersLogic();
        $check_code = $logic->check_validate_code($verify_code, $mobile, 'phone', session_id(), 1);
        if ($check_code['status'] != 1) {
            $this->ajaxReturn(['status' => 0, 'msg' => $check_code['msg'], 'result' => '']);
        }
        if (empty($mobile) || !check_mobile($mobile)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '手机格式错误']);
        }
        $users = Db::name('users')->where('mobile', $mobile)->find();
        if (empty($users)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '账号不存在']);
        }
        $user = new \app\common\logic\User();
        $user->setUserById($users['user_id']);
        $cartLogic = new CartLogic();
        try {
            $user->checkOauthBind();
            $user->oauthBind();
            $user->doLeader();
            $user->refreshCookie();
            $cartLogic->setUserId($users['user_id']);
            $cartLogic->doUserLoginHandle();
            $orderLogic = new OrderLogic(); //登录后将超时未支付订单给取消掉
            $orderLogic->setUserId($users['user_id']);
            $orderLogic->abolishOrder();
            $this->ajaxReturn(['status' => 1, 'msg' => '绑定成功']);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }
    /**
     * 先注册再绑定账号
     * @return \think\mixed
     */
    public function bind_reg()
    {
        $mobile = input('mobile/s');
        $verify_code = input('verify_code/s');
        $password = input('password/s');
        $nickname = input('nickname/s', '');
        if (empty($mobile) || !check_mobile($mobile)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '手机格式错误']);
        }
        if (empty($password)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '请输入密码']);
        }
        $logic = new UsersLogic();
        $check_code = $logic->check_validate_code($verify_code, $mobile, 'phone', session_id(), 1);
        if ($check_code['status'] != 1) {
            $this->ajaxReturn(['status' => 0, 'msg' => $check_code['msg'], 'result' => '']);
        }
        $thirdUser = session('third_oauth');
        $data = $logic->reg($mobile, $password, $password, 0, [], $nickname, $thirdUser['head_pic']);
        if ($data['status'] != 1) {
            $this->ajaxReturn(['status' => 0, 'msg' => $data['msg'], 'result' => '']);
        }
        $user = new \app\common\logic\User();
        $user->setUserById($data['result']['user_id']);
        try {
            $user->checkOauthBind();
            $user->oauthBind();
            $user->refreshCookie();
            $this->ajaxReturn(['status' => 1, 'msg' => '绑定成功']);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }

    public function ajaxAddressList()
    {
        $UserAddress = new UserAddress();
        $address_list = $UserAddress->where('user_id', $this->user_id)->order('is_default desc')->select();
        if ($address_list) {
            $address_list = collection($address_list)->append(['address_area'])->toArray();
        } else {
            $address_list = [];
        }
        $this->ajaxReturn($address_list);
    }

    /**
     * 用户地址列表
     */
    public function address_list()
    {
        $address_lists = db('user_address')->where('user_id', $this->user_id)->select();
        $region_list = db('region')->cache(true)->getField('id,name');
        $this->assign('region_list', $region_list);
        $this->assign('lists', $address_lists);
        return $this->fetch();
    }

    /**
     * 保存地址
     */
    public function addressSave()
    {
        $address_id = input('address_id/d', 0);
        $data = input('post.');
        $userAddressValidate = Loader::validate('UserAddress');
        if (!$userAddressValidate->batch()->check($data)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '操作失败', 'result' => $userAddressValidate->getError()]);
        }
        if (!empty($address_id)) {
            //编辑
            $userAddress = UserAddress::get(['address_id' => $address_id, 'user_id' => $this->user_id]);
            if (empty($userAddress)) {
                $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
            }
        } else {
            //新增
            $userAddress = new UserAddress();
            $user_address_count = Db::name('user_address')->where("user_id", $this->user_id)->count();
            if ($user_address_count >= 20) {
                $this->ajaxReturn(['status' => 0, 'msg' => '最多只能添加20个收货地址']);
            }
            $data['user_id'] = $this->user_id;
        }
        $userAddress->data($data);
        $userAddress['longitude'] = true;
        $userAddress['latitude'] = true;
        $row = $userAddress->save();
        if ($row !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'result' => ['address_id' => $userAddress->address_id]]);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '操作失败']);
        }
    }

    /*
     * 添加地址
     */
    public function add_address()
    {
        $source = input('source');
        if (IS_POST) {
            $post_data = input('post.');
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, 0, $post_data);
            if ($data['status'] != 1) {
                $this->ajaxReturn($data);
            } else {
                $data['url'] = U('/Shop/User/address_list');
                $this->ajaxReturn($data);
            }
        }
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $this->assign('province', $p);
        $this->assign('source', $source);
        return $this->fetch();

    }

    /**
     * 智能填写
     */
    public function intelligent_write()
    {
        if (IS_POST) {
            $post_data = input('level');
//            $a = implode('',$post_data);
            $mobie = new Address();
            $data = $mobie->smart_parse($post_data);
            if ($data != '') {
                $this->ajaxReturn(['status' => 1, 'msg' => $data]);
            }

            return $this->ajaxReturn(['status' => 0, 'msg' => '无法匹配，请手动填写']);
        }

    }

    /*
     * 地址编辑
     */
    public function edit_address()
    {
        $id = I('id/d');
        $address = M('user_address')->where(array('address_id' => $id, 'user_id' => $this->user_id))->find();
        if (IS_POST) {
            $post_data = input('post.');
            $source = $post_data['source'];
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, $id, $post_data);
            if ($source == 'cart2') {
                $data['url'] = U('/Shop/Cart/cart2', array('address_id' => $data['result'], 'goods_id' => $post_data['goods_id'], 'goods_num' => $post_data['goods_num'], 'item_id' => $post_data['item_id'], 'action' => $post_data['action']));
                $this->ajaxReturn($data);
            } elseif ($source == 'integral') {
                $data['url'] = U('/Shop/Cart/integral', array('address_id' => $data['result'], 'goods_id' => $post_data['goods_id'], 'goods_num' => $post_data['goods_num'], 'item_id' => $post_data['item_id']));
                $this->ajaxReturn($data);
            } elseif ($source == 'pre_sell_cart') {
                $data['url'] = U('/Shop/Cart/pre_sell_cart', array('address_id' => $data['result'], 'act_id' => $post_data['act_id'], 'goods_num' => $post_data['goods_num']));
                $this->ajaxReturn($data);
            } elseif ($source == 'team') {
                $data['url'] = U('/Shop/Team/order', array('address_id' => $data['result'], 'order_id' => $post_data['order_id']));
                $this->ajaxReturn($data);
            } elseif ($_POST['source'] == 'pre_sell') {
                $prom_id = input('prom_id/d');
                $data['url'] = U('/Shop/Cart/pre_sell', array('address_id' => $data['result'], 'goods_num' => $goods_num, 'prom_id' => $prom_id));
                $this->ajaxReturn($data);
            } else {
                $data['url'] = U('/Shop/User/address_list');
                $this->ajaxReturn($data);
            }
        }
        //获取省份
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $c = M('region')->where(array('parent_id' => $address['province'], 'level' => 2))->select();
        $d = M('region')->where(array('parent_id' => $address['city'], 'level' => 3))->select();
        if ($address['twon']) {
            $e = M('region')->where(array('parent_id' => $address['district'], 'level' => 4))->select();
            $this->assign('twon', $e);
        }
        $this->assign('province', $p);
        $this->assign('city', $c);
        $this->assign('district', $d);
        $this->assign('address', $address);
        return $this->fetch();
    }

    /*
     * 设置默认收货地址
     */
    public function set_default()
    {
        $id = I('get.id/d');
        $source = I('get.source');
        M('user_address')->where(array('user_id' => $this->user_id))->save(array('is_default' => 0));
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->save(array('is_default' => 1));
        if ($source == 'cart2') {
            header("Location:" . U('Shop/Cart/cart2'));
            exit;
        } else {
            header("Location:" . U('Shop/User/address_list'));
        }
    }

    /*
     * 地址删除
     */
    public function del_address()
    {
        $id = I('get.id/d');

        $address = M('user_address')->where("address_id", $id)->find();
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = M('user_address')->where("user_id", $this->user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(array('is_default' => 1));
        }
        if (!$row) {
            $this->error('操作失败', U('User/address_list'));
        } else {
            $this->success("操作成功", U('User/address_list'));
        }

    }

    /*
     * 个人信息
     */
    public function userinfo()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        if (IS_POST) {
            if ($_FILES['head_pic']['tmp_name']) {
                $file = $this->request->file('head_pic');
                $image_upload_limit_size = config('image_upload_limit_size');
                $validate = ['size' => $image_upload_limit_size, 'ext' => 'jpg,png,gif,jpeg'];
                $dir = UPLOAD_PATH . 'head_pic/';
                if (!($_exists = file_exists($dir))) {
                    $isMk = mkdir($dir);
                }
                $parentDir = date('Ymd');
                $info = $file->validate($validate)->move($dir, true);
                if ($info) {
                    $post['head_pic'] = '/' . $dir . $parentDir . '/' . $info->getFilename();
                } else {
                    $this->error($file->getError()); //上传错误提示错误信息
                }
            }
            I('post.nickname') ? $post['nickname'] = I('post.nickname') : false; //昵称
            I('post.qq') ? $post['qq'] = I('post.qq') : false; //QQ号码
            I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false; //头像地址
            I('post.sex') ? $post['sex'] = I('post.sex') : $post['sex'] = 0; // 性别
            I('post.birthday') ? $post['birthday'] = strtotime(I('post.birthday')) : false; // 生日
            I('post.province') ? $post['province'] = I('post.province') : false; //省份
            I('post.city') ? $post['city'] = I('post.city') : false; // 城市
            I('post.district') ? $post['district'] = I('post.district') : false; //地区
            I('post.email') ? $post['email'] = I('post.email') : false; //邮箱
            I('post.mobile') ? $post['mobile'] = I('post.mobile') : false; //手机

            $email = I('post.email');
            $mobile = I('post.mobile');
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 6);

            if (!empty($email)) {
                $c = M('users')->where(['email' => input('post.email'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("邮箱已被使用");
            }
            if (!empty($mobile)) {
                $c = M('users')->where(['mobile' => input('post.mobile'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("手机已被使用");
                if (!$code) {
                    $this->error('请输入验证码');
                }

                $check_code = $userLogic->check_validate_code($code, $mobile, 'phone', $this->session_id, $scene);
                if ($check_code['status'] != 1) {
                    $this->error($check_code['msg']);
                }

            }

            if (!$userLogic->update_info($this->user_id, $post)) {
                $this->error("保存失败");
            }

            setcookie('uname', urlencode($post['nickname']), null, '/');
            $this->redirect(U('User/p_details'));
            exit;
        }
        //  获取省份
        $province = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        //  获取订单城市
        $city = M('region')->where(array('parent_id' => $user_info['province'], 'level' => 2))->select();
        //  获取订单地区
        $area = M('region')->where(array('parent_id' => $user_info['city'], 'level' => 3))->select();
        $this->assign('province', $province);
        $this->assign('city', $city);
        $this->assign('area', $area);
        $this->assign('user', $user_info);
        $this->assign('sex', C('SEX'));
        //从哪个修改用户信息页面进来，
        $dispaly = I('action');
        if ($dispaly != '') {
            return $this->fetch("$dispaly");
        }
        return $this->fetch();
    }

    /**
     * 修改绑定手机
     * @return mixed
     */
    public function setMobile()
    {
        $userLogic = new UsersLogic();
        if (IS_POST) {
            $mobile = input('mobile');
            $mobile_code = input('mobile_code');
            $scene = input('post.scene', 6);
            $validate = I('validate', 0);
            $status = I('status', 0);
            $c = Db::name('users')->where(['mobile' => $mobile, 'user_id' => ['<>', $this->user_id]])->count();
            $c && $this->error('手机已被使用');
            if (!$mobile_code) {
                $this->error('请输入验证码');
            }

            $check_code = $userLogic->check_validate_code($mobile_code, $mobile, 'phone', $this->session_id, $scene);
            if ($check_code['status'] != 1) {
                $this->error($check_code['msg']);
            }

            if ($validate == 1 && $status == 0) {
                $res = Db::name('users')->where(['user_id' => $this->user_id])->update(['mobile' => $mobile, 'mobile_validated' => 1]);

                if ($res !== false) {
                    $source = I('source');
                    !empty($source) && $this->success('绑定成功', U("User/$source"));
                    $this->success('修改成功', U('User/personal'));
                }
                $this->error('修改失败');
            }
        }
        $this->assign('status', $status);
        return $this->fetch();
    }

    /*
     * 邮箱验证
     */
    public function email_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['email_validated'] == 0) {
            $step = 2;
        }

        //原邮箱验证是否通过
        if ($user_info['email_validated'] == 1 && session('email_step1') == 1) {
            $step = 2;
        }

        if ($user_info['email_validated'] == 1 && session('email_step1') != 1) {
            $step = 1;
        }

        if (IS_POST) {
            $email = I('post.email');
            $code = I('post.code');
            $info = session('email_code');
            if (!$info) {
                $this->error('非法操作');
            }

            if ($info['email'] == $email || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('email_code', null);
                    session('email_step1', null);
                    if (!$userLogic->update_email_mobile($email, $this->user_id)) {
                        $this->error('邮箱已存在');
                    }

                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('email_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/email_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码邮箱不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /*
     * 手机验证
     */
    public function mobile_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['mobile_validated'] == 0) {
            $step = 2;
        }

        //原手机验证是否通过
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') == 1) {
            $step = 2;
        }

        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') != 1) {
            $step = 1;
        }

        if (IS_POST) {
            $mobile = I('post.mobile');
            $code = I('post.code');
            $info = session('mobile_code');
            if (!$info) {
                $this->error('非法操作');
            }

            if ($info['email'] == $mobile || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('mobile_code', null);
                    session('mobile_step1', null);
                    if (!$userLogic->update_email_mobile($mobile, $this->user_id, 2)) {
                        $this->error('手机已存在');
                    }

                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('mobile_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/mobile_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码手机不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /**
     * 用户收藏列表
     */
    public function collect_list()
    {
        $userLogic = new UsersLogic();
        $data = $userLogic->get_goods_collect($this->user_id);
        $this->assign('page', $data['show']); // 赋值分页输出
        $this->assign('goods_list', $data['result']);
        if (IS_AJAX) { //ajax加载更多
            return $this->fetch('ajax_collect_list');
            exit;
        }
        return $this->fetch();
    }

    /*
     *取消收藏
     */
    public function cancel_collect()
    {
        $collect_id = I('collect_id/d');
        $user_id = $this->user_id;
        if (M('goods_collect')->where(['collect_id' => $collect_id, 'user_id' => $user_id])->delete()) {
            $this->success("取消收藏成功", U('User/collect_list'));
        } else {
            $this->error("取消收藏失败", U('User/collect_list'));
        }
    }

    /**
     * 我的留言
     */
    public function message_list()
    {
        C('TOKEN_ON', true);
        if (IS_POST) {
            if (!$this->verifyHandle('message')) {
                $this->error('验证码错误', U('User/message_list'));
            };

            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $user = session('user');
            $data['user_name'] = $user['nickname'];
            $data['msg_time'] = time();
            if (M('feedback')->add($data)) {
                $this->success("留言成功", U('User/message_list'));
                exit;
            } else {
                $this->error('留言失败', U('User/message_list'));
                exit;
            }
        }
        $msg_type = array(0 => '留言', 1 => '投诉', 2 => '询问', 3 => '售后', 4 => '求购');
        $count = M('feedback')->where("user_id", $this->user_id)->count();
        $Page = new Page($count, 100);
        $Page->rollPage = 2;
        $message = M('feedback')->where("user_id", $this->user_id)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $showpage = $Page->show();
        header("Content-type:text/html;charset=utf-8");
        $this->assign('page', $showpage);
        $this->assign('message', $message);
        $this->assign('msg_type', $msg_type);
        return $this->fetch();
    }

    /**账户明细*/
    public function points()
    {
        $type = I('type', 'all'); //获取类型
        $this->assign('type', $type);
        if ($type == 'recharge') {
            //充值明细
            $count = M('recharge')->where("user_id", $this->user_id)->count();
            $Page = new Page($count, 16);
            $account_log = M('recharge')->where("user_id", $this->user_id)->order('order_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else if ($type == 'points') {
            //积分记录明细
            $count = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else {
            //全部
            $count = M('account_log')->where(['user_id' => $this->user_id])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }
        $show = $Page->show();
        $this->assign('account_log', $account_log);
        $this->assign('page', $show);
        $this->assign('listRows', $Page->listRows);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
            exit;
        }
        return $this->fetch();
    }

    public function points_list()
    {
        $type = I('type', 'all');
        $usersLogic = new UsersLogic;
        $result = $usersLogic->points($this->user_id, $type);

        $this->assign('type', $type);
        $showpage = $result['page']->show();
        $this->assign('account_log', $result['account_log']);
        $this->assign('page', $showpage);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
        }
        return $this->fetch();
    }

    /*
     * 密码修改
     */
    public function password()
    {
        if (IS_POST) {
            $logic = new UsersLogic();
            $data = $logic->get_info($this->user_id);
            $user = $data['result'];
            if ($user['mobile'] == '' && $user['email'] == '') {
                $this->ajaxReturn(['status' => -1, 'msg' => '请先绑定手机或邮箱', 'url' => U('/Shop/User/index')]);
            }

            $userLogic = new UsersLogic();
            $data = $userLogic->password($this->user_id, I('post.old_password'), I('post.new_password'), I('post.confirm_password'));
            if ($data['status'] == -1) {
                $this->ajaxReturn(['status' => -1, 'msg' => $data['msg']]);
            }

            $this->ajaxReturn(['status' => 1, 'msg' => $data['msg'], 'url' => U('/Shop/User/index')]);
            exit;
        }
        return $this->fetch();
    }

    public function forget_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect("User/index");
        }
        $username = I('username');
        if (IS_POST) {
            if (!empty($username)) {
                if (!$this->verifyHandle('forget')) {
                    $this->ajaxReturn(['status' => -1, 'msg' => "验证码错误"]);
                };
                $field = 'mobile';
                if (check_email($username)) {
                    $field = 'email';
                }
                $user = M('users')->where("email", $username)->whereOr('mobile', $username)->find();
                if ($user) {
                    $sms_status = checkEnableSendSms(2);
                    session('find_password', array('user_id' => $user['user_id'], 'username' => $username,
                        'email' => $user['email'], 'mobile' => $user['mobile'], 'type' => $field, 'sms_status' => $sms_status['status']));
                    $regis_smtp_enable = $this->tpshop_config['smtp_regis_smtp_enable'];
                    if (($field == 'mobile' && $this->tpshop_config['sms_forget_pwd_sms_enable'] == 1)) {
                        $this->ajaxReturn(['status' => 1, 'msg' => "用户验证成功", 'url' => U('User/find_pwd')]);
                    }

                    if (($field == 'email' && $regis_smtp_enable == 0) || ($field == 'mobile' && $sms_status['status'] < 1)) {
                        $this->ajaxReturn(['status' => 1, 'msg' => "用户验证成功", 'url' => U('User/set_pwd')]);
                    }
                    exit;
                } else {
                    $this->ajaxReturn(['status' => -1, 'msg' => "用户名不存在，请检查"]);
                }
            }
        }
        return $this->fetch();
    }

    public function find_pwd()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('User/index'));
        }
        $user = session('find_password');
        if (empty($user)) {
            $this->error("请先验证用户名", U('User/forget_pwd'));
        }
        $this->assign('user', $user);
        return $this->fetch();
    }

    public function set_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect('Shop/User/index');
        }
        $check = session('validate_code');
        $find_password = session('find_password');
        $field = $find_password['field'];
        $sms_status = session('find_password')['sms_status'];
        $regis_smtp_enable = $this->tpshop_config['smtp_regis_smtp_enable'];
        $is_check_code = false;
        //需要验证邮箱或者手机
        if ($field == 'email' && $regis_smtp_enable == 1) {
            $is_check_code = true;
        }

        if ($field == 'mobile' && $sms_status['status'] == 1) {
            $is_check_code = true;
        }

        if ((empty($check) || $check['is_check'] == 0) && $is_check_code) {
            $this->error('验证码还未验证通过', U('User/forget_pwd'));
        }
        if (IS_POST) {
            $data['password'] = $password = I('post.password');
            $data['password2'] = $password2 = I('post.password2');
            $UserRegvalidate = Loader::validate('User');
            if (!$UserRegvalidate->scene('set_pwd')->check($data)) {
                $this->error($UserRegvalidate->getError(), U('User/forget_pwd'));
            }
            M('users')->where("user_id", $find_password['user_id'])->save(array('password' => encrypt($password)));
            session('validate_code', null);
            return $this->fetch('reset_pwd_sucess');
        }
        $is_set = I('is_set', 0);
        $this->assign('is_set', $is_set);
        return $this->fetch();
    }

    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), $id ? $id : 'user_login')) {
            return false;
        }
        return true;
    }

    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'fontSize' => 30,
            'length' => 4,
            'imageH' => 60,
            'imageW' => 300,
            'fontttf' => '5.ttf',
            'useCurve' => false,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
        exit();
    }

    /**
     * 账户管理
     */
    public function accountManage()
    {
        return $this->fetch();
    }

    public function recharge()
    {
        $order_id = I('order_id/d');
        $paymentList = M('Plugin')->where(['type' => 'payment', 'code' => ['neq', 'cod'], 'status' => 1, 'scene' => ['in', '0,1']])->select();
        $paymentList = convert_arr_key($paymentList, 'code');
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            unset($paymentList['weixinH5']);
        } else {
            unset($paymentList['weixin']);
        }
        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('bankCodeList', $bankCodeList);

        // 查找最近一次充值方式
        $recharge_arr = Db::name('Recharge')->field('pay_code')->where('user_id', $this->user_id)
            ->order('order_id desc')->find();
        $alipay = 'alipayMobile'; //默认支付宝支付
        if ($recharge_arr) {
            foreach ($paymentList as $key => $item) {
                if ($key == $recharge_arr['pay_code']) {
                    $alipay = $recharge_arr['pay_code'];
                }
            }
        }
        $this->assign('alipay', $alipay);

        if ($order_id > 0) {
            $order = M('recharge')->where("order_id", $order_id)->find();
            $this->assign('order', $order);
        }
        return $this->fetch();
    }

    public function recharge_list()
    {
        $usersLogic = new UsersLogic;
        $result = $usersLogic->get_recharge_log($this->user_id); //充值记录
        $this->assign('page', $result['show']);
        $this->assign('lists', $result['result']);
        if (I('is_ajax')) {
            return $this->fetch('ajax_recharge_list');
        }
        return $this->fetch();
    }

    public function performance_log()
    {
        $DistributLogic = new DistributLogic;
        $result = $DistributLogic->get_recharge_log($this->user_id, '', 'agent_performance_log'); //业务记录
        // dump($this->user_id);
        $this->assign('page', $result['show']);
        $this->assign('lists', $result['result']);
        if (I('is_ajax')) {
            return $this->fetch('ajax_log_list');
        }
        return $this->fetch();
    }

    public function commision()
    {
        $DistributLogic = new DistributLogic;
        $result = $DistributLogic->get_commision_log($this->user_id); //佣金明细
        /*
        $recharge_log_where = ['user_id'=>$this->user_id];

        $count = M('fan_log')->where($recharge_log_where)->count();
        $Page = new Page($count, 30);
        $lists = M('fan_log')->where($recharge_log_where)
        ->limit($Page->firstRow . ',' . $Page->listRows)
        ->order('log_id desc')
        ->select(); */

        $this->assign('page', $result['show']);
        $this->assign('lists', $result['result']);
        if (I('is_ajax')) {
            return $this->fetch('ajax_commision_list');
        }

        return $this->fetch();
    }

    //团队列表
    public function team_list()
    {
        $first_leader = I('first_leader');
        if (!$first_leader) {
            $first_leader = session('user.user_id');
        }
        //用户信息
        $user = M('users')->field('user_id,nickname,mobile')->where(['user_id' => $first_leader])->find();
        //下级信息
        $users = M('users')->field('user_id,nickname,mobile')->where(['first_leader' => $first_leader])->select();

        $this->assign('user', $user);
        $this->assign('lists', $users);

        return $this->fetch();
    }

    //添加、编辑提现支付宝账号
    public function add_card()
    {
        $user_id = $this->user_id;
        $data = I('post.');
        if ($data['type'] == 0) {
            $info['cash_alipay'] = $data['card'];
            $info['realname'] = $data['cash_name'];
            $info['user_id'] = $user_id;
            $res = DB::name('user_extend')->where('user_id=' . $user_id)->count();
            if ($res) {
                $res2 = Db::name('user_extend')->where('user_id=' . $user_id)->save($info);
            } else {
                $res2 = Db::name('user_extend')->add($info);
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
        } else {
            //防止非支付宝类型的表单提交
            $this->ajaxReturn(['status' => 0, 'msg' => '不支持的提现方式']);
        }

    }

    /**
     * 申请提现记录
     */
    public function withdrawals()
    {
        C('TOKEN_ON', true);
        $cash_open = tpCache('cash.cash_open');
        if ($cash_open != 1) {
            $this->error('提现功能已关闭,请联系商家');
        }
        if (IS_POST) {
            $cash_open = tpCache('cash.cash_open');
            if ($cash_open != 1) {
                $this->ajaxReturn(['status' => 0, 'msg' => '提现功能已关闭,请联系商家']);
            }

            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $data['create_time'] = time();
            $cash = tpCache('cash');

            if (encrypt($data['paypwd']) != $this->user['paypwd']) {
                $this->ajaxReturn(['status' => 0, 'msg' => '支付密码错误']);
            }
            if ($data['money'] > $this->user['user_money']) {
                $this->ajaxReturn(['status' => 0, 'msg' => "本次提现余额不足"]);
            }
            if ($data['money'] <= 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => '提现额度必须大于0']);
            }

            // 统计所有0，1的金额
            $status = ['in', '0'];
            $total_money = Db::name('withdrawals')->where(array('user_id' => $this->user_id, 'status' => $status))->sum('money');
            if ($total_money + $data['money'] > $this->user['user_money']) {
                $this->ajaxReturn(['status' => 0, 'msg' => "您有提现申请待处理，本次提现余额不足"]);
            }

            if ($cash['cash_open'] == 1) {
                $taxfee = round($data['money'] * $cash['service_ratio'] / 100, 2);
                // 限手续费
                if ($cash['max_service_money'] > 0 && $taxfee > $cash['max_service_money']) {
                    $taxfee = $cash['max_service_money'];
                }
                if ($cash['min_service_money'] > 0 && $taxfee < $cash['min_service_money']) {
                    $taxfee = $cash['min_service_money'];
                }
                if ($taxfee >= $data['money']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => '提现额度必须大于手续费！']);
                }
                $data['taxfee'] = $taxfee;

                // 每次限最多提现额度
                if ($cash['min_cash'] > 0 && $data['money'] < $cash['min_cash']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => '每次最少提现额度' . $cash['min_cash']]);
                }
                if ($cash['max_cash'] > 0 && $data['money'] > $cash['max_cash']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => '每次最多提现额度' . $cash['max_cash']]);
                }

                $status = ['in', '0,1,2,3'];
                $create_time = ['gt', strtotime(date("Y-m-d"))];
                // 今天限总额度
                if ($cash['count_cash'] > 0) {
                    $total_money2 = Db::name('withdrawals')->where(array('user_id' => $this->user_id, 'status' => $status, 'create_time' => $create_time))->sum('money');
                    if (($total_money2 + $data['money'] > $cash['count_cash'])) {
                        $total_money = $cash['count_cash'] - $total_money2;
                        if ($total_money <= 0) {
                            $this->ajaxReturn(['status' => 0, 'msg' => "你今天累计提现额为{$total_money2},金额已超过可提现金额."]);
                        } else {
                            $this->ajaxReturn(['status' => 0, 'msg' => "你今天累计提现额为{$total_money2}，最多可提现{$total_money}账户余额."]);
                        }
                    }
                }
                // 今天限申请次数
                if ($cash['cash_times'] > 0) {
                    $total_times = Db::name('withdrawals')->where(array('user_id' => $this->user_id, 'status' => $status, 'create_time' => $create_time))->count();
                    if ($total_times >= $cash['cash_times']) {
                        $this->ajaxReturn(['status' => 0, 'msg' => "今天申请提现的次数已用完."]);
                    }
                }
            } else {
                $data['taxfee'] = 0;
            }

            if (M('withdrawals')->add($data)) {
                $this->ajaxReturn(['status' => 1, 'msg' => "已提交申请", 'url' => U('User/account', ['type' => 2])]);
            } else {
                $this->ajaxReturn(['status' => 0, 'msg' => '提交失败,联系客服!']);
            }
        }
        $user_extend = Db::name('user_extend')->where('user_id=' . $this->user_id)->find();

        //获取用户绑定openId
        $oauthUsers = M("OauthUsers")->where(['user_id' => $this->user_id, 'oauth' => 'wx'])->find();
        $openid = $oauthUsers['openid'];
        if (empty($oauthUsers)) {
            $openid = Db::name('oauth_users')->where(['user_id' => $this->user_id, 'oauth' => 'weixin'])->value('openid');
        }

        $this->assign('user_extend', $user_extend);
        $this->assign('cash_config', tpCache('cash')); //提现配置项
        $this->assign('user_money', $this->user['user_money']); //用户余额
        $this->assign('openid', $openid); //用户绑定的微信openid
        return $this->fetch();
    }

    //手机端是通过扫码PC端来绑定微信,需要ajax获取一下openID
    public function get_openid()
    {
        //halt($this->user_id); 22
        $oauthUsers = M("OauthUsers")->where(['user_id' => $this->user_id, 'oauth' => 'weixin'])->find();
        $openid = $oauthUsers['openid'];
        if (empty($oauthUsers)) {
            $openid = Db::name('oauth_users')->where(['user_id' => $this->user_id, 'oauth' => 'wx'])->value('openid');
        }
        if ($openid) {
            $this->ajaxReturn(['status' => 1, 'result' => $openid]);
        } else {
            $this->ajaxReturn(['status' => 0, 'result' => '']);
        }
    }

    /**
     * 申请记录列表
     */
    public function withdrawals_list()
    {
        $withdrawals_where['user_id'] = $this->user_id;
        $count = M('withdrawals')->where($withdrawals_where)->count();
        // $pagesize = C('PAGESIZE'); //10条数据，不显示滚动效果
        // $page = new Page($count, $pagesize);
        $page = new Page($count, 15);
        $list = M('withdrawals')->where($withdrawals_where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();

        $this->assign('page', $page->show()); // 赋值分页输出
        $this->assign('list', $list); // 下线
        if (I('is_ajax')) {
            return $this->fetch('ajax_withdrawals_list');
        }
        return $this->fetch();
    }

    /**
     * 我的关注
     * @author lxl
     * @time   2017/1
     */
    public function myfocus()
    {
        return $this->fetch();
    }

    /**
     *  用户消息通知
     * @author yhj
     * @time 2018/07/10
     */
    public function message_notice()
    {
        $message_logic = new Message();
        $message_logic->checkPublicMessage();
        $where = array(
            'user_id' => $this->user_id,
            'deleted' => 0,
            'category' => 0,
        );
        $userMessage = new UserMessage();
        $data['message_notice'] = $userMessage->where($where)->LIMIT(1)->order('rec_id desc')->find();

        $where['category'] = 1;
        $data['message_activity'] = $userMessage->where($where)->LIMIT(1)->order('rec_id desc')->find();

        $where['category'] = 2;
        $data['message_logistics'] = $userMessage->where($where)->LIMIT(1)->order('rec_id desc')->find();

        //$where['category'] = 3;
        //$data['message_private'] = $userMessage->where($where)->LIMIT(1)->order('rec_id desc')->find();

        $data['no_read'] = $message_logic->getUserMessageCount();

        // 最近消息，日期，内容
        $this->assign($data);
        return $this->fetch();
    }

    /**
     * 查看通知消息详情
     */
    public function message_notice_detail()
    {

        $type = I('type', 0);
        // $type==3私信，暂时没有

        $message_logic = new Message();
        $message_logic->checkPublicMessage();

        $where = array(
            'user_id' => $this->user_id,
            'deleted' => 0,
            'category' => $type,
        );
        $userMessage = new UserMessage();
        $count = $userMessage->where($where)->count();
        $page = new Page($count, 10);
        //$lists = $userMessage->where($where)->order("rec_id DESC")->limit($page->firstRow . ',' . $page->listRows)->select();

        $rec_id = $userMessage->where($where)->LIMIT($page->firstRow . ',' . $page->listRows)->order('rec_id desc')->column('rec_id');
        $lists = $message_logic->sortMessageListBySendTime($rec_id, $type);
//dump($lists);exit;
        $this->assign('lists', $lists);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_message_detail');
        }
        if (empty($lists)) {
            return $this->fetch('user/message_none');
        }
        return $this->fetch();
    }

    /**
     * 通知消息详情
     */
    public function message_notice_info()
    {
        $message_logic = new Message();
        $message_details = $message_logic->getMessageDetails(I('msg_id'), I('type', 0));
        $this->assign('message_details', $message_details);
        return $this->fetch();
    }

    /**
     * 浏览记录
     */
    public function visit_log()
    {
        $count = M('goods_visit')->where('user_id', $this->user_id)->count();
        $Page = new Page($count, 20);
        $visit = M('goods_visit')->alias('v')
            ->field('v.visit_id, v.goods_id, v.visittime, g.goods_name, g.shop_price, g.cat_id')
            ->join('__GOODS__ g', 'v.goods_id=g.goods_id')
            ->where('v.user_id', $this->user_id)
            ->order('v.visittime desc')
            ->limit($Page->firstRow, $Page->listRows)
            ->select();

        /* 浏览记录按日期分组 */
        $curyear = date('Y');
        $visit_list = [];
        foreach ($visit as $v) {
            if ($curyear == date('Y', $v['visittime'])) {
                $date = date('m月d日', $v['visittime']);
            } else {
                $date = date('Y年m月d日', $v['visittime']);
            }
            $visit_list[$date][] = $v;
        }

        $this->assign('visit_list', $visit_list);
        if (I('get.is_ajax', 0)) {
            return $this->fetch('ajax_visit_log');
        }
        return $this->fetch();
    }

    /**
     * 删除浏览记录
     */
    public function del_visit_log()
    {
        $visit_ids = I('get.visit_ids', 0);
        $row = M('goods_visit')->where('visit_id', 'IN', $visit_ids)->delete();

        if (!$row) {
            $this->error('操作失败', U('User/visit_log'));
        } else {
            $this->success("操作成功", U('User/visit_log'));
        }
    }

    /**
     * 清空浏览记录
     */
    public function clear_visit_log()
    {
        $row = M('goods_visit')->where('user_id', $this->user_id)->delete();

        if (!$row) {
            $this->error('操作失败', U('User/visit_log'));
        } else {
            $this->success("操作成功", U('User/visit_log'));
        }
    }

    /**
     * 支付密码
     * @return mixed
     */
    public function paypwd()
    {
        //检查是否第三方登录用户
        $user = M('users')->where('user_id', $this->user_id)->find();
        if ($user['mobile'] == '') {
            $this->error('请先绑定手机号', U('User/setMobile', ['source' => 'paypwd']));
        }

        $step = I('step', 1);
        if ($step > 1) {
            $check = session('validate_code');
            if (empty($check)) {
                $this->error('验证码还未验证通过', U('Shop/User/paypwd'));
            }
        }
        if (IS_POST && $step == 2) {
            $new_password = trim(I('new_password'));
            $confirm_password = trim(I('confirm_password'));
            $oldpaypwd = trim(I('old_password'));
            //以前设置过就得验证原来密码
            if (!empty($user['paypwd']) && ($user['paypwd'] != encrypt($oldpaypwd))) {
                $this->ajaxReturn(['status' => -1, 'msg' => '原密码验证错误！', 'result' => '']);
            }
            $userLogic = new UsersLogic();
            $data = $userLogic->paypwd($this->user_id, $new_password, $confirm_password);
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /**
     * 重置支付密码
     * @return mixed
     */
    public function paypwd_reset()
    {
        //检查是否第三方登录用户
        $user = M('users')->where('user_id', $this->user_id)->find();
        if ($user['mobile'] == '') {
            $this->error('请先绑定手机号', U('User/setMobile', ['source' => 'paypwd']));
        }

        $step = I('step', 1);
        if ($step > 1) {
            $check = session('validate_code');
            if (empty($check)) {
                $this->error('验证码还未验证通过', U('mobile/User/paypwd'));
            }
        }
        if (IS_POST && $step == 2) {
            $new_password = trim(I('new_password'));
            $confirm_password = trim(I('confirm_password'));

            $userLogic = new UsersLogic();
            $data = $userLogic->paypwd($this->user_id, $new_password, $confirm_password);
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /**
     * 会员签到积分奖励
     * 2017/9/28
     */
    public function sign()
    {
        $userLogic = new UsersLogic();
        $user_id = $this->user_id;
        $info = $userLogic->idenUserSign($user_id); //标识签到
        $this->assign('info', $info);
        return $this->fetch();
    }

    /**
     * Ajax会员签到
     * 2017/11/19
     */
    public function user_sign()
    {
        $userLogic = new UsersLogic();
        $user_id = $this->user_id;
        $config = tpCache('sign');
        $date = I('date'); //2017-9-29
        //是否正确请求
        (date("Y-n-j", time()) != $date) && $this->ajaxReturn(['status' => false, 'msg' => '签到失败！', 'result' => '']);
        //签到开关
        if ($config['sign_on_off'] > 0) {
            $map['sign_last'] = $date;
            $map['user_id'] = $user_id;
            $userSingInfo = Db::name('user_sign')->where($map)->find();
            //今天是否已签
            $userSingInfo && $this->ajaxReturn(['status' => false, 'msg' => '您今天已经签过啦！', 'result' => '']);
            //是否有过签到记录
            $checkSign = Db::name('user_sign')->where(['user_id' => $user_id])->find();
            if (!$checkSign) {
                $result = $userLogic->addUserSign($user_id, $date); //第一次签到
            } else {
                $result = $userLogic->updateUserSign($checkSign, $date); //累计签到
            }
            $return = ['status' => $result['status'], 'msg' => $result['msg'], 'result' => ''];
        } else {
            $return = ['status' => false, 'msg' => '该功能未开启！', 'result' => ''];
        }
        $this->ajaxReturn($return);
    }

    /**
     * vip充值
     */
    public function rechargevip()
    {
        $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,1)")->select();
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
        }
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('bankCodeList', $bankCodeList);
        return $this->fetch();
    }

    /**
     * 个人海报推广二维码 （我的名片）
     */
    public function qr_code()
    {
        $user = session('user');
        $type = input('type');
        $user_id = $user['user_id'];
        $user_qrcode =  Db::name('users')->where('user_id',$user_id)->value('erweima');
        if(!file_exists($user_qrcode) || $type){
            $logic = new ShareLogic();
            $url = $logic->get_ticket_url($user_id);//链接
            $nickname = mb_substr($user['nickname'],0,6,'UTF8');
            //创建文件夹
            $date = date('Ymd',time());
            $path = 'public/qrcode/user/'.$date;
            if (!is_dir($path)){  
                mkdir($path,0777,true);
            }
            //缩放用户头像
            $q = substr($user['head_pic'],0,1);
            if($q == 'h'){
                $head_pic = getWxHead($user['head_pic']);
                $this->resizeImage($head_pic,350,350,$head_pic);
            }else{
                $head_pic = substr($user['head_pic'],1,200);
            }
            $tmp_arr = explode('.',$head_pic);
            $houzui = $tmp_arr[count($tmp_arr)-1];
            $new_img = $head_pic;
            if(file_exists($head_pic)){
                $head_img = \think\Image::open($head_pic);
                $head_img->thumb(350,350,\think\Image::THUMB_FILLED)->save($new_img);
                //生成二维码
                $user_qrcode = user_qrcode($url,$user['user_id']);
                $erweima = 'public/qrcode/user/erweima.png';
                if(file_exists($erweima)){
                    $image = \think\Image::open($erweima);
                    //width297，height494
                    //融合昵称和用户二维码s
                    $image->text($nickname,'SourceHanSansCN-Normal.ttf',38,'#686060',[600,455]);
                    $image->water($new_img,[450,70]);
                    $image->water($user_qrcode,[350,735])->save($user_qrcode);
                }
            }
            Db::name('users')->where('user_id',$user_id)->save(['erweima'=>$user_qrcode]);
        }
        $this->assign('image', '/'.$user_qrcode);
        return $this->fetch();
    }

    //图片放大
    function resizeImage($srcImage,$maxwidth,$maxheight,$name)
    {
        try {
            list($width, $height, $type, $attr) = getimagesize($srcImage);
            switch ($type) {
                case 1:
                    $img = imagecreatefromgif($srcImage);
                    break;
                case 2:
                    $img = imagecreatefromjpeg($srcImage);
                    break;
                case 3:
                    $img = imagecreatefrompng($srcImage);
                    break;
                default:
                    $img = imagecreatefromjpg($srcImage);
                    break;
            }
        } catch (EmptyIterator $e) {
            return false;
            //不操作继续执行
        }
        $canvas = imagecreatetruecolor($maxwidth,$maxheight); // 创建一个真彩色图像 我把它理解为创建了一个画布
        imagecopyresampled($canvas,$img,0,0,0,0,$maxwidth,$maxheight,$width,$height);
        imagejpeg($canvas,$name,100);
    }

    // 用户海报二维码
    public function poster_qrcode()
    {
        ob_end_clean();
        vendor('topthink.think-image.src.Image');
        vendor('phpqrcode.phpqrcode');

        error_reporting(E_ERROR);
        $url = isset($_GET['data']) ? $_GET['data'] : '';
        $url = urldecode($url);

        $poster = DB::name('poster')->where(['enabled' => 1])->find();
        define('IMGROOT_PATH', str_replace("\\", "/", realpath(dirname(dirname(__FILE__)) . '/../../'))); //图片根目录（绝对路径）
        $project_path = '/public/images/poster/' . I('_saas_app', 'all');
        $file_path = IMGROOT_PATH . $project_path;

        if (!is_dir($file_path)) {
            mkdir($file_path, 777, true);
        }

        $head_pic = input('get.head_pic', ''); //个人头像
        $back_img = IMGROOT_PATH . $poster['back_url']; //海报背景
        $valid_date = input('get.valid_date', 0); //有效时间

        $qr_code_path = UPLOAD_PATH . 'qr_code/';
        if (!file_exists($qr_code_path)) {
            mkdir($qr_code_path, 777, true);
        }

        /* 生成二维码 */
        $qr_code_file = $qr_code_path . time() . rand(1, 10000) . '.png';
        \QRcode::png($url, $qr_code_file, QR_ECLEVEL_M, 1.8);

        /* 二维码叠加水印 */
        $QR = Image::open($qr_code_file);
        $QR_width = $QR->width();
        $QR_height = $QR->height();

        /* 添加头像 */
        if ($head_pic) {
            //如果是网络头像
            if (strpos($head_pic, 'http') === 0) {
                //下载头像
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $head_pic);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $file_content = curl_exec($ch);
                curl_close($ch);
                //保存头像
                if ($file_content) {
                    $head_pic_path = $qr_code_path . time() . rand(1, 10000) . '.png';
                    file_put_contents($head_pic_path, $file_content);
                    $head_pic = $head_pic_path;
                }
            }
            //如果是本地头像
            if (file_exists($head_pic)) {
                $logo = Image::open($head_pic);
                $logo_width = $logo->height();
                $logo_height = $logo->width();
                $logo_qr_width = $QR_width / 4;
                $scale = $logo_width / $logo_qr_width;
                $logo_qr_height = $logo_height / $scale;
                $logo_file = $qr_code_path . time() . rand(1, 10000);
                $logo->thumb($logo_qr_width, $logo_qr_height)->save($logo_file, null, 100);
                $QR = $QR->thumb($QR_width, $QR_height)->water($logo_file, \think\Image::WATER_CENTER);
                $logo_file && unlink($logo_file);
            }
            $head_pic_path && unlink($head_pic_path);
        }

        if ($valid_date && strpos($url, 'weixin.qq.com') !== false) {
            $QR = $QR->text('有效时间 ' . $valid_date, "./vendor/topthink/think-captcha/assets/zhttfs/1.ttf", 7, '#00000000', Image::WATER_SOUTH);
        }
        $QR->save($qr_code_file, null, 100);

        $canvas_maxWidth = $poster['canvas_width'];
        $canvas_maxHeight = $poster['canvas_height'];
        $info = getimagesize($back_img); //取得一个图片信息的数组
        $im = checkPosterImagesType($info, $back_img); //根据图片的格式对应的不同的函数
        $rate_poster_width = $canvas_maxWidth / $info[0]; //计算绽放比例
        $rate_poster_height = $canvas_maxHeight / $info[1];
        $maxWidth = floor($info[0] * $rate_poster_width);
        $maxHeight = floor($info[1] * $rate_poster_height); //计算出缩放后的高度
        $des_im = imagecreatetruecolor($maxWidth, $maxHeight); //创建一个缩放的画布
        imagecopyresized($des_im, $im, 0, 0, 0, 0, $maxWidth, $maxHeight, $info[0], $info[1]); //缩放
        $news_poster = $file_path . '/' . createImagesName() . ".png"; //获得缩小后新的二维码路径
        inputPosterImages($info, $des_im, $news_poster); //输出到png即为一个缩放后的文件
        $QR = imagecreatefromstring(file_get_contents($qr_code_file));
        $background_img = imagecreatefromstring(file_get_contents($news_poster));

        imagecopyresampled($background_img, $QR, $poster['canvas_x'], $poster['canvas_y'], 0, 0, 80, 92, 80, 78); //合成图片
        $result_png = '/' . createImagesName() . ".png";
        $file = $file_path . $result_png;
        imagepng($background_img, $file); //输出合成海报图片
        $final_poster = imagecreatefromstring(file_get_contents($file)); //获得该图片资源显示图片
        header("Content-type: image/png");
        imagepng($final_poster);
        imagedestroy($final_poster);
        $news_poster && unlink($news_poster);
        $qr_code_file && unlink($qr_code_file);
        $file && unlink($file);
        exit;
    }

    // 用户自提过商品的门店管理
    public function store()
    {
        return $this->fetch();
    }
    //用户的二维码 、
    public function usercode()
    {
        vendor('phpqrcode.phpqrcode');
        $qr_code_path = UPLOAD_PATH . 'usercode/';
        if (!file_exists($qr_code_path)) {
            mkdir($qr_code_path, 777, true);
        }

        $qr_code_file = $this->user['code_url'];
        if (empty($qr_code_file)) {
            $session = session('user');
            $url = "http://" . $_SERVER['HTTP_HOST'] . "/Shop/user/login.html?u={$session['user_id']}&first_leader={$session['user_id']}";
            // reg/u/".$session['user_id']."/is_code/1
            /* 生成二维码 */
            $qr_code_file = $qr_code_path . time() . rand(1, 10000) . '.png';
            \QRcode::png($url, $qr_code_file, QR_ECLEVEL_M, 10, 2, true);
            if (file_exists($qr_code_file)) {
                M('users')->where(['user_id' => $session['user_id']])->update(['code_url' => $qr_code_file]);
            }
        }

        $this->assign('qr_code_file', $qr_code_file);
        return $this->fetch();
    }

    /**
     * 新的分享
     */
    public function fenxiang()
    {
        $user_id = session('user.user_id');

        $logic = new ShareLogic();
        $ticket = $logic->get_ticket($user_id);

        if (strlen($ticket) < 3) {
            $this->error("ticket不能为空");
            exit;
        }

        $url = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=" . $ticket;
        //1.二维码
        $url222 = ROOT_PATH . 'public/share/code/' . $user_id . '.jpg';
        if (@fopen($url222, 'r')) {
            //已经有二维码了
            $url_code = ROOT_PATH . 'public/share/code/' . $user_id . '.jpg';
        } else {
            //还没有二维码
            $re = $logic->getImage($url, ROOT_PATH . 'public/share/code', $user_id . '.jpg');
            $url_code = $re['save_path'];
        }

        //判断图片大小
        $logo_url = \think\Image::open($url_code);
        $logo_url_logo_width = $logo_url->height();
        $logo_url_logo_height = $logo_url->width();

        if ($logo_url_logo_height > 420 || $logo_url_logo_width > 420) {
            //压缩二维码图片
            $url_code = ROOT_PATH . 'public/share/code/' . $user_id . '.jpg';
            $logo_url->thumb(210, 210)->save($url_code, null, 100);
        }

        //2.头像
        $head_url = ROOT_PATH . 'public/share/head/' . $user_id . '.jpg';
        if (@fopen($head_url, 'r')) {
            //已经有头像了
            $url_head_pp = ROOT_PATH . 'public/share/head/' . $user_id . '.jpg';
        } else {
            //还没有头像
            $re = $logic->getImage($this->user['head_pic'], ROOT_PATH . 'public/share/head', $user_id . '.jpg');
            $url_head_pp = $re['save_path'];
        }

        //判断图片大小
        $logo = \think\Image::open($url_head_pp);
        $logo_width = $logo->height();
        $logo_height = $logo->width();

        //压缩头像
        if ($logo_height > 100 || $logo_width > 100) {
            //压缩图片
            $url_head_file = ROOT_PATH . 'public/share/head/' . $user_id . '.jpg';
            $logo->thumb(100, 100)->save($url_head_file, null, 120);
        }

        //3.得到二维码的绝对路径
        $pic = ROOT_PATH . "public/share/picture_ok44/'.$user_id.'.jpg";
        if (@fopen($pic, 'r')) {
            $pic = "/share/picture_ok44/" . $uid . ".jpg";
        } else {
            $image = \think\Image::open(ROOT_PATH . 'public/share/bg1.jpg');
            // 给原图中间添加水印
            $image->water($url_code, \think\Image::WATER_CENTER)->save(ROOT_PATH . 'public/share/picture_ok44/' . $user_id . '.jpg');

            // 给图片添加头像
            $images = \think\Image::open(ROOT_PATH . "/public/share/picture_ok44/" . $user_id . ".jpg");
            $images->water($url_head_pp, \think\Image::DCHQZG)->save(ROOT_PATH . 'public/share/picture_ok44/' . $user_id . '.jpg');

            // 添加名称
            $images->text($this->user['nickname'], './hgzb.ttf', 15, '#636363', 10)->save(ROOT_PATH . 'public/share/picture_ok44/' . $user_id . '.jpg');
            $pic = "/public/share/picture_ok44/" . $user_id . ".jpg";
        }
        $pic = $pic . '?v=' . time();
        $this->assign('pic', $pic);

        return $this->fetch();
    }

    public function fen()
    {
        $user_id = session('user.user_id');
        $url = SITE_URL . '?first_leader=' . $user_id;
        $this->assign('url', $url);
        $qr_back = M('config')->where(['name' => 'qr_back'])->value('value');
        $this->assign('qr_back', $qr_back);

        $head_pic = session('user.head_pic');
        $this->assign('head_pic', $head_pic);

        $nickname = session('user.nickname');
        $this->assign('nickname', $nickname);

        return $this->fetch();
    }

    /**
     * 我的库存
     */
    public function userStock()
    {

        $user = new UserStock();
        $c = $user->where('user_id', $this->user_id)
            ->limit(10)
            ->order('stock', 'asc')
            ->select();
        dump($c[0]->goods);exit;

    }

    /**
     * 申请列表
     */
    public function apply_list()
    {
        $userApply = new UserApply();
        $data = $userApply->applyList($data);
        $this->assign('secondCategoryList', $secondCategoryList);
    }

    /**
     * 邀请列表
     */
    public function invite_list()
    {
        $userApply = new UserApply();
        $data = $userApply->inviteList($data);
        $this->assign('secondCategoryList', $secondCategoryList);
    }

    /**
     * 申请代理
     */
    public function apply_upgrade()
    {

//        $userLevel = M('UserLevel')->where('level','>',$this->user['level'])->select();
        //        if (IS_POST) {
        $data['mobile'] = '15975561571'; //I('mobile');
        $data['level'] = '3'; //I('level');

        $userApply = new UserApply();
        $data = $userApply->applyUpgrade($this->user, $data);

        $this->ajaxReturn($data);
        exit;
//        }
        //        $this->assign('userLevel', $userLevel);
        //        $this->assign('user', $this->user);
        //        return $this->fetch();
    }

    // public function logout()
    // {
    //     session_unset();
    //     session_destroy();
    //     setcookie('uname','',time()-3600,'/');
    //     setcookie('cn','',time()-3600,'/');
    //     setcookie('user_id','',time()-3600,'/');
    //     setcookie('PHPSESSID','',time()-3600,'/');
    //     //$this->success("退出成功",U('Mobile/Index/index'));
    //     header("Location:" . U('Mobile/Index/index'));
    //     exit();
    // }
    //
    public function addVideo()
    {
        return $this->fetch();
    }

    //实现视频上传
    public function upload()
    {

        $file = request()->file('video');
        $data = I('post.');
        $video_title = $data['video_title'];
        $video_instro = $data['video_instro'];
        if ($video_title)
        // halt($video_title);
        // 移动到框架应用根目录/public/uploads/ 目录下
        {
            $path = './public/uploads/';
        }

        if (!is_dir) {
            @mkdir($path, 0777, true); //如果目录不存在，则生成
        }
        $info = $file->validate(['size' => 1024 * 1024 * 6, 'ext' => 'avi,mp4,dat,mkv,flv,vob'])->move($path);
        if ($info) {
            // 成功上传后 获取上传后组装文件信息准备插入数据库
            $video_size = $info->getSize(); //大小
            $user_id = session('user.user_id'); //用户id
            $url = $info->getSaveName(); // 文件路径
            $video_name = $info->getFilename(); //文件名
            $video_type = $info->getExtension(); //文件格式
            $up_time = time();

            //halt($up_time);
            //入库数据
            $data = [
                'userid' => $user_id,
                'video_size' => $video_size,
                'url' => $url,
                'video_name' => $video_name,
                'video_title' => $video_title,
                'video_instro' => $video_instro,
                'up_time' => $up_time,
            ];

            $validate = Loader::validate('VideUpload');
            $res = $validate->check($data);
            {}
            $res = Db::name('shop_video')->insert($data);
            if ($res) {
                echo '上传成功';
            }
        } else {
            // 上传失败获取错误信息
            echo $file->getError();
        }
    }
    //实现视频展示
    //

    //生成唯一文件名
    public function uniqidReal($lenght = 13)
    {
        $bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
        return substr(bin2hex($bytes), 0, $lenght);
    }

    // 授权vip
    public function ajax_get_userinfo()
    {
        $uid = I('get.uid/d', 0);
        $type = I('get.type/d', 0);
        $info = M('users')->field('mobile,level,first_leader,head_pic,nickname')->where(['user_id' => $uid])->find();

        // if ($type != 1) {
        //     //非下级且不是普通会员/VIP
        //     if (($info['first_leader'] != $this->user_id) && !in_array($info['level'], [1, 2])) {
        //         $this->ajaxReturn(['status' => -1, 'msg' => '非下级且不是普通会员/VIP!', 'data' => null]);
        //     }
        // }

        $this->ajaxReturn(['status' => 0, 'msg' => '请求成功!', 'data' => $info]);
    }

    //购物余额明细
    public function money_details()
    {
        $user = $this->user;
        $user_id = $user['user_id'];
        $p = input('p',1);
        $list = Db::name('account_log')->alias('a')->field('a.order_sn,a.user_money,a.change_time,a.log_id')->join('tp_order o','o.order_sn=a.order_sn','LEFT')->where('o.pay_name','余额支付')->where('a.user_id',$user_id)->where('a.user_money','<',0)->order('a.change_time desc')->page($p,20)->select();
        $this->assign('list',$list);
        return $this->fetch();
    }

    //ajax获取余额明细
    public function ajax_money_details()
    {
        $user = $this->user;
        $user_id = $user['user_id'];
        $p = input('p',1);
        $list = Db::name('account_log')->alias('a')->field('a.order_sn,a.user_money,a.change_time,a.log_id')->join('tp_order o','o.order_sn=a.order_sn','LEFT')->where('o.pay_name','余额支付')->where('a.user_id',$user_id)->where('a.user_money','<',0)->order('a.change_time desc')->page($p,20)->select();
        $this->assign('list',$list);
        return $this->fetch();
    }

}
