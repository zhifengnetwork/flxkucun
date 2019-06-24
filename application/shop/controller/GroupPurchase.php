<?php
/**
 * 免费团购
 */
// namespace app\mobile\controller;
namespace app\shop\controller;

use think\Db;
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
    public function details(){
              
        return $this->fetch();
    }
}