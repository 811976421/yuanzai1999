<?php
namespace app\index\command;

use app\api\controller\Fredis;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class CheckOrder extends Command {

    protected function configure()
    {
        $this->setName('checkOrder')->setDescription('for check order expire');
    }

    protected function execute(Input $input, Output $output)
    {
        $this->go();
    }

    public function go() {

        $list = Db::name('order')
            ->where('status', '0')
            ->order('create_time')
            ->select();

        $redis = Fredis::instance();
        foreach ($list as $v) {
            if (time() > strtotime($v['expire_time'])) {
                Db::name('order')->where('id', $v['id'])->setField('status', '-1');
                $merchant_amount = $redis->get('merchant_id_' . $v['merchant_id'] . '_day_' . date("Y-m-d") . '_sum_amount');
                $redis->set('merchant_id_' . $v['merchant_id'] . '_day_' . date("Y-m-d") . '_sum_amount', $merchant_amount - $v['amount']);
                $member_amount = $redis->get('member_id_' . $v['member_id'] . '_day_' . date("Y-m-d") . '_sum_amount');
                $redis->set('merchant_id_' . $v['merchant_id'] . '_day_' . date("Y-m-d") . '_sum_amount', $member_amount - $v['amount']);
            }
        }
    }

}
