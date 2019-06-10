<?php

namespace app\common\model;

use think\Db;
use think\Model;

class Region extends Model
{
    //自定义初始化
    protected static function init()
    {
        //TODO:自定义的初始化
    }
    public function getAreaList($where)
    {
//        $res = S('getAreaList');
//        if(!empty($res))
//            return $res;
        $parent_region = db('Region')->where($where)->cache(true)->select();

        S('getAreaList',$parent_region);
        return $parent_region;
    }

    public function getAreaInfo($where)
    {
        $parent_region = db('Region')->where($where)->cache(true)->find();

        return $parent_region;
    }
}
