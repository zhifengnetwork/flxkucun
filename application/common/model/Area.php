<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/31
 * Time: 11:59
 */

namespace app\common\model;


use think\Model;

class Area extends Model
{

    public function getAreaList($where)
    {
//        $res = S('getAreaList');
//        if(!empty($res))
//            return $res;
        $parent_region = db('Area')->where($where)->cache(true)->select();
        return $parent_region;
        $res = [];
        foreach($parent_region as $key=>$val){
            $res[$val['area_id']] = db('Area')->field('area_id,area_name')->where(array('area_parent_id'=>$parent_region[$key]['area_id']))->order('area_id asc')->cache(true)->select();
        }
        S('getAreaList',$res);
        return $res;
    }
	/**
	*是否编辑
	*@param $value
	*@param $data
	*@param $int
	*/
	public function getAreaInfo($where)
	{
        $parent_region = db('Area')->where($where)->cache(true)->find();

		return $parent_region;
	}

}