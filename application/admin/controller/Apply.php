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

	public function applyinfo(){
		$id = I('get.id/d',0);

		if(!$id)$this->error('参数错误');
		$info = M('Apply')->alias('A')->join('apply_info AI','A.id=AI.applyid','left')->where(['A.id'=>$id,'AI.type'=>1])->find();

		$info['uidinfo'] = M('Users')->field('nickname,mobile,head_pic')->find($info['uidinfo']);
		$info['leaderidinfo'] = M('Users')->field('nickname,mobile,head_pic')->find($info['leaderid']);
		$info['level_name'] = M('User_level')->where(['level'=>$info['level']])->value('level_name');

		$this->assign('info', $info);
        return $this->fetch();

	}

    public function apply_for()
    {
		$level_list = M('User_level')->field('level,level_name')->select();
		$this->assign('level_list', $level_list);
        return $this->fetch();
    }

    /**
     * 邀请列表
     */
    public function ajaxapply_for()
    {
        // 搜索条件
        $condition = array();
        $level = I('level');
        $sort_order = I('order_by','addtime') . ' ' . I('sort','desc');
		$level && $condition['level'] = $level;

        $model = M('Apply_for');
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

	public function apply_for_info(){
		$id = I('get.id/d',0);

		if(!$id)$this->error('参数错误');
		$info = M('Apply_for')->find($id);
		$info1 = M('Apply_info')->where(['applyid'=>$id])->find();

		$info['uidinfo'] = M('Users')->field('nickname,mobile,head_pic')->find($info['uidinfo']);
		$info['leaderidinfo'] = M('Users')->field('nickname,mobile,head_pic')->find($info['leaderid']);
		$info['level_name'] = M('User_level')->where(['level'=>$info['level']])->value('level_name');

		$this->assign('info', $info);
		$this->assign('info1', $info1);
        return $this->fetch();

	}

	public function refused(){
		$id = I('get.id/d',0);
		if(!$id)$this->ajaxReturn(['status'=>-1,'msg'=>'参数错误1']);

		$applyinfo = M('Apply_for')->find($id);	
		if(!$applyinfo)$this->ajaxReturn(['status'=>-1,'msg'=>'参数错误2']);
		if($applyinfo['leaderid'] != $this->user_id)$this->ajaxReturn(['status'=>-1,'msg'=>'警告，无此权限！']);
		if($applyinfo['status'] != 0)$this->ajaxReturn(['status'=>-1,'msg'=>'此申请已处理过啦！']);

		$res = M('Apply_for')->update(['id'=>$id,'status'=>2]);	
		if($res){
			$level_name = M('user_level')->where(['level'=>$applyinfo['level']])->value('level_name');

			$openid = M('users')->where(['user_id'=>$applyinfo['uid']])->value('openid');
			if($openid){
				//$url = SITE_URL."/Shop/apply/apply_agree?id=".$applyid;
				$wx_content = "您申请成为 $level_name 审核不通过！";
				$wechat = new \app\common\logic\wechat\WechatUtil();
				$wechat->sendMsg($openid, 'text', $wx_content);
			}

			//发送站内消息
			$msid = M('message_notice')->add(['message_title'=>'申请代理不通过通知','message_content'=>"您申请成为 $level_name 审核不通过！",'send_time'=>time(),'mmt_code'=>'','type'=>10]);
			if($msid)M('user_message')->add(['user_id'=>$applyinfo['uid'],'message_id'=>$msid]);
			$this->ajaxReturn(['status'=>0,'msg'=>'操作成功!','data'=>$msid]);
		}else
			$this->ajaxReturn(['status'=>-1,'msg'=>'操作失败！']);
	}

	public function agree(){
		$id = I('get.id/d',0);
		if(!$id)$this->ajaxReturn(['status'=>-1,'msg'=>'参数错误']);

		$applyinfo = M('Apply_for')->find($id);	
		if(!$applyinfo)$this->ajaxReturn(['status'=>-1,'msg'=>'参数错误']);
		if($applyinfo['leaderid'] != $this->user_id)$this->ajaxReturn(['status'=>-1,'msg'=>'警告，无此权限！']);
		if($applyinfo['status'] != 0)$this->ajaxReturn(['status'=>-1,'msg'=>'此申请已处理过啦！']);

		$res = M('Apply_for')->update(['id'=>$id,'status'=>1]);	
		if($res){
			$level_name = M('user_level')->where(['level'=>$applyinfo['level']])->value('level_name');

			$openid = M('users')->where(['user_id'=>$applyinfo['uid']])->value('openid');
			if($openid){
				$url = SITE_URL."/Shop/apply/invitation_agent?type=2&id=".$id;
				$wx_content = "您申请成为 $level_name 审核已通过啦！";
				$wechat = new \app\common\logic\wechat\WechatUtil();
				$wechat->sendMsg($openid, 'text', $wx_content);
			}

			//发送站内消息
			$msid = M('message_notice')->add(['message_title'=>'申请代理审核通过通知','message_content'=>"您申请成为 $level_name 审核已通过啦！",'send_time'=>time(),'mmt_code'=>"/Shop/apply/invitation_agent?type=2&id=".$id,'type'=>10]);
			if($msid)M('user_message')->add(['user_id'=>$applyinfo['uid'],'message_id'=>$msid]);

			$this->ajaxReturn(['status'=>0,'msg'=>'操作成功！']);
		}else
			$this->ajaxReturn(['status'=>-1,'msg'=>'操作失败！']);	
	}

}