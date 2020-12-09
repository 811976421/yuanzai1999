<?php
namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class ClearNotice extends Command {

    protected function configure()
    {
        $this->setName('ClearNotice')->setDescription('week clear notice');
    }

    protected function execute(Input $input, Output $output)
    {
        Db::name('notice')
            ->where('role_id','>','1')
            ->whereTime('create_time', '<',  date('Y-m-d', strtotime('-7 days')))
            ->delete();
    }

}