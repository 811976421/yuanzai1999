<?php
namespace app\index\model;
use think\Db;
use think\Model;


class Users extends Model {

    protected $table = 'center_admin_users';

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

    public function loginCheck($where) {
        $where['gid'] = 1;
        $info = Db::name('admin_user')->where($where)->find();
        if (!$info) {
            return errReturn(404, '账号或密码错误');
        }
        if (!$info['status']) {
            return errReturn(400, '该账号已被封禁');
        }
        $roles = Db::connect($this->connection)->name('roles')->where('id', $info['gid'])->find();
      
        if (!$roles['status']) {
            return errReturn(400, '该角色暂停使用');
        }
        $info['role_id'] = $info['gid'];
        $info['role_name'] = $roles['role_name'];
        $info['alias'] = $roles['alias'];
        unset($info['password']);
        return sucReturn(200, '登录成功', $info);
    }

}
