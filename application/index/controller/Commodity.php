<?php
namespace app\index\controller;

use think\Db;

class Commodity extends Common {

    public function index() {

        $shopId = input('id');

        $where['id'] = $shopId;

        if (Session('adminInfo')['role_id'] == '3' && Session('adminInfo')['role_name'] == '代理') {

            $proxyPower = Db::connect($this->connection)
                ->name('proxy_power')
                ->where('proxy_id', Session('adminInfo')['id'])
                ->find();

            if ($proxyPower) {
                if (isset($proxyPower['proxy_level']) && $proxyPower['proxy_level'] == '1') {
                    $where['address'] = ['like', '%'.$proxyPower['province'].$proxyPower['city'].'%'];
                } else {
                    $where['address'] = ['like', '%'.$proxyPower['province'].$proxyPower['city'].$proxyPower['county'].'%'];
                }
            } else {
                $this->error('该用户无权获取店铺信息');
            }
        }

        $table = 'mall_manage';
        $type = 1;
        if (isset($_GET['type']) && $_GET['type'] == 2) {
            $table = 'o2o_manage';
            $type = $_GET['type'];
        }

        $shopInfo = Db::name($table)->where($where)->find();

        if ($shopInfo) {
            $this->assign('type', $type);
            $this->assign('shop_id', $shopInfo['shops_id']);
            $this->assign('store_id', $shopInfo['id']);
            return $this->fetch();
        } else {
            $this->error('店铺不存在');
        }

    }

    public function getList() {

        $table = 'mall_goods';

        if (input('type') == 2) {
            $table = 'o2o_goods';
        }

        $list = Db::name($table)
            ->where([
            'shop_id' => input('shop_id'),
            'store_id' => input('store_id'),
        ])->field('id,title,cid,img,price,discount_price,volume,stock,status,create_time,commission,supply_price')
            ->page(input('page'))
            ->limit(input('limit'))
            ->select();

        foreach ($list as $k => $v) {
            $list[$k]['create_time'] = date("Y-m-d", $v['create_time']);
            $list[$k]['type'] = Db::name('mall_goods_cate')->where('id', $v['cid'])->value('name');
        }

        $count = Db::name($table)->where([
            'shop_id' => input('shop_id'),
            'store_id' => input('store_id'),
            'status' => '1'
        ])->count();

        return json([
            'code' => 0,
            'msg' => '',
            'data' => $list,
            'count' => $count
        ]);
    }

}
