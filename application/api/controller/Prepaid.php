<?php
namespace app\api\controller;

use think\Controller;
use think\Db;
use think\Request;

class Prepaid extends Controller {
    
    public function index() {
        
        $info = Db::table('cxxia_user')->where(['mobile' => input('mobile'), 'grade_id' => ['>=', input('grade_id')], 'status' => '1'])->find();
        
        if($info) {
            return '1';
        } else {
            return '0';
        }
    }
    
}