<?php
/**
 * Created by PhpStorm.
 * User: evolution
 * Date: 17-4-7
 * Time: 上午10:03
 */

namespace Evolution\DJob\Storage\Queue;


class Redis extends IQueue
{
    private $redis = null;
    private $prefix = 'djobs_';

    public function __construct(array  $config)
    {
        $this->redis = new \Redis();
        try{
            $this->redis->connect($config['host'], $config['port']);
        }catch (\Exception $e){
            SeasLog::error('Redis连接失败' . $e->getMessage().'--'.$e->getTraceAsString());
        }
    }

    public function push($key, $value)
    {
        return $this->redis->rPush($this->prefix.$key, $value);
    }

    public function pop($key)
    {
        return $this->redis->lPop($this->prefix.$key);
    }

    public function getAll($key)
    {
        $json = $this->redis->lRange($key,0,-1);
        if(!$this->redis->del($key)){
            SeasLog::error('删除键'.$key.'失败'.'----数据：'.$json);
        }
        return $json;
    }
}