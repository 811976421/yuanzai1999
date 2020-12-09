<?php
namespace app\api\controller;

use think\Config;
use think\Controller;
use think\Db;
use think\Request;

class Refill extends Controller {

    protected $cardNum;
    protected $phoneNo;
    protected $key;
    protected $openId;
    protected $domain;
    protected $roleId;
    protected $gradeId;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->roleId = input('roleId');
        $this->cardNum = input('cardNum');
        $this->phoneNo = input('phoneNo');
        $this->gradeId = input('gradeId');
        $juheConf = Config('juhe_conf');
        $this->key = $juheConf['key'];
        $this->openId = $juheConf['openId'];
        $this->domain = $juheConf['domain'];
    }

    public function telCheck() {
        $url = $this->domain . '/ofpay/mobile/telcheck';
        $res = doCurl($url, ['cardnum' => $this->cardNum, 'phoneno' => $this->phoneNo, 'key' => $this->key], 1);
        return json_decode($res, true);
    }

    public function telQuery() {
        $url = $this->domain . '/ofpay/mobile/telquery';
        $res = doCurl($url, ['phoneno' => $this->phoneNo, 'cardnum' => $this->cardNum, 'key' => $this->key], 1);
        return json_decode($res, true);
    }

    public function wxOrder() {
        $result = $this->telMember();
        if ($result['error_code'] == 0) {
            $result = $this->telCheck();
            if (isset($result['error_code']) && $result['error_code'] == 0) {
                $result = $this->telQuery();
                if (isset($result['error_code']) && $result['error_code'] == 0) {
                    //生成预付订单
                    $orderId = date('Ymd').substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(1000, 9999)) . substr($this->phoneNo, -4);
                    Db::table('yz_phone_recharge_order')->insert([
                        'cardNum' => $this->cardNum,
                        'phoneNo' => $this->phoneNo,
                        'order_id' => $orderId,
                    ]);
                    //拉起微信支付
                    $res = doCurl(request()->domain().'/api/wxpay/unifiedorder', [
                        'roleId' => $this->roleId,
                        'out_trade_no' => $orderId,
                        'phoneno' => $this->phoneNo,
                        'cardnum' => $this->cardNum,
                        'redirect' => $this->roleId == 1 ? 'https://v2.meiyidz.com/index/refill/index' : 'https://v2.meiyidz.com/index/refill/index' . $this->roleId
                    ], 1);
                    if ($res) {
                        return sucReturn(200,'',$res);
                    } else {
                        return errReturn(400,'支付失败');
                    }
                }
            }
        }
        if (isset($result['error_code'])) {
            Db::table('yz_phone_recharge_error_log')->insert([
                'cardnum' => $this->cardNum,
                'phoneno' => $this->phoneNo,
                'err_code' => $result['error_code'],
                'err_msg' => $result['reason'],
            ]);
        }
        return errReturn(400,$result['reason']);
    }

    public function aliOrder() {
        $result = $this->telMember();
        if ($result['error_code'] == 0) {
            $result = $this->telCheck();
            if (isset($result['error_code']) && $result['error_code'] == 0) {
                $result = $this->telQuery();
                if (isset($result['error_code']) && $result['error_code'] == 0) {
                    //生成预付订单
                    $orderId = date('Ymd').substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(1000, 9999)) . substr($this->phoneNo, -4);
                    Db::table('yz_phone_recharge_order')->insert([
                        'cardNum' => $this->cardNum,
                        'phoneNo' => $this->phoneNo,
                        'order_id' => $orderId,
                    ]);
                    //拉起微信支付
                    $res = doCurl(request()->domain().'/api/alipay/dopay', [
                        'roleId' => $this->roleId,
                        'out_trade_no' => $orderId,
                        'phoneno' => $this->phoneNo,
                        'cardnum' => $this->cardNum,
                        'redirect' => $this->roleId == 1 ? 'https://v2.meiyidz.com/index/refill/index' : 'https://v2.meiyidz.com/index/refill/index' . $this->roleId
                    ], 1);
                    echo $res;
                }
            }
        }
        if (isset($result['error_code'])) {
            Db::table('yz_phone_recharge_error_log')->insert([
                'cardnum' => $this->cardNum,
                'phoneno' => $this->phoneNo,
                'err_code' => $result['error_code'],
                'err_msg' => $result['reason'],
            ]);
        }
        $this->error($result['reason']);
    }

    public function query() {
        $orderId = input('orderId');
        $url = $this->domain . '/ofpay/mobile/ordersta';
        $res = doCurl($url, ['orderid' => $orderId, 'key' => $this->testKey],1);
        echo $res;
    }

    public function callback() {

        $sporder_id = addslashes($_POST['sporder_id']); //聚合订单号
        $orderid = addslashes($_POST['orderid']); //商户的单号
        $sta = addslashes($_POST['sta']); //充值状态
        $sign = addslashes($_POST['sign']); //校验值

        $local_sign = md5($this->key.$sporder_id.$orderid); //本地sign校验值

        if ($local_sign == $sign) {
            $info = Db::table('yz_phone_recharge_order')
                ->where([
                    'order_id' => $orderid,
                    'sporder_id' => $sporder_id,
                    'status' => '1',
                ])->find();
            if ($info) {
                if ($sta == '1') {
                    //充值成功,根据自身业务逻辑进行后续处理
                    Db::table('yz_phone_recharge_order')->where('id', $info['id'])->setField('status', '2');
                } elseif ($sta =='9') {
                    //充值失败,根据自身业务逻辑进行后续处理
                    Db::table('yz_phone_recharge_order')->where('id', $info['id'])->setField('status', '-2');
                }
            }
            echo 'success';
        }
    }

    public function telMember() {
        $result = ['error_code' => 0, 'reason' => ''];
        $monthSum = Db::table('yz_phone_recharge_order')->where([
            'phoneNo' => $this->phoneNo,
            'pay_status' => '1',
            'status' => ['in', '1,2']]
        )->whereTime('create_time', 'month')
            ->sum('cardNum');
        if (($monthSum + $this->cardNum) >= 300) {
            $result['error_code'] = 305865;
            $result['reason'] = '该用户充值已达上限';
        }
        if (doCurl('http://124.71.132.7:9495/prepaid.php?mobile='.$this->phoneNo.'&grade_id='.$this->gradeId) === '0') {
            if ($this->gradeId == '3') {
                if (doCurl('http://124.71.132.7:9495/prepaid.php?mobile='.$this->phoneNo.'&grade_id=59') === '0') {
                    $result['error_code'] = 305866;
                    $result['reason'] = '非平台用户或该用户不可享受此折扣';
                }
            } else {
                $result['error_code'] = 305866;
                $result['reason'] = '非平台用户或该用户不可享受此折扣';
            }
        }
        return $result;
    }

}