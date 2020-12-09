<?php
namespace app\index\controller;

use think\Controller;
use think\Db;

class Menu extends Controller {

    protected $connection = [
        // 数据库类型
        'type'        => 'mysql',
        // 服务器地址
        'hostname'    => '127.0.0.1',
        // 数据库名
        'database'    => 'cps.yuanzai.com',
        // 数据库用户名
        'username'    => 'cps.yuanzai.com',
        // 数据库密码
        'password'    => 'TPcEeySBBSjK5At7',
        // 数据库编码默认采用utf8
        'charset'     => 'utf8',
        // 数据库表前缀
        'prefix'      => 'center_',
        // 数据库调试模式
        'debug'       => false,
    ];

    public function getNodes() {

        $roleId = isset(Session('adminInfo')['role_id']) ? Session('adminInfo')['role_id'] : '';

        if (!$roleId) {
            return [];
        }
        $ruleIds = Db::connect($this->connection)->name('role_rule')->where('role_id', $roleId)->column('rule_id');

        $id = input('id');

        $where['status'] = '1';
        $where['is_show'] = '1';
        $where['id'] = ['in', $ruleIds];

        if ($id) {
            $where['pid'] = $id;
            $rulesInfo =Db::connect($this->connection)->name('rules')->where($where)->order('sort')->select();
        } else {
            $where['pid'] = 0;
            $rulesInfo = Db::connect($this->connection)->name('rules')->where($where)->order('sort')->select();
        }

        $node = [];
        foreach ($rulesInfo as $v) {
            $where['pid'] = $v['id'];
            $info = Db::connect($this->connection)->name('rules')->where($where)->find();
            if ($info) {
                $hasChildren = 1;
            } else {
                $hasChildren = 0;
            }
            $node[] = [
                'id' => $v['id'],
                'text' => $v['rule_name'],
                'icon'  => $v['icon'],
                'hasChildren'   => $hasChildren,
                'href' => $v['rule_alias'],
            ];
        }
        return $node;
    }

}
