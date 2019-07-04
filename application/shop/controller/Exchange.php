<?php
/**
 * 积分兑换
 */
// namespace app\mobile\controller;
namespace app\shop\controller;

use think\Db;
use app\common\logic\GoodsLogic;
use app\common\model\WxNews;

class Exchange extends MobileBase
{
    // 积分兑换首页
    public function index(){
        //获取商品
       $list =  M('Goods')->field('goods_id,goods_name,goods_remark,market_price,original_img,exchange_integral,virtual_sales_sum,sales_sum')->where(['cat_id'=>8,'exchange_integral'=>['gt',0],'is_on_sale'=>1])->select();
       $this->assign('list',$list);
        return $this->fetch();
    }
    // 积分兑换详情
    public function details(){
        $shareid = I('shareid');
        if(!empty($shareid) && !session('?user'))
        {
          $user = M('users')->where("user_id", $shareid)->find();
          $shareid =  $user['user_id'];
          Session::set('shareid',$shareid);
        }else{
            $user = session('user');
        }
        C('TOKEN_ON', true);
        $goodsLogic = new \app\common\logic\GoodsLogic();
        $goods_id = I("get.goods_id/d",0);

        $goodsModel = new \app\common\model\Goods();
        $goods = $goodsModel::get($goods_id);
        if (empty($goods) || ($goods['is_on_sale'] == 0)) {
            $this->error('此商品不存在或者已下架');
        }
        if(($goods['is_virtual'] == 1 && $goods['virtual_indate'] <= time())){
            $goods->save(['is_on_sale' => 0]);
            $this->error('此商品不存在或者已下架');
        }

        $user_id = cookie('user_id');
        if ($user_id) {
            $goodsLogic->add_visit_log($user_id, $goods);
            $collect = db('goods_collect')->where(array("goods_id" => $goods_id, "user_id" => $user_id))->count(); //当前用户收藏
            $this->assign('collect', $collect);
        }

        $recommend_goods = M('goods')->where("is_recommend=1 and is_on_sale=1 and cat_id = {$goods['cat_id']}")->cache(7200)->limit(9)->field("goods_id, goods_name, shop_price")->select();

        //等级价格
        if($user['level'] > 0){
            $price = M('goods_level_price')->where('goods_id',$goods_id)->where('level',$user['level'])->value('price');
            $price = $price?$price:$goods['market_price'];
        }else{
            $price = $goods['market_price'];
        }

        $commentType = I('commentType', '1'); // 1 全部 2好评 3 中评 4差评
        if ($commentType == 5) {
            $where = array(
                'goods_id' => $goods_id, 'parent_id' => 0, 'img' => ['<>', ''], 'is_show' => 1
            );
        } else {
            $typeArr = array('1' => '0,1,2,3,4,5', '2' => '4,5', '3' => '3', '4' => '0,1,2');
            $where = array('is_show' => 1, 'goods_id' => $goods_id, 'parent_id' => 0, 'ceil((deliver_rank + goods_rank + service_rank) / 3)' => ['in', $typeArr[$commentType]]);
        }        

        $list = M('Comment')
            ->alias('c')
            ->join('__USERS__ u', 'u.user_id = c.user_id', 'LEFT')
            ->where($where)->field('c.*,ceil((deliver_rank + goods_rank + service_rank) / 3) as goods_rank ,u.head_pic')
            ->order("add_time desc")
            ->select();
        $replyList = M('Comment')->where(['goods_id' => $goods_id, 'parent_id' => ['>', 0]])->order("add_time desc")->select();
        foreach ($list as $k => $v) {
            $list[$k]['img'] = unserialize($v['img']); // 晒单图片
            $replyList[$v['comment_id']] = M('Comment')->where(['is_show' => 1, 'goods_id' => $goods_id, 'parent_id' => $v['comment_id']])->order("add_time desc")->select();
            $list[$k]['reply_num'] = Db::name('reply')->where(['comment_id' => $v['comment_id'], 'parent_id' => 0])->count();
        }
        $this->assign('list', $list);    
        $goods['goods_price'] = $price; 
        $GoodsLogic = new GoodsLogic();
        $share_img = $GoodsLogic->goods_qrcode($goods,U('shop/Exchange/details',['goods_id'=>$goods_id,'source_uid'=>($user['user_id'] ? $user['user_id'] : 0)])); 
        if($user['user_id'] && I('source_uid'))share_deal_after($user['user_id'],I('source_uid'));

        $this->assign('price', $price); 
        $this->assign('share_img', $share_img); 
        //dump($goods);exit;
        $this->assign('recommend_goods', $recommend_goods);
        $this->assign('goods', $goods);
        $this->assign('user', $user);                   
        return $this->fetch();
    }
}