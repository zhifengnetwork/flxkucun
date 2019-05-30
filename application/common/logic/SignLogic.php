<?php


namespace app\common\logic;
use app\common\model\Cart;
use app\common\model\CouponList;
use app\common\model\Shop;
use app\common\util\TpshopException;
use app\mobile\controller\Sign;
use app\common\model\Goods;
use think\Model;
use think\Db;
/**
 * 计算价格类
 * Class CatsLogic
 * @package Home\Logic
 */
class SignLogic
{
    protected $payList;
    protected $userId;
    protected $user;

    private $totalAmount = 0;//订单总价
    private $orderAmount = 0;//应付金额
    private $totalNum = 0;// 商品总共数量
    private $integralMoney = 0;//积分抵消金额
    private $userMoney = 0;//使用余额
    private $payPoints = 0;//使用积分

    private $orderPromId;//订单优惠ID
    private $orderPromAmount = 0;//订单优惠金额
    private $couponId;
    private $shop;//自提点


    /**
     * 设置用户ID
     * @param $user_id
     * @return $this
     * @throws TpshopException
     */
    public function setUserId($user_id)
    {
        $this->userId = $user_id;
        $this->user = Db::name('users')->where(['user_id' => $this->userId])->find();
        if(empty($this->user)){
            throw new TpshopException("签到",0,['status' => -9, 'msg' => '未找到用户', 'result' => '']);
        }
        return $this;
    }

    /**
     * 赠送用户积分
     * @param Order $order
     */
    public function UserPointMoney($userID)
    {
        if($this->pay->getPayPoints() > 0 || $this->pay->getUserMoney() > 0){
            $user = $this->pay->getUser();
            $user = Users::get($user['user_id']);
            if($this->pay->getPayPoints() > 0){
                $user->pay_points = $user->pay_points + $this->pay->getPayPoints();// 消费积分
            }

            $user->save();
            $accountLogData = [
                'user_id' => $order['user_id'],
                'user_money' => -$this->pay->getUserMoney(),
                'pay_points' => -$this->pay->getPayPoints(),
                'change_time' => time(),
                'desc' => '下单消费',
                'order_sn'=>$order['order_sn'],
                'order_id'=>$order['order_id'],
            ];
            Db::name('account_log')->insert($accountLogData);
        }
    }








    /**
     * 计算订单表的普通订单商品
     * @param $order_goods
     * @return $this
     * @throws TpshopException
     */
    public function payOrder($order_goods){
        $this->payList = $order_goods;
        $order = Db::name('order')->where('order_id',  $this->payList[0]['order_id'])->find();
        if(empty($order)){
            throw new TpshopException('计算订单价格', 0, ['status' => -9, 'msg' => '找不到订单数据', 'result' => '']);
        }
        $reduce = tpCache('shopping.reduce');
        if($order['pay_status'] == 0 && $reduce == 2){
            $goodsListCount = count($this->payList);
            for ($payCursor = 0; $payCursor < $goodsListCount; $payCursor++) {
                $goods_stock = getGoodNum($this->payList[$payCursor]['goods_id'], $this->payList[$payCursor]['spec_key']); // 最多可购买的库存数量
                if($goods_stock <= 0 && $this->payList[$payCursor]['goods_num'] > $goods_stock){
                    throw new TpshopException('计算订单价格', 0, ['status' => -9, 'msg' => $this->payList[$payCursor]['goods_name'].','.$this->payList[$payCursor]['spec_key_name'] . "库存不足,请重新下单", 'result' => '']);
                }
            }
        }
        $this->Calculation();
        return $this;
    }


    /**
     * 使用积分
     * @throws TpshopException
     * @param $pay_points
     * @param $is_exchange|是否有使用积分兑换商品流程
     * @param $port
     * @return $this
     */
    public function usePayPoints($pay_points, $is_exchange = false, $port = "pc")
    {
        if($pay_points > 0 && $this->orderAmount > 0){
            //积分规则修改后的逻辑
            $isUseIntegral = tpCache('integral.is_use_integral');
            $isPointMinLimit = tpCache('integral.is_point_min_limit');
            $isPointRate = tpCache('integral.is_point_rate');
            $isPointUsePercent = tpCache('integral.is_point_use_percent');
            $point_rate = tpCache('integral.point_rate');
            if($is_exchange == false){
                if($isUseIntegral==1 && $isPointUsePercent==1) {
                    $use_percent_point = tpCache('integral.point_use_percent')/100;
                }else{
                    $use_percent_point = 1;
                }
                if($isUseIntegral==1 && $isPointMinLimit==1) {
                    $min_use_limit_point = tpCache('integral.point_min_limit');
                }else{
                    $min_use_limit_point = 0;
                }
                if($isUseIntegral == 0 || $isPointRate != 1){
                    throw new TpshopException("计算订单价格",0,['status' => -1, 'msg' => '该笔订单不能使用积分', 'result' => '']);
                }
                if($use_percent_point > 0 && $use_percent_point < 1){
                    //计算订单最多使用多少积分
                    $point_limit = intval($this->totalAmount * $point_rate * $use_percent_point);
                    if($pay_points > $point_limit){
                        if($port=="mobile"){
                            $pay_points = $point_limit;
                        }else {
                            throw new TpshopException("计算订单价格", 0, ['status' => -1, 'msg' => "该笔订单, 您使用的积分不能大于" . $point_limit, 'result' => '']);
                        }
                    }
                }
                //计算订单最多使用多少积分(没勾选比例的情况)
                $next_point_limit = intval($this->totalAmount * $point_rate * $use_percent_point);
                if($port!="mobile" && $pay_points > $next_point_limit){
                    throw new TpshopException("计算订单价格", 0, ['status' => -1, 'msg' => "该笔订单, 您使用的积分不能大于" . $next_point_limit, 'result' => '']);
                }

                if($pay_points > $this->user['pay_points']){
                    throw new TpshopException("计算订单价格",0,['status' => -5, 'msg' => "你的账户可用积分为:" . $this->user['pay_points'], 'result' => '']);
                }
                if ($min_use_limit_point > 0 && $this->user['pay_points'] < $min_use_limit_point) {
                    throw new TpshopException("计算订单价格",0,['status' => -1, 'msg' => "积分小于".$min_use_limit_point."时 ，不能使用积分", 'result' => '']);
                }
                $order_amount_pay_point = round($this->orderAmount * $point_rate,2);
                //$order_amount_pay_point = $this->orderAmount * $point_rate;
                if($pay_points > $order_amount_pay_point){
                    $this->payPoints = $order_amount_pay_point;
                }else{
                    $this->payPoints = $pay_points;
                }
                $this->integralMoney = $this->payPoints / $point_rate;
                $this->orderAmount = $this->orderAmount - $this->integralMoney;
            }else{
                //积分兑换流程
                if($pay_points <= $this->user['pay_points']){
                    $this->payPoints = $pay_points;
                    $this->integralMoney = $pay_points / $point_rate;//总积分兑换成的金额
                }else{
                    $this->payPoints = 0;//需要兑换的总积分
                    $this->integralMoney = 0;//总积分兑换成的金额
                }
                $this->orderAmount = $this->orderAmount - $this->integralMoney;
            }

        }
        return $this;
    }

    /**
     * 使用余额
     * @throws TpshopException
     * @param $user_money
     * @return $this
     */
    public function useUserMoney($user_money)
    {
        if($user_money > 0){
            if($user_money > $this->user['user_money']){
                throw new TpshopException("计算订单价格",0,['status' => -6, 'msg' =>  "你的账户可用余额为:" . $this->user['user_money'], 'result' => '']);
            }
            if($this->orderAmount > 0){
                if($user_money > $this->orderAmount){
                    $this->userMoney = $this->orderAmount;
                    $this->orderAmount = 0;
                }else{
                    $this->userMoney = $user_money;
                    $this->orderAmount = $this->orderAmount - $this->userMoney;
                }
            }
        }
        return $this;
    }



    /**
     * 获取实际上使用的积分
     * @return float|int
     */
    public function getPayPoints()
    {
        return $this->payPoints;
    }


    /**
     * 获取用户
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    public function getPayList()
    {
        return $this->payList;
    }

    public function getCouponId()
    {
        return $this->couponId;
    }

    public function getOrderPromAmount()
    {
        return $this->orderPromAmount;
    }
    public function getOrderPromId()
    {
        return $this->orderPromId;
    }

    public function getShop()
    {
        return $this->shop;
    }

    public function getToTalNum()
    {
        return $this->totalNum;
    }


}