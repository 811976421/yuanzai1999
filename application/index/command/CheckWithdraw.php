<?php
namespace app\index\command;

use app\api\controller\Fredis;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class CheckWithdraw extends Command {

    protected function configure()
    {
        $this->setName('checkWithdraw')->setDescription('for check withdraw');
    }

    protected function execute(Input $input, Output $output)
    {
        $this->go();
    }

    public function go() {

        $order = [];

        $order = array_merge($order, Db::name('merchant_withdraw_order')
            ->where(['status' => '1', 'warning' => '0'])
            ->where('create_time', '<= time', time() - Config('withdraw_alarm') * 60)
            ->select());

        $order = array_merge($order, Db::name('member_withdraw_order')
            ->where(['status' => '1', 'warning' => '0'])
            ->where('create_time', '<= time', time() - Config('withdraw_alarm') * 60)
            ->select());

        foreach ($order as $k => $v) {
            if (isset($v['member_id'])) {
                Db::name('member_withdraw_order')->where('id', $v['id'])->setField('warning', '1');
                sendEmail('提现超时报警', '码商提现订单超时,订单号:' . $v['id'] . ';金额:' . $v['amount'] . ';创建时间:' . $v['create_time'] . '.');
            } else {
                Db::name('merchant_withdraw_order')->where('id', $v['id'])->setField('warning', '1');
                sendEmail('提现超时报警', '商户提现订单超时,订单号:' . $v['id'] . ';金额:' . $v['amount'] . ';创建时间:' . $v['create_time'] . '.');
            }
        }
    }

}
