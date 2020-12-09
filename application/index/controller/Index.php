<?php
namespace app\index\controller;

use think\Controller;
use think\Cookie;
use think\Db;

class Index extends Controller
{

    public function index() {
        $adminInfo = Session('adminInfo');
        $roles = Config('roles');
        if (!$adminInfo) {
            $url = '/admin';
            if (Cookie::has('roles')) {
                switch (Cookie::get('roles')) {
                    case $roles['admin']:
                        $url = '/admin';
                        break;
                    case $roles['merchant']:
                        $url = '/merchant';
                        break;
                }
            }
            $this->redirect($url);
        }

        $this->assign('title', '内部管理系统');
        return $this->fetch();
    }

}
