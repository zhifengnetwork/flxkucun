<?php


namespace app\admin\controller;

use app\common\model\FlashSale;
use app\common\model\GoodsActivity;
use app\common\model\GroupBuy;
use app\admin\logic\GoodsLogic;
use app\common\model\Goods;
use app\common\model\PromGoods;
use app\common\model\PromGoodsItem;
use app\common\model\PromOrder;
use app\common\logic\MessageTemplateLogic;
use app\common\logic\MessageFactory;
use app\common\model\Auction;
use think\AjaxPage;
use think\Page;
use think\Loader;
use think\Db;
/***
手动添加库存
****/
class Warehouse extends Base
{
    //显示全部会员库存  
    public function index()
    {
        $count = db("warehouse")->count();
        $Page = new Page($count, 10);
        $list = M("warehouse")->alias('w')
        ->field('u.nickname,w.w_id,w.totals,w.create_time,w.update_time,u.user_id')
        ->join('users u','w.user_id=u.user_id','LEFT')
        ->order('w.w_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('page',$Page);
        $this->assign('list', $list);
        return $this->fetch();
    }
    //显示每个会员商品库存  
    public function warehouse_goods()
    {
        $user_id=I('user_id');
        $where =' u.user_id='.$user_id;
        $count = db("warehouse_goods")->alias('wg')
         ->join('users u','wg.user_id=u.user_id','LEFT')
        ->join('goods g','wg.goods_id=g.goods_id','LEFT')
        ->where($where)->count();
        $Page = new Page($count, 10);
        $list = M("warehouse_goods")->alias('wg')
         ->field('u.nickname,u.user_id,wg.id,wg.nums,wg.time,wg.change_time,g.goods_name,g.goods_id')
        ->join('users u','wg.user_id=u.user_id','LEFT')
        ->join('goods g','wg.goods_id=g.goods_id','LEFT')
        ->where($where)
        ->order('wg.id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('page',$Page);
        $this->assign('list', $list);
        return $this->fetch();
    }
     //显示商品库存记录  
    public function warehouse_goods_log()
    {
        $where =' 1 ';
        $user_id=I('user_id');
        $goods_id=I('goods_id');
        if($user_id)
        {
            $where.=' and u.user_id='.$user_id;
        }
         if($goods_id)
        {
            $where.=' and g.goods_id='.$goods_id;
        }
      
        $count = db("warehouse_goods_log")->alias('wgl')
            ->join('users u','wgl.user_id=u.user_id','LEFT')
            ->join('goods g','wgl.goods_id=g.goods_id','LEFT')
            ->where($where)
            ->count();
        $Page = new Page($count, 10);
        $list = M("warehouse_goods_log")->alias('wgl')
         ->field('wgl.id,u.nickname,u.user_id,wgl.id,wgl.changenum,wgl.time,g.goods_name,wgl.type')
        ->join('users u','wgl.user_id=u.user_id','LEFT')
        ->join('goods g','wgl.goods_id=g.goods_id','LEFT')
        ->where($where)
        ->order('wgl.id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('page',$Page);
        $this->assign('list', $list);
        return $this->fetch();
    }
    //显示单个会员每个商品库存
    public function warehouse_list()
    {
        $id =I('id');
        $count = db("warehouse_log")->where($prom_where)->count();
        $Page = new Page($count, 10);
        $list = M("warehouse_log")->order('w_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('page',$Page);
        $this->assign('list', $list);
        return $this->fetch();
    }
    //添加库存
    public function ware_add()
    {
        $act = I('GET.act', 'add');
        
        $this->assign('act', $act);
        return $this->fetch();
    }
    //选择商品多个
    public function search_goods()
    {
        $goods_id = input('goods_id');
        $intro = input('intro');
        $cat_id = input('cat_id');
        $brand_id = input('brand_id');
        $keywords = input('keywords');
        $prom_id = input('prom_id');
        $data =!empty(input('data'))?input('data'):'0';
        //var_dump($data);
        $tpl = input('tpl', 'search_goods');
        $where = ['is_on_sale' => 1, 'store_count' => ['gt', 0],'exchange_integral'=>0];
        $prom_type = input('prom_type/d',0);
        if ($prom_type != 0) {//指定商品优惠券 可以看到虚拟商品
            $where = ['is_on_sale' => 1, 'store_count' => ['gt', 0],'is_virtual'=>0,'exchange_integral'=>0];
        }
        if($goods_id){
            $where['goods_id'] = ['notin',trim($goods_id,',')];
        }
        if($intro){
            $where[$intro] = 1;
        }
        if($cat_id){
            $grandson_ids = getCatGrandson($cat_id);
            $where['cat_id'] = ['in',implode(',', $grandson_ids)];
        }
        if ($brand_id) {
            $where['brand_id'] = $brand_id;
        }
        if($keywords){
            $where['goods_name|keywords'] = ['like','%'.$keywords.'%'];
        }
        $Goods = new Goods();
        $count = $Goods->where($where)->where(function ($query) use ($prom_type, $prom_id) {
            if(in_array($prom_type,[3,6])){
                //优惠促销,拼团
                if ($prom_id) {
                    $query->where(['prom_id' => $prom_id, 'prom_type' => $prom_type])->whereor('prom_id', 0);
                } else {
                    $query->where('prom_type', 0);
                }
            }else if($prom_type == 7){
                //
                $query->where([ 'prom_type' => $prom_type])->whereor('prom_type', 0);
            }else if(in_array($prom_type,[1,2])){
                //抢购，团购
                $query->where('prom_type','in' ,[0,$prom_type])->where('prom_type',0);
            }else{
                $query->where('prom_type',0);
            }
        })->count();
        $Page = new Page($count, 10);
        $goodsList = $Goods->with('specGoodsPrice')->where($where)->where(function ($query) use ($prom_type, $prom_id) {
            if(in_array($prom_type,[3,6])){
                //优惠促销
                if ($prom_id) {
                    $query->where(['prom_id' => $prom_id, 'prom_type' => $prom_type])->whereor('prom_id', 0);
                } else {
                    $query->where('prom_type', 0);
                }
            }else if($prom_type == 7){
                //
                $query->where([ 'prom_type' => $prom_type])->whereor('prom_type', 0);
            }else if(in_array($prom_type,[1,2])){
                //抢购，团购
                $query->where('prom_type','in' ,[0,$prom_type])->where('prom_type',0);
            }else{
                $query->where('prom_type',0);
            }
        })->order('goods_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $GoodsLogic = new GoodsLogic;
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        //$select_data = '0';
        //选中商品
        if(!empty($data))
        {
            $str_data = explode(',', $data);
            foreach($str_data as $k=>$v)
            {
                $select_data[$v]=$v;
            }
        }
         $this->assign('select_data_js', json_encode($str_data));
        $this->assign('select_data', $select_data);
        $this->assign('brandList', $brandList);
        $this->assign('categoryList', $categoryList);
        $this->assign('page', $Page);
        $this->assign('goodsList', $goodsList);
        return $this->fetch($tpl);
    }

    public function warehouseHandle()
    {
        if (IS_POST) {
            $data = I('post.');
            $user_id = $data['user_id'];
            //$data['type'] = 1; //手动添加库存
            //$data['kucun'] = strtotime($data['end_time']);
            //循环添加库存
            $users = M('users')->where('user_id',$user_id)->find();
            if(!$users)
            {
                 $this->ajaxReturn(['status' => 0, 'msg' => '该会员不存在', 'result' => '']);
            }
           
            if(!empty($data['kucun']))
            {
                foreach($data['kucun'] as $key=>$val)
                {

                     if(!empty($val))
                     {
                        $bool = $this->changekucun($key,$user_id,$val,1,$data['desc']);
                     }
                }
            }
            

            $this->ajaxReturn(['status' => 1,'msg' =>'操作成功','url'=>U('Admin/Warehouse/index')]);



        }


    
    }
    /*库存*/
    public function changekucun($goods_id,$user_id,$num,$type=0,$desc='')
    {
         if(($num) == 0)return false;

         //查询库存产品表是否有记录
         $warehouse_goods = M("warehouse_goods")->where(['goods_id'=>$goods_id,'user_id'=>$user_id])->find();
            /* 插入用户商品库存记录 */
            $warehouse_goods_log = array(
                    'user_id'       => $user_id,
                    'goods_id'    => $goods_id,
                    'changenum'    => $num,
                    'time'   => time(),
                    'desc'   => $desc,
                    'type' => $type,

            );

         if($warehouse_goods)
         {
            /* 更新用户单个商品库存信息 */
             $update_data = array(
                'nums'        => ['exp','nums+'.$num],
                'change_time'=>time(),
             );
            $update = Db::name('warehouse_goods')->where(['goods_id'=>$goods_id,'user_id'=>$user_id])->save($update_data);
            if($update){

               $log_bool= M('warehouse_goods_log')->add($warehouse_goods_log);
                //return true;
            //}else{
              //  return false;
            }

         }else
         {
             /* 插入用户单个商品库存信息 */
             $insert_data = array(
                'user_id'       => $user_id,
                'goods_id'    => $goods_id,
                'nums'        => ['exp','nums+'.$num],
                'time'=>time(),
                //'desc'=>$desc,

             );
            $data = Db::name('warehouse_goods')->add($insert_data);
            if($data){
               $log_bool= M('warehouse_goods_log')->add($warehouse_goods_log);
            }

         }
         //记录总库存
          $warehouse = M("warehouse")->where(['user_id'=>$user_id])->find();
          if($warehouse)
          {
             /* 更新用户总商品库存信息 */
             $update_data1 = array(
                //'user_id'       => $user_id,
                'totals'        => ['exp','totals+'.$num],
                'update_time'=>time(),

             );
             if($type==1){
                $update_data1['manual_num']=['exp','manual_num+'.$num];//统计手动库存
             }
              Db::name('warehouse')->where(['user_id'=>$user_id])->save($update_data1);
              //echo Db::name('warehouse')->getlastsql();exit;
          }else
          {
            /* 更新用户总商品库存信息 */
             $insert_data1 = array(
                'user_id'       => $user_id,
                'totals'        => ['exp','totals+'.$num],
                'create_time'=>time(),

             );
               if($type==1){
                $insert_data1['manual_num']=['exp','manual_num+'.$num]; //统计手动库存
             }
              Db::name('warehouse')->add($insert_data1);
          }

          if($log_bool)
          {
            return ture;
          }else
          {
            return false;
          }
        

    }

  

}