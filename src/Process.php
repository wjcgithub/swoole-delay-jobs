<?php
namespace Evolution\DJob;
use Evolution\DJob\Events\AfterCreateProcessEvent;
use Evolution\DJob\Events\BeforeCreateProcessEvent;
use Evolution\DJob\Events\ECommon;
use Evolution\DJob\Storage\Queue\Redis;
use Evolution\DJob\Storage\Zk\Zk;

/**
 * Created by PhpStorm.
 * User: evolution
 * Date: 17-4-7
 * Time: 上午10:51
 */
class Process
{
    //master进程id
    public $mpid=0;
    //子进程worker数组
    public $works=[];
    //最大子进程数
    private $worker_num = 5;
    //子进程索引号码
    public static $new_index=0;
    //处理队列进程的进程id
    private $queueWorkPid = 0;
    //独立缓冲队列
    private $queueWorkerName = 'queuework';
    private $config=[];
    private $ptr=1;
    private $slotLength = 0;
    private $tickDuration = 1;
    private $queue = null;
    private $emitter = null;
    private $masterZk = [];
    private $agentZk = [];

    public function __construct($config)
    {
        try {
            $this->config = $config;
            $this->worker_num = $this->config['worker']['worker_num'];
            $this->slotLength = $this->config['time_wheel']['slotLength'];
            $this->tickDuration = $this->config['time_wheel']['tickDuration'] * 1000;
            $this->masterZk['connect'] = explode(',', $this->config['master']['zk']['zkConnect']);
            $this->masterZk['sessiontimeoutms'] = $this->config['master']['zk']['zkSessionTimeoutMs'];
            $this->masterZk['connectiontimeoutms'] = $this->config['master']['zk']['zkConnectionTimeoutMs'];
            $this->agentZk['connect'] = explode(',', $this->config['agent']['zk']['zkConnect']);
            $this->agentZk['sessiontimeoutms'] = $this->config['agent']['zk']['zkSessionTimeoutMs'];
            $this->agentZk['connectiontimeoutms'] = $this->config['agent']['zk']['zkConnectionTimeoutMs'];
            $this->init();
        } catch (\Exception $e) {
            die('ALL ERROR: '. $e->getMessage());
        }
    }

    /**
     * 初始化
     */
    private function init()
    {
        $this->queue = new Redis($this->config['queue'][$this->config['queue']['default']]);
        $this->emitter = new \League\Event\Emitter();
        $ptr = $this->queue->get('ptr');
        $this->ptr = empty($ptr) ? $this->ptr : $ptr;
        swoole_set_process_name(sprintf('djobs-timer:%s', 'master'));
        $this->mpid = posix_getpid();
        $this->registerEvent();
        $this->run();
        $this->registSignal();
    }

    /**
     * register event
     */
    public function registerEvent()
    {
        $this->emitter->addListener('BeforeCreateProcessEvent', new \Evolution\DJob\Listeners\BeforeCreateProcessListener());
        $this->emitter->addListener('AfterCreateProcessEvent', new \Evolution\DJob\Listeners\AfterCreateProcessListener());
    }

    /**
     * start run
     */
    private function run()
    {
        $this->zookeeper = Zk::connectZk(implode(',',$this->masterZk['connect']),$this->masterZk['sessiontimeoutms']);
        $this->registerToZk();
        $this->startTimer();
    }

    private function registerToZk()
    {
        try{
            $this->startReg();
        } catch (\ZookeeperSessionException $e){
            $this->startReg();
        } catch (\ZookeeperConnectionException $e){
            $this->startReg();
        } catch (\Exception $e) {
            print "exception----".$e->getMessage();
            $this->startReg();
        }
    }

    private function startReg()
    {
        if( ! $this->exists( Zk ::CONTAINER ) ) {
            $this->create( ZK ::CONTAINER , null, Zk::$acl );
        }
        while (1) {
//            $this->zookeeper->isRecoverable();
            echo "\r\n======recv======{$this->zookeeper->getRecvTimeout()}====\r\n";
            echo "\r\n================\r\n";
            $aclArray = array(
                array(
                    'perms'  => \Zookeeper::PERM_ALL,
                    'scheme' => 'world',
                    'id'     => 'anyone',
                )
            );
            $path = '/wjc/test'.time();
            $realPath = $this->zookeeper->create($path, null, $aclArray, \Zookeeper::EPHEMERAL);
            if ($realPath)
                echo $realPath;
            else
                echo 'ERR';

            echo "\r\n zk status \r\n";
            print_r($this->zookeeper->getState());
            echo "\r\n zk status \r\n";
            sleep(20);
        }
    }

    private function startTimer()
    {
        swoole_timer_tick($this->tickDuration, function () {
            \SeasLog::debug("ptr = {$this->ptr}\n");
            try {
                $starttime = explode(' ',microtime());
                $waitProcessList = $this->queue->zRangeByScore($this->ptr, 0, 0);
                $this->queue->zRemRangeByScore($this->ptr, 0, 0);
                $list = $this->queue->zRange($this->ptr, 0, -1);
                foreach ($list as $key => $val) {
                    $this->queue->zIncrBy($this->ptr, -1, $val);
                }
                \SeasLog::debug('默认工作进程数：'.$this->worker_num .'---当前工作进程数'. count($this->works)."\n");
                if ($this->worker_num > count($this->works) && !empty($waitProcessList)) {
                    $this->emitter->emit(new BeforeCreateProcessEvent());
                    $process = $this->CreateProcess(json_encode($waitProcessList));
                    $this->emitter->emit(new AfterCreateProcessEvent($process->pid));
                } else if ($this->worker_num <= count($this->works) && !empty($waitProcessList)) {
                    //当前执行进程太多，将信息入队列
                    \SeasLog::debug("进入worker队列, info => {$waitProcessList} \n");
                    $this->queue->push($this->queueWorkerName,json_encode($waitProcessList));
                }

                //指针++ && save
                if($this->ptr >= $this->slotLength){
                    $this->ptr=1;
                }else{
                    $this->ptr++;
                }
                $this->queue->set('ptr', $this->ptr);

                $endtime = explode(' ',microtime());
                $thistime = $endtime[0]+$endtime[1]-($starttime[0]+$starttime[1]);
                $thistime = round($thistime,3);
                \SeasLog::debug("本网页执行耗时：".$thistime." 秒。".time()."\n");
            } catch (\Exception $e) {
                \SeasLog::setLogger('error');
                \SeasLog::error('处理任务失败:失败信息是：'.$e->getTraceAsString().'msg:'.$e->getMessage().'line:'.$e->getLine());
            }
        });
    }

    //创建子进程
    public function CreateProcess($list, $index=null)
    {
        if (is_null($index)) {
            $index = self::$new_index;
            self::$new_index++;
        }
        $process = new \Swoole\Process(function (\Swoole\Process $worker) use ($list,$index){
            //设置子进程名字
            swoole_set_process_name(sprintf('djobs-timer-child:%s', $index));
            \SeasLog::debug("\n任务列表：====\n\n");
            \SeasLog::debug($list);
            \SeasLog::debug("\n任务列表：====\n\n");
            \SeasLog::debug("进程{$worker->pid}处理完毕---{$index}\n");
            $worker->exit($index);
        },0,0);

        $pid = $process->start();
        $this->works[$index] = $pid;
        return $process;
    }

    /**
     * 判断如果主进程退出了，那么子进程要主动退出
     * @param $worker
     */
    public function checkMpid(&$worker)
    {
        if (!\Swoole\Process::kill($this->mpid, 0)) {
            $worker->exit();
            \SeasLog::debug("Master process exited, I [{$worker['pid']} also quit\n");
        }
    }

    //监控子进程
    public function registSignal()
    {
        \Swoole\Process::signal(SIGTERM, function ($signo) {
            $this->exitMaster();
        });
        $workers = $this->works;
        \Swoole\Process::signal(SIGCHLD, function ($signo) use (&$workers) {
            while ($ret = \Swoole\Process::wait(false)) {
                if ($ret) {
                    $pid           = $ret['pid'];
                    $code         = $ret['code'];
                    \SeasLog::debug("Worker Exit, kill_signal={$ret['signal']} PID=" . $pid . PHP_EOL);
                    unset($this->works[$code]);
                }
            }
        });
    }

    private function exitMaster()
    {
        \SeasLog::debug("Master quit\n");
        \SeasLog::setLogger('djobs_master');
        \SeasLog::error("Time: " . microtime(true) . "主进程退出" . "\n");
        exit();
    }
}
