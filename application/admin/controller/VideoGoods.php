<?php

namespace app\admin\controller;
use think\Page;
use think\AjaxPage;
use think\Db;

class VideoGoods extends Base {

    public function video_list(){

        $model = M('video');
//        $username = I('nickname','','trim');
//        $content = I('content','','trim');
        $where['user_id'] = 0;
//        if($username){
//            $where['username'] = $username;
//        }
//        if ($content) {
//            $where['content'] = ['like', '%' . $content . '%'];
//        }
        $count = $model->where($where)->count();
        $Page = $pager = new AjaxPage($count,5);
        $show = $Page->show();

        $list = $model->where($where)->order('sort desc,update_time DESC')->limit($Page->firstRow.','.$Page->listRows)->select();
        if(!empty($list))
        {
            $goods_id_arr = get_arr_column($list, 'goods_id');
            $goods_list = M('Goods')->where("goods_id", "in" , implode(',', $goods_id_arr))->getField("goods_id,goods_name");
        }

        $this->assign('goods_list',$goods_list);
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$pager);// 赋值分页输出
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

    public function userVideoList()
    {
        return $this->fetch();
    }
    public function ajaxindex(){

        $model = M('video');
//        $username = I('nickname','','trim');
//        $content = I('content','','trim');
        $where['user_id'] = ['<>',0];
//        if($username){
//            $where['username'] = $username;
//        }
//        if ($content) {
//            $where['content'] = ['like', '%' . $content . '%'];
//        }
        $count = $model->where($where)->count();
        $Page = $pager = new AjaxPage($count,5);
        $show = $Page->show();

        $list = $model->where($where)->order('sort desc,update_time DESC')->limit($Page->firstRow.','.$Page->listRows)->select();

        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$pager);// 赋值分页输出
        return $this->fetch();
//        $Ad =  M('video');
//        $res = $Ad->where('user_id','<>',0)->select();
//        $this->assign('list',$res);// 赋值数据集
//        return $this->fetch();
    }

    /**
     * 添加修改视频
     */
    public function addEditVideo()
    {
//        $GoodsLogic = new GoodsLogic();
        $Goods = new \app\common\model\Goods();
        $goods_id = input('id');
        if($goods_id){
            $goods = $Goods->where('goods_id', $goods_id)->find();
            if($goods['rebate'])
            {
                $goods['rebate'] = unserialize($goods['rebate']);
            }

            //$GoodsLogic->find_parent_cat($goods['cat_id']); // 获取分类默认选中的下拉框
            //$level_cat2 = $GoodsLogic->find_parent_cat($goods['extend_cat_id']); // 获取分类默认选中的下拉框
            //$brandList = $GoodsLogic->getSortBrands($goods['cat_id']);   //获取三级分类下的全部品牌
            $this->assign('goods', $goods);
            /* $this->assign('level_cat', $level_cat);
            $this->assign('level_cat2', $level_cat2);
            $this->assign('brandList', $brandList); */
        }
        $cat_list = Db::name('goods_category')->select(); // 已经改成联动菜单
        $goodsType = Db::name("GoodsType")->select();
        $suppliersList = Db::name("suppliers")->where(['is_check'=>1])->select();
        $freight_template = Db::name('freight_template')->where('')->select();
        $this->assign('freight_template',$freight_template);
        $this->assign('suppliersList', $suppliersList);
        $this->assign('cat_list', $cat_list);
        $this->assign('goodsType', $goodsType);
        return $this->fetch();
    }

    /**
     * 删除视频
     */
    public function delVideo(){

        $id=I('id');
        if($id>0){
            $res = M('Vidoe')->where('id',$id)->delete();
            if($res){
                $this->ajaxReturn(['status'=>1,'msg'=>'操作成功']);
            }else{
                $this->ajaxReturn(['status'=>1,'msg'=>'参数失败']);
            }
        }
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