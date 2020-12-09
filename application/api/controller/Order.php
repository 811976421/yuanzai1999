<?php
namespace app\api\controller;

use think\Db;

class Order extends Common {

    protected $merchant_trade_charge;
    protected $merchant_introduce_reward;
    protected $member_trade_reward;
    protected $member_introduce_reward;
    protected $order_id;
    protected $member_id;
    protected $resource_id;
    protected $resource_type;
    protected $qrcode;
    protected $qrapi;
    protected $member_pid;
    protected $url;
    protected $sources;


    public function index() {
        set_time_limit(0);
        error_reporting(0);
        $this->order_id = create_order_no();
        //产品费率
        $info = Db::name('merchant_product')->where([
            'status' => '1',
            'merchant_id' => $this->merchant_id,
            'product_id' => $this->product_id
        ])->find();

        if (!$info) {
            myLog('该商户暂不支持此产品,merchant_id:'.$this->merchant_id.',product_id:'.$this->product_id);
            echo errReturn(405, '该商户暂不支持此产品', 1);
            die();
        }
        $this->merchant_trade_charge = $info['merchant_trade_charge'];
        $this->merchant_introduce_reward = $info['merchant_introduce_reward'];

        //风控
        $this->checkMerchantRisk();
        //获取指定资源
        $assign = Db::name('assign')->where(['merchant_id' => $this->merchant_id, 'product_id' => $this->product_id, 'status' => '1'])->find();
        $member_ids = [];
        if (!$assign) {
            //调取所有的码商
            switch ($this->product_type) {
                case '103':
                    $member_ids = Db::name('qrcode')->where(['product_id' => $this->product_id, 'status' => '1'])->group('member_id')->column('member_id');
                    break;
                case '104':
                    $member_ids = Db::name('qrapi')->where(['product_id' => $this->product_id, 'status' => '1'])->group('member_id')->column('member_id');
                    break;
                case '107':
                    $member_ids = Db::name('transfer_bank')->where(['product_id' => $this->product_id, 'status' => '1'])->group('member_id')->column('member_id');
                    break;
            }
        } else {
            $member_ids = explode(',', $assign['account_ids']);
        }

        if ($assign['status'] == '0') {
            myLog('指定禁用,merchant_id:'.$this->merchant_id);
            echo errReturn(409, '指定禁用', 1);
            die();
        }
        
        $this->getQr($member_ids, $this->product_id, $this->wish_amount);

        $this->createOrder();

        $resData = [
            'order_id' => $this->order_id,
            'merchant_id' => $this->merchant_id,
            'amount' => $this->amount,
            'qrcode' => $this->qrcode,
            'url'    => $this->url
        ];

        if ($this->product_id == '111') {
            unset($resData['qrcode']);
        }

        if ($this->product_id == '106') {
            $resData['scheme'] = 'alipayqr://platformapi/startapp?saId=10000007&qrcode=' . $this->qrcode;
        }

        echo sucReturn(200, 'success', $resData, 1);

        die();
    }

    /**
     * 商户下单
     */
    public function createOrder() {
        $redis = Fredis::instance();
        Db::startTrans();
        $merchant_introduce_reward = 0;
        $member_introduce_reward = 0;
        if ($this->merchant_pid) {
            $merchant_introduce_reward = floor($this->amount * $this->merchant_introduce_reward * 100)/100;
        }
        $merchant_trade_charge = ceil(($this->amount * $this->merchant_trade_charge) * 100)/100;
        $member_trade_reward = floor($this->amount * $this->member_trade_reward * 100)/100;
        if ($this->member_pid) {
            $member_introduce_reward = floor($this->amount * $this->member_introduce_reward * 100)/100;
        }
        $expireTime = date("Y-m-d H:i:s", time() + $this->expire * 60);
        if ($this->resource_type == '2') {
            $expireTime = date("Y-m-d H:i:s", time() + $this->expire * 60);
        }

        try {
            $result = [];
            $result[1] = Db::name('order')->insert([
                'order_id' => $this->order_id,
                'pay_id' => $this->payId,
                'product_id' => $this->product_id,
                'merchant_id' => $this->merchant_id,
                'resource_id' => $this->resource_id,
                'resource_type' => $this->resource_type,
                'member_id' => $this->member_id,
                'amount' => $this->amount,
                'merchant_introduce_reward' => $merchant_introduce_reward,
                'merchant_trade_charge' => $merchant_trade_charge,
                'member_trade_reward' => $member_trade_reward,
                'member_introduce_reward' => $member_introduce_reward,
                'expire_time' => $expireTime,
                'attach' => $this->attach,
                'status' => '0',
                'url' => $this->url,
                'notify_url' => $this->notify_url,
                'address' => $this->address,
            ]);

            //用户单日交易总额累加,交易取消,再减少

            do {
                if ($redis->lock('member_balance_lock_' . $this->member_id, 10)) {
                    $result[2] = Db::name('member_balance')->where('member_id', $this->member_id)->setDec('avaliable_amount', $this->amount);
                    $result[3] = Db::name('member_balance')->where('member_id', $this->member_id)->setInc('disabled_amount', $this->amount);
                    $isLock = true;
                    $redis->delete('member_balance_lock_' . $this->member_id);
                } else {
                    sleep(1);
                    $isLock = false;
                }
            } while (!$isLock);

            foreach ($result as $k => $v) {
                if (!$v) {
                    Db::rollback();
                    myLog('下单失败,操作编号:' . $k);
                    echo errReturn(400, 'failure', 1);
                    die();
                }
            }
            $dayAmountKey = 'day_' . date("Y-m-d") . '_sum_amount';
            $dayAmount = $redis->get($dayAmountKey);
            $redis->set($dayAmountKey, $dayAmount + $this->amount, 60*60*24);
            $dayCountKey = 'day_' . date("Y-m-d") . '_sum_count';
            $dayCount = $redis->get($dayCountKey);
            $redis->set($dayCountKey, $dayCount + 1, 60*60*24);

            $memberDayAmount = $redis->get('member_id_' . $this->member_id . '_day_' . date("Y-m-d") . '_sum_amount');
            $memberDayCount = $redis->get('member_id_' . $this->member_id . '_day_' . date("Y-m-d") . '_sum_count');
            $redis->set('member_id_' . $this->member_id . '_day_' . date("Y-m-d") . '_sum_amount', intval($memberDayAmount) + $this->amount, 60*60*24);
            $redis->set('member_id_' . $this->member_id . '_day_' . date("Y-m-d") . '_sum_count', intval($memberDayCount) + 1,60*60*24);
            $merchantDayAmount = $redis->get('merchant_id_' . $this->merchant_id . '_day_' . date("Y-m-d") . '_sum_amount');
            $merchantDayCount = $redis->get('merchant_id_' . $this->merchant_id . '_day_' . date("Y-m-d") . '_sum_count');
            $redis->set('merchant_id_' . $this->merchant_id . '_day_' . date("Y-m-d") . '_sum_amount', intval($merchantDayAmount) + $this->amount, 60*60*24);
            $redis->set('merchant_id_' . $this->merchant_id . '_day_' . date("Y-m-d") . '_sum_count', intval($merchantDayCount) + 1,60*60*24);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            myLog('下单异常:' . $e->getMessage());
            echo errReturn(411, $e->getMessage(), 1);
            die();
        }
    }

    public function getQr($memberIds, $productId, $amount) {
        if ((!is_array($memberIds) || count($memberIds) == 0)) {
            myLog('无可用资源,merchant_id:' . $this->merchant_id . ';amount:' . $this->amount);
            echo errReturn(410, '无可用资源', 1);
            die();
//            if (!is_array($this->sources) || count($this->sources) == 0) {
//                echo errReturn(410, '无可用资源', 1);
//                die();
//            } else {
//                array_multisort(array_column($this->sources,'diff'),SORT_ASC, $this->sources);
//                $this->resource_id = $this->sources[0]['resource_id'];
//                $this->resource_type = $this->sources[0]['resource_type'];
//                $this->member_id = $this->sources[0]['resource_id'];
//                $this->amount = $this->sources[0]['amount'];
//                $this->url = $this->sources[0]['url'];
//                $this->member_trade_reward = $this->sources[0]['member_trade_reward'];
//                $this->member_introduce_reward = $this->sources[0]['member_introduce_reward'];
//                $this->member_pid = $this->sources[0]['member_pid'];
//                return;
//            }
        }

        $rand_key = array_rand($memberIds, 1);
        $memberId = $memberIds[$rand_key];
        unset($memberIds[$rand_key]);
        //校验码商
        $where['m.id'] = $memberId;
        $where['m.role_id'] = Config('roles')['member'];
        $where['m.status'] = '1';
        $where['p.status'] = '1';
        $where['p.product_id'] = $productId;
        $memberInfo = Db::name('members')
            ->alias('m')
            ->join('center_member_product p', 'p.member_id = m.id')
            ->where($where)->field('p.member_trade_reward,p.member_introduce_reward,m.pid')
            ->find();

        if (!$memberInfo) {
            myLog('码商条件不满足' . $memberId);
            $this->getQr($memberIds, $productId, $amount);
        } else {
            //获取二维码地址
            $qrApi = '';
            $qrInfo = '';
            $banks = '';
            switch ($this->product_type) {
                case '103':
                    if ($this->interval > 0) {
                        $resourceIds = Db::name('order')
                            ->where(['member_id' => $memberId, 'amount' => $amount, 'status' => '0', 'product_id' => $this->product_id])
                            ->whereTime('create_time','-' . $this->interval . ' minutes')
                            ->column('resource_id');
                        $qrInfo = Db::name('qrcode')
                            ->where([
                                'member_id' => $memberId,
                                'status' => '1',
                                'product_id' => $productId,
                                'id' => ['not in', $resourceIds]
                            ])
                            ->column('id,qrcode');
                    } else {
                        $qrInfo = Db::name('qrcode')
                            ->where([
                                'member_id' => $memberId,
                                'status' => '1',
                                'product_id' => $productId
                            ])
                            ->column('id,qrcode');
                    }
                    $this->resource_type = '1';
                    $this->resource_id = array_rand($qrInfo);
                    $this->qrcode = $qrInfo[$this->resource_id];
                    break;
                case '104':
                    $this->resource_type = '2';
                    $qrApi = Db::name('qrapi')->where([
                        'member_id' => $memberId,
                        'status' => '1',
                        'product_id' => $productId
                    ])->column('id,api');
                    $this->resource_id = array_rand($qrApi);
                    $this->qrapi = $qrApi[$this->resource_id];
                    break;
                case '107':
                    $this->resource_type = '3';
                    $banks = Db::name('transfer_bank')
                        ->where([
                            'member_id' => $memberId,
                            'status' => '1',
                            'product_id' => $productId
                        ])->column('id,card_no');
                    $this->resource_id = $this->getBank($banks);
                    if ($this->resource_id == null) {
                        $this->getQr($memberIds, $productId, $amount);
                    }
                    break;
            }

            if (!$qrInfo && !$qrApi && !$banks) {
                myLog('该码商没有资源,member_id:' . $memberId);
                $this->getQr($memberIds, $productId, $amount);
            }

            $res = $this->checkMemberRisk($memberId);

            if (!$res) {
                $this->getQr($memberIds, $productId, $amount);
            } else {
                if ($this->resource_type != 3) {
                    if ($this->amount != $this->wish_amount) {
                        $this->sources[] = [
                            'resource_id' => $this->resource_id,
                            'resource_type' => $this->resource_type,
                            'member_id' => $memberId,
                            'amount' => $this->amount,
                            'url' => $this->url,
                            'member_trade_reward' => $memberInfo['member_trade_reward'],
                            'member_introduce_reward' => $memberInfo['member_introduce_reward'],
                            'member_pid' => $memberInfo['pid'],
                            'diff' => abs($this->wish_amount - $this->amount)
                        ];
                        $this->getQr($memberIds, $this->product_id, $this->wish_amount);
                    } else {
                        //码商费率
                        $this->member_trade_reward = $memberInfo['member_trade_reward'];
                        $this->member_introduce_reward = $memberInfo['member_introduce_reward'];
                        $this->member_id = $memberId;
                        $this->member_pid = $memberInfo['pid'];
                    }
                } else {
                    //码商费率
                    $this->member_trade_reward = $memberInfo['member_trade_reward'];
                    $this->member_introduce_reward = $memberInfo['member_introduce_reward'];
                    $this->member_id = $memberId;
                    $this->member_pid = $memberInfo['pid'];
                }
            }
        }
    }

    public function checkMerchantRisk() {
        try {
            $info = Db::name('merchant_riskcontrol')->where([
                'merchant_id' => $this->merchant_id,
                'product_id' => $this->product_id,
                'status' => '1',
            ])->find();

            if (!$info) {
                return true;
            }

            $timeNow = time();
            if ($timeNow < strtotime($info['start_time']) || $timeNow > strtotime($info['end_time'])) {
                myLog('该时段不可交易,merchant_id:' . $this->merchant_id);
                echo errReturn(412, '该时段不可交易', 1);
                die();
            }

            if ($this->amount < $info['unit_min_amount']) {
                myLog('单笔最小交易金额' . $info['unit_min_amount'] . ',merchant_id:' . $this->merchant_id);
                echo errReturn(406, '单笔最小交易金额' . $info['unit_min_amount'], 1);
                die();
            }
            if ($this->amount > $info['unit_max_amount']) {
                myLog('单笔最大交易金额' . $info['unit_max_amount'] . ',merchant_id:' . $this->merchant_id);
                echo errReturn(407, '单笔最大交易金额' . $info['unit_max_amount'], 1);
                die();
            }

            $redis = Fredis::instance();
            $key = 'merchant_id_' . $this->merchant_id . '_day_' . date("Y-m-d") . '_success_amount';
            $dayAmount = $redis->get($key);

            if ((intval($dayAmount) + $this->amount) > $info['day_max_amount']) {
                myLog('超过当日最高限额,merchant_id' . $this->merchant_id);
                echo errReturn(408, '超过当日最高限额', 1);
                die();
            }

        } catch (\Exception $e) {
            myLog('商户风控异常' . $e->getMessage() . ',merchant_id:' . $this->merchant_id);
            echo errReturn(400, $e->getMessage(), 1);
            die();
        }

    }

    public function checkMemberRisk($memberId) {

        if ($this->resource_type == '1') {
            $this->url = request()->domain() . '/index/cashier/index?order_id=' . $this->order_id;
        }

        if ($this->resource_type == '2') {
            $apiInfo = Db::name('qrapi')->where('id', $this->resource_id)->field('api_assign,email')->find();
            $res = doCurl($this->qrapi . '/getproduct.php?amount='. $this->amount . '&mid=' . $apiInfo['api_assign']);
            $this->email = $apiInfo['email'];
            $result = json_decode($res, true);

            if (isset($result['code'])) {
                if ($result['code'] == 200) {
                    $this->amount = $result['amount'];
                    if ($this->amount <= 0) {
                        echo errReturn(408, 'error:联系管理员调整产品价格,当前价格为' . $this->amount, 1);
                        die();
                    }
                    if ($this->product_id == '110') {
                        $this->url = $this->qrapi  . '/nativePay.php?pid=' . $result['pid'] . '&genkey=yzf' . $this->order_id . '&email=' . $this->email;
                    } else {
                        if ($this->redirect) {
                            $this->url = $this->qrapi  . '/pay.php?pid=' . $result['pid'] . '&genkey=yzf' . $this->order_id . '&email=' . $this->email . '&redirect=' . urlencode($this->redirect);
                        } else {
                            $this->url = $this->qrapi  . '/pay.php?pid=' . $result['pid'] . '&genkey=yzf' . $this->order_id . '&email=' . $this->email;
                        }
                    }
                } else {
                    myLog($res);
                    return false;
                }
            } else {
                myLog('动态码获取对应价格商品失败,请求地址：' . $this->qrapi . '/getproduct.php?amount='. $this->amount . '&mid=' . $apiInfo['api_assign']);
                return false;
            }
        }

        if ($this->resource_type == '3') {
            $amounts = Db::name('order')->whereTime('create_time', '-' . $this->expire . ' minutes')->column('amount');
            $this->amount = $this->getAmounts($this->amount, $amounts);
            if ($this->amount == null) {
                myLog('无可用价格');
                return false;
            }
            $this->url = request()->domain() . '/index/cashier/bank?order_id=' . $this->order_id;
        }

        $availableAmount = Db::name('member_balance')->where('member_id', $memberId)->value('avaliable_amount');

        if ($availableAmount < $this->amount) {
            myLog('码商余额不足,风控没过,member_id:' . $memberId);
            return false;
        }

        $info = Db::name('member_riskcontrol')->where([
            'member_id' => $memberId,
            'product_id' => $this->product_id,
            'status' => 1,
        ])->find();

        if (!$info) {
            return true;
        }

        $timeNow = time();

        if ($timeNow < strtotime($info['start_time']) || $timeNow > strtotime($info['end_time'])) {
            myLog('不在交易期,风控没过,member_id:'.$memberId);
            return false;
        }

        if ($this->amount < $info['unit_min_amount']) {
            myLog('价格低于风控单次最低,风控没过,member_id:'.$memberId);
            return false;
        }

        if ($this->amount > $info['unit_max_amount']) {
            myLog('价格高于风控单次最高,风控没过,member_id:'.$memberId);
            return false;
        }
        
        $lastOrderTime = Db::name('order')->where('member_id', $memberId)->order('create_time desc')->value('create_time');

        $tradeInterval = rand($info['trade_interval_min'], $info['trade_interval_max']);

        if ((time() - strtotime($lastOrderTime)) < $tradeInterval) {
            myLog('交易间隔没过,时间差:'.(time() - strtotime($lastOrderTime)).',风控没过,member_id:'.$memberId);
            return false;
        }

        $redis = Fredis::instance();
        $key = 'member_id_' . $memberId . '_day_' . date("Y-m-d") . '_success_amount';
        $dayAmount = $redis->get($key);

        if ((intval($dayAmount) + $this->amount) > $info['day_max_amount']) {
            myLog('达到当日最高限额,风控没过,member_id:'.$memberId);
            return false;
        }

        return true;
    }

    public function getBank($banks) {
        if ((!is_array($banks) || count($banks) == 0)) {
            return null;
        }

        $id = array_rand($banks, 1);
        unset($banks[$id]);
        $info = Db::name('bankcard_risk')->where(['bankId' => $id, 'status' => '1'])->find();
        if (!$info) {
            return $id;
        }

        $timeNow = time();

        if ($timeNow < strtotime($info['begin_time']) || $timeNow > strtotime($info['end_time'])) {
            $this->getBank($banks);
        }

        if ($info['unit_min_amount'] > $this->amount || $info['unit_max_amount'] < $this->amount) {
            $this->getBank($banks);
        }

        $redis = Fredis::instance();
        $todayAmount = $redis->get('member_id_' . $this->member_id . '_bankcard_' . $id);
        if ($info['day_max_amount'] > ($this->amount + intval($todayAmount))) {
            $this->getBank($banks);
        }

        $lastOrderTime = Db::name('order')->where(['resource_type' => '3', 'resource_id' => $id])->order('create_time desc')->value('create_time');

        if ((time() - strtotime($lastOrderTime)) <= $info['interval_time'] * 60) {
            $this->getBank($banks);
        }

        return $id;
    }

    public function getAmounts($wishAmounts, $amounts) {
        foreach (Config('volatility') as $v) {
            $amount = $wishAmounts + $v;
            if (in_array($amount, $amounts)) {
                continue;
            } else {
                return $amount;
            }
        }
    }

}
