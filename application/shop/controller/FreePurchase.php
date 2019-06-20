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
    public function index(){
       
        return $this->fetch();
    }
    public function free_details(){
       
        return $this->fetch();
    }
}