<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/6/14 0014
 * Time: 18:15
 */
namespace app\admin\controller;
use think\Page;
use think\Db;

class Audio extends Base {

    /**
     * 音频列表
     */

   public function audio_list(){

       $model = M('audio');
       $res = $list = array();
       $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
       $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];
       $where = " 1 = 1 ";
       if(isset($_REQUEST['title'])&&!empty($_REQUEST['title'])){
           $title = trim(I('title'));
           $title && $where.=" and title like '%$title%' ";
           $this->assign('title',$title);
       }
       $res = $model->where($where)->order('sort desc,addtime DESC')->page("$p,$size")->select();
       $count = $model->where($where)->count();// 查询满足要求的总记录数
       $pager = new Page($count,$size);// 实例化分页类 传入总记录数和每页显示的记录数
       $page = $pager->show();
       if($res){
           foreach ($res as $val){
               $list[] = $val;
           }
       }
       $this->assign('audioList',$list);
       $this->assign('pager',$page);// 赋值分页输出
       return $this->fetch();
   }

  /**
   * 音频新增
   */

  public function add(){
        $id = input('id');
        $info = [];
        if($id){
            $info = Db::name('audio')->find($id);
        }

        $typeList=Db::name("audio_type")->where(['status'=>1])->select();
        $this->assign('typeList',$typeList);
        $this->assign('info',$info);
        return $this->fetch();
  }

 /**
  *音频插入
 */

 public function do_add(){

     $data=I('post.','');
     $data['addtime']=time();
     if($data['id']){
        $res=Db::name("audio")->update($data);
     }else{
        $res=Db::name("audio")->insert($data);
     }
     if($res !== false){
         $this->ajaxReturn(['status'=>1,'msg'=>'操作成功']);
     }else{
         $this->ajaxReturn(['status'=>1,'msg'=>'参数失败']);
     }
 }

 /**
  * 音频类别列表
  */

 public function audio_type_list(){

     $audioTypeList=Db::name("audio_type")->select();
     $this->assign('typeList',$audioTypeList);
     return $this->fetch();
 }

 /**
  * 添加音频类型
  */

 public function audio_type_add(){

     return $this->fetch();
 }

/**
* 音频类型插入
*/
 public function do_audio_type_add(){

     $data=I('post.','');
     $res=Db::name("audio_type")->insert($data);
     if($res){
         $this->ajaxReturn(['status'=>1,'msg'=>'操作成功']);
     }else{
         $this->ajaxReturn(['status'=>1,'msg'=>'参数失败']);
     }

 }

 /**
  * 音频类型修改
  */

 public function audio_type_edit(){
     $id=I('id');
     if(!empty($id)){
         $audioTypeInfo=M('audio_type')->where('id',$id)->find();
         if(!empty($audioTypeInfo)){
             $this->assign('audioTypeInfo',$audioTypeInfo);
         }else{
             return $this->error("操作失败，不存在该类型", url('Admin/Audio/audio_type_list'));
         }
     }else{
         return $this->error("非法操作",url('Admin/Audio/audio_type_list'));
     }
     return $this->fetch();

 }

    /**
     * 音频类型更新
     */
    public function do_audio_type_edit(){

        $data=I('post.','');
        $res=Db::name("audio_type")->update($data);
        if($res){
            $this->ajaxReturn(['status'=>1,'msg'=>'操作成功']);
        }else{
            $this->ajaxReturn(['status'=>1,'msg'=>'参数失败']);
        }

    }

}