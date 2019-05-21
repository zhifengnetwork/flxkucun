<?php 
namespace app\admin\controller;
use think\Page;
use think\Db;

class VideoUser extends Base {



	public function video_list(){

        $model = M('video');
        $res = $list = array();
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        $where = " 1 = 1 ";
        if(isset($_REQUEST['content'])&&!empty($_REQUEST['content'])){
            $content = trim(I('content'));
            $content && $where.=" and content like '%$content%' ";
            $this->assign('content',$content);
        }

        if(isset($_REQUEST['nickname'])&&!empty($_REQUEST['nickname'])){
            $nickname = trim(I('nickname'));
            $nickname && $where.=" and nickname like '%$nickname%' ";
            $this->assign('nickname',$nickname);
        }
        $res = $model->where($where)->order('sort desc,update_time DESC')->page("$p,$size")->select();
        $count = $model->where($where)->count();// 查询满足要求的总记录数
        $pager = new Page($count,$size);// 实例化分页类 传入总记录数和每页显示的记录数
        $page = $pager->show();
        if($res){
            foreach ($res as $val){
                $list[] = $val;
            }
        }
        $this->assign('videoList',$list);
        $this->assign('pager',$page);// 赋值分页输出
        return $this->fetch();
	}

    /**
     * 用户视频审核
     */
    public function checkUserVideo(){
        $id=I('id');
        if(!empty($id)){
            $userVideoInfo=M('Video')->where('id',$id)->find();
            if(!empty($userVideoInfo)){
                $this->assign('userVideoInfo',$userVideoInfo);
            }else{
                return $this->error("操作失败，不存在该视频信息", url('Admin/UserVideo/video_list'));
            }
        }else{
            return $this->error("非法操作",url('Admin/UserVideo/video_list'));
        }
        return $this->fetch();
    }

    /**
     * 更新是视频的审核状态
     */
    public function updateCheckVideo(){

         $data=I('post.','');
         $res=Db::name("Video")->update($data);
        if($res){
            $this->ajaxReturn(['status'=>1,'msg'=>'操作成功']);
        }else{
            $this->ajaxReturn(['status'=>1,'msg'=>'参数失败']);
        }
    }

    /**
     * 删除用户视频
     */
    public function delUserVideo(){
        $id=I('del_id');
        if($id>0){
            $videoInfo = M('Video')->where('id',$id)->field('video_url')->find();
            $res = M('Video')->where('id',$id)->delete();
            if($res){
                $path=$_SERVER['DOCUMENT_ROOT'].$videoInfo['video_url'];
                @unlink($path);
                $this->ajaxReturn(['status'=>1,'msg'=>'操作成功']);
            }else{
                $this->ajaxReturn(['status'=>1,'msg'=>'参数失败']);
            }
        }
    }

    /**
     * 批量用户删除视频
     */

    public function  delManyVideo(){

        $ids = I('post.ids','');
        empty($ids) &&  $this->ajaxReturn(['status' => -1,'msg' =>"非法操作！",'data'  =>'']);
        $video_ids = rtrim($ids,",");
        $videoPath=M('Video')->whereIn('id',$video_ids)->field('video_url')->select();
        $res=M('Video')->whereIn('id',$video_ids)->delete();
        if($res){
            //删除上传的视频
            foreach ($videoPath as $value){
                $path=$_SERVER['DOCUMENT_ROOT'].$value['video_url'];
                @unlink($path);
            }
            $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url'=>U("Admin/UserVideo/video_list")]);
        }else{
            $this->ajaxReturn(['status' => 1,'msg' => '操作失败','url'=>U("Admin/UserVideo/video_list")]);
        }
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