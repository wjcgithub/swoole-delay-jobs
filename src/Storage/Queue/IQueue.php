<?php
/**
 * Created by PhpStorm.
 * User: evolution
 * Date: 17-4-6
 * Time: 下午7:19
 */
namespace Evolution\DJob\Storage\Queue;

abstract class IQueue
{
    public function push($key, $value){}

    public function pop($key){}

    public function uuid()
    {
        $len     = 20;
        $hashStr = substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz0123456789', $len)), 0, $len);
        $uuid = md5(uniqid($hashStr, true) . microtime(true) . mt_rand(0, 1000));
        return $uuid;
    }
}