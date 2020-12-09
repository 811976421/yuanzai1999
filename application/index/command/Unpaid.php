<?php
namespace app\index\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class Unpaid extends Command {

    protected function configure()
    {
        $this->setName('unpaid')->setDescription('unpaid police');
    }


    protected function execute(Input $input, Output $output)
    {

    }
}