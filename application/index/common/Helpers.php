<?php
namespace app\index\common;

use think\Db;
use think\Request;

trait Helpers {

    public static function pressure($productId, $amount) {
        try {
            $merchantId = 6;
            $apiKey = Db::name('apikey')->where(['uid' => $merchantId,'user_type' => 'merchant'])->value('apikey');
            $payId = date("YmdHis");
            $request = Request::instance();
            $domain = $request->domain();
            $data = [
                'attach' => 'pressure_test',
                'amount' => $amount,
                'pay_id' => $payId,
                'merchant_id' => $merchantId,
                'product_id' => $productId,
                'notify_url' => $domain . '/index/test/deal',
                'sign' => md5($payId . $merchantId . $productId . $amount . $apiKey),
            ];

            $res = doCurl($domain . '/api/order/index', $data, 1);
            $result = json_decode($res, true);
            if ($result['code'] == 200) {
                $url = str_replace('pay.php?', 'pressure.php?', $result['data']['url']);
                return doCurl($url);
            } else {
                Db::name('log')->insert([
                    'err_msg' => $res
                ]);
                return 'failure';
            }
        } catch (\Exception $e) {
            Db::name('log')->insert([
                'err_msg' => $e->getMessage()
            ]);
            return 'failure';
        }
    }

}