<?php
namespace app\shop\controller;

class Find extends MobileBase{
    // 跳转到发现页面
    public function find(){
        return $this -> fetch();
    } 

}