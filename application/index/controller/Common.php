<?php
namespace app\index\controller;

use think\Controller;
use think\Db;
use think\Request;
use think\Session;

class Common extends Controller {

    protected $roleId;

    protected $sonIds = [];

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

    protected function _initialize()
    {
        $adminInfo = Session('adminInfo');

        $refresh = 0;

        if (!$adminInfo) {

            $refresh = 1;

        } else {

            $this->roleId = $adminInfo['role_id'];

            $request = Request::instance();
            $controller = $request->controller();
            $action = $request->action();
            $alias = strtolower($controller) . '/' . strtolower($action);
            $isExist = Db::connect($this->connection)->table('center_role_rule')->alias('r')
                ->join('center_rules t', 'r.rule_id = t.id')
                ->where('r.role_id', $this->roleId)
                ->where('t.status', '1')
                ->where('t.rule_alias', $alias)
                ->find();
            if (!$isExist) {
                if ($request->isAjax()) {

                    echo errReturn(400, '暂无权限', 1);
                    die();
                }
                $this->error('暂无权限');
            }
        }

        $this->assign('refresh', $refresh);
        $this->assign('role_id', $this->roleId);
    }

    protected function getSons($ids) {

        $sonIds = Db::name('user')->where('pid', 'in', $ids)->column('id');

        if (empty($sonIds)) {

            return;

        } else {
            $this->sonIds = array_merge($sonIds, $this->sonIds);
            $this->getSons($sonIds);
        }
    }
}
