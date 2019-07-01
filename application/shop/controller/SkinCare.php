<?php
/**
 * 护肤
 */
// namespace app\mobile\controller;
namespace app\shop\controller;

use think\Db;
use app\common\model\WxNews;

class SkinCare extends MobileBase
{
    // 首页
    public function index(){
        $cat_id = 11;//护肤类别id
        $p = 1;
        $num = 10;
        //获取用户
        $user = session('user');
        $level = $user['level'];
        $list = Db::name('goods')->where(['cat_id'=>$cat_id,'is_on_sale'=>1])->field('original_img,goods_name,goods_id,market_price,virtual_sales_sum,sales_sum')->order('sort desc,last_update desc')->page($p,$num)->select();
        $goods_id = array();
        foreach($list as $key=>$val){
            $goods_id[] = $val['goods_id'];
        }
        $goods_level_list = Db::name('goods_level_price')->where('goods_id','in',$goods_id)->where('level',$level)->column('goods_id,price');
        foreach($list as $k=>$v){
            $v['shop_price'] = $goods_level_list[$v['goods_id']];
            $v['shop_price'] = $v['shop_price']?$v['shop_price']:$v['market_price'];
            $list[$k]['shop_price'] = $v['shop_price'];
        }
        $this->assign('list',$list);
        return $this->fetch();
    }

    //ajax获取商品
    public function ajaxGoodsList()
    {
        $cat_id = 11;//护肤类别id
        $p = input('p',1);
        $num = 10;
        //获取用户
        $user = session('user');
        $level = $user['level'];
        $list = Db::name('goods')->where(['cat_id'=>$cat_id,'is_on_sale'=>1])->field('original_img,goods_name,goods_id,market_price,virtual_sales_sum,sales_sum')->order('sort desc,last_update desc')->page($p,$num)->select();
        $goods_id = array();
        foreach($list as $key=>$val){
            $goods_id[] = $val['goods_id'];
        }
        $goods_level_list = Db::name('goods_level_price')->where('goods_id','in',$goods_id)->where('level',$level)->column('goods_id,price');
        foreach($list as $k=>$v){
            $v['shop_price'] = $goods_level_list[$v['goods_id']];
            $v['shop_price'] = $v['shop_price']?$v['shop_price']:$v['market_price'];
            $list[$k]['shop_price'] = $v['shop_price'];
        }
        $this->assign('list',$list);
        return $this->fetch();
        return $this->fetch();
    }

    // 详情
    public function details(){
        return $this->fetch();
    }

}