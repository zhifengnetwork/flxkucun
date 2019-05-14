<?php

namespace app\common\logic;

use app\common\model\Users;
use app\common\util\TpshopException;
use think\Model;
use think\Db;

/**
 * 用户申请升级类
 * Class
 * @package Home\Logic
 */
class UserApply
{
    private $user;
    public $coupon = [];

    public function setUserById($user_id)
    {
        $this->user = Users::get($user_id);
    }

    public function setUserByMobile($mobile)
    {
        $this->user = Users::get(['mobile' => $mobile]);
    }

    /**
     * 用户申请升级
     * $user 用户信息
     * $data 申请信息
     */
    public function applyUpgrade($user,$data)
    {
        // 验证手机号
        if(!check_mobile($data['mobile']) && !check_telephone($data['mobile']))
            return array('status'=>-1,'msg'=>'手机号码格式有误','result'=>'');
        if($user['level'] >= $data['level'])
            return array('status'=>-1,'msg'=>'申请不能低于当前等级','result'=>'');

        $userData = M('Users')->where('is_lock',0)->select();
        $receiveId = $this->_getTopParent($userData,$user,$data['level']);

        $data['user_id'] = $user['user_id'];
        $data['apply_level'] = $data['level'];
        $data['start_level'] = $user['level'];
        $data['receive_id'] = $receiveId;

        $id = Db::name('user_agent_apply')->add($data);

        //发送信息通知上级
        if (!empty($user['openid'])){
            $url = SITE_URL."/Shop/Goods/goodsInfo?id=".$id;
            $wx_content = "有人跟你申请代理！\n\n<a href='{$url}'>点击处理</a>";
            $wechat = new \app\common\logic\wechat\WechatUtil();
            $wechat->sendMsg($user['openid'], 'text', $wx_content);
        }

        return array('status'=>1,'msg'=>'申请成功，等待审核！','result'=>'');
    }

    /**
     * 获取发送信息上级
     * @param $userData 所有会员信息
     * @param $user     当前会员信息
     * @param $level    需要匹配的等级
     * @return int
     */
    protected function _getTopParent ($userData, $user, $level)
    {

        if ( $user['first_leader'] != 0 ) {

            foreach ( $userData as $value ) {
                if ( $value['user_id'] == $user['first_leader'] ) {

                    if ( $value['level'] > $level ) {
                        return $value['user_id'];
                    } else {
                        $parentCate = [
                            'user_id' => $value['user_id'],
                            'first_leader'=> $value['first_leader']
                        ];

                        return $this->_getTopParent($userData, $parentCate, $level);
                    }

                }
            }

        } else {
            // 没有符合等级的上线
            return 0;
        }
    }


    public function setUser($user){
        $this->user = $user;
    }

    public function getUser()
    {
        return $this->user;
    }

}