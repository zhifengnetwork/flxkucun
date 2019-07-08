<?php 
namespace app\admin\controller;
use think\Page;
use think\Db;
use think\Request;

class VideoUser extends Base {



	public function video_list(){
        $model = M('video');
        $res = $list = array();
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        $where = " user_id != 0 ";
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
            $videoInfo = M('Video')->where('id',$id)->field('video_url,video_img')->find();
            $res = M('Video')->where('id',$id)->delete();
            if($res){
                $path=$_SERVER['DOCUMENT_ROOT'].$videoInfo['video_url'];
                $path_img=$_SERVER['DOCUMENT_ROOT'].$videoInfo['video_img'];
                @unlink($path);
                @unlink($path_img);
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
        $videoPath=M('Video')->whereIn('id',$video_ids)->field('video_url,video_img')->select();
        $res=M('Video')->whereIn('id',$video_ids)->delete();
        if($res){
            //删除上传的视频
            foreach ($videoPath as $value){
                $path=$_SERVER['DOCUMENT_ROOT'].$value['video_url'];
                $path_img=$_SERVER['DOCUMENT_ROOT'].$value['video_img'];
                @unlink($path);
                @unlink($path_img);
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


    /**
     * 后台视频管理
     */
    public function admin_video()
    {
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
        $list = M('video')->where('user_id','0')->order('sort desc ,update_time desc')->page($p,$size)->select();
        $count =  M('video')->where('user_id','0')->count();// 查询满足要求的总记录数
        $pager = new Page($count,$size);// 实例化分页类 传入总记录数和每页显示的记录数
        $page = $pager->show();
        $this->assign('list',$list);
        $this->assign('page',$page);
        return $this->fetch();
    }

    /**
     * 添加后台视频
     */
    public function add()
    {
        $id = input('id');
        $info = Db::name('video')->where('id',$id)->find();
        $this->assign('info',$info);
        return $this->fetch();
    }

    //上传公共方法
    public function upload()
    {
        $uploadDir = './public/uploads/video/';
        $path = '';
        $file = request()->file('video');
        $info = $file->validate(['size' =>1024*1024*10,'ext'=>'avi,mp4,flw'])->move($uploadDir);
        if($info){
            $path = str_replace("\\","/",$info->getSaveName());
        }else{
            $result['msg'] = $file->getError();
            $result['status'] = 2;
            return json($result);
        }
        $result['url'] = ltrim($uploadDir.$path,'.');
        $result['img'] = '';
        if($result['url']){     
            $videoImg=explode('.',$result['url']);
            if(!empty($videoImg)){
                $result['img']=$videoImg[0].".jpg";
            }
        };
        $result['status'] = 1;
        return json($result);
    }

    //添加/修改视频的提交
    public function videoPost()
    {
        if(Request::instance()->isPost()){
            $post = input('post.');
            if(!$post['title']){
                $result['status'] = 2;
                $result['msg'] = '请输入标题';
                return json($result);
            }
            if(!$post['video_url']){
                $result['status'] = 2;
                $result['msg'] = '请上传视频';
                return json($result);
            }
            if(!$post['content']){
                $result['status'] = 2;
                $result['msg'] = '请输入描述';
                return json($result);
            }
            if($post['id']){
                $res = Db::name('video')->where('id',$post['id'])->update($post);
            }else{
                $post['update_time'] = time();
                $res = Db::name('video')->insert($post);
            }
            if($res){
                $result['status'] = 1;
                $result['msg'] = '操作成功';
                return json($result);
            }else{
                $result['status'] = 2;
                $result['msg'] = '操作失败';
                return json($result);
            }
        }
    }

    //图片上传
    public function upload_image(){
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('image');
        // 移动到框架应用根目录/public/uploads/ 目录下
        if($file){
            $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'video');
            if($info){
                $result['url'] = '/public/uploads/video/'.$info->getSaveName();
                $result['status'] = 1;
                return json($result);
            }else{
                // 上传失败获取错误信息
                $result['msg'] = $file->getError();
                $result['status'] = 2;
                return json($result);
            }
        }
    }
}
?>	