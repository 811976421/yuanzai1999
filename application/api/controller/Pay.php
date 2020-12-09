<?php
namespace app\api\controller;

use think\Controller;
use think\Db;

class Pay extends Controller {

    public function index() {

        $info = Db::name('order')
            ->field('expire_time,amount,order_id,resource_type,resource_id')
            ->where([
                'id' => $_GET['id'],
                'status' => '0',
            ])->find();

        if (!$info) {
            $this->error('订单号有误');
        }

        if (time() + 30 > strtotime($info['expire_time'])) {
            echo '订单已过期';
            die();
        }
        //静态收款码有支付截止时间
        if ($info['resource_type'] !== '1') {
            echo 'error type';
            die();
        }

        $qrInfo = Db::name('qrcode')->where([
            'id' => $info['resource_id'],
            'status' => '1'
        ])->field('qrcode,qr_id')->find();

        if (!$qrInfo) {
            echo 'error qrcode';
            die();
        }

        $this->assign('order_id', $info['order_id']);
        $this->assign('amount', $info['amount']);
        $this->assign('qrcode', $qrInfo['qrcode']);
        $this->assign('qr_id', $qrInfo['qr_id']);
        return $this->fetch();
    }

    public function bank() {
        $info = Db::name('order')
            ->field('amount,order_id,resource_type,resource_id')
            ->where([
                'id' => $_GET['id'],
                'status' => '0',
            ])->find();

        if (!$info) {
            $this->error('订单号有误');
        }

        if ($info['resource_type'] !== '3') {
            echo 'error type';
            die();
        }

        $bankInfo = Db::name('transfer_bank')
            ->where([
                'id' => $info['resource_id'],
                'status' => '1'
            ])->find();

        if (!$bankInfo) {
            echo 'error bank';
            die();
        }
        $url = 'alipay://platformapi/startapp?appId=09999988&actionType=toCard&sourceId=bill&cardNo='.$bankInfo['card_no'].'&bankAccount='.$bankInfo['bank_account'].'&money='.$info['amount'].'&amount='.$info['amount'].'&bankName='.$bankInfo['bankname'];
        $this->assign('amount', $info['amount']);
        $this->assign('order_id', $info['order_id']);
        $this->assign('card_no', $bankInfo['card_no']);
        $this->assign('bank_account', $bankInfo['bank_account']);
        $this->assign('bankName', $bankInfo['bankname']);
        $this->assign('url', $url);
        return $this->fetch();
    }

    public function scan() {
        $info = Db::name('order')
            ->field('amount,order_id,resource_type,resource_id')
            ->where([
                'id' => $_GET['id'],
                'status' => '0',
            ])->find();
        if (!$info) {
            $this->error('订单号有误');
        }

        if ($info['resource_type'] !== '3') {
            echo 'error type';
            die();
        }

        $bankInfo = Db::name('transfer_bank')
            ->where([
                'id' => $info['resource_id'],
                'status' => '1'
            ])->find();

        if (!$bankInfo) {
            echo 'error bank';
            die();
        }
        $url = 'alipay://platformapi/startapp?appId=09999988&actionType=toCard&sourceId=bill&cardNo='.$bankInfo['card_no'].'&bankAccount='.$bankInfo['bank_account'].'&money='.$info['amount'].'&amount='.$info['amount'].'&bankName='.$bankInfo['bankname'];
        $this->redirect($url);
    }

}
