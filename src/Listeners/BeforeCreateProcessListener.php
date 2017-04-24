<?php
namespace Evolution\DJob\Listeners;

use League\Event\AbstractListener;
use League\Event\EventInterface;

/**
 * Created by PhpStorm.
 * User: evolution
 * Date: 17-4-24
 * Time: 下午6:01
 */
class BeforeCreateProcessListener extends AbstractListener
{
    public function handle(EventInterface $event, $param = null)
    {
        echo "准备开启进程去处理任务\n";
        \SeasLog::debug("准备开启进程去处理任务\n");
    }
}