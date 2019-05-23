<?php

// namespace app\mobile\controller;
namespace app\shop\controller;

use app\common\logic\Message;
use app\common\logic\UsersLogic;
use app\common\model\Users as UserModel;
use app\common\model\UserMessage;
use app\common\logic\MessageBase;
use app\common\logic\MessageNoticeLogic;
use app\common\logic\UserApply;
use think\Cache;
use think\Page;
use think\db;


class Apply extends MobileBase
{

    public $user_id = 0;
    public $user = array();

    /*
    * 初始化操作
    */
    public function _initialize()
    {

        parent::_initialize();
        $this->user_id = $_SESSION['think']['user']['user_id'];
    }

	//邀请代理提交
	public function SendInvitation(){
		$level = I('post.level/d',0);
		$uid1 = I('post.uid1/d',0);

		if(!$this->user_id){
			$this->ajaxReturn(['status'=>-1,'msg'=>'请先登录!','data'=>null]);
		}
		if(!$level || !$uid1){
			$this->ajaxReturn(['status'=>-1,'msg'=>'参数错误!','data'=>null]);
		}
		$Users = M('users');
		$info = $Users->field('mobile,head_pic')->where(['user_id'=>$uid1,'first_leader'=>$this->user_id])->find();
		if(!$info)$this->ajaxReturn(['status'=>-1,'msg'=>'未查询到该用户!','data'=>null]);
		$user_level = M('user_level')->field('level')->where(['level'=>$level])->find();
		if(!$user_level)$this->ajaxReturn(['status'=>-1,'msg'=>'级别错误!','data'=>null]);

		$applyinfo = M('apply')->where(['uid'=>$uid1,'status'=>['in','0,1']])->find();  //已有申请
		if($applyinfo)$this->ajaxReturn(['status'=>-1,'msg'=>'该用户已有申请正在审核中!','data'=>null]);
		$leader_level = $Users->where(['user_id'=>$this->user_id])->value('level');
		
		if($level > $leader_level){
			$this->ajaxReturn(['status'=>-1,'msg'=>'邀请的级别不能超过自己的级别!','data'=>null]);
		}
		/*
		$UserApply = new UserApply();
		$leader_id = $UserApply->getLeaderTop($uid1,$level);  //级别对应的上级用户ID
		if(!$leader_id)$this->ajaxReturn(['status'=>-1,'msg'=>'此级别的上级用户不存在!','data'=>null]);
		*/
		$level_name = M('user_level')->where(['level'=>$level])->value('level_name');
		if(!$level_name)$this->ajaxReturn(['status'=>-1,'msg'=>'级别名称不存在!','data'=>null]);
		
		$applyid = M('apply')->add(['uid'=>$uid1,'leaderid'=>$this->user_id,'level'=>$level,'addtime'=>time()]);

		if($applyid){
			$url = SITE_URL."/Shop/apply/invitation_agent?id=".$applyid;
			$wx_content = "您的上级邀请您成为$level_name！\n\n<a href='{$url}'>点击处理</a>";
			$wechat = new \app\common\logic\wechat\WechatUtil();
			$wechat->sendMsg($user['openid'], 'text', $wx_content);

			//发送站内消息
			$msid = M('message_notice')->add(['message_title'=>'邀请代理通知','message_content'=>"您的上级邀请您成为$level_name！",'send_time'=>time(),'mmt_code'=>"/Shop/apply/invitation_agent?id=".$applyid,'type'=>7]);
			if($msid)M('user_message')->add(['user_id'=>$uid1,'message_id'=>$msid]);
			$this->ajaxReturn(['status'=>0,'msg'=>'邀请成功!','data'=>$msid]);
		}else{
			$this->ajaxReturn(['status'=>-1,'msg'=>'邀请失败!','data'=>null]);
		}

	}

    public function invitation_agent(){
        return $this->fetch();
    }

} ?>
