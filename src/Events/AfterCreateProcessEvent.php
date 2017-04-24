<?php
namespace Evolution\DJob\Events;
use League\Event\AbstractEvent;
use League\Event\EventInterface;

/**
 * Created by PhpStorm.
 * User: evolution
 * Date: 17-4-24
 * Time: 下午6:21
 */
class AfterCreateProcessEvent extends AbstractEvent
{
    private $pid;

    public function __construct($pid)
    {
        $this->pid = $pid;
    }

    public function getPid()
    {
        return $this->pid;
    }

    /**
     * 可使用getName自定义事件名称
     * @return string
     */
    public function getName()
    {
        return "AfterCreateProcessEvent";
    }
}