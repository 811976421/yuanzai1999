<?php
namespace app\index\controller;

use think\Db;

class Order extends Common {

    public function index() {

        $this->assign('proxy_id', $_GET['proxy_id']);
        return $this->fetch();
    }

    public function getList() {

        $proxyId = $_GET['proxy_id'];

        $type = $_GET['type'];

        $table = '';
        if ($type == '1') {
            $table = 'order_record';
        } elseif ($type == '2') {
            $table = 'mall_order';
        } elseif ($type == '3') {
            $table = 'o2o_order';
        }

        $list = Db::name($table)->where('uid', $proxyId)->page(input('page'))->limit(input('limit'))->select();

        foreach ($list as $k => $v) {
            $list[$k]['create_time'] = date("Y-m-d H:i:s", $v['create_time']);
            $list[$k]['earning_time'] = date("Y-m-d H:i:s", $v['earning_time']);
        }

        $count = Db::name($table)->where('uid', $proxyId)->count();

        return json([
            'code' => 0,
            'msg' => '',
            'data' => $list,
            'count' => $count,
        ]);
    }

}