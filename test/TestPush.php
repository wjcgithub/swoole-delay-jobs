<?php
/**
 * Created by PhpStorm.
 * User: evolution
 * Date: 17-4-8
 * Time: 上午11:07
 */
date_default_timezone_set('Asia/Shanghai');
require __DIR__ . '../../vendor/autoload.php';
$config = parse_ini_file('./config.ini', true);
$client = new \Evolution\DJob\Client($config);
$info = [
    'delayTime' => 50,
    'json' => json_encode(['name'=>'lisi', 'sex'=>'zhangsan'])
];
$client->pushToSolt($info);
