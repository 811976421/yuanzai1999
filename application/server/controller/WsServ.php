<?php
namespace app\server\controller;

use think\swoole\Server;

class WsServ extends Server {

    protected $server;

    public function __construct()
    {
        $this->server = new \Swoole\WebSocket\Server("0.0.0.0", 9503);

        $this->server->on('open', function (Swoole\WebSocket\Server $server, $request) {
            echo "server: handshake success with fd{$request->fd}\n";
        });

        $this->server->on('message', function (Swoole\WebSocket\Server $server, $frame) {
            echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
            $server->push($frame->fd, "this is server");
        });

        $this->server->on('request', function (Swoole\Http\Request $request, Swoole\Http\Response $response) {
            global $server;//调用外部的server
            // $server->connections 遍历所有websocket连接用户的fd，给所有用户推送
            foreach ($server->connections as $fd) {
                // 需要先判断是否是正确的websocket连接，否则有可能会push失败
                if ($server->isEstablished($fd)) {
                    $server->push($fd, $request->get['message']);
                }
            }
        });

        $this->server->on('close', function ($ser, $fd) {
            echo "client {$fd} closed\n";
        });
    }

    public function start()
    {
        $this->server->start();
    }

}
