<?php
namespace app\server\controller;

use think\swoole\Server;

class TcpServ extends Server {

    protected $server;

    public function __construct()
    {
        $this->server = new \Swoole\Server('0.0.0.0', $port = 9501, $mode = SWOOLE_PROCESS, $sockType = SWOOLE_SOCK_TCP);
        $this->server->on('connect', function ($server, $fd){
            echo "Client:Connect.\n";
        });
        $this->server->set([
            'reactor_num'   => 12,     // reactor thread num
            'worker_num'    => 4,     // worker process num
            'backlog'       => 128,   // listen backlog
            'max_request'   => 50,
            'dispatch_mode' => 1,
            'daemonize'     => 1,
        ]);
        $this->server->on('receive', function ($server, $fd, $reactor_id, $data) {
            $server->send($fd, 'Swoole: '.$data);
            $server->close($fd);
        });
        $this->server->on('close', function ($server, $fd) {
            echo "Client: Close.\n";
        });
    }

    public function start() {
        $this->server->start();
    }

    public function reload() {
        $this->server->reload();
    }

    public function stop() {
        $this->server->stop();
    }

}
