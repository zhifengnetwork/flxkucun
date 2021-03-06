<?php


namespace app\admin\controller;

use app\admin\logic\OrderLogic;
use app\common\model\UserLabel;
use think\AjaxPage;
use think\console\command\make\Model;
use think\Page;
use think\Verify;
use think\Db;
use app\common\model\Config;
use app\admin\logic\UsersLogic;
use app\common\logic\MessageTemplateLogic;
use app\common\logic\MessageFactory;
use app\common\model\Withdrawals;
use app\common\model\Users;
use think\Loader;


class Sign extends Base
{

       /**
     * 签到列表
     * @date 2017/09/28
     */
    public function signList()
    {
        $mobile = input('mobile');
        $where['sl.user_id'] = array('gt',0);
        if($mobile){
            $where['u.mobile'] = array('like','%'.$mobile.'%');
        }
        $count = Db::name('sign_log')->alias('sl')->join('tp_users u','u.user_id=sl.user_id')->order('sl.sign_day desc')->where($where)->group('sl.user_id')->count();
        $page = new Page($count, 10);
        $list = Db::name('sign_log')->alias('sl')->join('tp_users u','u.user_id=sl.user_id')->field('u.nickname,sl.id,u.mobile,sl.sign_day,sl.user_id')->order('sl.sign_day desc')->where($where)->group('sl.user_id')->limit($page->firstRow . ',' . $page->listRows)->select();
        foreach($list as $k=>$v){
            $list[$k]['day_num'] = Db::name('sign_log')->where('user_id',$v['user_id'])->count();
        }
        // 获取分页显示
        $this->assign('list',$list);
        $this->assign('page',$page->show());
        return $this->fetch();
    }


    /**
     * 会员签到 ajax
     * @date 2017/09/28
     */
    public function ajaxsignList()
    {

        // $list = M('sign_log')->group("user_id")->select();

        $list =  Db::query("select *,count(sign_day) as day_num from tp_sign_log as a,tp_users  as b where a.user_id = b.user_id group by a.user_id order by a.sign_day desc");
        $this->assign('list',$list); 

        return $this->fetch();
    }

    /**
     * 签到规则设置
     * @date 2017/09/28
     */
    public function signRule()
    {
        // 設置等級
        $Pickup =  M('user_level'); 
        $res = $Pickup->order('level_name desc')->select();
        $this->assign('res',$res);
    	

        if(IS_POST){
            $post = I('post.');

            // ["sign_on_off"] => string(1) "1"
            // ["sign_integral"] => string(2) "10"
            // ["sign_signcount"] => string(2) "10"
            // ["sign_award"] => string(2) "10"

            
            $model = new Config();
            $model->where(['name'=>'sign_rule'])->save(['value'=>$post['sign_rule']]);
            $model->where(['name'=>'sign_on_off'])->save(['value'=>$post['sign_on_off']]);
            $model->where(['name'=>'sign_require_level'])->save(['value'=>$post['level_name']]);


            $this->success('保存成功');
        }

        $model = new Config();

        $config['sign_on_off'] = $model->where(['name'=>'sign_on_off'])->value('value');
        $config['sign_rule'] = $model->where(['name'=>'sign_rule'])->value('value');
        $config['sign_require_level'] = $model->where(['name'=>'sign_require_level'])->value('value');

        $this->assign('config',$config);

        return $this->fetch();
    }
   
}