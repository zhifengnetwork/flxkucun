<?php
/**
 * 免费团购
 */
// namespace app\mobile\controller;
namespace app\shop\controller;

use think\Db;
use app\common\logic\GoodsLogic;
use app\common\model\WxNews;

class GroupPurchase extends MobileBase
{
    public function index(){
        $grouplist = M('group_buy')->alias('GB')->join('goods G','GB.goods_id=G.goods_id','left')->field('GB.id,GB.title,GB.goods_id,GB.item_id,GB.price,GB.goods_num,GB.buy_num,GB.virtual_num,GB.goods_price,GB.goods_name,G.original_img')->where(['GB.goods_num-GB.buy_num'=>['gt',0],'GB.end_time'=>['gt',time()],'GB.is_on'=>1,'GB.is_end'=>0])->order('GB.start_time desc')->select();  

        foreach($grouplist as $k=>$v){
            $grouplist[$k]['rate'] = !$v['buy_num'] ? 100 : 100-intval(($v['buy_num']/$v['goods_num'])*100);
        } 
        $this->assign('grouplist',$grouplist);          
       
        return $this->fetch();
    }

    // 团购详情
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
        $id = I("get.id/d",0);
        $goodsModel = new \app\common\model\Goods();
        $goods = $goodsModel::get($goods_id);
        if (empty($goods) || ($goods['is_on_sale'] == 0)) {
            $this->error('此商品不存在或者已下架');
        }
        if(($goods['is_virtual'] == 1 && $goods['virtual_indate'] <= time())){
            $goods->save(['is_on_sale' => 0]);
            $this->error('此商品不存在或者已下架');
        }

        $prominfo = M('group_buy')->find($id);
        if($prominfo['start_time'] > time())$this->error('此活动还未开始');
        if($prominfo['end_time'] < time())$this->error('此活动已结束');

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
        $share_img = $GoodsLogic->goods_qrcode($goods,U('shop/GroupPurchase/details',['id'=>$goods_id,'shareid'=>($user['user_id'] ? $user['user_id'] : 0),'source_uid'=>($user['user_id'] ? $user['user_id'] : 0)])); 

        $prominfo['rate'] = !$prominfo['order_num'] ? 100 : 100-intval(($prominfo['order_num']/$prominfo['goods_num'])*100);
        $this->assign('price', $price); 
        $this->assign('share_img', $share_img); 
        $this->assign('source_uid', I('get.source_uid/d',0));
        //dump($goods);exit;
        $this->assign('recommend_goods', $recommend_goods);
        $this->assign('goods', $goods);
        $this->assign('user', $user);    
        $this->assign('prominfo', $prominfo);                 
        return $this->fetch();
    }

    // 团购规则
    public function rule(){
        return $this->fetch();
    }
}