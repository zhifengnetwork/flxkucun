<?php

namespace app\admin\controller;
use think\Page;

class Rewardo extends Base {

    public function lists(){
    	$Ad =  M('reward_config');
    	$res = $Ad->select();
    	$this->assign('list',$res);// 赋值数据集
    	return $this->fetch();
    }
     public function info(){
        $act = I('get.act','add');
        $this->assign('act',$act);
        $id = I('get.id');
        if($id){
            $reward_info = D('reward_config')->where('reward_id='.$id)->find();
            $this->assign('info',$reward_info);
        }
        return $this->fetch();
    }
    
    public function rewardaction(){
    	$data = I('post.');
       // $data['topic_content'] = $_POST['topic_content']; // 这个内容不做转义
    	if($data['act'] == 'add'){
    		$r = D('reward_config')->add($data);
    	}
    	if($data['reward_config'] == 'edit'){

    		$r = D('topic')->where('reward_id='.$data['reward_id'])->save($data);
    	}
    	 
    	if($data['act'] == 'del'){
    		$r = D('reward_config')->where('reward_id='.$data['reward_id'])->delete();
    		if($r) exit(json_encode(1));
    	}
    	 
    	if($r !== false){
			$this->ajaxReturn(['status'=>1,'msg'=>'操作成功','result'=>'']);
    	}else{
			$this->ajaxReturn(['status'=>0,'msg'=>'操作失败','result'=>'']);
    	}
    }
}