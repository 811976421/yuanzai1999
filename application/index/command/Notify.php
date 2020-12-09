<?php
namespace app\index\command;

use app\api\controller\Fredis;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class Notify extends Command {

    protected function configure()
    {
        $this->setName('notify')->setDescription('for notify');
    }

    protected function execute(Input $input, Output $output)
    {
        $redis = Fredis::instance();
        $orderIdSArr = $redis->lranges('notify_task', 0,5);
        if (!empty($orderIdSArr)) {
            //发送异步通知
            $v = $redis->lpop('notify_task');
            while ($v) {
                $orderInfo = Db::name('order')->where(['order_id' => $v, 'status' => '1'])->find();
                if ($orderInfo) {
                    echo '订单：' . $v . '通知任务开始';
                    $notify_url = $orderInfo['notify_url'];
                    if ($notify_url) {
                        $params = [
                            'code' => 200,
                            'message' => 'SUCCESS',
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
                        if ($result['code'] = '200' && $result['msg'] = 'success') {
                            Db::name('order')->where('id', $orderInfo['id'])->setField('status', '2');
                        } else {
                            echo '订单：' . $v . '通知失败,重新通知';
                            $redis->rpush('notify_task', $orderInfo['order_id']);
                        }
                    }
                }
                $v = $redis->lpop('notify_task');
            }
        }

    }

}
