<?php
namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class Risk extends Command {

    protected function configure()
    {
        $this->setName('Risk')->setDescription('risk control');
    }

    protected function execute(Input $input, Output $output)
    {
        $risks = Db::name('source_riskcontrol')
            ->where([
                'status' => '1',
                'source_type' => '2',
            ])->field('source_id,risk_alarm_orderTimes,risk_alarm_period,risk_alarm_controlWay')
            ->select();

        foreach ($risks as $k => $v) {
            $list = Db::name('order')->where([
                'resource_type' => '2',
                'resource_id' => $v['source_id']
            ])->order('create_time', 'desc')->field('status,create_time')
            ->limit($v['risk_alarm_orderTimes'])->select();
            if (is_array($list) && count($list) > 0) {
                if (array_sum(array_column($list, 'status')) > 0) {
                    continue;
                }
                $timePeriod = strtotime(current($list)['create_time']) - strtotime(end($list)['create_time']);
                if ($timePeriod <= $v['risk_alarm_period'] * 60) {
                    if ($v['risk_alarm_controlWay'] == '1') {
                        Db::name('qrapi')->where('id', $v['source_id'])->setField('status', '0');
                    }
                    $memberId = Db::name('qrapi')->where('id', $v['source_id'])->value('member_id');
                    $isExist = Db::name('risk_alarm')->where([
                        'member_id' => $memberId,
                        'source_id' => $v['source_id'],
                        'source_type' => '2',
                        'is_alarm' => '0',
                        ])->find();
                    if (!$isExist) {
                        Db::name('risk_alarm')->insert([
                            'member_id' => $memberId,
                            'source_id' => $v['source_id'],
                            'source_type' => '2',
                            'times' => $v['risk_alarm_orderTimes'],
                            'period' => $v['risk_alarm_period'],
                        ]);
                    }
                }
            }
        }
    }
}
