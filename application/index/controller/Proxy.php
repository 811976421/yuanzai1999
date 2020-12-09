<?php
namespace app\index\controller;

use think\Db;

class Proxy extends Common {

    public function index() {


        return $this->fetch();
    }

    public function getList() {

        $where['grade_id'] = ['>=', '3'];
        $where['status'] = '1';

        if (input('username')) {
            $where['username'] = ['like', '%' . trim(input('username')) . '%'];
        }

        if (input('mobile')) {
            $where['mobile'] = ['like', '%' . trim(input('mobile')) . '%'];
        }

        $list = Db::name('user')
            ->where($where)
            ->field('id,username,pid,mobile,grade_id')
            ->page(input('page'))
            ->limit(input('limit'))
            ->select();

        foreach ($list as $k => $v) {
            $list[$k]['referrer'] = Db::name('user')->where('id', $v['pid'])->value('username');
            $proxyInfo = Db::connect($this->connection)->name('proxy_power')->where('proxy_id', $v['id'])->find();
            if ($proxyInfo) {
                $list[$k]['proxy_level'] = $proxyInfo['proxy_level'];
                $list[$k]['province'] = $proxyInfo['province'];
                $list[$k]['city'] = $proxyInfo['city'];
                $list[$k]['county'] = $proxyInfo['county'];
                $list[$k]['area'] = $list[$k]['province'] . $list[$k]['city'] . $list[$k]['county'];
            }
        }

        $count = Db::name('user')->where(['grade_id' => ['>=', '3'], 'status' => '1'])->count();

        return json([
            'code' => '0',
            'msg' => '',
            'data' => $list,
            'count' => $count
        ]);
    }

    public function handler() {

        $data = [
            'proxy_id'    => input('proxy_id'),
            'proxy_level' => input('proxy_level'),
            'province'    => input('province'),
            'city'        => input('city'),
            'county'      => input('county'),
        ];

        $rule = [
            ['proxy_id', 'require|number', '代理id不能为空|代理id必须位数字'],
            ['proxy_level', 'require|in:1,2', '代理等级不能为空|代理等级只能为市或区'],
            ['province', 'require', '省级不能为空'],
            ['city', 'require', '市级不能为空'],
        ];

        $res = checkAll($data, $rule);

        if ($res !== true) {

            return errReturn(400, $res);

        }

        $insert = [
            'proxy_id' => input('proxy_id'),
            'proxy_level' => input('proxy_level'),
            'province' => input('province'),
            'city' => input('city'),
        ];

        if (input('proxy_level') == '1') {
            //市代理
            $isExist = Db::connect($this->connection)->name('proxy_power')->where([
                'proxy_id' => ['<>', input('proxy_id')],
                'proxy_level' => '1',
                'province' => input('province'),
                'city' => input('city'),
            ])->find();
        } else {
            $isExist = Db::connect($this->connection)->name('proxy_power')->where([
                'proxy_id' => ['<>', input('proxy_id')],
                'proxy_level' => '1',
                'province' => input('province'),
                'city' => input('city'),
                'county' => input('county')
            ])->find();
            $insert['county'] = input('county');
        }

        if ($isExist) {
            return errReturn(400,'该区域范围已有代理管辖');
        }

        Db::connect($this->connection)->name('proxy_power')->where('proxy_id', input('proxy_id'))->delete();

        $res = Db::connect($this->connection)->name('proxy_power')->insert($insert);

        if ($res) {
            return sucReturn(200,'设置成功');
        }
        return errReturn(400,'设置失败');
    }

}
