<?php
/**
 * Created by PhpStorm.
 * User: evolution
 * Date: 17-4-7
 * Time: 上午11:04
 */
date_default_timezone_set('Asia/Shanghai');
require __DIR__ . '/vendor/autoload.php';
$config = parse_ini_file('./config.ini', true);
$server = new \Evolution\DJob\Process($config);