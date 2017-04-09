<?php
/**
 * Created by PhpStorm.
 * User: evolution
 * Date: 17-4-7
 * Time: 下午2:33
 */

namespace Evolution\DJob;

use Evolution\DJob\Storage\Queue\Redis;

class Client
{
    private $queue=null;
    private $config=[];
    private $slotLength=0;

    public function __construct($config)
    {
        $this->config = $config;
        $this->slotLength = $this->config['time_wheel']['slotLength'];
        $this->queue = new Redis($this->config['queue'][$this->config['queue']['default']]);
    }

    /**
     * add task to solt of time wheel
     *
     * @param array $param
     */
    public function pushToSolt(array $param)
    {
        $delayTime = $param['delayTime'];
        //计算周期
        $ptr = $this->queue->get('ptr');
        $offsetDelayTime = $delayTime+$ptr;
        $cycle = floor($delayTime / $this->slotLength);
        $solt = $offsetDelayTime % $this->slotLength;
        $info = [
            'cycle' => $cycle,
            'json' => $param['json'],
            'jobid' => $this->uuid()
        ];
        $this->queue->zadd($solt,$cycle,json_encode($info));
    }

    /**
     * generate uuid of jobs
     *
     * @return string
     */
    protected function uuid()
    {
        $len     = 20;
        $hashStr = substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz0123456789', $len)), 0, $len);
        $uuid = md5(uniqid($hashStr, true) . microtime(true) . mt_rand(0, 1000));
        return $uuid;
    }
}