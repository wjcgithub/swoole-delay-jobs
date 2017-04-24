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
class AfterCreateProcessListener extends AbstractListener
{
    public function handle(EventInterface $event, $param = null)
    {
        echo "进程创建完毕，pid:{$event->getPid()}\n";
        \SeasLog::debug("进程创建完毕，pid:{$event->getPid()}\n");
    }
}