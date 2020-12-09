<?php
namespace app\index\controller;

use think\Db;
use think\Request;

class Rule extends Common {

    protected $db;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->db = Db::connect($this->connection);
    }

    public function nodeList() {
        $nodes = $this->db->name('rules')->where('status', '1')->select();
        $this->assign('nodes', $nodes);
        return $this->fetch();
    }

    public function getNodes() {

        $nodes = $this->db->name('rules')->order('sort')->select();

        return $nodes;
    }

    public function handler() {

        $data = [
            'id'         => input('id'),
            'pid'        => input('pid'),
            'rule_name'  => trim(input('rule_name')),
            'rule_alias' => trim(input('rule_alias')),
            'sort'       => trim(input('sort')),
            'icon'       => trim(input('icon')),
            'status'     => input('status'),
            'is_show'    => input('is_show'),
        ];

        $rule = [
            ['id', 'number', 'id必须为数字'],
            ['pid', 'require|number|different:id', '父级节点必须|pid必须为数字|父级节点不能是自身'],
            ['rule_name', 'require', '节点名称必须'],
            ['sort', 'require|number', '排序必须|排序必须且为数字'],
            ['status', 'in:0,1', '节点状态只能为禁用或正常'],
            ['is_show', 'in:0,1', '菜单栏可见只能为隐藏或显示'],
        ];

        $res = checkAll($data, $rule);

        if ($res !== true) {

            return errReturn(400, $res);

        }

        if (input('id')) {
            //修改
            $info = $this->db->name('rules')->where('id', $data['id'])->find();

            if (!$info) {

                return errReturn(400, '修改对象不存在');
            }

            $res = $this->db->name('rules')->update($data);

            if ($res === false) {

                return errReturn(400, '更新失败');
            }
            return sucReturn(200, '更新成功');
        } else {
            //新增
            $res = $this->db->name('rules')->insert($data);

            if ($res) {

                return sucReturn(200, '添加成功');
            }
            return errReturn(400, '添加失败');
        }

    }

    public function batchForbid() {

        $ids = input('ids');
        $status = input('status');

        $res = $this->db->name('rules')->where('id', 'in', $ids)->setField('status', $status);

        if ($res === false) {
            return errReturn(400, '更新失败');
        }
        return sucReturn(200, '更新成功');
    }

    public function batchDelete() {

        $ids = input('ids');

        try {
            $res = $this->db->name('rules')->where('id', 'in', $ids)->delete();
        } catch (\Exception $e) {

            return errReturn(400, '存在使用中的节点,删除失败');
        }

        if ($res) {

            return sucReturn(200, '删除成功');
        }
        return errReturn(400, '删除失败');
    }

    public function roleList() {

        $fatherRoles = $this->db->name('roles')->where('pid', 0)->select();

        $this->assign('fatherRoles', $fatherRoles);

        return $this->fetch();
    }

    public function getRoles() {

        $roles = $this->db->name('roles')->select();

        return $roles;
    }

    public function roleHandler() {

        $data = [
            'id'         => input('id'),
            'pid'        => input('pid'),
            'role_name'  => trim(input('role_name')),
            'alias'      => trim(input('alias')),
            'status'     => input('status'),
        ];

        $rule = [
            ['id', 'number', 'id必须为数字'],
            ['pid', 'require|number|different:id', '父级角色必须|pid必须为数字|父级角色不能是自身'],
            ['role_name', 'require', '角色名称必须'],
            ['status', 'in:0,1', '角色状态只能为禁用或正常'],
        ];

        $res = checkAll($data, $rule);

        if ($res !== true) {

            return errReturn(400, $res);

        }

        if (input('id')) {
            //修改
            $info = $this->db->name('roles')->where('id', $data['id'])->find();

            if (!$info) {

                return errReturn(400, '修改对象不存在');
            }

            $res = $this->db->name('roles')->update($data);

            if ($res === false) {

                return errReturn(400, '更新失败');
            }
            return sucReturn(200, '更新成功');
        } else {
            //新增
            $res = $this->db->name('roles')->insert($data);

            if ($res) {

                return sucReturn(200, '添加成功');
            }
            return errReturn(400, '添加失败');
        }
    }

    public function deleteRole() {

        $id = input('id');

        try {
            $res = $this->db->name('roles')->where('id',  $id)->delete();
        } catch (\Exception $e) {

            return errReturn(400, '请先将该角色的节点清空！');
        }

        if ($res) {

            return sucReturn(200, '删除成功');
        }
        return errReturn(400, '删除失败');

    }

    public function auth() {

        $roles = $this->db->name('roles')->where('status', '1')->select();

        $role_id = input('role_id', 1);

        $this->assign('role_id', $role_id);
        $this->assign('roles', $roles);
        return $this->fetch();
    }

    public function authTree() {

        $role_id = input('role_id');

        $rules = $this->db->name('rules')->order('sort')->select();

        $checkedId = $this->db->name('role_rule')->where('role_id', $role_id)->column('rule_id');

        return sucReturn(0, 'success', [
            'list' => $rules,
            'checkedId' =>$checkedId
        ]);
    }

    public function authHandler() {

        $role_id = input('role_id');

        $rules = $_POST['rules'] ?? [];

        if (!is_array($rules)) {
            return errReturn(400, '节点集合必须为数组');
        }

        $rulesArr = [];

        foreach ($rules as $v) {
            $rulesArr[] = [
                'role_id' => $role_id,
                'rule_id' => $v
            ];
        }

        Db::startTrans();
        try{
            $res1 = $this->db->name('role_rule')->where('role_id', $role_id)->delete();

            $res2 = $this->db->name('role_rule')->insertAll($rulesArr);

            if (($res1 === false) || ($res2 === false)) {
                Db::rollback();
                return errReturn(200, '更新失败');
            }

            Db::commit();
            return sucReturn(400, '更新成功');
        } catch (\Exception $e) {
            Db::rollback();
            return errReturn(400, $e->getMessage());
        }
    }
}
