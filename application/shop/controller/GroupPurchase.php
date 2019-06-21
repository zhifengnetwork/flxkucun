<?php
/**
 * 免费团购
 */
// namespace app\mobile\controller;
namespace app\shop\controller;

use think\Db;
use app\common\model\WxNews;

class GroupPurchase extends MobileBase
{
    public function index(){
       
        return $this->fetch();
    }
}