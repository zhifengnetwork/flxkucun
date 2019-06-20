<?php

namespace app\admin\controller;

use think\Page;
use think\Db;


class MaterialZone extends Base {

	public function material_zone_cate_list(){
		$list = Db::name('material_zone_cate')->order('sort DESC,cat_id DESC')->select();
		return $this->fetch('',[
			'list'	=>	$list,
		]);

	}

	public function add_cate(){

		$cat_id = input('cat_id');

		if(request()->isPost()){
			$data = input('post.');
			
			if(!$data['cat_name']){
				$this->ajaxReturn(['status' => 0, 'msg' => '分类名称必须填写！']);
			}

			if($cat_id){
				$res = Db::name('material_zone_cate')->update($data);
			}else{
				$res = Db::name('material_zone_cate')->insert($data);
			}

			if($res !== false){
				$this->ajaxReturn(['status' => 1, 'msg' => '成功！']);
			}else{
				$this->ajaxReturn(['status' => 0, 'msg' => '失败！']);
			}
		}

		$info = [];
		if($cat_id){
			$info = Db::name('material_zone_cate')->find($cat_id);
		}
		
		return $this->fetch('',[
			'info'	=>	$info,
		]);

	}

	public function cate_del(){
		$cat_id = input('cat_id');

		$res = Db::name('material_zone_cate')->where('cat_id',$cat_id)->delete();

		if($res){
			$this->ajaxReturn(['status' => 1, 'msg' => '删除成功！']);
		}
		
		$this->ajaxReturn(['status' => 0, 'msg' => '删除失败！']);
	}

	public function cate_sort(){
		$data = input('post.');

		$res = Db::name('material_zone_cate')->update($data);

		if($res){
			$this->ajaxReturn(['status' => 1, 'msg' => '更新成功！']);
		}else{
			$this->ajaxReturn(['status' => 0, 'msg' => '更新失败！']);
		}
	}

	public function material_zone_list(){
		$where = [];
        $pageParam = ['query' => []];

		$list = Db::name('material_zone')->alias('mz')
				->join('material_zone_cate mzc','mzc.cat_id=mz.cat_id','LEFT')
				->where($where)
				->field('mz.*,mzc.cat_name')
				->order('mz.sort DESC,id DESC')
				->paginate(10,false,$pageParam);

		return $this->fetch('',[
			'list'	=>	$list,
		]);

	}

	public function add(){
		$id = input('id');

		if(request()->isPost()){
			$data = input('post.');

			if(!$data['name']){
				$this->ajaxReturn(['status' => 0, 'msg' => '名字必须填写！']);
			}

			if(!$data['cat_id']){
				$this->ajaxReturn(['status' => 0, 'msg' => '分类必须选择！']);
			}

			if($id){
				$res = Db::name('material_zone')->strict(false)->update($data);

				//组图
				if( isset($data['img']) && !empty($data['img'][0])){
					foreach ($data['img'] as $key => $value) {
						$saveName = $data['add_time'].rand(0,99999) . '.png';

						$img=base64_decode($value);
						//生成文件夹
						$names = "material_zone" ;
						$name = "material_zone/" .date('Ymd',$data['add_time']) ;
						if (!file_exists(ROOT_PATH .'/public/upload/'.$names)){ 
							mkdir(ROOT_PATH .'/public/upload/'.$names,0777,true);
						}
						//保存图片到本地
						file_put_contents(ROOT_PATH .'/public/upload/'.$name.$saveName,$img);

						unset($data['img'][$key]);
						$data['img'][] = '/public/upload/'.$name.$saveName;
					}
					$data['img'] = array_values($data['img']);
					
					foreach ($data['img'] as $key => $value) {
						$datas[$key]['img'] = $value;
						$datas[$key]['mz_id'] = $id;
					}
					Db::name('material_zone_img')->insertAll($datas);
				}
			}else{
				$data['add_time'] = time();

				$res = Db::name('material_zone')->strict(false)->insertGetId($data);
				if($res){
					//组图
					if( isset($data['img']) && !empty($data['img'][0])){
						foreach ($data['img'] as $key => $value) {
							$saveName = $data['add_time'].rand(0,99999) . '.png';
	
							$img=base64_decode($value);
							//生成文件夹
							$names = "material_zone" ;
							$name = "material_zone/" .date('Ymd',$data['add_time']) ;
							if (!file_exists(ROOT_PATH .'/public/upload/'.$names)){ 
								mkdir(ROOT_PATH .'/public/upload/'.$names,0777,true);
							}
							//保存图片到本地
							file_put_contents(ROOT_PATH .'/public/upload/'.$name.$saveName,$img);
	
							unset($data['img'][$key]);
							$data['img'][] = '/public/upload/'.$name.$saveName;
						}
						$data['img'] = array_values($data['img']);
						
						foreach ($data['img'] as $key => $value) {
							$datas[$key]['img'] = $value;
							$datas[$key]['mz_id'] = $res;
						}
						Db::name('material_zone_img')->insertAll($datas);
					}
				}
			}


			if($res !== false){
				$this->ajaxReturn(['status' => 1, 'msg' => '成功！']);
			}else{
				$this->ajaxReturn(['status' => 0, 'msg' => '失败！']);
			}

		}

		$info = [];
		$img_arr = [];
		if($id){
			$info = Db::name('material_zone')->find($id);
			$img_arr = Db::name('material_zone_img')->where('mz_id',$id)->select();
		}
		
		$cat_list = Db::name('material_zone_cate')->order('sort DESC,cat_id DESC')->select();

		return $this->fetch('',[
			'info'	=>	$info,
			'cat_list'	=>	$cat_list,
			'img_arr'	=>	$img_arr,
		]);

	}

	public function del(){
		$id = input('id');

		$res = Db::name('material_zone')->where('id',$id)->delete();

		if($res){
			Db::name('material_zone_img')->where('mz_id',$id)->delete();
			$this->ajaxReturn(['status' => 1, 'msg' => '删除成功！']);
		}

		$this->ajaxReturn(['status' => 0, 'msg' => '删除失败！']);
	}

	public function del_img(){
		$id = input('id');
		$res = Db::name('material_zone_img')->find();
		if($res){
			Db::name('material_zone_img')->where('id',$id)->delete();
			@unlink($res['img']);
			$this->ajaxReturn(['status' => 1, 'msg' => '删除成功！']);
		}
		$this->ajaxReturn(['status' => 0, 'msg' => '删除失败！']);
	}


}