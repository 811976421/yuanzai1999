<?php
namespace app\index\common;

use app\api\controller\Fredis;
use Swoole\WebSocket\Server;
use think\Db;
use think\Request;

class Ws {

    public $server;
    public $port;
    public $host;
    public $config;
    public $key = 'easy_pay$wss';

    public function __construct(array $config, $host, $port)
    {
        ini_set('display_errors','no');
        error_reporting(-1);
        $this->config = array_merge(['heartbeat_check_interval' => 60, 'heartbeat_idle_time' => 600], $config);
        $this->host = $host;
        $this->port = $port;
    }

    public function onOpen(Server $server, $request) {
        $this->checkAccess($server, $request);
    }

    public function checkAccess($server, $request)
    {
        // get不存在或者uid和token有一项不存在，关闭当前连接
        if (!isset($request->get) || !isset($request->get['uid']) || !isset($request->get['token'])) {
            $this->server->close($request->fd);
            return false;
        }
        $uid = $request->get['uid'];
        $token = $request->get['token'];
        // 校验token是否正确,无效关闭连接
        if (md5(md5($uid) . $this->key) != $token) {
            $this->server->close($request->fd);
            return false;
        }
    }

    public function onMessage(Server $server, $frame) {
        if ($server->isEstablished($frame->fd)) {
            $info = $frame->data;
            $info = json_decode($info, true);
            $sendData = [];
            $redis = Fredis::instance();
            switch ($info['target']) {
                case 'home':
                    $data = explode("_", $info['data']);
                    switch ($data['0']) {
                        case Config('roles')['admin']:
                            $sendData['role'] = 'admin';
                            $sendData['todaySumAmount'] = $redis->get('day_' . date("Y-m-d") . '_sum_amount');
                            $sendData['todaySumCount'] = $redis->get('day_' . date("Y-m-d") . '_sum_count');
                            $sendData['todaySuccessCount'] = $redis->get('day_' . date("Y-m-d") . '_success_count');
                            $sendData['todaySuccessAmount'] = $redis->get('day_' . date("Y-m-d") . '_success_amount');
                            $sendData['waitRecharge'] = Db::name('member_recharge_order')->where(['status' => '1', 'mark1' => '1'])->count();
                            $waitMemberWithdraw = Db::name('member_withdraw_order')->where('status', '1')->count();
                            $waitMerchantWithdraw = Db::name('merchant_withdraw_order')->where('status', '1')->count();
                            $sendData['waitWithdraw'] = $waitMemberWithdraw + $waitMerchantWithdraw;
                            $waitMemberCommission = Db::name('member_commission_order')->where('status', '1')->count();
                            $waitMerchantCommission = Db::name('merchant_commission_order')->where('status', '1')->count();
                            $sendData['waitCommission'] = $waitMemberCommission + $waitMerchantCommission;
                            $sendData['todayProfit'] = Db::name('member_balance_change')
                                ->where(['change_type' => '2', 'change_way' => '1'])
                                ->whereTime('create_time', 'today')
                                ->sum('amount');
                            $sendData['todayProfit'] += Db::name('merchant_balance_change')
                                ->where(['change_type' => '2', 'change_way' => '1'])
                                ->whereTime('create_time', 'today')
                                ->sum('amount');
                            $warning = Db::name('merchant_withdraw_order')
                                ->where('status', '1')
                                ->where('create_time', '<= time', time() - Config('withdraw_alarm') * 60)
                                ->find();
                            if ($warning) {
                                $sendData['time_out'] = 1;
                            }
                            $sendData['taskNum'] = $redis->llen('notify_task');

                            $risks = Db::name('risk_alarm')
                                ->where('is_alarm', '0')
                                ->whereTime('create_time', '-24 hours')
                                ->select();

                            foreach ($risks as $k => $v) {
                                $remark = '';
                                $memberName = Db::name('members')->where('id', $v['member_id'])->value('username');
                                if ($v['source_type'] == '2') {
                                    $remark = Db::name('qrapi')->where('id', $v['source_id'])->value('remark');
                                }
                                $sendData['alarms'][] = '码商:'.$memberName.',资源:'.$remark.','.$v['period'].'分钟内未支付笔数:'.$v['times'].',时间:'.$v['create_time'];
                            }
                            break;
                        case Config('roles')['admin2']:
                            $sendData['role'] = 'admin';
                            $sendData['todaySumAmount'] = $redis->get('day_' . date("Y-m-d") . '_sum_amount');
                            $sendData['todaySumCount'] = $redis->get('day_' . date("Y-m-d") . '_sum_count');
                            $sendData['todaySuccessCount'] = $redis->get('day_' . date("Y-m-d") . '_success_count');
                            $sendData['todaySuccessAmount'] = $redis->get('day_' . date("Y-m-d") . '_success_amount');
                            $sendData['waitRecharge'] = Db::name('member_recharge_order')->where(['status' => '1', 'mark1' => '1'])->count();
                            $waitMemberWithdraw = Db::name('member_withdraw_order')->where('status', '1')->count();
                            $waitMerchantWithdraw = Db::name('merchant_withdraw_order')->where('status', '1')->count();
                            $sendData['waitWithdraw'] = $waitMemberWithdraw + $waitMerchantWithdraw;
                            $waitMemberCommission = Db::name('member_commission_order')->where('status', '1')->count();
                            $waitMerchantCommission = Db::name('merchant_commission_order')->where('status', '1')->count();
                            $sendData['waitCommission'] = $waitMemberCommission + $waitMerchantCommission;
                            $sendData['todayProfit'] = Db::name('member_balance_change')
                                ->where(['change_type' => '2', 'change_way' => '1'])
                                ->whereTime('create_time', 'today')
                                ->sum('amount');
                            $sendData['todayProfit'] += Db::name('merchant_balance_change')
                                ->where(['change_type' => '2', 'change_way' => '1'])
                                ->whereTime('create_time', 'today')
                                ->sum('amount');
                            $warning = Db::name('merchant_withdraw_order')
                                ->where('status', '1')
                                ->where('create_time', '<= time', time() - Config('withdraw_alarm') * 60)
                                ->find();
                            if ($warning) {
                                $sendData['time_out'] = 1;
                            }
                            $sendData['taskNum'] = $redis->llen('notify_task');

                            $risks = Db::name('risk_alarm')
                                ->where('is_alarm', '0')
                                ->whereTime('create_time', '-24 hours')
                                ->select();

                            foreach ($risks as $k => $v) {
                                $remark = '';
                                $memberName = Db::name('members')->where('id', $v['member_id'])->value('username');
                                if ($v['source_type'] == '2') {
                                    $remark = Db::name('qrapi')->where('id', $v['source_id'])->value('remark');
                                }
                                $sendData['alarms'][] = '码商:'.$memberName.',资源:'.$remark.','.$v['period'].'分钟内未支付笔数:'.$v['times'];
                            }
                            break;
                        case Config('roles')['member']:
                            $sendData['role'] = 'member';
                            $sendData['todaySumCount'] = $redis->get('member_id_' . $data['1'] . '_day_' . date("Y-m-d") . '_sum_count');
                            $sendData['todaySumAmount'] = $redis->get('member_id_' . $data['1'] . '_day_' . date("Y-m-d") . '_sum_amount');

                            $sendData['todaySuccessCount'] = $redis->get('member_id_' . $data['1'] . '_day_' . date("Y-m-d") . '_success_count');
                            $sendData['todaySuccessAmount'] = $redis->get('member_id_' . $data['1'] . '_day_' . date("Y-m-d") . '_success_amount');

                            $sendData['todayProfit'] = Db::name('member_balance_change')
                                ->where('member_id', $data['1'])
                                ->where(['change_type' => '2', 'change_way' => '1'])
                                ->whereTime('create_time', 'today')
                                ->sum('amount');

                            $sendData['waitWithdraw'] = Db::name('member_withdraw_order')->where(['member_id' => $data['1'], 'status' => '4'])->count();
                            $sendData['waitSystemRecharge'] = Db::name('member_recharge_order')->where(['member_id' => $data['1'], 'status' => '0', 'mark1' => '1'])->count();
                            $sendData['waitReceiveRecharge'] = Db::name('member_recharge_order')->where(['member_id' => $data['1'], 'status' => '0', 'mark1' => '2'])->count();
                            $fund = Db::name('member_balance')->where('member_id', $data['1'])->find();
                            $sendData['freezeAmount'] = $fund['disabled_amount'];
                            $sendData['available_money'] = $fund['avaliable_amount'];
                            break;
                        case Config('roles')['merchant']:
                            $sendData['role'] = 'merchant';
                            $sendData['todaySumAmount'] = $redis->get('merchant_id_' . $data['1'] . '_day_' . date("Y-m-d") . '_sum_amount');
                            $sendData['todaySumCount'] = $redis->get('merchant_id_' . $data['1'] . '_day_' . date("Y-m-d") . '_sum_count');
                            $sendData['todaySuccessAmount'] = $redis->get('merchant_id_' . $data['1'] . '_day_' . date("Y-m-d") . '_success_amount');
                            $sendData['todaySuccessCount'] = $redis->get('merchant_id_' . $data['1'] . '_day_' . date("Y-m-d") . '_success_count');
                            $sendData['todayProfit'] = Db::name('merchant_balance_change')
                                ->where('merchant_id', $data['1'])
                                ->where(['change_type' => '2', 'change_way' => '1'])
                                ->whereTime('create_time', 'today')
                                ->sum('amount');
                            $fund = Db::name('merchant_balance')->where('merchant_id', $data['1'])->find();
                            $sendData['unpaidOrder'] = Db::name('order')->where(['merchant_id' => $data['1'], 'status' => '0'])->count();
                            $sendData['waitMerchantWithdraw'] = Db::name('merchant_withdraw_order')->where(['merchant_id' => $data['1'], 'status' => '4'])->count();
                            $warning = Db::name('merchant_withdraw_order')
                                ->where(['merchant_id' => $data['1'], 'status' => '4'])
                                ->where('update_time', '<= time', time() - Config('withdraw_alarm') * 60)
                                ->find();
                            if ($warning) {
                                $sendData['time_out'] = 1;
                            }
                            $sendData['freezeAmount'] = $fund['disabled_amount'];
                            $sendData['available_money'] = $fund['avaliable_amount'];
                            break;
                    }
                    break;
//                case 'order':
//                    $data = explode("_", $info['data']);
//                    $lastOrderId = $data['2'];
//                    $url = $data['5'];
//                    $where = json_decode($data['6']);
//                    if (strpos($url,'?') !== false) {
//                        $url = $url . '&' . http_build_query([
//                                'page' => $data['3'],
//                                'limit' => 20,
//                                'roleId' => $data['0'],
//                                'userId' => $data['1']
//                            ]) . '&' . http_build_query($where);
//                    } else {
//                        $url = $url . '?' . http_build_query([
//                                'page' => $data['3'],
//                                'limit' => 20,
//                                'roleId' => $data['0'],
//                                'userId' => $data['1']
//                            ]). '&' . http_build_query($where);
//                    }
//                    $res = doCurl($url);
//                    $list = json_decode($res, true);
//                    if ($list) {
//                        $sendData['data'] = $list['data'];
//                    }
//                    break;
            }
            $server->push($frame->fd, json_encode($sendData));
        }
    }

    public function onClose($ser, $fd) {
        echo "client {$fd} closed\n";
    }

    public function onTask(Server $server, int $taskId, int $srcWorkerId, string $taskData)
    {

    }

    public function onFinish(Server $server, int $taskId, string $data)
    {
        Db::name('pressure_task')->where('id', $data)->update(['status' => '2']);
        echo 'finish...', PHP_EOL;
    }

    public function run()
    {
        echo "\n------------websocket server is listening on $this->host:$this->port-------\n";
        $server = new Server($this->host, $this->port);
        $server->set($this->config);
        $server->on('open', [$this, 'onOpen']);
        $server->on('message', [$this, 'onMessage']);
        $server->on('task', [$this, 'onTask']);
        $server->on('finish', [$this, 'onFinish']);
        $server->on('close', [$this, 'onClose']);
        $this->server = $server;
        $this->server->start();
    }

}
