<?php
/**
 * Created by PhpStorm.
 * User: evolution
 * Date: 17-4-7
 * Time: 下午2:33
 */

namespace Evolution\DJob;


class Client
{
    private $queue=null;
    private $config=[];

    public function __construct($config)
    {
        $this->config = $config;
        $this->queue = new Redis($this->config['queue'][$this->config['queue']['default']]);
    }

    public function pushToSolt(array $info)
    {
        $delayTime = $info['delayTime'];
        $json = $info['json'];
    }
}