<?php
namespace app\server\controller;

use think\swoole\Server;

class RsServ extends Server {

    protected $server;

    public function __construct()
    {
        $this->server = new \Swoole\Redis\Server("0.0.0.0", '9504');
        if (is_file(DB_FILE)) {
            $this->server->data = unserialize(file_get_contents(DB_FILE));
        } else {
            $this->server->data = array();
        }

        $this->server->setHandler('GET', function ($fd, $data) {
            if (count($data) == 0) {
                return  $this->server->send($fd, Server::format(Server::ERROR, "ERR wrong number of arguments for 'GET' command"));
            }

            $key = $data[0];
            if (empty($this->server->data[$key])) {
                return $this->server->send($fd, Server::format(Server::NIL));
            } else {
                return $this->server->send($fd, Server::format(Server::STRING, $this->server->data[$key]));
            }
        });

        $this->server->setHandler('SET', function ($fd, $data) {
            if (count($data) < 2) {
                return $this->server->send($fd, Server::format(Server::ERROR, "ERR wrong number of arguments for 'SET' command"));
            }

            $key = $data[0];
            $this->server->data[$key] = $data[1];
            return $this->server->send($fd, Server::format(Server::STATUS, "OK"));
        });

        $this->server->setHandler('sAdd', function ($fd, $data) {
            if (count($data) < 2) {
                return $this->server->send($fd, Server::format(Server::ERROR, "ERR wrong number of arguments for 'sAdd' command"));
            }

            $key = $data[0];
            if (!isset($server->data[$key])) {
                $array[$key] = array();
            }

            $count = 0;
            for ($i = 1; $i < count($data); $i++) {
                $value = $data[$i];
                if (!isset($server->data[$key][$value])) {
                    $this->server->data[$key][$value] = 1;
                    $count++;
                }
            }

            return $this->server->send($fd, Server::format(Server::INT, $count));
        });

        $this->server->setHandler('sMembers', function ($fd, $data)  {
            if (count($data) < 1) {
                return $this->server->send($fd, Server::format(Server::ERROR, "ERR wrong number of arguments for 'sMembers' command"));
            }
            $key = $data[0];
            if (!isset($server->data[$key])) {
                return $this->server->send($fd, Server::format(Server::NIL));
            }
            return $this->server->send($fd, Server::format(Server::SET, array_keys($server->data[$key])));
        });

        $this->server->setHandler('hSet', function ($fd, $data) {
            if (count($data) < 3) {
                return $this->server->send($fd, Server::format(Server::ERROR, "ERR wrong number of arguments for 'hSet' command"));
            }

            $key = $data[0];
            if (!isset($server->data[$key])) {
                $array[$key] = array();
            }
            $field = $data[1];
            $value = $data[2];
            $count = !isset($server->data[$key][$field]) ? 1 : 0;
            $this->server->data[$key][$field] = $value;
            return $this->server->send($fd, Server::format(Server::INT, $count));
        });

        $this->server->setHandler('hGetAll', function ($fd, $data) {
            if (count($data) < 1) {
                return $this->server->send($fd, Server::format(Server::ERROR, "ERR wrong number of arguments for 'hGetAll' command"));
            }
            $key = $data[0];
            if (!isset($server->data[$key])) {
                return $this->server->send($fd, Server::format(Server::NIL));
            }
            return $this->server->send($fd, Server::format(Server::MAP, $server->data[$key]));
        });

        $this->server->on('WorkerStart', function ($server) {
            $server->tick(10000, function () use ($server) {
                file_put_contents(DB_FILE, serialize($server->data));
            });
        });

        $this->server->start();
    }

}
