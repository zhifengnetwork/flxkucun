<?php
namespace app\common\logic;

use app\common\model\Region;
use think\Model;

/**
* 
*/

class Address
{
    /**
     * 地址智能解析
     * @param string 包含丰富信息的字符串
     * @return array 姓名，手机号，邮编，详细地址
     */
    public static function smart_parse($address)
    {   
        //解析结果
        $parse = [];
        $parse['name']     = '';
        $parse['mobile']   = '';
        $parse['postcode'] = '';
        $parse['detail']   = '';

        //1. 过滤掉收货地址中的常用说明字符，排除干扰词
        $search = ['地址', '收货地址', '收货人', '收件人', '收货', '邮编', '电话', '：', ':', '；', ';', '，', ',', '。', ];
        $replace = [' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' '];
        $address = str_replace($search, $replace, $address);

        //2. 连续2个或多个空格替换成一个空格
        $address = preg_replace('/ {2,}/', ' ', $address);

        //3. 去除手机号码中的短横线 如136-3333-6666 主要针对苹果手机
        $address = preg_replace('/(\d{3})-(\d{4})-(\d{4})/', '$1$2$3', $address);

        //4. 提取11位手机号码
        preg_match('/\d{11}/', $address, $match);
        if ($match && $match[0]) {
            $parse['mobile'] = $match[0];
            $address = str_replace($match[0], '', $address);
        }

        //5. 提取6位邮编 邮编也可用后面解析出的省市区地址从数据库匹配出
        preg_match('/\d{6}/', $address, $match);
        if ($match && $match[0]) {
            $parse['postcode'] = $match[0];
            $address = str_replace($match[0], '', $address);
        }

        //再次把2个及其以上的空格合并成一个，并首位TRIM
        $address = trim(preg_replace('/ {2,}/', ' ', $address));

        //按照空格切分 长度长的为地址 短的为姓名 因为不是基于自然语言分析，所以采取统计学上高概率的方案
        $split_arr = explode(' ', $address);
        if (count($split_arr) > 1) {
            $parse['name'] = $split_arr[0];
            foreach ($split_arr as $value) {
                if (strlen($value) < strlen($parse['name'])) {
                    $parse['name'] = $value;
                }
            }
            $address = trim(str_replace($parse['name'], '', $address));
        }

        $parse['detail'] = Address::detail_parse($address); //详细地址传入另一个文件的函数，解析出：省，市，区，街道地址

        return $parse;
    }

    public static function detail_parse($detail)
    {
        // 测试例子
        // $detail = '成都市高新区天府软件园B区科技大楼';
        // $detail = '双流县郑通路社保局区52050号';
        // $detail = '岳市岳阳楼区南湖求索路碧灏花园A座1101';
        // $detail = '四川省南充市阆中市公园路25号';
        // $detail = '四川省阆中市公园路25号';
        // $detail = '四川省 凉山州美姑县xxx小区18号院';
        // $detail = '重庆攀枝花市东区机场路3中学校';
        // $detail = '渝北区渝北中学51200街道地址';
        // $detail = '天津天津市红桥区水木天成1区临湾路9-3-1101';

        $detail = str_replace(' ', '', $detail);

        //返回结果
        $result = [];

        /**
         * 1. 三级地址识别 共有2992个三级地址 高频词为【县，区，旗，市】是整个识别系统的关键
         * 返回 [%第3级% 模糊地址] [街道地址]
         * 三级地址 前面一般2或3个字符就够用了【3个字符，比如高新区，仁和区，武侯区，占比96%】【2个字符的县和区有140个左右，占比4%，比如理县】
         */

        if (mb_strstr($detail, '县') || mb_strstr($detail, '区') || mb_strstr($detail, '旗')) {
            // 如果同时出现 县和区 我们可以确定的是县一定在区前面，所以下面三个if顺序是有要求的，不能随便调整
            if (mb_strstr($detail, '旗')) {
                $deep3_keyword_pos = mb_strpos($detail, '旗');
                $deep3_area_name = mb_substr($detail, $deep3_keyword_pos - 1, 2);
            }

            if (mb_strstr($detail, '区')) {
                $deep3_keyword_pos = mb_strpos($detail, '区');

                // 判断区、市是同时存在 同时存在 可以简单 比如【攀枝花市东区攀枝花三中高三班2010级】
                if (mb_strstr($detail, '市')) {
                    $city_pos = mb_strpos($detail, '市');
                    $zone_pos = mb_strpos($detail, '区');
                    $deep3_area_name = mb_substr($detail, $city_pos + 1, $zone_pos - $city_pos);
                } else {
                    $deep3_area_name = mb_substr($detail, $deep3_keyword_pos - 2, 3);
                    //县名称最大的概率为3个字符 美姑县 阆中市 高新区
                }
            }

            if (mb_strstr($detail, '县')) {
                $deep3_keyword_pos = mb_strpos($detail, '县');
                // 判断县市是同时存在 同时存在 可以简单 比如【湖南省常德市澧县】
                if (mb_strstr($detail, '市')) {
                    $city_pos = mb_strpos($detail, '市');
                    $zone_pos = mb_strpos($detail, '县');
                    $deep3_area_name = mb_substr($detail, $city_pos + 1, $zone_pos - $city_pos);
                } else {
                    $deep3_area_name = mb_substr($detail, $deep3_keyword_pos - 2, 3);
                    //县名称最大的概率为3个字符 美姑县 阆中市 高新区
                }
            }

            $street = mb_substr($detail, $deep3_keyword_pos + 1);
        } else {
            if (mb_strripos($detail, '市')) {
                //最大的可能性为县级市 可能的情况有【四川省南充市阆中市公园路25号，四川省南充市阆中市公园路25号】市要找【最后一次】出现的位置
                $deep3_keyword_pos = mb_strripos($detail, '市');
                $deep3_area_name = mb_substr($detail, $deep3_keyword_pos - 2, 3);
                $street = mb_substr($detail, $deep3_keyword_pos + 1);
            } else {
                //不能识别的解析
                $deep3_area_name = '';
                $street = $detail;
            }
        }

        $result['street'] = $street;

        /**
         * 2. 二级地址的识别 共有410个二级地址 高频词为【市，盟，州】 高频长度为3,4个字符 因为有用户可能会填写 '四川省阆中市'，所以二级地址的识别可靠性并不高 需要与三级地址 综合使用
         * 返回 [%第2级% 模糊地址]
         */
        if (mb_strrpos($detail, '市') || mb_strstr($detail, '盟') || mb_strstr($detail, '州')) {
            if ($tmp_pos = mb_strrpos($detail, '市')) {
                $deep2_area_name = mb_substr($detail, $tmp_pos - 2, 3);
            }

            if ($tmp_pos = mb_strrpos($detail, '盟')) {
                $deep2_area_name = mb_substr($detail, $tmp_pos - 2, 3);
            }

//            if ($tmp_pos = mb_strrpos($detail, '州')) {
//                $deep2_area_name = mb_substr($detail, $tmp_pos - 2, 3);
//            }
        } else {
            $deep2_area_name = '';
        }

        //3. 到数据中智能匹配
        if ($deep3_area_name != '') {

            //数据库匹配 以下的数据库匹配需要程序员根据自己的框架自行替换
            $model_area = new Region();
            $condition = [];
            $condition['level'] = 3;
            $condition['name'] = array('like', '%' . $deep3_area_name . '%');
            $deep3_area_list = $model_area->getAreaList($condition);
//            dump($deep2_area_name);exit;
            // 三级地址的匹配出现多个结果 依靠二级地址缩小范围
            if ($deep3_area_list && count($deep3_area_list) > 1) {
//                dump(888);exit;
                if ($deep2_area_name) {
                    $area_info_2 = $model_area->getAreaInfo(['name' => array('like', '%' . $deep2_area_name . '%')]);
//                    dump($area_info_2);exit;
                    //2级地址匹配成功 再次缩小三级地址 然后确定一级地址
                    if ($area_info_2) {
                        $area_info_3 = $model_area->getAreaInfo(['parent_id' => $area_info_2['id'], 'name' => array('like', '%' . $deep3_area_name . '%')]);
                    }
                    $area_info_1 = $model_area->getAreaInfo(['id' => $area_info_2['parent_id'], 'level' => 1]);

                    //获得结果
                    $result[1]['id'] = $area_info_2['parent_id'];
                    $result[1]['name'] = $area_info_1['name'];
                    $result[2]['id'] = $area_info_2['id'];
                    $result[2]['name'] = $area_info_2['name'];
                    $result[3]['id'] = $area_info_3['id'];
                    $result[3]['name'] = $area_info_3['name'];
                }

            } else {
                if ($deep3_area_list && count($deep3_area_list) == 1) {
                    $area_info_2 = $model_area->getAreaInfo(['id' => $deep3_area_list[0]['parent_id'], 'level' => 2]);
                    if ($area_info_2) {
                        $area_info_1 = $model_area->getAreaInfo(['id' => $area_info_2['parent_id'], 'level' => 1]);

                        //获得结果
                        $result[1]['id'] = $area_info_2['parent_id'];
                        $result[1]['name'] = $area_info_1['name'];
                        $result[2]['id'] = $area_info_2['id'];
                        $result[2]['name'] = $area_info_2['name'];
                        $result[3]['id'] = $deep3_area_list[0]['id'];
                        $result[3]['name'] = $deep3_area_list[0]['name'];
                    }

                }
            }
        }

        //最终结果
        return $result;
    }
}



