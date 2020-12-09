<?php
namespace app\index\controller;

class Upload extends Common {
    /**
     * 处理上传二维码
     */
    public function index() {
        $dir = input('dir');
        $file = request()->file('file');
        if($file) {
            $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads/' . $dir . '/');
            if ($info) {
                // 成功上传后 获取上传信息
                return [
                    'code' => 200,
                    'msg' => '上传成功',
                    'data' => [
                        'src' => '/uploads/' . $dir . '/' . $info->getSaveName(),
                    ]
                ];
            } else {
                // 上传失败获取错误信息
                return [
                    'code' => 400,
                    'msg' => '上传失败',
                    'data' => []
                ];
            }
        }
    }
}
