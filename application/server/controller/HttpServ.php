<?php
namespace app\server\controller;

use think\swoole\Server;

class HttpServ extends Server {

    protected $server;

    public function __construct()
    {
        $this->server = new \Swoole\Http\Server("0.0.0.0", 9502);
        $this->server->on('request', function ($request, $response) {
            $response->end("<h1>Hello Swoole. #".rand(1000, 9999)."</h1>");
        });
    }

    public function start() {
        $this->server->start();
    }


}