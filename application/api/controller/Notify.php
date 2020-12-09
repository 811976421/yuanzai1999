<?php
namespace app\api\controller;

use think\Controller;
use think\Db;
use think\Exception;

class Notify extends Controller {

    public function index() {
        echo json_encode(['code' => 0]);
        die();
    }

    public function listen() {
        $data = file_get_contents("php://input");
        $res = json_decode($data, true);
        $content = $res['content'];
        $device_id = $res['device_id'];
        $sign = $res['sign'];
        if(!$sign) {
            die();
        }
        $type = $res['type'];
        $time = $res['time'];
        $username = $res['username'];
        $regex = '/\d+(.\d+)?/';
        preg_match_all($regex, $content, $arr);
        $amount = array_pop($arr[0]);
        $mySign = $this->getSign($type, $content, $time, $device_id, $username);
        if ($mySign != $sign) {
            myLog('静态码验签失败,数据包:'.$data);
            die();
        }

        $orderInfo = Db::name('order')
            ->alias('o')
            ->join('center_members m', 'o.member_id = m.id')
            ->join('center_qrcode q', 'o.resource_id = q.id')
            ->where([
            'o.amount' => $amount,
            'm.username' => $username,
            'm.status' => '1',
            'o.status' => '0',
            'q.device_id' => $device_id,
            'q.qr_type' => $type
            ])->find();

        if (!$orderInfo) {
            myLog('静态码订单不存在,数据包:'.$data);
            die();
        }

        if ($time > strtotime($orderInfo['expire_time']) *1000) {
            myLog('静态码订单已过期，数据包：'.$data);
            die();
        }
        Db::startTrans();
        try {
            $result = [];
            $info = Db::name('order')->where('order_id', $orderInfo['order_id'])->find();
            $memberId = $info['member_id'];
            $merchantId = $info['merchant_id'];
            $memberpid = Db::name('members')->where('id', $memberId)->value('pid');
            $merhcantpid = Db::name('merchants')->where('id', $merchantId)->value('pid');
            $merchant_introduce_reward = $info['merchant_introduce_reward'];
            $merchant_trade_charge = $info['merchant_trade_charge'];
            $member_trade_reward = $info['member_trade_reward'];
            $member_introduce_reward = $info['member_introduce_reward'];

            $merchantFund = Db::name('merchant_balance')->where('merchant_id', $merchantId)->find();
            $memberFund = Db::name('member_balance')->where('member_id', $memberId)->find();

            $result['1'] = Db::name('order')->where('order_id', $orderInfo['order_id'])->setField('status', '1');
            $result['2'] = Db::name('order')->where('order_id', $orderInfo['order_id'])->setField('pay_time', date("Y-m-d H:i:s", $time));

            //商户资金变动表
            $result['3'] = Db::name('merchant_balance_change')->insert([
                'merchant_id' => $merchantId,
                'order_id' => $orderInfo['order_id'],
                'trade_type' => '1',
                'change_type' => '1',
                'change_way' => '1',
                'before_amount' => $merchantFund['avaliable_amount'],
                'amount' => $amount,
                'charge' => $merchant_trade_charge,
                'after_amount' => $merchantFund['avaliable_amount'] + $amount - $merchant_trade_charge,
                'remark' => '收款码交易'
            ]);

            //商户推荐返佣
            if ($merhcantpid) {
                $merchantPfund = Db::name('merchant_balance')->where('merchant_id', $merhcantpid)->find();
                $result['p_merchant_balance_profit_change'] = Db::name('merchant_balance_change')->insert([
                    'merchant_id' => $merhcantpid,
                    'order_id' => $orderInfo['order_id'],
                    'trade_type' => '5',
                    'change_type' => '2',
                    'change_way' => '1',
                    'before_amount' => $merchantPfund['profit_amount'],
                    'amount'  => $merchant_introduce_reward,
                    'charge' => '0',
                    'after_amount' => $merchantPfund['profit_amount'] + $merchant_introduce_reward,
                    'remark' => '一级推荐返佣'
                ]);
            }

            //码商资金变动表
            $result['4'] = Db::name('member_balance_change')->insert([
                'member_id' => $memberId,
                'order_id' => $orderInfo['order_id'],
                'charge' => 0,
                'change_type' => '1',
                'change_way' => '-1',
                'before_amount' => $memberFund['avaliable_amount'],
                'amount' => $amount,
                'after_amount' => $memberFund['avaliable_amount'] - $amount,
                'trade_type' => '1',
                'remark' => '收款码交易'
            ]);

            //码商交易返佣
            $result['5'] = Db::name('member_balance_change')->insert([
                'member_id' => $memberId,
                'order_id' => $orderInfo['order_id'],
                'charge' => 0,
                'change_type' => '2',
                'change_way' => '1',
                'before_amount' => $memberFund['profit_amount'],
                'amount' => $member_trade_reward,
                'after_amount' => $memberFund['profit_amount'] + $member_trade_reward,
                'trade_type' => '6',
                'remark' => '交易返佣'
            ]);

            //码商推荐返佣
            if ($memberpid) {
                $memberPfund = Db::name('member_balance')->where('member_id', $memberpid)->find();
                $result['6'] = Db::name('member_balance_change')->insert([
                    'member_id' => $memberpid,
                    'order_id' => $orderInfo['order_id'],
                    'charge' => 0,
                    'change_type' => '2',
                    'change_way' => '1',
                    'before_amount' => $memberPfund['profit_amount'],
                    'amount' => $member_introduce_reward,
                    'after_amount' => $memberPfund['profit_amount'] + $member_introduce_reward,
                    'trade_type' => '5',
                    'remark' => '一级推荐返佣'
                ]);
            }

            //商户交易结算
            $result['7'] = Db::name('merchant_balance')->where('merchant_id', $merchantId)->setInc('avaliable_amount', $info['amount'] - $merchant_trade_charge);
            //商户返佣结算
            if ($info) {
                $result['8'] = Db::name('merchant_balance')->where('merchant_id', $merhcantpid)->setInc('profit_amount', $merchant_introduce_reward);
            }
            //码商交易结算
            $result['9'] = Db::name('member_balance')->where('member_id', $memberId)->setDec('disabled_amount', $info['amount']);
            //码商推荐返佣
            $result['10'] = Db::name('member_balance')->where('member_id', $memberpid)->setInc('profit_amount', $member_introduce_reward);
            //码商交易返佣
            $result['11'] = Db::name('member_balance')->where('member_id', $memberId)->setInc('profit_amount', $member_trade_reward);

            foreach ($result as $k => $v) {
                if (!$v) {
                    Db::rollback();
                    myLog('静态码数据库更新异常：操作编号:'. $k . ',订单信息:' . json_encode($info));
                    die();
                }
            }
            $redis = Fredis::instance();
            if ($orderInfo['notify_url']) {
                $redis->rpush('notify_task', $info['order_id']);
            }
            $merchant_amount_key = 'merchant_id_' . $merchantId . '_day_' . date("Y-m-d") . '_sum_amount';
            $dayMerchantAmount = $redis->get($merchant_amount_key);
            $redis->set($merchant_amount_key, intval($dayMerchantAmount) + $info['amount'],60*60*24);
            $member_amount_key = 'member_id_' . $memberId . '_day_' . date("Y-m-d") . '_sum_amount';
            $dayMemberAmount = $redis->get($member_amount_key);
            $redis->set($member_amount_key, intval($dayMemberAmount) + $info['amount'],60*60*24);
            Db::commit();
            die();
        } catch (\Exception $e) {
            Db::rollback();
            myLog('静态码回调异常:' . $e->getMessage() . ',订单数据:' . json_encode($info));
        }
    }

    public function getSign($type, $content, $time, $device_id, $username) {
        return md5($type . $content . $time . $device_id . $username . 'king');
    }

    public function bank() {
        $data = file_get_contents("php://input");
        myLog($data);
        $str = urldecode($data);
        parse_str($str, $arr);
        $content = isset($arr['content']) ? $arr['content'] : '';
        $device_id = isset($arr['mobile']) ? $arr['mobile'] : '';
        $username = isset($arr['use']) ? $arr['use'] : '';
        $bankNo = isset($arr['source']) ? $arr['source'] : '';
        $time = isset($arr['time']) ? $arr['time'] : '';
        $sign = isset($arr['sign']) ? $arr['sign'] : '';
        //验签
        $signText = $content . $device_id . $username . $time . $bankNo;
        $mySign = strtoupper(md5($signText));
        if($mySign == $sign) {
            $content = json_decode($content, true);
            preg_match('/([0-9]{1,}[.][0-9]{1})/', $content['sendContext'], $matches);
            $amount = $matches[0];
            $payTime = $content['dt'];
            $orderInfo = Db::name('order')
                ->alias('o')
                ->join('center_transfer_bank b', 'o.resource_id = b.id')
                ->join('center_members m', 'o.member_id = m.id')
                ->where([
                    'b.device_id' => $device_id,
                    'm.username'  => $username,
                    'b.bank_no'   => $bankNo,
                    'o.amount'    => $amount,
                    'o.status'    => '0',
                    'm.status'    => '1',
                ])
                ->find();

            if (!$orderInfo) {
                throw new Exception('订单不存在');
            }

            Db::startTrans();
            try {
                $result = [];
                $memberId = $orderInfo['member_id'];
                $merchantId = $orderInfo['merchant_id'];
                $memberpid = Db::name('members')->where('id', $memberId)->value('pid');
                $merhcantpid = Db::name('merchants')->where('id', $merchantId)->value('pid');
                $merchant_introduce_reward = $orderInfo['merchant_introduce_reward'];
                $merchant_trade_charge = $orderInfo['merchant_trade_charge'];
                $member_trade_reward = $orderInfo['member_trade_reward'];
                $member_introduce_reward = $orderInfo['member_introduce_reward'];

                $result['1'] = Db::name('order')->where('order_id', $orderInfo['order_id'])->setField('status', '1');
                $result['2'] = Db::name('order')->where('order_id', $orderInfo['order_id'])->setField('pay_time', $payTime);

                $merchantFund = Db::name('merchant_balance')->where('merchant_id', $merchantId)->find();
                $memberFund = Db::name('member_balance')->where('member_id', $memberId)->find();

                //商户资金变动表
                $result['3'] = Db::name('merchant_balance_change')->insert([
                    'merchant_id' => $merchantId,
                    'order_id' => $orderInfo['order_id'],
                    'trade_type' => '1',
                    'change_type' => '1',
                    'change_way' => '1',
                    'before_amount' => $merchantFund['avaliable_amount'] + $merchantFund['disabled_amount'],
                    'amount' => $orderInfo['amount'],
                    'charge' => $merchant_trade_charge,
                    'after_amount' => $merchantFund['avaliable_amount'] + $merchantFund['disabled_amount'] + $orderInfo['amount'] - $merchant_trade_charge,
                    'remark' => '支转卡交易'
                ]);

                //商户推荐返佣
                if ($merhcantpid) {
                    $merchantPfund = Db::name('merchant_balance')->where('merchant_id', $merhcantpid)->find();
                    $result['4'] = Db::name('merchant_balance_change')->insert([
                        'merchant_id' => $merhcantpid,
                        'order_id' => $orderInfo['order_id'],
                        'trade_type' => '5',
                        'change_type' => '2',
                        'change_way' => '1',
                        'before_amount' => $merchantPfund['profit_amount'],
                        'amount'  => $merchant_introduce_reward,
                        'charge' => '0',
                        'after_amount' => $merchantPfund['profit_amount'] + $merchant_introduce_reward,
                        'remark' => '一级推荐返佣'
                    ]);
                    //商户返佣结算
                    $result['5'] = Db::name('merchant_balance')->where('merchant_id', $merhcantpid)->setInc('profit_amount', $merchant_introduce_reward);
                }

                //码商资金变动表
                $result['6'] = Db::name('member_balance_change')->insert([
                    'member_id' => $memberId,
                    'order_id' => $orderInfo['order_id'],
                    'charge' => 0,
                    'change_type' => '1',
                    'change_way' => '-1',
                    'before_amount' => $memberFund['avaliable_amount'] + $memberFund['disabled_amount'],
                    'amount' => $orderInfo['amount'],
                    'after_amount' => $memberFund['avaliable_amount'] + $memberFund['disabled_amount'] - $orderInfo['amount'],
                    'trade_type' => '1',
                    'remark' => '支转卡交易'
                ]);

                //码商交易返佣
                $result['7'] = Db::name('member_balance_change')->insert([
                    'member_id' => $memberId,
                    'order_id' => $orderInfo['order_id'],
                    'charge' => 0,
                    'change_type' => '2',
                    'change_way' => '1',
                    'before_amount' => $memberFund['profit_amount'],
                    'amount' => $member_trade_reward,
                    'after_amount' => $memberFund['profit_amount'] + $member_trade_reward,
                    'trade_type' => '6',
                    'remark' => '交易返佣'
                ]);

                //码商推荐返佣
                if ($memberpid) {
                    $memberPfund = Db::name('member_balance')->where('member_id', $memberpid)->find();
                    $result['8'] = Db::name('member_balance_change')->insert([
                        'member_id' => $memberpid,
                        'order_id' => $orderInfo['order_id'],
                        'charge' => 0,
                        'change_type' => '2',
                        'change_way' => '1',
                        'before_amount' => $memberPfund['profit_amount'],
                        'amount' => $member_introduce_reward,
                        'after_amount' => $memberPfund['profit_amount'] + $member_introduce_reward,
                        'trade_type' => '5',
                        'remark' => '一级推荐返佣'
                    ]);
                    //码商推荐返佣
                    $result['9'] = Db::name('member_balance')->where('member_id', $memberpid)->setInc('profit_amount', $member_introduce_reward);
                }

                //商户交易结算
                $result['10'] = Db::name('merchant_balance')->where('merchant_id', $merchantId)->setInc('avaliable_amount', $orderInfo['amount'] - $merchant_trade_charge);
                //码商交易结算
                $result['11'] = Db::name('member_balance')->where('member_id', $memberId)->setDec('disabled_amount', $orderInfo['amount']);
                //码商交易返佣
                $result['12'] = Db::name('member_balance')->where('member_id', $memberId)->setInc('profit_amount', $member_trade_reward);

                foreach ($result as $k => $v) {
                    if ($v === false) {
                        Db::rollback();
                        myLog('支转卡数据库更新异常：操作编号:' . $k . ';订单信息:' . json_encode($orderInfo));
                        echo json_encode(['code' => 400, 'message' => 'FAILURE']);
                        die();
                    }
                }
                $redis = Fredis::instance();
                if ($orderInfo['notify_url']) {
                    $redis->rpush('notify_task', $orderInfo['order_id']);
                }
                Db::commit();
                $merchant_amount_key = 'merchant_id_' . $merchantId . '_day_' . date("Y-m-d") . '_sum_amount';
                $dayMerchantAmount = $redis->get($merchant_amount_key);
                $redis->set($merchant_amount_key, intval($dayMerchantAmount) + $orderInfo['amount'],60*60*24);
                $member_amount_key = 'member_id_' . $memberId . '_day_' . date("Y-m-d") . '_sum_amount';
                $dayMemberAmount = $redis->get($member_amount_key);
                $redis->set($member_amount_key, intval($dayMemberAmount) + $orderInfo['amount'],60*60*24);
                $resource_day_key = 'member_id_' . $orderInfo['member_id'] . '_bankcard_' . $orderInfo['resource_id'];
                $dayResourceAmount = $redis->get($resource_day_key);
                $redis->set($resource_day_key, intval($dayResourceAmount) + $orderInfo['amount'], 60*60*24);
                echo json_encode(['code' => 200, 'message' => 'SUCCESS']);
                die();
            } catch (\Exception $e) {
                Db::rollback();
                myLog('支转卡回调异常:' . $e->getMessage() . ';订单信息:' . json_encode($orderInfo));
                die();
            }
            echo 'success';
            die();
        }
        exit('sign error');
    }



}
