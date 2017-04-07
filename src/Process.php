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
    private $worker_num = 0;
    //子进程索引号码
    public static $new_index=0;
    //处理队列进程的进程id
    private $queueWorkPid = 0;
    private $queueWorkerName = 'queuework';
    //config
    private $config=[];
    private $ptr=1;
    private $timeWheel = [];
    private $timeWheelLen = 0;
    private $queue = null;

    public function __construct($config)
    {
        try {
            $this->config = $config;
            $this->worker_num = $this->config['worker']['worker_num'];
            $this->timeWheelLen = $this->config['time_wheel']['length'];
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
        swoole_timer_tick(1000, function () {
            if($this->ptr >= $this->timeWheelLen){
                $this->ptr=1;
            }else{
                $this->ptr++;
            }

            if(!empty($this->timeWheel[$this->ptr])){
                $cycle = $this->timeWheel[$this->ptr]['cycle'];
                if($cycle<=0){
                    //立即执行或者永久执行
                    $redisKey = $this->timeWheel[$this->ptr]['key'];
                    if ($this->worker_num < count($this->works)) {
                        $process = $this->CreateProcess($redisKey);
                        SeasLog::info("开启进程{$process->pid}处理任务\n");
                    } else {
                        //当前执行进程太多，将信息入队列
                        $this->queue->push($this->queueWorkerName,$this->queue->getAll($redisKey));
                    }
                } else if ($cycle>0){
                    $this->timeWheel[$this->ptr]['cycle'] = $cycle-1;
                }
            }
        });
    }

    //创建子进程
    public function CreateProcess($redisKey, $index=null)
    {
        if (is_null($index)) {
            $index = self::$new_index;
            self::$new_index++;
        }
        $json = $this->queue->getAll($redisKey);
        $process = new \Swoole\Process(function (\Swoole\Process $worker) use ($json,$index){
            //设置子进程名字
            swoole_set_process_name(sprintf('djobs-timer-child:%s', $index));
            print_r('djobs-timer-child:%s', $index.'--'.$json);
            sleep(10);
            SeasLog::info("进程{$worker->pid}处理完毕\n");
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