<?php


namespace app\admin\controller;

use think\AjaxPage;
use think\Page;
use think\Verify;
use think\Db;
use think\Loader;

class Apply extends Base
{
    public function index()
    {
		$level_list = M('User_level')->field('level,level_name')->select();
		$this->assign('level_list', $level_list);
        return $this->fetch();
    }

    /**
     * 邀请列表
     */
    public function ajaxindex()
    {
        // 搜索条件
        $condition = array();
        $level = I('level');
        $sort_order = I('order_by','addtime') . ' ' . I('sort','desc');
		$level && $condition['level'] = $level;

        $model = M('Apply');
        $count = $model->where($condition)->count();
        $Page = new AjaxPage($count, 10);

        if(trim($sort_order) == ''){
            $list = $model->where($condition)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }else{
            $list = $model->where($condition)->order($sort_order)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }

		$Users = M('Users');
		$UserLevel = M('User_level');
		foreach($list as $k=>$v){
			$list[$k]['uidinfo'] = $Users->field('nickname,mobile,head_pic')->find($v['uid']);
			$list[$k]['leaderidinfo'] = $Users->field('nickname,mobile,head_pic')->find($v['leaderid']);
			$list[$k]['level_name'] = $UserLevel->where(['level'=>$v['level']])->value('level_name');
		}

        $this->assign('first_leader', $first_leader);
        $this->assign('second_leader', $second_leader);
        $this->assign('third_leader', $third_leader);
        $show = $Page->show();
        $this->assign('list', $list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }
}