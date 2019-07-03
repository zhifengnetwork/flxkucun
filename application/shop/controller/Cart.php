<?php
/**
 * DC环球直供网络
 * ============================================================================
 * * 版权所有 2015-2027 广州滴蕊生物科技有限公司，并保留所有权利。
 * 网站地址: http://www.dchqzg1688.com
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * $Author: IT宇宙人 2015-08-10 $
 */
// namespace app\mobile\controller;
namespace app\shop\controller;
use app\common\logic\FreightLogic;
use app\common\logic\GoodsPromFactory;
use app\common\logic\CartLogic;
use app\common\logic\CouponLogic;
use app\common\logic\Integral;
use app\common\logic\Pay;
use app\common\logic\PlaceOrder;
use app\common\logic\PreSellLogic;
use app\common\model\Combination;
use app\common\model\Goods;
use app\common\model\Order;
use app\common\model\PreSell;
use app\common\model\SpecGoodsPrice;
use app\common\util\TpshopException;
use app\common\logic\UsersLogic;
use think\Db;
use think\Loader;
use think\Url;

class Cart extends MobileBase {

    public $cartLogic; // 购物车逻辑操作类    
    public $user_id = 0;
    public $user = array();
    /**
     * 析构流函数
     */
    public function  __construct() {
        parent::__construct();
        $this->cartLogic = new CartLogic();
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
            $this->assign('user', $user); //存储用户信息
            // 给用户计算会员价 登录前后不一样
            if ($user) {
                $discount = (empty((float)$user['discount'])) ? 1 : $user['discount'];
                if ($discount != 1) {
                    $c = Db::name('cart')->where(['user_id' => $user['user_id'], 'prom_type' => 0])->where('member_goods_price = goods_price')->count();
                    $c && Db::name('cart')->where(['user_id' => $user['user_id'], 'prom_type' => 0])->update(['member_goods_price' => ['exp', 'goods_price*' . $discount]]);
                }
            }
        }
    }

    public function index()
    {
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getCartList();//用户购物车
        // $user = session('user');
        // $level = $user['level'];
        $hot_goods = db('Goods')->field('goods_id,goods_name,shop_price,market_price')->where('is_hot=1 and is_on_sale=1')->limit(20)->cache(true, TPSHOP_CACHE_TIME)->select();
        foreach($hot_goods as $key=>$val){
            $hot_goods[$key]['shop_price'] = $val['market_price'];
        }
        $this->assign('hot_goods', $hot_goods); 
        $this->assign('cartList', $cartList);//购物车列表
        return $this->fetch();
    }

    /**
     * 更新购物车，并返回计算结果
     */
    public function AsyncUpdateCart()
    {
        $cart = input('cart/a', []);
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartLogic->AsyncUpdateCart($cart);
        $select_cart_list = $cartLogic->getCartList(1);//获取选中购物车
        $cart_price_info = $cartLogic->getCartPriceInfo($select_cart_list);//计算选中购物车
        $user_cart_list = $cartLogic->getCartList();//获取用户购物车
        $return['cart_list'] = $cartLogic->cartListToArray($user_cart_list);//拼接需要的数据
        $return['cart_price_info'] = $cart_price_info;
        $this->ajaxReturn(['status' => 1, 'msg' => '计算成功', 'result' => $return]);
    }

    /**
     *  购物车加减
     */
    public function changeNum(){
        $cart = input('cart/a',[]);
        if (empty($cart)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '请选择要更改的商品', 'result' => '']);
        }
        $cartLogic = new CartLogic();
        $result = $cartLogic->changeNum($cart['id'], $cart['goods_num']);
        $this->ajaxReturn($result);
    }

    /**
     * 删除购物车商品
     */
    public function delete(){
        $cart_ids = input('cart_ids/a',[]);
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->delete($cart_ids);
        if($result !== false){
            $this->ajaxReturn(['status'=>1,'msg'=>'删除成功','result'=>$result]);
        }else{
            $this->ajaxReturn(['status'=>0,'msg'=>'删除失败','result'=>$result]);
        }
    }

    /**
     * 购物车第二步确定页面
     */
    public function cart2(){
        $goods_id = input("goods_id/d"); // 商品id
        $goods_num = input("goods_num/d");// 商品数量
        $item_id = input("item_id/d"); // 商品规格id
        $action = input("action/s"); // 行为
        $type = input("type/d",1);
        $applyid = input("applyid/d",0);
        $pei_parent = input("pei_parent/d",0);
        $goods_prom_type = input("goods_prom_type/d",0);
        $prom_id = input("prom_id/d",0);
        if ($this->user_id == 0){
            $this->error('请先登录', U('Shop/User/login'));
        }
        $logic = new UsersLogic();
        $data = $logic->get_info($this->user_id);
        $user = $data['result'];
        if ($user['mobile'] == '')
            $this->error('请先绑定手机号码', U('Shop/User/setMobile'));

        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        //立即购买
        if($action == 'buy_now'){
            $cartLogic->setProm($goods_prom_type,$prom_id);
            $cartLogic->setGoodsModel($goods_id);
            $cartLogic->setSpecGoodsPriceById($item_id);
            $cartLogic->setGoodsBuyNum($goods_num);
            $buyGoods = [];
            try{
                $buyGoods = $cartLogic->buyNow();
            }catch (TpshopException $t){
                $error = $t->getErrorArr();
                $this->error($error['msg']);
            }

            if(($user['level'] > 0) && !$goods_prom_type && !$prom_id){
                $price = M('goods_level_price')->where('goods_id',$goods_id)->where('level',$user['level'])->value('price');
                $buyGoods['member_goods_price']= $price?$price:$goods['market_price'];
            }elseif(!$goods_prom_type && !$prom_id){
                $buyGoods['member_goods_price']= $buyGoods['market_price'];
            }

            $cartList['cartList'][0] = $buyGoods;
            $cartGoodsTotalNum = $goods_num;
            $setRedirectUrl = new UsersLogic();
            $setRedirectUrl->orderPageRedirectUrl($_SERVER['REQUEST_URI'],'',$goods_id,$goods_num,$item_id ,$action);
        }elseif($action=="kucun_buy")
        { 
            //添加到购物车
            $data =I('post.');
            if(!empty($data))
            {
                //先删除购物车
                Db::name('cart')->where(['user_id' => $this->user_id, 'cart_type' =>1])->delete();
                $goods_data_ids = $data['goods_ids'];//id
                $goods_data_number = $data['number'];//数量   
                $goods_data_checkItem = $data['checkItem'];
                $pei_parent =$data['pei_parent'];
                if(count($goods_data_checkItem) > 20)$this->error('一次最多勾选20种商品');
                foreach($goods_data_ids as $k=>$v)
                {
                    if(!empty($goods_data_checkItem[$k]))
                    {
                        if($pei_parent){
                            $num = M('warehouse_goods')->where(['user_id'=>$pei_parent,'goods_id'=>$goods_data_ids[$k]])->value('nums');
                            if($num < $goods_data_number[$k])$this->error('商品库存不足');
                        }
                     // echo $pei_parent;exit;
                        $result = $this->kucun_add($goods_data_ids[$k],$goods_data_number[$k],0,$pei_parent);
                    }
                }
            }
            //print_R($cartLogic->getUserCartOrderkucunCount());
            if ($cartLogic->getUserCartOrderkucunCount() == 0){
                $this->error('你的购物车没有选中商品', 'Cart/index');
            }
            $cartList['cartList'] = $cartLogic->getCartkucunList(1); // 获取用户选中的购物车商品
            $cartList['cartList'] = $cartLogic->getCombination($cartList['cartList']);  //找出搭配购副商品
            $cartGoodsTotalNum = count($cartList['cartList']);
        }
        else{
            if ($cartLogic->getUserCartOrderCount() == 0){
                $this->error('你的购物车没有选中商品', 'Cart/index');
            }
            $cartList['cartList'] = $cartLogic->getCartList(1); // 获取用户选中的购物车商品
            $cartList['cartList'] = $cartLogic->getCombination($cartList['cartList']);  //找出搭配购副商品
            $cartGoodsTotalNum = count($cartList['cartList']);
        }
        $cartPriceInfo = $cartLogic->getCartPriceInfo($cartList['cartList']);  //初始化数据。商品总额/节约金额/商品总共数量
 
        //$levellist = M('user_level')->field('stock,replenish')->where(['level'=>['gt',$this->user['level']]])->select();
        $levelinfo = M('user_level')->field('stock,replenish')->where(['level'=>$this->user['level']])->find();
        if(($type == 1) && !$applyid && ($cartPriceInfo['total_fee'] < $levelinfo['replenish']) && ($action=="kucun_buy"))$this->error('补货金额必须达到'.$levelinfo['replenish'].'元','/shop/User/superior_store/type/1');

        if($applyid){
            $model = ($type == 1) ? M('Apply') : M('Apply_for');
            $applyinfo = $model->find($applyid);	
            if($applyinfo['uid'] != $this->user_id)$this->error('您无权限进入此仓库',"User/user_store/type/$type/applyid/$applyid");
            $levelinfo = M('user_level')->field('stock')->where(['level'=>$applyinfo['level']])->find();
            if(($cartPriceInfo['total_fee'] < $levelinfo['stock']) && ($action=="kucun_buy"))$this->error('首次进货金额必须达到'.$levelinfo['stock'].'元',"/shop/User/user_store/type/$type/applyid/".$applyid);
        }   

        $cartList = array_merge($cartList,$cartPriceInfo);

        $this->assign('type', $type);
        $this->assign('pei_parent', $pei_parent);
        $this->assign('third_leader', $this->user['third_leader']);
        $this->assign('applyid', $applyid);
        $this->assign('goods_prom_type', $goods_prom_type);
        $this->assign('prom_id', $prom_id);
        $this->assign('level', $this->user['level']);
        $this->assign('cartGoodsTotalNum', $cartGoodsTotalNum);
        $this->assign('cartList', $cartList['cartList']); // 购物车的商品
        $this->assign('cartPriceInfo', $cartPriceInfo);//商品优惠总价
        $this->assign('pei_parent', $cartList['cartList'][0]['cart_seller_id']);//取货上级
        //echo 1;exit;
        if($action=='kucun_buy'){
             return $this->fetch('kucuncart');

        }else
        {
            return $this->fetch();
        }
        
    }


    /**
     * ajax 获取订单商品价格 或者提交 订单
     */
    public function cart3(){
        if ($this->user_id == 0) {
            exit(json_encode(array('status' => -100, 'msg' => "登录超时请重新登录!", 'result' => null))); // 返回结果状态
        }
        $address_id = input("address_id/d", 0); //  收货地址id
        $invoice_title = input('invoice_title');  // 发票
        $taxpayer = input('taxpayer');       // 纳税人识别号
        $invoice_desc = input('invoice_desc');       // 发票内容
        $user_note = input("user_note/s", ''); // 用户留言
        $user_money = input("user_money/f", 0); //  使用余额
        $pay_pwd = input("pay_pwd/s", ''); // 支付密码
        $goods_id = input("goods_id/d"); // 商品id
        $goods_num = input("goods_num/d");// 商品数量
        $item_id = input("item_id/d"); // 商品规格id
        $action = input("action"); // 立即购买
        $shop_id = input('shop_id/d', 0);//自提点id
        $take_time = input('take_time/d');//自提时间
        $consignee = input('consignee/s');//自提点收货人
        $mobile = input('mobile/s');//自提点联系方式
        $data = input('request.');
        $action_type =input('action_type');
        $type = input('type/d',1);
        $applyid = input('applyid/d',0); 
        $seller_id = input('seller_id');
        $pei_parent = input('pei_parent/d',0);
        $kuaidi_type = input('kuaidi_type/d',0);
        $goods_prom_type = input('goods_prom_type/d',0);
        $prom_id = input('prom_id/d',0);
       // echo $seller_id;exit;
        $cart_validate = Loader::validate('Cart');
        if($action_type=='kucun_buy')
        {
           
            $cart_validate = Loader::validate('Newcart');
        }

        if (!$cart_validate->check($data)) {
            $error = $cart_validate->getError();
            $this->ajaxReturn(['status' => 0, 'msg' => $error, 'result' => '']);
        }
        $address = Db::name('user_address')->where("address_id", $address_id)->find();
        $cartLogic = new CartLogic();
        $pay = new Pay();
        try {
            $cartLogic->setUserId($this->user_id);
            if ($action == 'buy_now') {
                $cartLogic->setProm($goods_prom_type,$prom_id);
                $cartLogic->setGoodsModel($goods_id);
                $cartLogic->setSpecGoodsPriceById($item_id);
                $cartLogic->setGoodsBuyNum($goods_num);
                $buyGoods = $cartLogic->buyNow();
                $cartList[0] = $buyGoods;
                $pay->payGoodsList($cartList);
            } else {
                
                if($action_type=='kucun_buy')
                {
                  $userCartList = $cartLogic->getCartkucunList(1);
                }else
                {
                    $userCartList = $cartLogic->getCartList(1);
                }
                $cartLogic->checkStockCartList($userCartList,$action_type);
                $pay->payCart($userCartList);
            }
            if(($type == 1) || $applyid)
                $pay->setUserId($this->user_id)->useUserMoney($user_money);
            else
                $pay->setUserId($this->user_id)->delivery($address['district'])->useUserMoney($user_money);
                
            // 提交订单
            if ($_REQUEST['act'] == 'submit_order') {
                $prominfo = M('goods')->field('prom_type,prom_id')->find($goods_id);
                $placeOrder = new PlaceOrder($pay);
                $placeOrder->setUserAddress($address)->setUserNote($user_note)->setPayPsw($pay_pwd)->addNormalOrder($prominfo['prom_type'],$prominfo['prom_id']);
                $cartLogic->clear();
                $order = $placeOrder->getOrder();
                $this->ajaxReturn(['status' => 1, 'msg' => '提交订单成功', 'result' => $order['order_sn']]);
            }
            elseif ($_REQUEST['act'] == 'kucun_submit_order') {
                if($kuaidi_type == 2)$pay->setShippingPrice(0);
                $placeOrder = new PlaceOrder($pay);
                if(($type == 1) || $applyid){
                    $placeOrder->setUserNote($user_note)->setOrdertype()->setApplyid($applyid,$type)->setPayPsw($pay_pwd)->setSellerId($seller_id)->addNormalOrder();
                }else{
                    $placeOrder->setUserAddress($address)->setUserNote($user_note)->setPayPsw($pay_pwd)->setSellerId($seller_id)->setApplyid($applyid,$type)->setKuaiditype($kuaidi_type)->addNormalOrder();
                }
                $cartLogic->clear();
                $order = $placeOrder->getOrder();

                if($seller_id != $this->user_id){
                    $str = (($type == 2) && !$applyid) ? '取货' : '进货';
                    $msid = M('message_notice')->add(['message_title'=>'下级'.$str.'通知','message_content'=>"您有下级提交".$str."订单!",'send_time'=>time(),'mmt_code'=>'/shop/Order/order_send','type'=>11]);
                    if($msid)M('user_message')->add(['user_id'=>$seller_id,'message_id'=>$msid]);
                }

                //有上级取货时，运费为零直接减库存
                if(($type == 2) && !$applyid && ($this->user['level'] > 2) && !$order['shipping_price']){
                    $order_goods = M('order_goods')->field('goods_id,goods_name,goods_num')->where(["order_id" => $order['order_id']])->select();
                    foreach($order_goods as $v){ 
                        changekucun($v['goods_id'],$order['seller_id'],-$v['goods_num']);
                    }
                }

                $this->ajaxReturn(['status' => 1, 'msg' => '提交订单成功', 'result' => $order['order_sn'],'third_leader'=>$this->user['third_leader'],'applyid'=>$applyid,'type'=>$type]);
            }

            $pricedata = $pay->toArray();
            if (($action == 'buy_now') && ($goods_num==2)) {
                $prominfo = M('goods')->field('prom_type,prom_id')->find($goods_id);
                if($prominfo['prom_type'] == 1){
                    $prominfo1 = M('flash_sale')->field('price,is_tow_half')->find($prominfo['prom_id']); 
                    $promprice = $prominfo1['price']; 
                    $pricedata['order_prom_amount'] =  ($prominfo1['is_tow_half'] == 1) ? round($promprice/2,2) : 0;
                    $pricedata['total_amount'] -= $pricedata['order_prom_amount'];
                    $pricedata['goods_price'] -= $pricedata['order_prom_amount'];
                    if(!$user_money)
                        $pricedata['order_amount'] -= $pricedata['order_prom_amount'];
                    else
                    $pricedata['order_amount'] = $pricedata['goods_price'] + $pricedata['shipping_price'];
                }
            }

            //$region=Db::name('user_address')->where('user_id',$this->user_id)->find();
            //$pricedata['shipping_price']=$this->dispatching($goods_id,$region['district']);
            if($kuaidi_type == 2){
                $pricedata['order_amount'] -= $pricedata['shipping_price'];
                $pricedata['total_amount'] -= $pricedata['shipping_price'];
                $pricedata['shipping_price'] = 0;
            }
            if(($type == 2) && !$applyid && ($this->user_id == $pei_parent)){
                $pricedata['order_amount'] = $pricedata['shipping_price'];
                $pricedata['total_amount'] = $pricedata['shipping_price'];    
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '计算成功', 'result' => $pricedata]);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }

           /**
     * 商品物流配送和运费
     */
    public function dispatching($goods_id,$region_id)
    {
        $Goods = new \app\common\model\Goods();
        $goods = $Goods->cache(true)->where('goods_id', $goods_id)->find();
        $freightLogic = new FreightLogic();
        $freightLogic->setGoodsModel($goods);
        $freightLogic->setRegionId($region_id);
        $freightLogic->setGoodsNum(1);
        $isShipping = $freightLogic->checkShipping();
        if ($isShipping) {
            $freightLogic->doCalculation();
            $freight = $freightLogic->getFreight();
            // $dispatching_data = ['status' => 1, 'msg' => '可配送', 'result' => ['freight' => $freight]];
            return $freight;
        } else {
            // $dispatching_data = ['status' => 0, 'msg' => '该地区不支持配送', 'result' => ''];
            return 0;
        }
        // $this->ajaxReturn($dispatching_data);
    }


    /*
     * 订单支付页面
     */
    public function cart4(){
        if(empty($this->user_id)){
            $this->redirect('User/login');
        }
        $order_id = I('order_id/d');
        $order_sn = I('order_sn/s','');
        $type = I('type/d',0);
        $applyid = I('applyid/d',0);
        $order_where = ['user_id'=>$this->user_id];
        if($order_sn)
        {
            $order_where['order_sn'] = $order_sn;
        }else{
            $order_where['order_id'] = $order_id;
        }
        $Order = new Order();
        $order = $Order->where($order_where)->find();

        empty($order) && $this->error('订单不存在！');
        if($order['order_status'] == 3){
            $this->error('该订单已取消',U("Shop/Order/order_detail",array('id'=>$order['order_id'])));
        }
        if(empty($order) || empty($this->user_id)){
            $order_order_list = U("User/login");
            header("Location: $order_order_list");
            exit;
        }
        // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
        if($order['pay_status'] == 1){
            $order_detail_url = U("Shop/Order/order_detail",array('id'=>$order['order_id']));
            header("Location: $order_detail_url");
            exit;
        }
        $orderGoodsPromType = M('order_goods')->where(['order_id'=>$order['order_id']])->getField('prom_type',true);
        //如果是预售订单，支付尾款
        if ($order['pay_status'] == 2 && $order['prom_type'] == 4) {
            if ($order['pre_sell']['pay_start_time'] > time()) {
                $this->error('还未到支付尾款时间' . date('Y-m-d H:i:s', $order['pre_sell']['pay_start_time']));
            }
            if ($order['pre_sell']['pay_end_time'] < time()) {
                $this->error('对不起，该预售商品已过尾款支付时间' . date('Y-m-d H:i:s',$order['pre_sell']['pay_end_time'] ));
            }
        }
        $payment_where['type'] = 'payment';
        $no_cod_order_prom_type = [4,5];//预售订单，虚拟订单不支持货到付款
        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
            //微信浏览器
            if(in_array($order['prom_type'],$no_cod_order_prom_type) || in_array(1,$orderGoodsPromType) || $order['shop_id'] > 0){
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = 'weixin';
            }else{
                $payment_where['code'] = array('in',array('weixin','cod'));
            }
        }else{
            if(in_array($order['prom_type'],$no_cod_order_prom_type) || in_array(1,$orderGoodsPromType) || $order['shop_id'] > 0){
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = array('neq','cod');
            }
            $payment_where['scene'] = array('in',array('0','1'));
        }
        $payment_where['status'] = 1;
        //预售和抢购暂不支持货到付款
        $orderGoodsPromType = M('order_goods')->where(['order_id'=>$order['order_id']])->getField('prom_type',true);
        if($order['prom_type'] == 4 || in_array(1,$orderGoodsPromType)){
            $payment_where['code'] = array('neq','cod');
        }
        $paymentList = M('Plugin')->where($payment_where)->select();
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach($paymentList as $key => $val)
        {
            $val['config_value'] = unserialize($val['config_value']);
            if($val['config_value']['is_bank'] == 2)
            {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
            if($key != 'cod' && (($key == 'weixin' && !is_weixin()) // 不是微信app,就不能微信付，只能weixinH5付,用于手机浏览器
                || ($key != 'weixin' && is_weixin()) //微信app上浏览，只能微信
                || ($key != 'alipayMobile' && is_alipay()))){ //在支付宝APP上浏览，只能用支付宝支付
                unset($paymentList[$key]);
            }
        }

        $bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片
        $this->assign('paymentList',$paymentList);
        $this->assign('bank_img',$bank_img);
        $this->assign('order',$order);
        $this->assign('type',$type);
        $this->assign('applyid',$applyid);
        $this->assign('bankCodeList',$bankCodeList);
        $this->assign('pay_date',date('Y-m-d', strtotime("+1 day")));
        return $this->fetch();
    }

    public function PayShipping(){
        $orderLogic = new \app\common\logic\OrderLogic();
        $action = 'confirm';
        $order_id = I('get.oid/d',0);
        $type = I('get.type/d',1);

        $order = new \app\common\model\Order();
        $goods = $order::get($order_id);
        if($goods['order_status'] != 0)$this->error('请勿重复操作！');

        if(($type == 1) || (($type == 2) && $this->user['level'] > 2))
            foreach($goods['order_goods'] as $value){
                $num = user_kucun_goods($this->user_id,$value['goods_id']);
                if ($num['nums'] < $value['goods_num'] && $action != 'invalid'){
                    $this->error('您的库存不足!');
                }
            }

        $order = M('Order')->field('order_id,seller_id,order_sn,shipping_price,prom_type,pay_shipping_status')->find($order_id);
        if(!$order || (($type == 1) && ($order['seller_id'] != $this->user_id)))
            $this->error('参数错误！');
        if($order['pay_shipping_status'] !== 0)
            $this->error('此订单已支付过运费啦！');

        $payment_where['type'] = 'payment';
        $no_cod_order_prom_type = [4,5];//预售订单，虚拟订单不支持货到付款
        //预售和抢购暂不支持货到付款
        $orderGoodsPromType = M('order_goods')->where(['order_id'=>$order['order_id']])->getField('prom_type',true);
        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
            //微信浏览器
            if(in_array($order['prom_type'],$no_cod_order_prom_type) || in_array(1,$orderGoodsPromType) || $order['shop_id'] > 0){
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = 'weixin';
            }else{
                $payment_where['code'] = array('in',array('weixin','cod'));
            }
        }else{
            if(in_array($order['prom_type'],$no_cod_order_prom_type) || in_array(1,$orderGoodsPromType) || $order['shop_id'] > 0){
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = array('neq','cod');
            }
            $payment_where['scene'] = array('in',array('0','1'));
        }
        $payment_where['status'] = 1;  
        
        if($order['prom_type'] == 4 || in_array(1,$orderGoodsPromType)){
            $payment_where['code'] = array('neq','cod');
        }
        $paymentList = M('Plugin')->where($payment_where)->select();
        $paymentList = convert_arr_key($paymentList, 'code');        

        foreach($paymentList as $key => $val)
        {
            $val['config_value'] = unserialize($val['config_value']);
            if($val['config_value']['is_bank'] == 2)
            {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
            if($key != 'cod' && (($key == 'weixin' && !is_weixin()) // 不是微信app,就不能微信付，只能weixinH5付,用于手机浏览器
                || ($key != 'weixin' && is_weixin()) //微信app上浏览，只能微信
                || ($key != 'alipayMobile' && is_alipay()))){ //在支付宝APP上浏览，只能用支付宝支付
                unset($paymentList[$key]);
            }
        }

        $bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片
        $this->assign('paymentList',$paymentList);
        $this->assign('bank_img',$bank_img);
        $this->assign('order',$order);
        $this->assign('bankCodeList',$bankCodeList);
        $this->assign('pay_date',date('Y-m-d', strtotime("+1 day")));
        return $this->fetch();        
    }    

    /*
     * 订单支付页面 (上传凭证)
     */
    public function cart5(){
        if(empty($this->user_id)){
            $this->redirect('User/login');
        }
        $order_id = I('order_id/d');
        $order_sn= I('order_sn/s','');
        $type= I('type/d',0);
        $applyid= I('applyid/d',0);
        $order_where = ['user_id'=>$this->user_id];
        if($order_sn)
        {
            $order_where['order_sn'] = $order_sn;
        }else{
            $order_where['order_id'] = $order_id;
        }
        $Order = new Order();
        $order = $Order->where($order_where)->find();
        empty($order) && $this->error('订单不存在！');
        if($order['order_status'] == 3){
            $this->error('该订单已取消',U("Mobile/Order/order_detail",array('id'=>$order['order_id'])));
        }
        if(empty($order) || empty($this->user_id)){
            $order_order_list = U("User/login");
            header("Location: $order_order_list");
            exit;
        }
        // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
        if($order['pay_status'] == 1){
            $order_detail_url = U("Mobile/Order/order_detail",array('id'=>$order['order_id']));
            header("Location: $order_detail_url");
            exit;
        }
        $orderGoodsPromType = M('order_goods')->where(['order_id'=>$order['order_id']])->getField('prom_type',true);
        //如果是预售订单，支付尾款
        if ($order['pay_status'] == 2 && $order['prom_type'] == 4) {
            if ($order['pre_sell']['pay_start_time'] > time()) {
                $this->error('还未到支付尾款时间' . date('Y-m-d H:i:s', $order['pre_sell']['pay_start_time']));
            }
            if ($order['pre_sell']['pay_end_time'] < time()) {
                $this->error('对不起，该预售商品已过尾款支付时间' . date('Y-m-d H:i:s',$order['pre_sell']['pay_end_time'] ));
            }
        }
        $payment_where['type'] = 'payment';
        $no_cod_order_prom_type = [4,5];//预售订单，虚拟订单不支持货到付款
        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
            //微信浏览器
            if(in_array($order['prom_type'],$no_cod_order_prom_type) || in_array(1,$orderGoodsPromType) || $order['shop_id'] > 0){
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = 'weixin';
            }else{
                $payment_where['code'] = array('in',array('weixin','cod'));
            }
        }else{
            if(in_array($order['prom_type'],$no_cod_order_prom_type) || in_array(1,$orderGoodsPromType) || $order['shop_id'] > 0){
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = array('neq','cod');
            }
            $payment_where['scene'] = array('in',array('0','1'));
        }
        $payment_where['status'] = 1;
        //预售和抢购暂不支持货到付款
        $orderGoodsPromType = M('order_goods')->where(['order_id'=>$order['order_id']])->getField('prom_type',true);
        if($order['prom_type'] == 4 || in_array(1,$orderGoodsPromType)){
            $payment_where['code'] = array('neq','cod');
        }
        $paymentList = M('Plugin')->where($payment_where)->select();
        $paymentList = convert_arr_key($paymentList, 'code');

//        foreach($paymentList as $key => $val)
//        {
//            $val['config_value'] = unserialize($val['config_value']);
//            if($val['config_value']['is_bank'] == 2)
//            {
//                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
//            }
//            if($key != 'cod' && (($key == 'weixin' && !is_weixin()) // 不是微信app,就不能微信付，只能weixinH5付,用于手机浏览器
//                    || ($key != 'weixin' && is_weixin()) //微信app上浏览，只能微信
//                    || ($key != 'alipayMobile' && is_alipay()))){ //在支付宝APP上浏览，只能用支付宝支付
//                unset($paymentList[$key]);
//            }
//        }

        $bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片
        $this->assign('paymentList',$paymentList);
        $this->assign('bank_img',$bank_img);
        $this->assign('order',$order);
        $this->assign('level',$this->user['level']);
        $this->assign('first_leader',$this->user['first_leader']);
        $this->assign('type',$type);
        $this->assign('applyid',$applyid);
        $this->assign('bankCodeList',$bankCodeList);
        $this->assign('third_leader',I('get.third_leader/d',0));
        $this->assign('pay_date',date('Y-m-d', strtotime("+1 day")));
        return $this->fetch();
    }

    /**
     * ajax 将商品加入购物车
     */
    function add()
    {
        $goods_id = I("goods_id/d"); // 商品id
        $goods_num = I("goods_num/d");// 商品数量
        $item_id = I("item_id/d"); // 商品规格id
        if(empty($goods_id)){
            $this->ajaxReturn(['status'=>-1,'msg'=>'请选择要购买的商品','result'=>'']);
        }
        if(empty($goods_num)){
            $this->ajaxReturn(['status'=>-1,'msg'=>'购买商品数量不能为0','result'=>'']);
        }
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartLogic->setGoodsModel($goods_id);
        $cartLogic->setSpecGoodsPriceById($item_id);
        $cartLogic->setGoodsBuyNum($goods_num);
        try {
            $cartLogic->addGoodsToCart();
            $this->ajaxReturn(['status' => 1, 'msg' => '加入购物车成功']);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }

        /**
     * ajax 将库存商品加入购物车 
     */
    function kucun_add($goods_id,$goods_num,$item_id=0,$seller_id=0)
    {
        $message =array();
       // $goods_id = I("goods_id/d"); // 商品id
       // $goods_num = I("goods_num/d");// 商品数量
        //$item_id = I("item_id/d"); // 商品规格id
        if(empty($goods_id)){
            //$this->ajaxReturn(['status'=>-1,'msg'=>'请选择要购买的商品','result'=>'']);
            $message =['status'=>-1,'msg'=>'请选择要购买的商品','result'=>''];
        }
        if(empty($goods_num)){
           // $this->ajaxReturn(['status'=>-1,'msg'=>'购买商品数量不能为0','result'=>'']);
            $message =['status'=>-1,'msg'=>'购买商品数量不能为0','result'=>''];
        }
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartLogic->setGoodsModel($goods_id);
        $cartLogic->setSpecGoodsPriceById($item_id);
        $cartLogic->setGoodsBuyNum($goods_num);
        $cartLogic->setCartDellerId($seller_id);
        try {
            $cartLogic->kucun_addGoodsToCart();
           // $cartLogic->addGoodsToCart();
           // $this->ajaxReturn(['status' => 1, 'msg' => '加入购物车成功']);
             $message =['status' => 1, 'msg' => '加入购物车成功'];
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $message =$error;
            //$this->ajaxReturn($error);
        }
        return $message;
    }
    /**
    检查线下库存
    **/
    public function chekoutuserkucun()
    {
      
        $message =array();
         
        //检查库存
            $data =I('post.');
            if(!empty($data))
            {
              
                $goods_data_ids = $data['goods_ids'];//id
                $goods_data_number = $data['number'];//数量   
                $goods_data_checkItem = $data['checkItem'];
                $pei_parent =$data['pei_parent'];
                /*
                if(!$pei_parent){
                    $pei_parent =$this->user['first_leader'];
                }*/
                
                foreach($goods_data_ids as $k=>$v)
                {
                    if(!empty($goods_data_checkItem[$k]))
                    {
                        if(($this->user['level']==5) || !$pei_parent)
                        {
                            $store_count =  $goods = M("goods")->where(['goods_id'=>$goods_data_ids[$k]])->value('store_count');
                            if($store_count<$goods_data_number[$k])
                            {
                                $message =['status' => 0, 'msg' => '商品库存数量不够'];
                                return  $this->ajaxReturn($message);
                                 break;
                            }
                        }else
                        {
                            $warehouse_goods_list = M("warehouse_goods")->alias('wg')
                                                    ->field('wg.nums,g.goods_name,g.goods_id,g.shop_price,g.original_img')
                                                // ->join('users u','wg.user_id=u.user_id','LEFT')
                                                    ->join('goods g','wg.goods_id=g.goods_id','LEFT')
                                                    ->where(['wg.user_id'=>$pei_parent,'g.goods_id'=>$goods_data_ids[$k]])
                                                    ->find();

                            if($warehouse_goods_list['nums']<$goods_data_number[$k])
                            {
                                $message =['status' => 0, 'msg' => '商品库存数量不够'];
                                return  $this->ajaxReturn($message);
                                break;
                            }
                        }
                    }
                }
            }

            $message =['status' => 1, 'msg' => '加入购物车成功'];
            return  $this->ajaxReturn($message);
                        

            
            
    }

    /**
     * ajax 将搭配购商品加入购物车
     */
    public function addCombination()
    {
        $combination_id = input('combination_id/d');//搭配购id
        $num = input('num/d');//套餐数量
        $combination_goods = input('combination_goods/a');//套餐里的商品
        if (empty($combination_id)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
        }
        $cartLogic = new CartLogic();
        $combination = Combination::get(['combination_id' => $combination_id]);
        $cartLogic->setUserId($this->user_id);
        $cartLogic->setCombination($combination);
        $cartLogic->setGoodsBuyNum($num);
        try {
            $cartLogic->addCombinationToCart($combination_goods);
            $this->ajaxReturn(['status' => 1, 'msg' => '成功加入购物车']);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }

    /**
     * ajax 获取用户收货地址 用于购物车确认订单页面
     */
    public function ajaxAddress(){
        $regionList = get_region_list();
        $address_list = M('UserAddress')->where("user_id", $this->user_id)->select();
        $c = M('UserAddress')->where("user_id = {$this->user_id} and is_default = 1")->count(); // 看看有没默认收货地址
        if((count($address_list) > 0) && ($c == 0)) // 如果没有设置默认收货地址, 则第一条设置为默认收货地址
            $address_list[0]['is_default'] = 1;

        $this->assign('regionList', $regionList);
        $this->assign('address_list', $address_list);
        return $this->fetch('ajax_address');
    }


    /**
     * 兑换积分商品
     */
    public function buyIntegralGoods(){
        $goods_id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num');
        $Integral = new Integral();
        $Integral->setUserById($this->user_id);
        $Integral->setGoodsById($goods_id);
        $Integral->setSpecGoodsPriceById($item_id);
        $Integral->setBuyNum($goods_num);
        try{
            $Integral->checkBuy();
            $url = U('Shop/Cart/integral', ['goods_id' => $goods_id, 'item_id' => $item_id, 'goods_num' => $goods_num]);
            $result = ['status' => 1, 'msg' => '购买成功', 'result' => ['url' => $url]];
            $this->ajaxReturn($result);
        }catch (TpshopException $t){
            $result = $t->getErrorArr();
            $this->ajaxReturn($result);
        }
    }

    /**
     *  积分商品结算页
     * @return mixed
     */
    public function integral(){
        $goods_id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num/d', 1);
        if (empty($goods_id)) {
            $this->error('非法操作');
        }
        $Goods = new Goods();
        $goods = $Goods->where(['goods_id' => $goods_id])->find();
        $goods['shop_price'] = getLevelPrice($goods['goods_id'],$this->user['level']);//获取等级价格
        if (empty($goods)) {
            $this->error('该商品不存在');
        }
        $goods = $goods->toArray();
        if ($item_id) {
            $SpecGoodsPrice = new SpecGoodsPrice();
            $spec_goods_price = $SpecGoodsPrice->where('goods_id', $goods_id)->where('item_id', $item_id)->find();
            $goods['shop_price'] = $spec_goods_price['price'];
            $goods['key_name'] = $spec_goods_price['key_name'];
        }
        $goods['goods_num'] = $goods_num;
        $this->assign('goods', $goods);
        return $this->fetch();
    }

    /**
     *  积分商品价格提交
     * @return mixed
     */
    public function integral2()
    {
        if ($this->user_id == 0) {
            $this->ajaxReturn(['status' => -100, 'msg' => "登录超时请重新登录!", 'result' => null]);
        }
        $goods_id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num/d');
        $address_id = input("address_id/d"); //  收货地址id
        $user_note = input('user_note'); // 给卖家留言
        $invoice_title = input('invoice_title');  // 发票
        $taxpayer = input('taxpayer');       // 纳税人识别号
        $invoice_desc = input('invoice_desc');       // 发票内容
        $user_money = input("user_money/f", 0); //  使用余额
        $pay_pwd = input('pay_pwd');
        $shop_id = input('shop_id/d', 0);//自提点id
        $take_time = input('take_time/d');//自提时间
        $consignee = input('consignee/s');//自提点收货人
        $mobile = input('mobile/s');//自提点联系方式
        $integral = new Integral();
        $integral->setUserById($this->user_id);
        $integral->setShopById($shop_id);
        $integral->setGoodsById($goods_id);
        $integral->setBuyNum($goods_num);
        $integral->setSpecGoodsPriceById($item_id);
        $integral->setUserAddressById($address_id);
        $integral->useUserMoney($user_money);
        try {
            $integral->checkBuy();
            $pay = $integral->pay();
            // 提交订单
            if ($_REQUEST['act'] == 'submit_order') {
                $placeOrder = new PlaceOrder($pay);
                $placeOrder->setUserAddress($integral->getUserAddress());
                $placeOrder->setConsignee($consignee);
                $placeOrder->setMobile($mobile);
                $placeOrder->setInvoiceTitle($invoice_title);
                $placeOrder->setUserNote($user_note);
                $placeOrder->setTaxpayer($taxpayer);
                $placeOrder->setInvoiceDesc($invoice_desc);
                $placeOrder->setPayPsw($pay_pwd);
                $placeOrder->setTakeTime($take_time);
                $placeOrder->addNormalOrder();
                $order = $placeOrder->getOrder();
                $this->ajaxReturn(['status' => 1, 'msg' => '提交订单成功', 'result' => $order['order_id']]);
                if($_SESSION["invoiceInfo"] != "") {
                    unset($_SESSION["invoiceInfo"]);
                }
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '计算成功', 'result' => $pay->toArray()]);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }
	
	 /**
     *  获取发票信息
     * @date2017/10/19 14:45
     */
    public function invoice(){
        $setRedirectUrl = new UsersLogic();
        $setRedirectUrl->setUserId($this->user_id);
        $info = $setRedirectUrl->getUserDefaultInvoice();
        if(empty($info)){
            $result=['status' => -1, 'msg' => 'N', 'result' =>''];
        }else{
            $result=['status' => 1, 'msg' => 'Y', 'result' => $info];
        }
        $this->ajaxReturn($result);            
    }
     /**
     *  保存发票信息
     * @date2017/10/19 14:45
     */
    public function save_invoice(){

        if(IS_AJAX){
            
            //A.1获取发票信息
            $invoice_title = trim(I("invoice_title"));
            $taxpayer      = trim(I("taxpayer"));
            $invoice_desc  = trim(I("invoice_desc"));
            
            //B.1校验用户是否有历史发票记录
            $map            = [];
            $map['user_id'] =  $this->user_id;
            $info           = M('user_extend')->where($map)->find();
            
           //B.2发票信息
            $data=[];  
            $data['invoice_title'] = $invoice_title;
            $data['taxpayer']      = $taxpayer;  
            $data['invoice_desc']  = $invoice_desc;     
            
            //B.3发票抬头
            if($invoice_title=="个人"){
                $data['invoice_title'] ="个人";
                $data['taxpayer']      = "";
            }                              
            
            
            //是否存贮过发票信息
            if(empty($info)){
                $data['user_id'] = $this->user_id;
                (M('user_extend')->add($data))?
                $status=1:$status=-1;                
             }else{
                (M('user_extend')->where($map)->save($data))?
                $status=1:$status=-1;                
            }            
            $result = ['status' => $status, 'msg' => '', 'result' =>''];           
            $this->ajaxReturn($result); 
            
        }      
    }
    /**
     * 预售
     */
    public function pre_sell()
    {
        $prom_id = input('prom_id/d');
        $goods_num = input('goods_num/d');
        if ($this->user_id == 0){
            $this->error('请先登录');
        }
        if(empty($prom_id)){
            $this->error('参数错误');
        }
        $PreSell = new PreSell();
        $preSell = $PreSell::get($prom_id);
        if(empty($preSell)){
            $this->error('活动不存在');
        }
        $PreSellLogic = new PreSellLogic($preSell->goods, $preSell->specGoodsPrice);
        if($PreSellLogic->checkActivityIsEnd()){
            $this->error('活动已结束');
        }
        if(!$PreSellLogic->checkActivityIsAble()){
            $this->error('活动未开始');
        }
        $cartList = [];
        try{
            $cartList[0] = $PreSellLogic->buyNow($goods_num);
        }catch (TpshopException $t){
            $error = $t->getErrorArr();
            $this->error($error['msg']);
        }
        $cartTotalPrice = array_sum(array_map(function($val){return $val['goods_fee'];}, $cartList));//商品优惠总价
        $this->assign('cartList', $cartList);//购物车列表
        $this->assign('preSell', $preSell);
        $this->assign('cartTotalPrice', $cartTotalPrice);
        return $this->fetch();
    }
    
}
