<?php
namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class Email extends Command {


    protected function configure()
    {
        $this->setName('email')->setDescription('send email');
    }

    protected function execute(Input $input, Output $output)
    {
        $list = Db::name('email_task')->where('status', '0')->select();
        foreach ($list as $v) {
            sendEmail($v['subject'], $v['content']);
            Db::name('email_task')->where('id', $v['id'])->setField('status', '1');
        }
    }

}
