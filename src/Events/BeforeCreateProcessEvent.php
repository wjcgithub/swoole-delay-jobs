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
class BeforeCreateProcessEvent extends AbstractEvent
{
    public function getName()
    {
        return "BeforeCreateProcessEvent";
    }
}