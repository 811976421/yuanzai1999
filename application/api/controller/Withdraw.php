<?php
namespace app\api\controller;

use think\Controller;
use think\Db;
use think\Exception;

class Withdraw extends Controller {

    public function index() {

        $data = [
            'order_id'     => input('order_id'),
            'merchant_id'  => input('merchant_id'),
            'bankaccount'  => input('bankaccount'),
            'cardno'       => input('cardno'),
            'bankname'     => input('bankname'),
            'subbranch'    => input('subbranch'),
            'province'     => input('province'),
            'city'         => input('city'),
            'amount'       => input('amount'),
            'sign'         => input('sign'),
            'notify_url'   => input('notify_url')
        ];

        $rule = [
            ['order_id', 'require|number', '商户提现id不能为空|商户提现id必须为数字'],
            ['merchant_id', 'require|number', '商户id不能为空|商户id必须为数字'],
            ['bankaccount', 'require', '收款银行开户名不能为空'],
            ['cardno', 'require|number','收款银行卡号不能为空|收款银行卡号必须为数字'],
            ['bankname', 'require', '收款银行名称不能为空'],
            ['subbranch', 'require', '收款银行分行名称不能为空'],
            ['province', 'require', '收款银行所在省份不能为空'],
            ['city', 'require', '收款银行所在城市不能为空'],
            ['amount', 'require|number', '提现金额不能为空|提现金额必须为数字'],
            ['notify_url', 'require', '回调地址为空'],
            ['sign', 'require', '签名不能为空'],
        ];

        $res = checkAll($data, $rule);

        if ($res !== true) {

            return errReturn(401, $res,1);

        }

        $where['id'] = $data['merchant_id'];
        $where['status'] = '1';
        $info = Db::name('merchants')->where($where)->find();
        if (!$info) {
            return errReturn(401, 'invalid user', 1);
        }
        $apiKey = Db::name('apikey')->where(['uid' => $data['merchant_id'], 'user_type' => 'merchant'])->value('apikey');
        $sign = $data['sign'];
        unset($data['sign']);
        if ($sign != getSign($data, $apiKey)) {
            return errReturn(400, '验签失败', 1);
        }

        $withdrawConfig = Db::name('merchant_withdraw')
            ->where(['merchant_id' => $data['merchant_id'], 'status' => '1'])
            ->field('rate_charge,service_charge,unit_min_amount,unit_max_amount,day_max_count,day_max_amount')
            ->find();

        $countKey = 'merchant_id_' . $data['merchant_id'] . '_day_' .date("Y-m-d"). '_withdraw_count';
        $amountKey = 'merchant_id_' . $data['merchant_id'] . '_day_' .date("Y-m-d"). '_withdraw_amount';
        $redis = Fredis::instance();
        $count = $redis->get($countKey) ? $redis->get($countKey) : 0;
        $amount = $redis->get($amountKey) ? $redis->get($amountKey) : 0;
        $funds = Db::name('merchant_balance')->where('merchant_id', $data['merchant_id'])->find();

        if ($withdrawConfig) {
            //校验单次提现金额
            if ($data['amount'] < $withdrawConfig['unit_min_amount']) {
                return errReturn(400, '单笔最小提现金额:' . $withdrawConfig['unit_min_amount'],1);
            }

            if ($data['amount'] > $withdrawConfig['unit_max_amount']) {
                return errReturn(400, '单笔最大提现金额:' . $withdrawConfig['unit_max_amount'],1);
            }

            if (!$funds) {
                return errReturn(400, '用户余额数据异常',1);
            }

            if ($funds['avaliable_amount'] < $data['amount']) {
                return errReturn(400, '用户可用余额不足',1);
            }

            //校验次数
            if ($count) {
                if (($count + 1) > $withdrawConfig['day_max_count']) {
                    return errReturn(400, '单日提现次数已达上线',1);
                }
            }

            //校验额度
            if ($amount) {
                if (($amount + $data['amount']) > $withdrawConfig['day_max_amount']) {
                    return errReturn(400, '超过单日提现额度',1);
                }
            }
        }

        //计算提现费用
        $charge = 0;

        if ($withdrawConfig['rate_charge'] > 0) {
            $charge = $charge + $data['amount'] * $withdrawConfig['rate_charge'];
        }

        if ($withdrawConfig['service_charge'] > 0) {
            $charge = $charge + $withdrawConfig['service_charge'];
        }

        Db::startTrans();
        try {
            //用户提现表插入新数据
            $data['real_amount'] = $data['amount'] - $charge;
            $data['charge'] = $charge;
            $data['mark'] = tradeCertificate();
            $res1 = Db::name('merchant_withdraw_order')->insertGetId($data);
            //用户资金表变更
            if ($res1) {
                $res2 = Db::name('merchant_balance')->where('merchant_id', $data['merchant_id'])->update([
                    'avaliable_amount' => $funds['avaliable_amount'] - $data['amount'],
                    'disabled_amount' => $funds['disabled_amount'] + $data['amount']
                ]);
                if ($res2) {
                    Db::commit();
                    $redis->set($countKey, $count + 1,60 * 60 * 24);
                    $redis->set($amountKey, $amount + $data['amount'],60 * 60 * 24);
                    Db::name('email_task')->insert([
                        'subject' => '提现提醒',
                        'content'=> '提现结算： 订单号 ' . $res1 . '; ' . '商户id' . ': ' . $data['merchant_id'] . '; 金额: ' . $data['amount'] . '; 时间: ' . date("Y-m-d H:i:s", time())
                    ]);
                    Db::name('notice')->insert([
                        'role_id' => strval(Config('roles')['admin']),
                        'uid' => '1',
                        'music' => '/static/music/notice2.mp3',
                        'content' => '提现订单： 订单号 ' . $res1 . '; ' . '商户id' . ': ' . $data['merchant_id'] . '; 金额: ' . $data['amount'] . '; 时间: ' . date("Y-m-d H:i:s", time()),
                    ]);
                    $insertAll = [];
                    $ids = Db::name('members')->where('status', '1')->column('id');
                    foreach ($ids as $v) {
                        $insertAll[] = [
                            'role_id' => strval(Config('roles')['member']),
                            'uid' => $v,
                            'music' => '/static/music/notice2.mp3',
                            'content' => '提现订单： 订单号 ' . $res1 . '; ' . '; 金额: ' . $data['amount'] . '; 时间: ' . date("Y-m-d H:i:s", time()),
                        ];
                    }
                    Db::name('notice')->insertAll($insertAll);
                    return sucReturn(200, 'success',[
                        'order_id'     => input('order_id'),
                        'merchant_id'  => input('merchant_id'),
                        'bankaccount'  => input('bankaccount'),
                        'cardno'       => input('cardno'),
                        'bankname'     => input('bankname'),
                        'subbranch'    => input('subbranch'),
                        'province'     => input('province'),
                        'city'         => input('city'),
                        'amount'       => input('amount'),
                        'real_amount'  => $data['real_amount'],
                        'mark'         => $data['mark'],
                    ],1);
                }
            }
            Db::rollback();
            $redis->set($countKey, $count,60 * 60 * 24);
            $redis->set($amountKey, $amount,60 * 60 * 24);
            return errReturn(400,'error',1);
        } catch (\Exception $e) {
            Db::rollback();
            $redis->set($countKey, $count,60 * 60 * 24);
            $redis->set($amountKey, $amount,60 * 60 * 24);
            return errReturn(400,$e->getMessage(),1);
        }

    }

    public function arrive() {

        $orderId = input('order_id');
        $merchantId = input('merchant_id');

        $data = [
            'order_id'     => $orderId,
            'merchant_id'  => $merchantId,
            'sign'       => input('sign'),
        ];

        $rule = [
            ['order_id', 'require|number', '订单号为空|订单号必须为数字'],
            ['merchant_id', 'require|number','商户id为空|商户id必须为数字'],
            ['sign', 'require', '签名为空'],
        ];

        $res = checkAll($data, $rule);

        if ($res !== true) {

            return errReturn(400, $res, 1);

        }

        $apiKey = Db::name('apikey')->where(['uid' => $merchantId,'user_type' => 'merchant'])->value('apikey');

        if ($data['sign'] != MD5($data['order_id'] . $data['merchant_id'] . $apiKey)) {

            return errReturn(400, '验签失败', 1);

        }

        $withdrawInfo = Db::name('merchant_withdraw_order')
            ->where([
                'order_id' => $orderId,
                'merchant_id' => $merchantId,
                'status' => '4'
            ])
            ->find();

        if (!$withdrawInfo) {
            return errReturn(400, Db::name('merchant_withdraw_order')->getLastSql(), 1);
        }

        Db::startTrans();

        try {
            $mark2 = 'merchant_' . $withdrawInfo['id'];
            $fund = Db::name('merchant_balance')->where('merchant_id', $merchantId)->find();
            $result[1] = Db::name('merchant_balance')->where('merchant_id', $merchantId)->setDec('disabled_amount', $withdrawInfo['amount']);
            $result[2] = Db::name('merchant_withdraw_order')->where('id', $withdrawInfo['id'])->setField('status','2');
            $result[3] = Db::name('merchant_balance_change')->insert([
                'merchant_id' => $merchantId,
                'order_id' => $withdrawInfo['id'],
                'trade_type' => '3',
                'change_type' => '1',
                'change_way' => '-1',
                'before_amount' => $fund['avaliable_amount'] + $fund['disabled_amount'],
                'amount' => $withdrawInfo['amount'],
                'charge' => $withdrawInfo['charge'],
                'after_amount' => $fund['avaliable_amount'] + $fund['disabled_amount'] - $withdrawInfo['amount'],
                'remark' => '提现'
            ]);

            $rechargeInfo = Db::name('member_recharge_order')
                ->where([
                    'member_id' => $withdrawInfo['receieve_member_id'],
                    'mark2' => $mark2,
                    'mark1' => '2'
                ])->find();

            if (!$rechargeInfo) {
                throw new Exception('充值订单不存在');
            }

            $result[4] = Db::name('member_recharge_order')
                ->where(['id' => $rechargeInfo['id'], 'member_id' => $withdrawInfo['receieve_member_id']])
                ->setField('status', '2');
            if (date("Y-m-d", strtotime($rechargeInfo['create_time'])) != date("Y-m-d")) {
                $result[7] = Db::name('member_static_detail_day')->where([
                    'member_id' => $rechargeInfo['member_id'],
                    'date' => date("Y-m-d", strtotime($rechargeInfo['create_time']))
                ])->setInc('recharge_success_amount', $rechargeInfo['amount']);
                $result[8] = Db::name('member_static_detail_month')->where([
                    'member_id' => $rechargeInfo['member_id'],
                    'date' => date("Y-m-01", strtotime($rechargeInfo['create_time']))
                ])->setInc('recharge_success_amount', $rechargeInfo['amount']);
            }
            $fund = Db::name('member_balance')->where('member_id', $withdrawInfo['receieve_member_id'])->find();
            $result[5] = Db::name('member_balance')->where('member_id', $withdrawInfo['receieve_member_id'])->setInc('avaliable_amount', $rechargeInfo['real_amount']);
            $result[6] = Db::name('member_balance_change')->insert([
                'member_id' => $withdrawInfo['receieve_member_id'],
                'order_id' => $rechargeInfo['id'],
                'trade_type' => '2',
                'change_type' => '1',
                'change_way' => '1',
                'before_amount' => $fund['avaliable_amount'] + $fund['disabled_amount'],
                'amount' => $rechargeInfo['amount'],
                'charge' => $rechargeInfo['charge'],
                'after_amount' => $fund['avaliable_amount'] + $fund['disabled_amount'] + $rechargeInfo['real_amount'],
                'remark' => '充值'
            ]);

            foreach ($result as $v) {
                if (!$v) {
                    Db::rollback();
                    return errReturn(400, 'failure',1);
                }
            }
            Db::name('notice')->insert([
                'role_id' => Config('roles')['member'],
                'uid' => $rechargeInfo['member_id'],
                'music' => '/static/music/notice2.mp3',
                'content' => '充值到账提醒： 充值订单号 ' . $rechargeInfo['id']. '; 金额: ' . $rechargeInfo['amount'] . '; 时间: ' . $rechargeInfo['update_time'],
            ]);
            Db::commit();
            return json_encode(['code' => 200, 'message' => 'success']);
        } catch (\Exception $e) {
            Db::rollback();
            return errReturn(400,$e->getMessage(),1);
        }
    }

}
