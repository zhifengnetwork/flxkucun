<?php
/**
 * 护肤
 */
// namespace app\mobile\controller;
namespace app\shop\controller;

use think\Db;
use app\common\model\WxNews;

class SkinCare extends MobileBase
{
    // 首页
    public function index(){
        return $this->fetch();
    }

    // 详情
    public function details(){
        return $this->fetch();
    }

}