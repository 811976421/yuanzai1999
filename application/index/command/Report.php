<?php
namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class Report extends Command {

    private $date;

    protected function configure()
    {
        $this->setName('Report')
            ->setDescription('Generate statistics')
            ->addArgument('user_type')
            ->addArgument('date')
            ->addOption('day')
            ->addOption('month');
    }

    protected function execute(Input $input, Output $output)
    {
        $userType = $input->getArgument('user_type');
        $this->date = $input->getArgument('date');

        if ($userType == 'member') {
            if ($input->hasOption('day')) {
                $this->memberDay();
            }
            if ($input->hasOption('month')) {
                $this->memberMonth();
            }
        }

        if ($userType == 'merchant') {
            if ($input->hasOption('day')) {
                $this->merchantDay();
            }
            if ($input->hasOption('month')) {
                $this->merchantMonth();
            }
        }

        if ($userType == 'source') {
            if ($input->hasOption('day')) {
                $this->sourceDay();
            }
            if ($input->hasOption('month')) {
                $this->sourceMonth();
            }
        }

        if ($userType == 'all') {
            if ($input->hasOption('day')) {
                $this->countAll();
            }
        }

    }

    private function memberDay() {
        if (!$this->date) {
            $this->date = date("Y-m-d", strtotime('-1 day'));
        }
        $whereTime1['create_time'] = ['between time', [$this->date, date("Y-m-d", strtotime("+1 day", strtotime($this->date)))]];
        $whereTime2['pay_time'] = ['between time', [$this->date, date("Y-m-d", strtotime("+1 day", strtotime($this->date)))]];

        $orderData = Db::name('order')
            ->group('member_id')
            ->where($whereTime1)
            ->field('member_id,sum(amount) as amount,count(*) as count')
            ->select();

        $orderSucData = Db::name('order')
            ->group('member_id')
            ->where($whereTime2)
            ->where(['status' => ['in', ['1','2','-3','3']]])
            ->field('member_id,sum(amount) as amount,count(*) as count')
            ->select();

        $rechargeData = Db::name('member_recharge_order')
            ->group('member_id')
            ->where($whereTime1)
            ->field('member_id,sum(amount) as amount,count(*) as count')
            ->select();

        $rechargeSucData = Db::name('member_recharge_order')
            ->group('member_id')
            ->where($whereTime1)
            ->where(['status' => '2'])
            ->field('member_id,sum(amount) as amount,count(*) as count')
            ->select();

        $withdrawData = Db::name('member_withdraw_order')
            ->group('member_id')
            ->where($whereTime1)
            ->field('member_id,sum(amount) as amount,count(*) as count')
            ->select();

        $withdrawSucData = Db::name('member_withdraw_order')
            ->group('member_id')
            ->where($whereTime1)
            ->where(['status' => '2'])
            ->field('member_id,sum(amount) as amount,count(*) as count')
            ->select();

        $commissionData = Db::name('member_commission_order')
            ->group('member_id')
            ->where($whereTime1)
            ->field('member_id,sum(amount) as amount,count(*) as count')
            ->select();

        $commissionSucData = Db::name('member_commission_order')
            ->group('member_id')
            ->where($whereTime1)
            ->where(['status' => '2'])
            ->field('member_id,sum(amount) as amount,count(*) as count')
            ->select();

        $profit = Db::name('member_balance_change')
            ->group('member_id')
            ->where($whereTime1)
            ->where(['change_type' => '2', 'change_way' => '1'])
            ->field('member_id,sum(amount) as amount')
            ->select();

        $orderData = array_column($orderData, null,'member_id');
        $orderSucData = array_column($orderSucData, null,'member_id');
        $rechargeData = array_column($rechargeData, null,'member_id');
        $rechargeSucData = array_column($rechargeSucData, null,'member_id');
        $withdrawData = array_column($withdrawData, null,'member_id');
        $withdrawSucData = array_column($withdrawSucData, null,'member_id');
        $commissionData = array_column($commissionData, null,'member_id');
        $commissionSucData = array_column($commissionSucData, null,'member_id');
        $profit = array_column($profit, null,'member_id');

        $memberIds = Db::name('members')->column('id');
        $detailDay = [];
        foreach ($memberIds as $v) {
            $detailDay[$v] = [
                'date' => $this->date,
                'member_id' => $v,
                'available_amount' => Db::name('member_balance')->where('member_id', $v)->value('avaliable_amount'),
                'order_sum_amount' => isset($orderData[$v]['amount']) ? $orderData[$v]['amount'] : 0,
                'order_success_amount' => isset($orderSucData[$v]['amount']) ? $orderSucData[$v]['amount'] : 0,
                'profit' => isset($profit[$v]['amount']) ? $profit[$v]['amount'] : 0,
                'order_sum_count' => isset($orderData[$v]['count']) ? $orderData[$v]['count'] : 0,
                'order_success_count' => isset($orderSucData[$v]['count']) ? $orderSucData[$v]['count'] : 0,
                'recharge_sum_amount' => isset($rechargeData[$v]['amount']) ? $rechargeData[$v]['amount'] : 0,
                'recharge_success_amount' => isset($rechargeSucData[$v]['amount']) ? $rechargeSucData[$v]['amount'] : 0,
                'withdraw_sum_amount' => isset($withdrawData[$v]['amount']) ? $withdrawData[$v]['amount'] : 0,
                'withdraw_success_amount' => isset($withdrawSucData[$v]['amount']) ? $withdrawSucData[$v]['amount'] : 0,
                'commission_sum_amount' => isset($commissionData[$v]['amount']) ? $commissionData[$v]['amount'] : 0,
                'commission_success_amount' => isset($commissionSucData[$v]['amount']) ? $commissionSucData[$v]['amount'] : 0,
            ];
        }

        Db::name('member_static_detail_day')->insertAll($detailDay);
    }

    private function memberMonth() {
        $memberIds = Db::name('members')->column('id');
        $date = date("Y-m-01",strtotime("-1 day"));
        if (!$this->date) {
            $this->date = date("Y-m-d",strtotime("-1 day"));
        }
        $detailInsertMonth = [];
        foreach ($memberIds as $v) {
            $info = Db::name('member_static_detail_month')->where(['date' => $date, 'member_id' => $v])->find();
            $detailDay = Db::name('member_static_detail_day')->where(['date' => $this->date, 'member_id' => $v])->find();

            if ($info) {
                Db::name('member_static_detail_month')->where('id', $info['id'])->update([
                    'order_sum_amount' => $info['order_sum_amount'] + $detailDay['order_sum_amount'],
                    'order_success_amount' => $info['order_success_amount'] + $detailDay['order_success_amount'],
                    'profit' => $info['profit'] + $detailDay['profit'],
                    'order_sum_count' => $info['order_sum_count'] + $detailDay['order_sum_count'],
                    'order_success_count' => $info['order_success_count'] + $detailDay['order_success_count'],
                    'recharge_sum_amount' => $info['recharge_sum_amount'] + $detailDay['recharge_sum_amount'],
                    'recharge_success_amount' => $info['recharge_success_amount'] + $detailDay['recharge_success_amount'],
                    'withdraw_sum_amount' => $info['withdraw_sum_amount'] + $detailDay['withdraw_sum_amount'],
                    'withdraw_success_amount' => $info['withdraw_success_amount'] + $detailDay['withdraw_success_amount'],
                    'commission_sum_amount' => $info['commission_sum_amount'] + $detailDay['commission_sum_amount'],
                    'commission_success_amount' => $info['commission_success_amount'] + $detailDay['commission_success_amount'],
                ]);
            } else {
                $detailInsertMonth[] = [
                    'date' => $date,
                    'member_id' => $v,
                    'order_sum_amount' => isset($detailDay['order_sum_amount']) ? $detailDay['order_sum_amount'] : 0,
                    'order_success_amount' => isset($detailDay['order_success_amount']) ? $detailDay['order_success_amount'] : 0,
                    'profit' => isset($detailDay['profit']) ? $detailDay['profit'] : 0,
                    'order_sum_count' => isset($detailDay['order_sum_count']) ? $detailDay['order_sum_count'] : 0,
                    'order_success_count' => isset($detailDay['order_success_count']) ? $detailDay['order_success_count'] : 0,
                    'recharge_sum_amount' => isset($detailDay['recharge_sum_amount']) ? $detailDay['recharge_sum_amount'] : 0,
                    'recharge_success_amount' => isset($detailDay['recharge_success_amount']) ? $detailDay['recharge_success_amount'] : 0,
                    'withdraw_sum_amount' => isset($detailDay['withdraw_sum_amount']) ? $detailDay['withdraw_sum_amount'] : 0,
                    'withdraw_success_amount' => isset($detailDay['withdraw_success_amount']) ? $detailDay['withdraw_success_amount'] : 0,
                    'commission_sum_amount' => isset($detailDay['commission_sum_amount']) ? $detailDay['commission_sum_amount'] : 0,
                    'commission_success_amount' => isset($detailDay['commission_success_amount']) ? $detailDay['commission_success_amount'] : 0,
                ];
            }
        }

        if ($detailInsertMonth) {
            Db::name('member_static_detail_month')->insertAll($detailInsertMonth);
        }
    }

    private function merchantDay() {

        if (!$this->date) {
            $this->date = date("Y-m-d", strtotime('-1 day'));
        }

        $whereTime1['create_time'] = ['between time', [$this->date, date("Y-m-d", strtotime("+1 day", strtotime($this->date)))]];
        $whereTime2['pay_time'] = ['between time', [$this->date, date("Y-m-d", strtotime("+1 day", strtotime($this->date)))]];

        $orderData = Db::name('order')
            ->group('merchant_id')
            ->where($whereTime1)
            ->field('merchant_id,sum(amount) as amount,count(*) as count')
            ->select();

        $orderSucData = Db::name('order')
            ->group('merchant_id')
            ->where($whereTime2)
            ->where(['status' => ['in', ['1','2','-3','3']]])
            ->field('merchant_id,sum(amount) as amount,count(*) as count, sum(amount - merchant_trade_charge) as real_amount')
            ->select();

        $withdrawData = Db::name('merchant_withdraw_order')
            ->group('merchant_id')
            ->where($whereTime1)
            ->field('merchant_id,sum(amount) as amount,count(*) as count')
            ->select();

        $withdrawSucData = Db::name('merchant_withdraw_order')
            ->group('merchant_id')
            ->where($whereTime1)
            ->where(['status' => '2'])
            ->field('merchant_id,sum(amount) as amount,count(*) as count')
            ->select();

        $commissionData = Db::name('merchant_commission_order')
            ->group('merchant_id')
            ->where($whereTime1)
            ->field('merchant_id,sum(amount) as amount,count(*) as count')
            ->select();

        $commissionSucData = Db::name('merchant_commission_order')
            ->group('merchant_id')
            ->where($whereTime1)
            ->where(['status' => '2'])
            ->field('merchant_id,sum(amount) as amount,count(*) as count')
            ->select();

        $profit = Db::name('merchant_balance_change')
            ->group('merchant_id')
            ->where($whereTime1)
            ->where(['change_type' => '2', 'change_way' => '1'])
            ->field('merchant_id,sum(amount) as amount')
            ->select();

        $orderData = array_column($orderData, null,'merchant_id');
        $orderSucData = array_column($orderSucData, null,'merchant_id');
        $withdrawData = array_column($withdrawData, null,'merchant_id');
        $withdrawSucData = array_column($withdrawSucData, null,'merchant_id');
        $commissionData = array_column($commissionData, null,'merchant_id');
        $commissionSucData = array_column($commissionSucData, null,'merchant_id');
        $profit = array_column($profit, null,'merchant_id');

        $merchantIds = Db::name('merchants')->column('id');
        $detailDay = [];
        foreach ($merchantIds as $v) {
            $detailDay[$v] = [
                'date' => $this->date,
                'merchant_id' => $v,
                'available_amount' => Db::name('merchant_balance')->where('merchant_id', $v)->value('avaliable_amount'),
                'order_sum_amount' => isset($orderData[$v]['amount']) ? $orderData[$v]['amount'] : 0,
                'order_success_amount' => isset($orderSucData[$v]['amount']) ? $orderSucData[$v]['amount'] : 0,
                'order_real_amount' => isset($orderSucData[$v]['real_amount']) ? $orderSucData[$v]['real_amount'] : 0,
                'profit' => isset($profit[$v]['amount']) ? $profit[$v]['amount'] : 0,
                'order_sum_count' => isset($orderData[$v]['count']) ? $orderData[$v]['count'] : 0,
                'order_success_count' => isset($orderSucData[$v]['count']) ? $orderSucData[$v]['count'] : 0,
                'withdraw_sum_amount' => isset($withdrawData[$v]['amount']) ? $withdrawData[$v]['amount'] : 0,
                'withdraw_success_amount' => isset($withdrawSucData[$v]['amount']) ? $withdrawSucData[$v]['amount'] : 0,
                'commission_sum_amount' => isset($commissionData[$v]['amount']) ? $commissionData[$v]['amount'] : 0,
                'commission_success_amount' => isset($commissionSucData[$v]['amount']) ? $commissionSucData[$v]['amount'] : 0,
            ];
        }
   
        Db::name('merchant_static_detail_day')->insertAll($detailDay);
    }

    private function merchantMonth() {
        $merchantIds = Db::name('merchants')->column('id');
        $date = date("Y-m-01",strtotime("-1 day"));
        if (!$this->date) {
            $this->date = date("Y-m-d",strtotime("-1 day"));
        }
        $detailInsertMonth = [];
        foreach ($merchantIds as $v) {
            $info = Db::name('merchant_static_detail_month')->where(['date' => $date, 'merchant_id' => $v])->find();
            $detailDay = Db::name('merchant_static_detail_day')->where(['date' => $this->date, 'merchant_id' => $v])->find();
            if ($info) {
                Db::name('merchant_static_detail_month')->where('id', $info['id'])->update([
                    'order_sum_amount' => $info['order_sum_amount'] + $detailDay['order_sum_amount'],
                    'order_success_amount' => $info['order_success_amount'] + $detailDay['order_success_amount'],
                    'order_real_amount' => $info['order_real_amount'] + $detailDay['order_real_amount'],
                    'profit' => $info['profit'] + $detailDay['profit'],
                    'order_sum_count' => $info['order_sum_count'] + $detailDay['order_sum_count'],
                    'order_success_count' => $info['order_success_count'] + $detailDay['order_success_count'],
                    'withdraw_sum_amount' => $info['withdraw_sum_amount'] + $detailDay['withdraw_sum_amount'],
                    'withdraw_success_amount' => $info['withdraw_success_amount'] + $detailDay['withdraw_success_amount'],
                    'commission_sum_amount' => $info['commission_sum_amount'] + $detailDay['commission_sum_amount'],
                    'commission_success_amount' => $info['commission_success_amount'] + $detailDay['commission_success_amount'],
                ]);
            } else {
                $detailInsertMonth[] = [
                    'date' => $date,
                    'merchant_id' => $v,
                    'order_sum_amount' => isset($detailDay['order_sum_amount']) ? $detailDay['order_sum_amount'] : 0,
                    'order_success_amount' => isset($detailDay['order_success_amount']) ? $detailDay['order_success_amount'] : 0,
                    'order_real_amount' => isset($detailDay['order_real_amount']) ? $detailDay['order_real_amount'] : 0,
                    'profit' => isset($detailDay['profit']) ? $detailDay['profit'] : 0,
                    'order_sum_count' => isset($detailDay['order_sum_count']) ? $detailDay['order_sum_count'] : 0,
                    'order_success_count' => isset($detailDay['order_success_count']) ? $detailDay['order_success_count'] : 0,
                    'withdraw_sum_amount' => isset($detailDay['withdraw_sum_amount']) ? $detailDay['withdraw_sum_amount'] : 0,
                    'withdraw_success_amount' => isset($detailDay['withdraw_success_amount']) ? $detailDay['withdraw_success_amount'] : 0,
                    'commission_sum_amount' => isset($detailDay['commission_sum_amount']) ? $detailDay['commission_sum_amount'] : 0,
                    'commission_success_amount' => isset($detailDay['commission_success_amount']) ? $detailDay['commission_success_amount'] : 0,
                ];
            }
        }

        Db::name('merchant_static_detail_month')->insertAll($detailInsertMonth);
    }

    private function sourceDay() {

        if (!$this->date) {
            $this->date = date("Y-m-d", strtotime('-1 day'));
        }

        $whereTime1['create_time'] = ['between time', [$this->date, date("Y-m-d", strtotime("+1 day", strtotime($this->date)))]];
        $whereTime2['pay_time'] = ['between time', [$this->date, date("Y-m-d", strtotime("+1 day", strtotime($this->date)))]];

        $orderData = Db::name('order')
            ->group('resource_id,product_id')
            ->where($whereTime1)
            ->field('resource_id,product_id,sum(amount) as amount,count(*) as count')
            ->select();

        $orderSucData = Db::name('order')
            ->group('resource_id,product_id')
            ->where($whereTime2)
            ->where(['status' => ['in', ['1','2','-3','3']]])
            ->field('resource_id,product_id,sum(amount) as amount,count(*) as count')
            ->select();

        $detailDay = [];
        foreach ($orderData as $v) {
            $sucData = [
                'suc_count' => '0',
                'suc_amount' => '0',
            ];
            foreach ($orderSucData as $v2) {
                if ($v['resource_id'] == $v2['resource_id'] && $v['product_id'] == $v2['product_id']) {
                    $sucData['suc_count'] = $v2['count'];
                    $sucData['suc_amount'] = $v2['amount'];
                    break;
                }
            }
            $detailDay[] = [
                'date' => $this->date,
                'source_id' => $v['resource_id'],
                'product_id' => $v['product_id'],
                'day_sum_count' => $v['count'],
                'day_success_count' => $sucData['suc_count'],
                'day_sum_amount' => $v['amount'],
                'day_success_amount' => $sucData['suc_amount'],
            ];
        }

        Db::name('source_static_detail_day')->insertAll($detailDay);
    }

    private function sourceMonth() {
        $products = Db::name('product')->field('id,type_id')->where('_delete','0')->select();
        $date = date("Y-m-01",strtotime("-1 day"));
        if (!$this->date) {
            $this->date = date("Y-m-d",strtotime("-1 day"));
        }
        $detailInsertMonth = [];
        foreach ($products as $v) {
            switch ($v['type_id']) {
                case '103':
                    //静态
                    $sources = Db::name('qrcode')->column('id');
                    break;
                case '104':
                    //动态
                    $sources = Db::name('qrapi')->column('id');
                    break;
                case '107':
                    //转账银行卡
                    $sources = Db::name('transfer_bank')->column('id');
                    break;
                case '108':
                    //话费
                    $sources = [];
                    break;
                default:
                    $sources = [];
                    break;
            }
            foreach ($sources as $v2) {
                $info = Db::name('source_static_detail_month')->where(['date' => $date, 'source_id' => $v2, 'product_id' => $v['id']])->find();
                $detailDay = Db::name('source_static_detail_day')->where(['date' => $this->date, 'source_id' => $v2, 'product_id' => $v['id']])->find();
                if ($info) {
                    Db::name('source_static_detail_month')->where('id', $info['id'])->update([
                        'month_sum_count' => $info['month_sum_count'] + $detailDay['day_sum_count'],
                        'month_success_count' => $info['month_success_count'] + $detailDay['day_success_count'],
                        'month_sum_amount' => $info['month_sum_amount'] + $detailDay['day_sum_amount'],
                        'month_success_amount' => $info['month_success_amount'] + $detailDay['day_success_amount'],
                    ]);
                } else {
                    if ($detailDay) {
                        $detailInsertMonth[] = [
                            'date' => $date,
                            'source_id' => $v2,
                            'product_id' => $v['id'],
                            'month_sum_count' => isset($detailDay['day_sum_count']) ? $detailDay['day_sum_count'] : 0,
                            'month_success_count' => isset($detailDay['day_success_count']) ? $detailDay['day_success_count'] : 0,
                            'month_sum_amount' => isset($detailDay['day_sum_amount']) ? $detailDay['day_sum_amount'] : 0,
                            'month_success_amount' => isset($detailDay['day_success_amount']) ? $detailDay['day_success_amount'] : 0,
                        ];
                    }
                }
            }
        }
        Db::name('source_static_detail_month')->insertAll($detailInsertMonth);
    }

    private function countAll() {

        $orderSum = Db::name('order')
            ->whereTime('create_time', 'yesterday')
            ->field('sum(amount) as amount,count(*) as count')
            ->find();

        $orderSuccess = Db::name('order')
            ->where(['status' => ['in', ['1', '2','-3','3']]])
            ->whereTime('pay_time', 'yesterday')
            ->field('sum(amount) as amount,count(*) as count, sum(amount - merchant_trade_charge) as real_amount')
            ->find();

        $rechargeAmount = Db::name('member_recharge_order')
            ->where('status', '2')
            ->whereTime('create_time', 'yesterday')
            ->sum('amount');

        $memberWithdrawAmount = Db::name('member_withdraw_order')
            ->where('status', '2')
            ->whereTime('create_time', 'yesterday')
            ->sum('amount');

        $merchantWithdrawAmount = Db::name('merchant_withdraw_order')
            ->where('status', '2')
            ->whereTime('create_time', 'yesterday')
            ->sum('amount');

        $withdrawAmount = $memberWithdrawAmount + $merchantWithdrawAmount;

        $memberProfit = Db::name('member_balance_change')
            ->whereTime('create_time', 'yesterday')
            ->where(['change_type' => '2', 'change_way' => '1'])
            ->sum('amount');
        $merchantProfit = Db::name('merchant_balance_change')
            ->whereTime('create_time', 'yesterday')
            ->where(['change_type' => '2', 'change_way' => '1'])
            ->sum('amount');

        $profitAmount = $memberProfit + $merchantProfit;

        Db::name('statistic_day')->insert([
            'date' => date("Y-m-d", strtotime("-1 day")),
            'order_sum_amount' => $orderSum['amount'] ?? 0,
            'order_success_amount' => $orderSuccess['amount'] ?? 0,
            'order_sum_count' => $orderSum['count'],
            'order_real_amount' => $orderSuccess['real_amount'],
            'order_success_count' => $orderSuccess['count'],
            'withdraw_amount' => $withdrawAmount,
            'recharge_amount' => $rechargeAmount,
            'profit' => $profitAmount,
        ]);
    }

}
