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

for ($i=0; $i<10; $i++) {
    $dtime = rand(1,100000);
    $info = [
        'delayTime' => $dtime,
        'json' => ['name'=>'lisi', 'sex'=>'zhangsan']
    ];
    $client->pushToSolt($info);
    echo $dtime."\n";
    sleep(1);
}
