<?php
/**
 * 0元购
 */
// namespace app\mobile\controller;
namespace app\shop\controller;

use think\Db;
use app\common\model\WxNews;

class FreePurchase extends MobileBase
{
    // 0元购首页
    public function index(){
        return $this->fetch();
    }
    // 0元购详情
    public function details(){
       
        return $this->fetch();
    }
}