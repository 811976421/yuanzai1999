<?php
namespace app\index\command;

use app\index\common\Ntps;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\console\input\Option;

class Swoole extends Command {

    const ACTION_START = 'start';
    const ACTION_SHUTDOWN = 'stop';
    const ACTION_RELOAD = 'reload';

    protected function configure()
    {
        $this->setName('swoole')->setDescription('swoole manager');
        $this->addArgument('action', Argument::REQUIRED, 'start|shutDown|reload');
        $this->addOption('host','host',Option::VALUE_OPTIONAL,'listen ip');
        $this->addOption('port','port',Option::VALUE_OPTIONAL,'listen port');
    }

    protected function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');
        $host = $input->getOption('host');
        $port = $input->getOption('port');
        switch ($action){
            case self::ACTION_START:
                $this->start($host, $port);
                break;
            case self::ACTION_SHUTDOWN:
                $this->shutDown();
                break;
            case self::ACTION_RELOAD:
                $this->reload();
                break;
            default:
                break;
        }
    }

    private function start($host, $port)
    {
        $setting = config('swoole.setting');
        $tcp_config = config('swoole.tcp');
        $host = $host ?: $tcp_config['host'];
        $port = $port ?: $tcp_config['port'];
        $ntps = new Ntps($setting, $host, $port);
        $ntps->run();
    }

    private function shutDown()
    {
        echo "\n------------swoole start shutdown--------\n";
        $this->cmd('_shutdown');
        echo "\n------------swoole end shutdown--------\n";
    }

    private function reload()
    {
        echo "\n------------swoole start reload--------\n";
        $this->cmd('_reload');
        echo "\n------------swoole end reload--------\n";
    }

    private function cmd($cmd = '')
    {
        $tcp_config = config('swoole.tcp');
        $client = stream_socket_client('tcp://127.0.0.1:' . $tcp_config['port'], $errno,$errstr,1);
        $content = json_encode([
            'cmd' => $cmd,
        ]);
        fwrite($client,$content,strlen($content));
        $res = fread($client, 8180);
        echo $res;
        fclose($client);
    }

}
