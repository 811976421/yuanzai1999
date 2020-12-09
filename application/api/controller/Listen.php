<?php
namespace app\api\controller;

use think\Controller;
use think\Db;
use think\Exception;
use think\Request;

class Listen extends Controller {

    public function index() {
        $params = $_POST;
        $order_id = $params['out_trade_no'];
        $info = $this->verify($params);
        if (!$info) {
            echo json_encode(['code' => 400, 'message' => 'FAILURE']);
            die();
        }
        $redis = Fredis::instance();
        $res = $redis->sadd('callback_order', $order_id);
        if ($res) {
            myLog('订单'.$order_id.'推入回调任务成功');
        }
    }

    public function verify($param) {
        $from = $param['from'];
        $apikey = Db::name('portal')->where('id', $from)->value('apikey');
        $sign = md5($param['amount'] . $param['out_trade_no'] . $param['transactionId'] . $apikey . $from);
        if ($sign != $param['sign']) {
            myLog('动态码验签失败,数据包：' . json_encode($param));
            return false;
        }
        $info = Db::name('order')->where(['order_id' => $param['out_trade_no'], 'status' => '0'])->find();
        if (!$info) {
            myLog('订单' . $param['out_trade_no'] . '不存在或者已回调');
            return false;
        }
        if ($param['amount'] != $info['amount']) {
            myLog('金额错误,订单号:' . $param['out_trade_no']);
            return false;
        }
        Db::name('order')->where('id', $info['id'])->setField('escrow_orderId', $param['transactionId']);
        return $info;
    }

}
