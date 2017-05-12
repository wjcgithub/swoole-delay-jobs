<?php
namespace Evolution\DJob\Storage\Zk;

/**
 * Created by PhpStorm.
 * User: evolution
 * Date: 17-5-10
 * Time: 下午2:17
 */
class Zk
{
    const CONTAINER = '/cluster' ;
    static public $acl = array(
        array (
            'perms' => \Zookeeper:: PERM_ALL,
            'scheme' => 'world' ,
            'id' => 'anyone' ) );
    static public $zookeeper=NULL;

    static public function connectZk($hosts, $recv_timeout)
    {
        try{
            if(empty(self::$zookeeper)){
                echo "\r\n zookeeper 创建新链接 \r\n";
                self::$zookeeper = new \Zookeeper($hosts,['Evolution\DJob\Storage\Zk\Zk','zkcb'],$recv_timeout);
            }else if(!self::$zookeeper->getState()){
                self::$zookeeper = new \Zookeeper($hosts,['Evolution\DJob\Storage\Zk\Zk','zkcb'],$recv_timeout);
            }
            self::$zookeeper->setWatcher(['Evolution\DJob\Storage\Zk\Zk','allCb']);

            return self::$zookeeper;
        } catch (\Exception $e) {
            self::createZk();
        }
    }

    static public function zkcb($status){
        echo "\r\nzk －－＞　　connection success\r\n";
        echo "\r\nzk cb ->".$status."\r\n";
    }

    static public function allCb($a=null,$b=null,$c=null)
    {
        echo "\r\n allcb \r\n";
        print_r('a'.$a);
        print_r('b'.$b);
        print_r('c'.$c);
        echo "\r\n allcb \r\n";
    }
}