<?php 
namespace app\shop\validate;

use think\Validate;
/***
*用户视频上传验证器
 * @package app\mobile\validate
 *
**/

class videoUpload extends validate{
	//验证规则
	protected $rule = [
		'video_size' =>'max:6242880',
		 'video_name' =>'require',
		 'video_title' =>'require',
		 'video_instro'=>'require',
		 		 
	];
	//提示语句
	protected $message = [
		'video_size.max' =>'请上传5M以内的视频',
		 'video_name.require' =>'系统繁忙，请重试',
		 'video_title.require' =>'请给视频添加标题',
		 'video_instro.require'=>'请给视频添加简介',		 
		 'video_type.vedio'=>'请给上传视频格式文件',	
	];

}
?>
