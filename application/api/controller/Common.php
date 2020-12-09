<?php
namespace app\api\controller;

use think\Controller;
use think\Db;
use think\Request;

class Common extends Controller {

    protected $merchant_id;
    protected $product_id;
    protected $product_type;
    protected $payId;
    protected $amount;
    protected $attach;
    protected $merchant_pid;
    protected $notify_url;
    protected $email;
    protected $interval;
    protected $expire;
    protected $address;
    protected $wish_amount;
    protected $redirect;


    protected function _initialize()
    {
        set_time_limit(0);
        error_reporting(0);
        //校验ip
        $request = Request::instance();
        $clientIp = $request->ip();
        $this->attach = isset($_POST['attach']) ? $_POST['attach'] : '';
        $this->amount = $_POST['amount'];
        $this->wish_amount = $_POST['amount'];
        $this->payId = $_POST['pay_id'];
        $this->merchant_id = $_POST['merchant_id'];
        $this->product_id = $_POST['product_id'];
        $this->notify_url = isset($_POST['notify_url']) ? $_POST['notify_url'] : '';
        $this->redirect = isset($_POST['redirect']) ? $_POST['redirect'] : '';
        $sign = $_POST['sign'];
        $this->checkSign($sign);
        $where['id'] = $this->merchant_id;
        $where['status'] = '1';
        $info = Db::name('merchants')->where($where)->find();
        if (!$info) {
            myLog('invalid user,merchant_id:' . $this->merchant_id);
            echo errReturn(401, 'invalid user', 1);
            die();
        }
        $this->merchant_pid = $info['pid'];
        $productInfo = Db::name('product')
            ->alias('p')
            ->join('center_product_type t', 'p.type_id = t.id')
            ->where([
                'p.id' => $this->product_id,
                'p.status' => '1',
                'p._delete' => '0',
                't.status' => '1',
                't._delete' => '0'
            ])->find();
        if (!$productInfo) {
            myLog('invalid product,product_id:' . $this->product_id);
            echo errReturn(402, 'invalid product', 1);
            die();
        }
        $this->product_type = $productInfo['type_id'];
        $this->interval = $productInfo['trade_interval'];
        $this->expire = $productInfo['trade_expire'];
        $ips = explode(',', $info['ip_white_list']);
        if (!in_array('0.0.0.0', $ips)) {
            if (!in_array($clientIp, $ips)) {
                myLog('invalid ip,ip:' . $clientIp);
                echo errReturn(403, 'invalid ip: ' . $clientIp, 1);
                die();
            }
        }
    }

    private function checkSign($sign) {
        $apiKey = Db::name('apikey')->where(['uid' => $this->merchant_id, 'user_type' => 'merchant'])->value('apikey');
        if ($sign != md5($this->payId . $this->merchant_id . $this->product_id . $this->amount . $apiKey)) {
            myLog('invalid sign:' . $sign . ';merchant_id:' . $this->merchant_id);
            echo errReturn(404, 'invalid sign', 1);
            die();
        }
    }


}
