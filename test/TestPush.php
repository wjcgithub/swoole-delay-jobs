<?php
/**
 * Created by PhpStorm.
 * User: evolution
 * Date: 17-4-8
 * Time: 上午11:07
 */
date_default_timezone_set('Asia/Shanghai');
require dirname(__DIR__) . '/vendor/autoload.php';
$config = parse_ini_file('./config.ini', true);
$client = new \Evolution\DJob\Client($config);

$redis = new \Evolution\DJob\Storage\Queue\Redis(['host'=>'127.0.0.1','port'=>6379]);
for ($i=0; $i<20000; $i++) {
    $dtime = rand(10,6000);
    $info = [
        'type' => 'delay',
        'isrepeat' => 0,
        'exectime' => $dtime,
        'json' => ['name'=>'lisi', 'sex'=>'zhangsan']
    ];

    $redis->lpush('delayqueue', json_encode($info));
    echo json_encode($info)."\r\n";
    sleep(1);
}
