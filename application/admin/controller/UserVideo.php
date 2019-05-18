<?php 
namespace app\admin\controller;
use think\Page;
use think\AjaxPage;
use think\Db;

class UserVideo extends Base {

	public function video_list(){
		$videos = Db::table('tp_video')->select();

		$this->assign('videos',$videos);
        return $this->fetch();
	}


// 	   public function ajaxindex(){

//         $model = M('video');
// //        $username = I('nickname','','trim');
// //        $content = I('content','','trim');
        
//         $count = $model->where($where)->count();
//         $Page = $pager = new AjaxPage($count,5);
//         $show = $Page->show();
//         $list = Db::table('tp_video')->select();
//         // $list = $model->where($where)->order('sort desc,update_time DESC')->limit($Page->firstRow.','.$Page->listRows)->select();

//         $this->assign('list',$list);
//         $this->assign('page',$show);// 赋值分页输出
//         $this->assign('pager',$pager);// 赋值分页输出
//         return $this->fetch();
// //        $Ad =  M('video');
// //        $res = $Ad->where('user_id','<>',0)->select();
// //        $this->assign('list',$res);// 赋值数据集
// //        return $this->fetch();
//     }







}
?>	