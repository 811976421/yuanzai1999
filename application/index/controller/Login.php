<?php
namespace app\index\controller;

use app\index\model\Members;
use app\index\model\Merchants;
use app\index\model\Users;
use think\Cookie;
use think\Controller;
use think\Db;
use think\Request;


class Login extends Controller {

    private $ip = null;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->ip = Request::instance()->ip();
    }

    /**
     * 登录
     * @return mixed
     */
    public function index() {
       $adminInfo = Session('adminInfo');

       if ($adminInfo) {
           $this->redirect('/index/index/index');
       }

        return $this->fetch();
    }

    public function merchant() {

        $adminInfo = Session('adminInfo');

        if ($adminInfo) {
            $this->redirect('/index/index/index');
        }
        return $this->fetch();
    }

    /**
     *
     * 登录校验
     * @return false|string
     */
    public function check() {
        $account = trim(input('account'));
        $password = trim(input('password'));
        $where['username'] = $account;
        $where['password'] = MD5($password);
        $user = new Users();
        $info = $user->loginCheck($where);
        if ($info['code'] == 200) {
            Cookie::set('roles', Config('roles')['admin']);
            Session('adminInfo', $info['data']);
        }
        return $info;
    }

    /**
     * 商户登录校验
     */
    public function checkMerchant() {
        $account = trim(input('account'));
        $password = trim(input('password'));
        $where['mobile'] = $account;
        $where['password'] = MD5($password);
        $merchant = new Merchants();
        $info = $merchant->loginCheck($where);
        if ($info['code'] == 200) {
            Cookie::set('roles', Config('roles')['merchant']);
            Session('adminInfo', $info['data']);
        }
        return $info;
    }


    public function logout() {
        $adminInfo = Session('adminInfo');
        $roleId = isset($adminInfo['role_id']) ? $adminInfo['role_id'] : '';
        $roles = Config('roles');
        session('adminInfo', null);
        switch ($roleId) {
            case $roles['admin']:
                $this->redirect('/admin');
                break;
            case $roles['merchant']:
                $this->redirect('/merchant');
                break;
            default:
                $this->redirect('/');
                break;
        }
    }

}
