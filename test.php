<?php
/**
 * Created by PhpStorm.
 * User: evolution
 * Date: 17-5-11
 * Time: 下午5:11
 */
date_default_timezone_set('Asia/Shanghai');
require __DIR__ . '/vendor/autoload.php';
$config = parse_ini_file('./config.ini', true);
$obj = new \Evolution\TimerWheel\Timer\TimingWheel($config);
$obj->start();