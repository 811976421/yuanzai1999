<?php
namespace app\api\controller;

use think\Controller;
use think\Db;

class Query extends Controller {

    public function order() {
        $order_Id = isset($_GET['order_id']) ? $_GET['order_id'] : '';

        if (!$order_Id) {
            echo errReturn(400, 'error: order_id 不能为空', 1);
            die();
        }

        $orderInfo = Db::name('order')
            ->where('order_id', $order_Id)
            ->field('order_id,pay_id,product_id,merchant_id,amount,status,create_time')
            ->find();

        if (!$orderInfo) {
            echo errReturn(400, 'error: 查询不到该笔订单', 1);
            die();;
        }

        echo sucReturn(200, 'success', $orderInfo, 1);
        die();
    }

    public function withdraw() {

        $order_Id = isset($_GET['order_id']) ? $_GET['order_id'] : '';
        if (!$order_Id) {
            echo errReturn(400, 'error: order_id 不能为空', 1);
            die();
        }

        $orderInfo = Db::name('merchant_withdraw_order')
            ->where('order_id', $order_Id)
            ->field('order_id,merchant_id,amount,real_amount,status,create_time')
            ->find();

        if (!$orderInfo) {
            echo errReturn(400, 'error: 查询不到该笔订单', 1);
            die();;
        }

        echo sucReturn(200, 'success', $orderInfo, 1);
        die();
    }

}