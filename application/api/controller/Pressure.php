<?php
namespace app\api\controller;

use think\Controller;
use think\Db;

class Pressure extends Controller {

    public function index() {

        $data = [
            'product_id'    => isset($_POST['product_id']) ? $_POST['product_id'] : '',
            'amount'        => isset($_POST['amount']) ? $_POST['amount'] : '',
            'interval_time' => isset($_POST['interval_time']) ? $_POST['interval_time'] : '',
            'duration'      => isset($_POST['duration']) ? $_POST['duration'] : '',
        ];

        $rule = [
            ['product_id', 'require|number', '产品为空'],
            ['amount', 'require|number', '金额为空'],
            ['interval_time', 'require|number', '间隔期为空'],
            ['duration', 'require|number', '持续时间为空'],
        ];

        $res = checkAll($data, $rule);

        if ($res !== true) {

            return errReturn(400, $res);

        }
        $res = Db::name('pressure_task')->insertGetId($data);

        if ($res) {

            return sucReturn(200, '任务投递成功');

        }
        return errReturn(400,'任务投递失败');
    }

}