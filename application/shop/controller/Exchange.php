<?php
/**
 * 积分兑换
 */
// namespace app\mobile\controller;
namespace app\shop\controller;

use think\Db;
use app\common\model\WxNews;

class Exchange extends MobileBase
{
    // 积分兑换首页
    public function index(){
        //获取商品
        M('Goods')->where(['is_on_sale'=>1])->select();
        return $this->fetch();
    }
    // 积分兑换详情
    public function details(){
        return $this->fetch();
    }
}