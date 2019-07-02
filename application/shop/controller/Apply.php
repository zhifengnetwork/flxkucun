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
		$info = $Users->field('level,mobile,first_leader,head_pic,openid')->where(['user_id'=>$uid1])->find();
		//非下级且不是普通会员/VIP
		//2019-07-01修改的
        if(($info['first_leader'] != $this->user_id) && !in_array($info['level'],[1,2]))$this->ajaxReturn(['status'=>-1,'msg'=>'该用户已有上级!','data'=>null]);  
		if(!$info)$this->ajaxReturn(['status'=>-1,'msg'=>'未查询到该用户!','data'=>null]);
		if($info['level'] >= $level)$this->ajaxReturn(['status'=>-1,'msg'=>'该下级用户的代理级别已不小于邀请级别!','data'=>null]);
		$user_level = M('user_level')->field('level')->where(['level'=>$level])->find();
		if(!$user_level)$this->ajaxReturn(['status'=>-1,'msg'=>'级别错误!','data'=>null]);

		$applyinfo = M('apply')->where(['uid'=>$uid1,'status'=>['in','0,1,3']])->find();  //已有申请
		if($applyinfo)$this->ajaxReturn(['status'=>-1,'msg'=>'该用户已有申请正在审核中!','data'=>null]);
		$leader_level = $Users->where(['user_id'=>$this->user_id])->value('level');
		
		if($level > $leader_level){
			$this->ajaxReturn(['status'=>-1,'msg'=>'邀请的级别不能超过自己的级别!','data'=>null]);
		}

		$level_name = M('user_level')->where(['level'=>$level])->value('level_name');
		if(!$level_name)$this->ajaxReturn(['status'=>-1,'msg'=>'级别名称不存在!','data'=>null]);
		
		$applyid = M('apply')->add(['uid'=>$uid1,'leaderid'=>$this->user_id,'level'=>$level,'addtime'=>time()]);

		if($applyid){
			if($info['openid']){
				$url = SITE_URL."/Shop/apply/invitation_agent?id=".$applyid;
				$wx_content = "您的上级邀请您成为".$level_name."！\n\n<a href='{$url}'>点击处理</a>";
				$wechat = new \app\common\logic\wechat\WechatUtil();
				$wechat->sendMsg($info['openid'], 'text', $wx_content);
			}

			//发送站内消息
			$msid = M('message_notice')->add(['message_title'=>'邀请代理通知','message_content'=>"您的上级邀请您成为".$level_name."！",'send_time'=>time(),'mmt_code'=>"/Shop/apply/invitation_agent?id=".$applyid,'type'=>7]);
			if($msid)M('user_message')->add(['user_id'=>$uid1,'message_id'=>$msid]);
			$this->ajaxReturn(['status'=>0,'msg'=>'邀请成功!','data'=>$msid]);
		}else{
			$this->ajaxReturn(['status'=>-1,'msg'=>'邀请失败!','data'=>null]);
		}

	}

    public function invitation_agent(){
		$id = I('get.id/d',1);
		$type = I('get.type/d',1);
		if(!$id)$this->error('参数错误');
		$model = ($type == 1) ? M('Apply') : M('Apply_for');
		$applyinfo = $model->find($id);		
		if(!$applyinfo)$this->error('无此次邀请');
		if($applyinfo['uid'] != $this->user_id)$this->error('您无权限查看此邀请');

		$num = M('Apply_info')->where(['applyid'=>$id,'type'=>$type])->count();
		if((($type == 1) && ($applyinfo['status'] == 1) && ($this->user['level'] < $applyinfo['level'])) || $num){//跳转到上级页面
			$this->redirect(U("User/user_store",['type'=>$type,'applyid'=>$id,'leaderid'=>$applyinfo['leaderid'],'level'=>$applyinfo['level']]));
			return;
		}
		if(($type == 1) && ($applyinfo['status'] == 1) && ($this->user['level'] >= $applyinfo['level'])){//跳转到上级页面
			$this->redirect(U("User/superior_store",['applyid'=>$id,'leaderid'=>$applyinfo['leaderid'],'level'=>$applyinfo['level']]));
			return;
		}	

		$str = ($type == 1) ? '邀请' : '申请';	
		if(in_array($applyinfo['status'],[2,3]))$this->error('此'.$str.'已处理过啦');	

		$level = M('Users')->where(['user_id'=>$applyinfo['uid']])->value('level');
		if($level >= $applyinfo['level'])$this->error('您的代理级别已不小于'.$str.'级别！');	

		$applyinfo['leaderidinfo'] = M('Users')->field('nickname,mobile,head_pic')->find($applyinfo['leaderid']);
		$applyinfo['level_name'] = M('User_level')->where(['level'=>$applyinfo['level']])->value('level_name');

		//$model->save(['id'=>$id,'status'=>1]);

		$this->assign('applyinfo',$applyinfo);
		$this->assign('str',$str);
		$this->assign('type',$type); 
        return $this->fetch();
	}
	
    public function apply_for_agent(){
		$id = I('get.id/d',0);
		$type = I('get.type/d',2);
		if(!$id)$this->error('参数错误');
		
		$applyinfo = M('Apply_for')->find($id);	
		if(!$applyinfo)$this->error('无此次申请');
		if($applyinfo['uid'] != $this->user_id)$this->error('您无权限查看此申请');
		if($applyinfo['status'] == 1){//跳转到上级页面
			$this->redirect(U("User/superior_store",['leaderid'=>$applyinfo['leaderid'],'level'=>$applyinfo['level']]));
			return;
		}	
		if(in_array($applyinfo['status'],[2,3]))$this->error('此邀请已处理过啦');	

		$level = M('Users')->where(['user_id'=>$applyinfo['uid']])->value('level');
		if($level >= $applyinfo['level'])$this->error('您的代理级别已不小于申请级别！');	

		$applyinfo['leaderidinfo'] = M('Users')->field('nickname,mobile,head_pic')->find($applyinfo['leaderid']);
		$applyinfo['level_name'] = M('User_level')->where(['level'=>$applyinfo['level']])->value('level_name');

		//M('Apply_for')->save(['id'=>$id,'status'=>1]);

		$this->assign('applyinfo',$applyinfo);
		$this->assign('type',$type);
        return $this->fetch();
    }	

	public function submit_invitation_agent(){
		$applyid = I('post.applyid/d',0);
		$name = I('post.name/s','');
		$weixin = I('post.weixin/s','');
		$tel = I('post.tel/s','');
		$idcard = I('post.idcard/s','');
		$wx_nickname = I('post.wx_nickname/s','');
		$type = I('post.type/d',1);

		if(!$applyid || !$name || !$weixin || !$tel || !$idcard || !$wx_nickname){
			$this->ajaxReturn(['status' => -1, 'msg' => "参数错误"]);
		}

		$typemsg = ($type == 1) ? '邀请' : '申请';

		$model = ($type == 1) ? M('Apply') : M('Apply_for');
		$applyinfo = $model->find($applyid);	
		if(!$applyinfo)$this->ajaxReturn(['status' => -1, 'msg' => "无此次$typemsg"]);
		if($applyinfo['uid'] != $this->user_id)$this->ajaxReturn(['status' => -1, 'msg' => "您无权限查看此$typemsg"]);
		if(($type == 1) && ($applyinfo['status'] != 0))$this->ajaxReturn(['status' => -1, 'msg' => '此'.$typemsg.'您已处理过啦']);
		if(($type == 2) && in_array($applyinfo['status'],[2,3]))$this->ajaxReturn(['status' => -1, 'msg' => '此'.$typemsg.'您已处理过啦']);

		$level = M('Users')->where(['user_id'=>$applyinfo['uid']])->value('level');
		if($level >= $applyinfo['level'])$this->ajaxReturn(['status' => -1, 'msg' => "您的代理级别已不小于$typemsg级别！"]);

		Db::startTrans();
		try{
			$res = m('apply_info')->add(['applyid'=>$applyid,'name'=>$name,'weixin'=>$weixin,'tel'=>$tel,'idcard'=>$idcard,'wx_nickname'=>$wx_nickname,'type'=>$type]);
			$res ? $model->where(['id'=>$applyid])->update(['status'=>1]) : Db::rollback();
			/* 2019-06-22 add */
			// 提交事务
			Db::commit(); 
			//跳转到上级仓库
			//$this->redirect(U("User/user_store",['applyid'=>$applyid,'leaderid'=>$applyinfo['leaderid'],'level'=>$applyinfo['level']]));
			$this->ajaxReturn(['status'=>0,'msg'=>'请求成功!','result'=>U("User/user_store",['type'=>$type,'applyid'=>$applyid,'leaderid'=>$applyinfo['leaderid'],'level'=>$applyinfo['level']])]);
			// return;
			/* 2019-06-22 add */
			///* 2019-06-22 del */M('Users')->where(['user_id'=>$applyinfo['uid']])->update(['level'=>$applyinfo['level']]);

			$nickname = M('Users')->where(['user_id'=>$applyinfo['uid']])->value('nickname');
			$openid = M('Users')->where(['user_id'=>$applyinfo['leaderid']])->value('openid');
			if($openid){
				//$url = SITE_URL."/Shop/apply/invitation_agent?id=".$applyid;
				$wx_content = "您的下级 $nickname 已填写资料，$typemsg成功!";
				$wechat = new \app\common\logic\wechat\WechatUtil();
				$wechat->sendMsg($openid, 'text', $wx_content);
			}

			//发送站内消息
			$msid = M('message_notice')->add(['message_title'=>$typemsg.'成功通知','message_content'=>"您的下级 $nickname 已填写资料，$typemsg成功!",'send_time'=>time(),'mmt_code'=>'','type'=>8]);
			if($msid)M('user_message')->add(['user_id'=>$applyinfo['leaderid'],'message_id'=>$msid]);
			// 提交事务
			Db::commit(); 			
			$this->ajaxReturn(['status'=>0,'msg'=>'请求成功!']);
		} catch (TpshopException $t) {
			// 回滚事务
            Db::rollback();
            $error = $t->getErrorArr();
            $this->ajaxReturn(['status' => -99, 'msg' => $error, 'data' => null]);
		}

		$this->ajaxReturn(['status' => 0, 'msg' => "请求成功"]);
	}

	public function submit_apply_for(){
		$level = I('post.level/d',0);
		$name = I('post.name/s','');
		$tel = I('post.tel/s','');
		$msg = I('post.msg/s','');

		if(!$level || !$name || !$tel || !$msg){
			$this->ajaxReturn(['status' => -1, 'msg' => "参数错误"]);
		}

		$UserApply = new UserApply();
		$leader_id = $UserApply->getLeaderTop($this->user_id,$level);  //级别对应的上级用户ID

		if((mb_strlen($msg) < 5) || (mb_strlen($msg) > 590))$this->ajaxReturn(['status' => -1, 'msg' => "申请说明过短或超长"]);

		$info = M('apply_for')->where(['uid'=>$this->user_id,'status'=>['in','0,1']])->find();
		if($info)$this->ajaxReturn(['status' => -1, 'msg' => "您已有申请正在审核中！"]);

		$level_name = M('user_level')->where(['level'=>$level])->value('level_name');
		if(!$level_name)$this->ajaxReturn(['status'=>-1,'msg'=>'级别错误!','data'=>null]);

		$applyid = M('apply_for')->add(['uid'=>$this->user_id,'leaderid'=>$leader_id,'level'=>$level,'addtime'=>time(),'name'=>$name,'tel'=>$tel,'msg'=>$msg]);
		if($applyid){
			if($leader_id){
				$openid = M('users')->where(['user_id'=>$leader_id])->value('openid');
				if($openid){
					$url = SITE_URL."/Shop/apply/apply_agree?id=".$applyid;
					$wx_content = "您有下级向您申请成为 $level_name ！ \n\n<a href='{$url}'>点击处理</a>";
					$wechat = new \app\common\logic\wechat\WechatUtil();
					$wechat->sendMsg($openid, 'text', $wx_content);
				}

				//发送站内消息
				$msid = M('message_notice')->add(['message_title'=>'申请代理通知','message_content'=>"您有下级向您申请成为 $level_name ！",'send_time'=>time(),'mmt_code'=>"/Shop/apply/apply_agree?id=".$applyid,'type'=>9]);
				if($msid)M('user_message')->add(['user_id'=>$leader_id,'message_id'=>$msid]);
				$this->ajaxReturn(['status'=>0,'msg'=>'申请成功!','data'=>$msid]);
			}else
				$this->ajaxReturn(['status'=>0,'msg'=>'申请成功']);
		}else{
			$this->ajaxReturn(['status'=>-1,'msg'=>'申请失败!','data'=>null]);
		}
	}

	public function apply_agree(){
		$id = I('get.id/d',0);
		if(!$id)$this->error('参数错误');
		
		$applyinfo = M('Apply_for')->find($id);	
		if(!$applyinfo)$this->error('无此次申请');
		if($applyinfo['leaderid'] != $this->user_id)$this->error('您无权限查看此申请');
		if($applyinfo['status'] != 0)$this->error('此申请您已处理过啦');	

		$level = M('Users')->where(['user_id'=>$applyinfo['leaderid']])->value('level');
		if($level < $applyinfo['level'])$this->error('您的代理级别不能进行此次操作！');	

		$applyinfo['uidinfo'] = M('Users')->field('nickname,mobile,head_pic')->find($applyinfo['uid']);
		$applyinfo['level_name'] = M('User_level')->where(['level'=>$applyinfo['level']])->value('level_name');

		$this->assign('applyinfo',$applyinfo);
        return $this->fetch();	
	}

	public function refused(){
		$id = I('get.id/d',0);
		if(!$id)$this->ajaxReturn(['status'=>-1,'msg'=>'参数错误']);

		$applyinfo = M('Apply_for')->find($id);	
		if(!$applyinfo)$this->ajaxReturn(['status'=>-1,'msg'=>'参数错误']);
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

} ?>
