<?php
namespace app\index\controller;

use think\Db;
use think\Session;

class Nearbyshops extends Common {

    public function review() {
        $this->assign('img_src','https://www.yuanzai1999.com');
        return $this->fetch();
    }

    public function getOrders() {

        $where = [];

        if (input('mobile')) {
            $where['mobile'] = input('mobile');
        }

        if (Session('adminInfo')['role_id'] == '3' && Session('adminInfo')['role_name'] == '代理') {
            $proxyPower = Db::connect($this->connection)
                ->name('proxy_power')
                ->where('proxy_id', Session('adminInfo')['id'])
                ->find();

            if ($proxyPower) {
                if ($proxyPower['proxy_level'] == '1') {
                    //市代理
                    $where['address'] = ['like', '%'.$proxyPower['province'].$proxyPower['city'].'%'];
                } else {
                    //区代理
                    $where['address'] = ['like', '%'.$proxyPower['province'].$proxyPower['city'].$proxyPower['county'].'%'];
                }
            } else {
                return json([
                    'code' => 0,
                    'msg'  => '',
                    'count' => 0,
                    'data' => [],
                ]);
            }
        }


        $list = Db::name('o2o_manage')->where($where)->select();

        foreach ($list as $k => $v) {
            $list[$k]['create_time'] = date("Y-m-d", $v['create_time']);
            if ($v['status'] == '0') {
                $isExist = Db::connect($this->connection)->name('proxy_review')->where([
                    'store_id' => $v['id'],
                    'shop_type' => '2',
                ])->find();
                if ($isExist) {
                    $list[$k]['status'] = '5';
                    if ($this->roleId == '1') {
                        $list[$k]['reviewer'] = Db::name('user')->where('id', $isExist['proxy_id'])->value('username');
                    }
                }
            }
        }

        $count = Db::name('o2o_manage')->where($where)->count();


        return json([
            'code' => 0,
            'msg'  => '',
            'count' => $count,
            'data' => $list,
        ]);
    }

    public function handler() {
        $id = input('id');
        $res = '';
        switch (input('action')) {
            case 'enable':
                $info = Db::name('o2o_manage')->where(['id' => $id, 'status' => '4'])->find();
                if (!$info) {
                    return errReturn(400,'不可启用的店铺类型');
                }
                $res = Db::name('o2o_manage')->where(['id' => $id, 'status' => '4'])->update([
                    'status' => '2',
                ]);
                if ($res) {
                    return sucReturn(200,'启用成功');
                }
                return errReturn(400,'启用失败');
                break;
            case 'disable':
                $info = Db::name('o2o_manage')->where(['id' => $id, 'status' => '2'])->find();
                if (!$info) {
                    return errReturn(400,'不可禁用的店铺类型');
                }
                $res = Db::name('o2o_manage')->where(['id' => $id, 'status' => '2'])->update([
                    'status' => '4'
                ]);
                if ($res) {
                    return sucReturn(200,'禁用成功');
                }
                return errReturn(400,'禁用失败');
                break;
            case 'passed':
                $info = Db::name('o2o_manage')->where(['id' => $id, 'status' => '0'])->find();
                if (!$info) {
                    return errReturn(400,'不可审核');
                }
                if ($this->roleId == '1') {
                    $res = Db::name('o2o_manage')->where(['id' => $id, 'status' => '0'])->update([
                        'status' => '1'
                    ]);
                    if ($res) {
                        return sucReturn(200,'审核通过');
                    }
                    return errReturn(400,'审核失败');
                } else {
                    $isExist = Db::connect($this->connection)->name('proxy_review')->where([
                        'proxy_id' => Session('adminInfo')['id'],
                        'store_id' => $id,
                        'shop_type' => '2'
                    ])->find();
                    if ($isExist) {
                        return errReturn(400,'请勿重复提交审核');
                    }
                    $res = Db::connect($this->connection)->name('proxy_review')->insert([
                        'proxy_id' => Session('adminInfo')['id'],
                        'store_id' => $id,
                        'shop_type' => '2'
                    ]);
                    if ($res) {
                        return sucReturn(200,'已通过,待管理员审核');
                    }
                    return errReturn(400,'操作有误');
                }
                break;
            case 'down':
                $info = Db::name('o2o_manage')->where(['id' => $id, 'status' => '0'])->find();
                if (!$info) {
                    return errReturn(400,'不可审核');
                }
                Db::connect($this->connection)->name('proxy_review')->where([
                    'store_id' => $id,
                    'shop_type' => '2'
                ])->delete();
                $res2 = Db::name('o2o_manage')->where(['id' => $id, 'status' => '0'])->update([
                    'status' => '3'
                ]);
                if ($res2) {
                    return sucReturn(200,'已驳回');
                }
                return errReturn(400,'操作有误');
                break;
            default:
                break;
        }
    }

    public function delete() {

        $res = Db::name('o2o_manage')->where('id', input('id'))->delete();

        if ($res === false) {
            return errReturn(400, '删除失败');
        }
        return sucReturn(200,'删除成功');
    }


    public function orderList() {

        $this->assign('store_id', input('store_id'));
        return $this->fetch();
    }

    public function getorderlist() {

        $where = [];

        if (input('store_id')) {
            $where['store_id'] = input('store_id');
        }

        if (Session('adminInfo')['role_id'] == '3' && Session('adminInfo')['role_name'] == '代理') {

            $where['store_id'] = input('store_id');
        }

        $list = Db::name('o2o_order')
            ->where($where)
            ->page(input('page'))
            ->limit(input('limit'))
            ->order('id desc')
            ->select();

        foreach ($list as $k => $v) {
            $storeInfo = Db::name('o2o_manage')->where('id', $v['store_id'])->find();
            $list[$k]['store_mobile'] = $storeInfo['mobile'] ?? '';
            $list[$k]['store_nickname'] = $storeInfo['username'] ?? '';
            $list[$k]['store_type'] = $storeInfo['shop_type'] ?? '';
            $list[$k]['store_contacts'] = $storeInfo['contacts'] ?? '';
            $list[$k]['store_contacts_mobile'] = $storeInfo['contact_mobile'] ?? '';
            $list[$k]['store_email'] = $storeInfo['email'] ?? '';
            $list[$k]['store_qq'] = $storeInfo['qq'] ?? '';
            $list[$k]['create_time'] = date("Y-m-d H:i:s", $v['create_time']);
            $list[$k]['complete_time'] = $v['complete_time'] > 0 ? $v['complete_time'] : '';
            $list[$k]['commission_rate'] = $v['commission_rate'] . '%';
        }

        $count = Db::name('o2o_order')->where($where)->count();

        return json([
            'code' => '0',
            'msg' => '',
            'data' => $list,
            'count' => $count,
        ]);
    }

    public function manage() {

        return $this->fetch();
    }

    public function getMine() {

        if (input('mobile')) {
            $where['mobile'] = input('mobile');
        }

        $where['status'] = '2';

        if (Session('adminInfo')['role_id'] == '3' && Session('adminInfo')['role_name'] == '代理') {

            $where['contact_mobile'] = Session('adminInfo')['mobile'];

        }

        $list = Db::name('o2o_manage')
            ->where($where)
            ->page(input('page'))
            ->limit(input('limit'))
            ->order('id desc')
            ->select();

        $count = Db::name('o2o_manage')->where($where)->count();

        return json([
            'code' => 0,
            'msg' => '',
            'data' => $list,
            'count' => $count,
        ]);

    }

}
