# swoole-delay-jobs
> 基于swoole实现的延迟任务

## 功能　&& 实现原理
1. 基于时间轮实现
2. 使用redis `zset` 做数据存储
3. 使用swoole做定时触发
4. 使用子进程做数据处理　（否）
5. 消息落地不丢失处理（否）
6. 超时任务处理（否）
7. 失败任务达到最大失败次数处理（否）
8. 消息幂等性设置（否）
9. 进程异常中断处理，从启后保证还能正常执行（否）

## 安装 && 启动
### 安装依赖
1. redis 扩展
2. seaslog 扩展
3. swoole > 1.8.9

### 安装程序
1. git clone git@github.com:wjcgithub/swoole-delay-jobs.git
2. cd swoole-delay-jobs
3. composer install

### 配置
> [time_wheel]
- slotLength=3600  ; 单位second
- tickDuration=1   ; 单位second

>[worker]
- worker_num=20

> [queue]
- default=redis
- redis[host]=127.0.0.1
- redis[port]=6379

### 启动测试
- php start.php
- php /test/TestPush.php

## SeasLog日志记录
```php
[SeasLog]
seaslog.default_basepath = /home/wwwlogs/seaslog
seaslog.default_logger = default
seaslog.disting_type = 1
seaslog.disting_by_hour = 0
seaslog.use_buffer = 0
seaslog.buffer_size = 20
seaslog.level = 0
seaslog.trace_error = 1
seaslog.trace_exception = 0
seaslog.default_datetime_format = "Y:m:d H:i:s"
seaslog.appender = 1
seaslog.remote_host = 127.0.0.1
seaslog.remote_port = 514
```