<?php
namespace app\index\controller;

use app\api\controller\Fredis;
use think\Db;

class Home extends Common {


    private $taoke_order = 0;
    private $upgrade = 0;
    private $mall = 0;
    private $newuser = 0;
    private $o2o = 0;
    private $down_pay = 0;
    private $days = [];
    private $order_success_count = [];
    private $order_sum_count = [];

    /**
     * 首页
     * @return mixed
     */
    public function index() {
        if ($this->roleId == '1') {
            $list = Db::name('commission_log')->where('uid', Session('adminInfo')['id'])->select();

            print_r($list);exit;
        } elseif ($this->roleId == '3') {
            $info = Db::name('total_income')->where('uid', Session('adminInfo')['id'])->find();
            if ($info) {
                $this->taoke_order = $info['taoke_order'];
                $this->upgrade = $info['upgrade'];
                $this->mall = $info['mall'];
                $this->newuser = $info['newuser'];
                $this->o2o = $info['o2o'];
                $this->down_pay = $info['down_pay'];
            }
        }

        $this->getEcharts();
        $this->assign('taoke_order', $this->taoke_order);
        $this->assign('upgrade', $this->upgrade);
        $this->assign('mall', $this->mall);
        $this->assign('newuser', $this->newuser);
        $this->assign('o2o', $this->o2o);
        $this->assign('down_pay', $this->down_pay);
        $this->assign('order_success_count', json_encode($this->order_success_count));
        $this->assign('order_sum_count', json_encode($this->order_sum_count));
        $this->assign('days', json_encode($this->days));
        return $this->fetch();
    }

    public function getEcharts() {
        $j = date("t"); //获取当前月份天数
        $start_time = date("Y-m-d H:i:s", strtotime("-31 day"));
        for($i=0;$i<$j;$i++){
            $this->days[] = $start_time;
            $this->countDay($start_time);
            $start_time = date('Y-m-d',strtotime($start_time) + 86400);
        }
    }

    public function countDay($day) {
        $this->order_success_count[] = Db::name('order_record')
            ->where('create_time', 'between time', [date("Y-m-d", strtotime($day)), date("Y-m-d", strtotime($day) + 3600*24)])
            ->where('uid', Session('adminInfo')['id'])
            ->where('order_status', '3')
            ->count();

        $this->order_sum_count[] = Db::name('order_record')
            ->where('create_time', 'between time', [date("Y-m-d", strtotime($day)), date("Y-m-d", strtotime($day) + 3600*24)])
            ->where('uid', Session('adminInfo')['id'])
            ->count();
    }

}
