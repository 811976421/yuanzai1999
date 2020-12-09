<?php
namespace app\index\controller;

use think\Db;

class Team extends Common {

    public function index() {
        $this->assign('proxy_id', input('proxy_id'));
        return $this->fetch();
    }

    public function getList() {

        $this->getMyteam(input('proxy_id'));

        $where = [];

        if (input('mobile')) {
            $where['mobile'] = trim(input('mobile'));
        }

        if (input('username')) {
            $where['username'] = ['like', "%" . trim(input('username')) . "%"];
        }

        $where['id'] = ['in', $this->sonIds];

        $list = Db::name('user')
            ->where($where)
            ->field('id,username,pid,mobile,grade_id,avatar,status,code,last_time,money,score')
            ->page(input('page'))
            ->limit(input('limit'))
            ->select();

        foreach ($list as $k => $v) {
            $list[$k]['referrer'] = Db::name('user')->where('id', $v['pid'])->value('username');
            $list[$k]['last_time'] = get_time($v['last_time']);
            $address = Db::name('user_address')->where('uid', $v['id'])->find();
            $list[$k]['location'] = $address['province'] . $address['city'] . $address['district'];
            $list[$k]['power'] = Db::connect($this->connection)->name('proxy_power')->where('proxy_id', $v['id'])->field('province,city,county')->find();
        }

        $count = Db::name('user')->where($where)->count();

        return json([
            'code' => '0',
            'msg' => '',
            'data' => $list,
            'count' => $count
        ]);
    }

    public function getMyteam($proxyId) {

        if (!$proxyId) {
            $proxyId = Session('adminInfo')['id'];
        }

        $this->sonIds = Db::name('user')->where('pid', $proxyId)->column('id');

        $this->getSons($this->sonIds);

        $proxyPower = Db::connect($this->connection)
            ->name('proxy_power')
            ->where('proxy_id', $proxyId)
            ->find();

        if ($proxyPower['proxy_level'] == '1') {
            $where['province'] = $proxyPower['province'];
            $where['city'] = $proxyPower['city'];
        } else {
            $where['province'] = $proxyPower['province'];
            $where['city'] = $proxyPower['city'];
            $where['county'] = $proxyPower['county'];
        }
        $where['proxy_id'] = ['<>', $proxyId];

        $quyu = Db::connect($this->connection)
            ->name('proxy_power')
            ->where($where)
            ->column('proxy_id');

        $this->sonIds = array_merge($this->sonIds, $quyu);

    }
}