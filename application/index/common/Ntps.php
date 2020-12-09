<?php
namespace app\index\common;
use app\api\controller\Fredis;
use Swoole\Server;
use think\Db;

class Ntps{

    public $server;
    public $port;
    public $host;
    public $config;

    public function __construct(array $config, $host, $port)
    {
        ini_set('display_errors','no');
        error_reporting(-1);
        $this->config = array_merge([], $config);
        $this->host = $host;
        $this->port = $port;
    }

    public function onReceive(Server $server, int $fd, int $reactorId, string $data)
    {
        $receiveData = json_decode($data, true);
        $responseData = [];
        switch ($receiveData['cmd']) {
            case '_reload':
                $this->server->reload();
                $responseData['msg'] = '系统正在重启中';
                break;
            case '_shutdown':
                $this->server->shutdown();
                $responseData['msg'] = '系统正在关闭';
                break;
            default:
                break;
        }
        $responseData['sys_info'] = $server->stats();
        $server->send($fd, json_encode($responseData, JSON_UNESCAPED_UNICODE));
        $server->close($fd, true);
    }

    /**
     * @param Server $server
     * @param int $taskId
     * @param int $srcWorkerId
     * @param string $taskData
     * @return bool|null|string
     */
    public function onTask(Server $server, int $taskId, int $srcWorkerId, string $taskData)
    {

    }

    public function onFinish(Server $server, int $taskId, string $data)
    {
        Db::name('pressure_task')->where('id', $data)->update(['status' => '2']);
        echo 'finish...', PHP_EOL;
    }

    public function onWorkerStart(Server $server, int $workerId)
    {
        if (!$server->taskworker) {
            if ($workerId == 0) {
                    $redis = Fredis::instance();
                    $server->tick(1000, function ($id) use ($server,$redis) {
                        $length = $redis->scard('callback_order');
                        if ($length > 0) {
                            $order_id = $redis->spop('callback_order');
                            myLog($order_id.'订单回调任务开始');
                            $info = Db::name('order')->where(['order_id' => $order_id, 'status' => '0'])->find();
                            if (!$info) {
                                myLog('订单已回调或者不存在' . $order_id);
                            } else {
                                Db::startTrans();
                                try {
                                    $result = [];
                                    $memberId = $info['member_id'];
                                    $merchantId = $info['merchant_id'];
                                    $memberpid = Db::name('members')->where('id', $memberId)->value('pid');
                                    $merhcantpid = Db::name('merchants')->where('id', $merchantId)->value('pid');
                                    $merchant_introduce_reward = $info['merchant_introduce_reward'];
                                    $merchant_trade_charge = $info['merchant_trade_charge'];
                                    $member_trade_reward = $info['member_trade_reward'];
                                    $member_introduce_reward = $info['member_introduce_reward'];
                                    $result['1'] = Db::name('order')->where('order_id', $order_id)->setField('status', '1');
                                    $result['2'] = Db::name('order')->where('order_id', $order_id)->setField('pay_time', date("Y-m-d H:i:s", time()));
                                    $merchantFund = Db::name('merchant_balance')->where('merchant_id', $merchantId)->find();
                                    $memberFund = Db::name('member_balance')->where('member_id', $memberId)->find();
                                    //商户资金变动表
                                    $result['3'] = Db::name('merchant_balance_change')->insert([
                                        'merchant_id' => $merchantId,
                                        'order_id' => $order_id,
                                        'trade_type' => '1',
                                        'change_type' => '1',
                                        'change_way' => '1',
                                        'before_amount' => $merchantFund['avaliable_amount'] + $merchantFund['disabled_amount'],
                                        'amount' => $info['amount'],
                                        'charge' => $merchant_trade_charge,
                                        'after_amount' => $merchantFund['avaliable_amount'] + $merchantFund['disabled_amount'] + $info['amount'] - $merchant_trade_charge,
                                        'remark' => '收款码交易'
                                    ]);
                                    //商户推荐返佣
                                    if ($merhcantpid) {
                                        $merchantPfund = Db::name('merchant_balance')->where('merchant_id', $merhcantpid)->find();
                                        $result['4'] = Db::name('merchant_balance_change')->insert([
                                            'merchant_id' => $merhcantpid,
                                            'order_id' => $order_id,
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
                                        if ($merchant_introduce_reward > 0) {
                                            $result['5'] = Db::name('merchant_balance')->where('merchant_id', $merhcantpid)->setInc('profit_amount', $merchant_introduce_reward);
                                        }
                                    }
                                    //码商资金变动表
                                    $result['6'] = Db::name('member_balance_change')->insert([
                                        'member_id' => $memberId,
                                        'order_id' => $order_id,
                                        'charge' => 0,
                                        'change_type' => '1',
                                        'change_way' => '-1',
                                        'before_amount' => $memberFund['avaliable_amount'] + $memberFund['disabled_amount'],
                                        'amount' => $info['amount'],
                                        'after_amount' => $memberFund['avaliable_amount'] + $memberFund['disabled_amount'] - $info['amount'],
                                        'trade_type' => '1',
                                        'remark' => '收款码交易'
                                    ]);
                                    //码商交易返佣
                                    $result['7'] = Db::name('member_balance_change')->insert([
                                        'member_id' => $memberId,
                                        'order_id' => $order_id,
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
                                            'order_id' => $order_id,
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
                                        if ($member_introduce_reward > 0) {
                                            $result['9'] = Db::name('member_balance')->where('member_id', $memberpid)->setInc('profit_amount', $member_introduce_reward);
                                        }
                                    }
                                    //商户交易结算
                                    $result['10'] = Db::name('merchant_balance')->where('merchant_id', $merchantId)->setInc('avaliable_amount', $info['amount'] - $merchant_trade_charge);
                                    //码商交易结算
                                    if ($memberFund['disabled_amount'] < $info['amount']) {
                                        myLog('冻结金额异常数据,订单信息:' . json_encode($info));
                                    }
                                    $result['11'] = Db::name('member_balance')->where('member_id', $memberId)->setDec('disabled_amount', $info['amount']);
                                    //码商交易返佣
                                    if ($member_trade_reward > 0) {
                                        $result['12'] = Db::name('member_balance')->where('member_id', $memberId)->setInc('profit_amount', $member_trade_reward);
                                    }
                                    $flag = true;
                                    foreach ($result as $k => $v) {
                                        if (!$v) {
                                            $flag = false;
                                            myLog('动态码数据库更新异常：操作编码:' . $k . ';订单信息:' . $order_id);
                                        }
                                    }
                                    if ($flag) {
                                        $merchant_amount_key = 'merchant_id_' . $merchantId . '_day_' . date("Y-m-d") . '_success_amount';
                                        $merchant_count_key = 'merchant_id_' . $merchantId . '_day_' . date("Y-m-d") . '_success_count';
                                        $dayMerchantAmount = $redis->get($merchant_amount_key);
                                        $dayMerchantCount = $redis->get($merchant_count_key);
                                        $redis->set($merchant_amount_key, intval($dayMerchantAmount) + $info['amount'],60*60*24);
                                        $redis->set($merchant_count_key, intval($dayMerchantCount) + 1,60*60*24);
                                        $member_amount_key = 'member_id_' . $memberId . '_day_' . date("Y-m-d") . '_success_amount';
                                        $member_count_key = 'member_id_' . $memberId . '_day_' . date("Y-m-d") . '_success_count';
                                        $dayMemberAmount = $redis->get($member_amount_key);
                                        $dayMemberCount = $redis->get($member_count_key);
                                        $redis->set($member_amount_key, intval($dayMemberAmount) + $info['amount'],60*60*24);
                                        $redis->set($member_count_key, intval($dayMemberCount) + 1,60*60*24);
                                        $dayAmountKey = 'day_' . date("Y-m-d") . '_success_amount';
                                        $dayAmount = $redis->get($dayAmountKey);
                                        $redis->set($dayAmountKey, $dayAmount + $info['amount'], 60*60*24);
                                        $dayCountKey = 'day_' . date("Y-m-d") . '_success_count';
                                        $dayCount = $redis->get($dayCountKey);
                                        $redis->set($dayCountKey, $dayCount + 1, 60*60*24);
                                        Db::commit();
                                        $res = $redis->rpush('notify_task', $info['order_id']);
                                        if($res) {
                                            myLog('通知任务'.$info['order_id'].'推入成功'.date("Y-m-d H:i:s",time()));
                                        }
                                    }
                                } catch (\Exception $e) {
                                    $redis->sadd('callback_order', $order_id);
                                    myLog('动态码回调异常:' . $e->getMessage() . $order_id);
                                    Db::rollback();
                                }
                            };
                        }
                    });
            } elseif ($workerId == 1) {
                //支付通知
                $redis = Fredis::instance();
                $server->tick(1000, function ($id) use($server, $redis){
                    $length = $redis->llen('notify_task');
                    if ($length > 0) {
                        //发送异步通知
                        $v = $redis->lpop('notify_task');
                        while ($v) {
                            $arr = explode('_', $v,2);
                            $time = isset($arr[1]) ? $arr[1] : '';

                            if ($time && intval(time()) < intval($time)) {
                                $redis->rpush('notify_task', $v);
                                break;
                            }
                            $v = $arr[0];
                            Db::startTrans();
                            try {
                                $orderInfo = Db::name('order')->where(['order_id' => $v, 'status' => ['in', '1,3']])->find();
                                if ($orderInfo) {
                                    $notify_url = $orderInfo['notify_url'];
                                    if ($notify_url) {
                                        $params = [
                                            'code' => 200,
                                            'message' => 'SUCCESS',
                                            'order_id' => $orderInfo['order_id'],
                                            'pay_id' => $orderInfo['pay_id'],
                                            'product_id' => $orderInfo['product_id'],
                                            'merchant_id' => $orderInfo['merchant_id'],
                                            'amount' => $orderInfo['amount'],
                                            'pay_time' => $orderInfo['pay_time'],
                                        ];
                                        if ($orderInfo['attach']) {
                                            $params['attach'] = $orderInfo['attach'];
                                        }
                                        $apiKey = Db::name('apikey')->where([
                                            'uid' => $orderInfo['merchant_id'],
                                            'user_type' => 'merchant',
                                        ])->value('apiKey');
                                        $sign = getSign($params, $apiKey);
                                        $params['sign'] = $sign;
                                        $res = doCurl($notify_url, $params, 1);
                                        $result = json_decode($res, true);
                                        if (isset($result['code']) && isset($result['msg']) && $result['code'] == '200' && $result['msg'] == 'success') {
                                            Db::name('order')->where('id', $orderInfo['id'])->setField('status', '2');
                                        } else {
                                            if ($orderInfo['notify_failure_times'] <= 9) {
                                                Db::name('order')->where('id', $orderInfo['id'])->setInc('notify_failure_times', 1);
                                                Db::name('order')->where('id', $orderInfo['id'])->setField('status', '3');
                                                $redis->rpush('notify_task', $orderInfo['order_id'] . '_' . (time() + Config('notify_frequency')[$orderInfo['notify_failure_times']]));
                                            } else {
                                                Db::name('order')->where('id', $orderInfo['id'])->setField('status', '-3');
                                            }
                                        }
                                    }
                                } else {
                                    $realInfo = Db::name('order')->where('order_id', $v)->find();
                                    myLog('---------------异步通知订单状态异常:'.$v.',时间'.date("Y-m-d H:i:s",time()).'订单信息:'.json_encode($realInfo).'-----------------');
                                }
                                $v = $redis->lpop('notify_task');
                                Db::commit();
                            } catch (\Exception $e) {
                                $redis->rpush('notify_task', $v);
                                myLog('支付通知执行异常：' . $e->getMessage());
                                Db::rollback();
                            }
                        }
                    }
                });
            } elseif ($workerId == 2) {
                //提现通知
                $redis = Fredis::instance();
                $server->tick(1000, function ($id) use($redis) {
                    $tasks = $redis->lranges('withdraw_notify_task', 0,5);
                    if (!empty($tasks)) {
                        $v = $redis->lpop('withdraw_notify_task');
                        while ($v > 0) {
                            $info = Db::name('merchant_withdraw_order')->where(['id' => $v, 'notify_status' => '0', 'status' => ['in', '2,4,-1']])->find();
                            if ($info) {
                                $id = $info['id'];
                                $times = $info['notify_failure_times'];
                                $url = $info['notify_url'];
                                if ($info['status'] == '2' || $info['status'] == '4') {
                                    $data['code'] = '200';
                                    $data['message'] = 'SUCCESS';
                                    $data['order_id'] = $info['order_id'];
                                    $data['merchant_id'] = $info['merchant_id'];
                                    $data['amount'] = $info['amount'];
                                    $data['real_amount'] = $info['real_amount'];
                                    $data['charge'] = $info['charge'];
                                    $data['create_time'] = $info['create_time'];
                                    $data['update_time'] = $info['update_time'];
                                } else {
                                    $data['code'] = '400';
                                    $data['message'] = 'Failure';
                                    $data['order_id'] = $info['order_id'];
                                    $data['merchant_id'] = $info['merchant_id'];
                                    $data['amount'] = $info['amount'];
                                    $data['create_time'] = $info['create_time'];
                                    $data['update_time'] = $info['update_time'];
                                }
                                $apiKey = Db::name('apikey')->where(['uid' => $info['merchant_id'],'user_type' => 'merchant'])->value('apikey');
                                $data['sign'] = getSign($data, $apiKey);
                                $result = json_decode(doCurl($url,$data,1), true);
                                if ($result['code'] == '200' && $result['msg'] == 'success') {
                                    Db::name('merchant_withdraw_order')->where('id', $id)->setField('notify_status', '1');
                                } else {
                                    if ($times <= 5) {
                                        $redis->rpush('withdraw_notify_task', $id);
                                        Db::name('merchant_withdraw_order')->where('id', $id)->setInc('notify_failure_times', 1);
                                    } else {
                                        Db::name('merchant_withdraw_order')->where('id', $id)->setField('notify_status', '-1');
                                    }
                                }
                            }
                            $v = $redis->lpop('withdraw_notify_task');
                        }
                    }
                });
            } elseif ($workerId == 3) {
                $redis = Fredis::instance();
                $server->tick(1000, function ($id) use ($redis) {
                    $info = Db::name('order')
                        ->where('status', '0')
                        ->order('create_time')
                        ->find();
                    if ($redis->lock('expire_' . $info['order_id'], 10)) {
                        if (time() > strtotime($info['expire_time'])) {
                            Db::startTrans();
                            try {
                                $res1 = Db::name('order')->where('order_id', $info['order_id'])->setField('status', '-1');
                                if ($res1) {
                                    $res2 = Db::name('member_balance')->where('member_id', $info['member_id'])->setInc('avaliable_amount', $info['amount']);
                                    if ($res2) {
                                        $res3 = Db::name('member_balance')->where('member_id', $info['member_id'])->setDec('disabled_amount', $info['amount']);
                                        if ($res3) {
                                            Db::commit();
                                        }
                                    }
                                }
                                Db::rollback();
                            } catch (\Exception $e) {
                                myLog('订单过期任务异常,错误信息:' . $e->getMessage());
                                Db::rollback();
                            }
                        }
                    }
                    $redis->delete('expire_' . $info['order_id']);
                });
            }
        }
    }

    public function onConnect()
    {
        echo 'connect...', PHP_EOL;
    }

    public function onClose()
    {
        echo 'close...', PHP_EOL;
    }

    public function run()
    {
        echo "\n------------server is listening on $this->host:$this->port-------\n";
        $server = new Server($this->host, $this->port);
        $server->set($this->config);
        $server->on('WorkerStart', [$this, 'onWorkerStart']);
        $server->on('receive', [$this, 'onReceive']);
        $server->on('connect', [$this, 'onConnect']);
        $server->on('task', [$this, 'onTask']);
        $server->on('close', [$this, 'onClose']);
        $server->on('finish', [$this, 'onFinish']);
        $this->server = $server;
        $this->server->start();
    }

}