<?php
/**
 * 听书商城
 */
// namespace app\mobile\controller;
namespace app\shop\controller;

use think\Db;
use app\common\model\WxNews;

class Microclass extends MobileBase
{
    public function index(){
       
        return $this->fetch();
    }
}