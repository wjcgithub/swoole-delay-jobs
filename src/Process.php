<?php
namespace Evolution\DJob;
use Evolution\DJob\Storage\Queue\Redis;

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
    private $queueWorkerName = 'queuework';
    //config
    private $config=[];
    private $ptr=1;
    private $timeWheel = [];
    private $slotLength = 0;
    private $tickDuration = 1;
    private $queue = null;

    public function __construct($config)
    {
        try {
            $this->config = $config;
            $this->worker_num = $this->config['worker']['worker_num'];
            $this->slotLength = $this->config['time_wheel']['slotLength'];
            $this->tickDuration = $this->config['time_wheel']['tickDuration'] * 1000;
            $this->queue = new Redis($this->config['queue'][$this->config['queue']['default']]);
            swoole_set_process_name(sprintf('djobs-timer:%s', 'master'));
            $this->mpid = posix_getpid();
            $this->run();
            $this->processWait();
        } catch (\Exception $e) {
            die('ALL ERROR: '. $e->getMessage());
        }
    }

    //开始运行时间轮
    public function run()
    {
        swoole_timer_tick($this->tickDuration, function () {
            echo "ptr = {$this->ptr}\n";
            try {
                $starttime = explode(' ',microtime());
                $waitProcessList = $this->queue->zRangeByScore($this->ptr, 0, 0);
                $this->queue->zRemRangeByScore($this->ptr, 0, 0);
                $list = $this->queue->zRange($this->ptr, 0, -1);
                foreach ($list as $key => $val) {
                    $this->queue->zIncrBy($this->ptr, -1, $val);
                }
                echo '默认工作进程数：'.$this->worker_num .'---当前工作进程数'. count($this->works)."\n";
                if ($this->worker_num > count($this->works) && !empty($waitProcessList)) {
                    $process = $this->CreateProcess(json_encode($waitProcessList));
                    echo "开启进程{$process->pid}处理任务\n";
                    \SeasLog::info("开启进程{$process->pid}\n");
                } else if ($this->worker_num <= count($this->works) && !empty($waitProcessList)) {
                    //当前执行进程太多，将信息入队列
                    echo "进入worker队列\n";
                    \SeasLog::info("进入worker队列\n");
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
                echo "本网页执行耗时：".$thistime." 秒。".time()."\n";
            } catch (\Exception $e) {
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
            print_r('djobs-timer-child:%s', $index.'--'.$list);
            sleep(10);
            \SeasLog::info("进程{$worker->pid}处理完毕---{$index}\n");
            print_r($this->works);
            unset($this->works[$index]);
            $worker->exit();
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
            echo "Master process exited, I [{$worker['pid']} also quit\n]";
        }
    }

    /**
     * 重启进程
     * @param $ret
     */
    public function rebootProcess($ret)
    {
        $pid = $ret['pid'];
        $index = array_search($pid, $this->works);
        if ($index !== false) {
            $index=intval($index);
            $process=$this->CreateProcess($index);
            echo "rebootProcess: {$index}={$process->pid} Done\n";
            return;
        }
    }

    /**
     * 回收结束运行的子进程
     */
    public function processWait()
    {
        while (1) {
            if (count($this->works)) {
                $ret = \Swoole\Process::wait();
                if($ret){
                    $this->rebootProcess($ret);
                }
            } else {
                break;
            }
        }
    }
}
