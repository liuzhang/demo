<?php

class Worker
{
    /**
     * 子进程的数量
     * @var int
     */
    public $count = 4;

    /**
     * 子进程 pid 数组
     * @var array
     */
    private $forkList = [];

    /**
     * 主进程 ID 文件
     * @var string
     */
    private $masterPidFile = 'master.pid';

    /**
     * 主进程是否停止
     * @var int
     */
    private $masterStop = 0;


    public function __construct()
    {
        
    }

    public function start()
    {
        // 判断当前程序是否已经启动
        $masterPidFileExist = is_file($this->masterPidFile);
        if ($masterPidFileExist) {
            exit("当前程序已经在运行，请不要重启启动\n");
        }

        // 保存主进程pid到文件用于stop,reload,status等命令操作
        $masterPid = posix_getpid();
        file_put_contents($this->masterPidFile, $masterPid);


        pcntl_signal(SIGINT, array($this, 'signalHandler'), false);
        pcntl_signal(SIGTERM, array($this, 'signalHandler'), false);
        pcntl_signal(SIGUSR1, array($this, 'signalHandler'), false);

        // 创建count个子进程，用于接受请求和处理数据
        while(count($this->forkList) < $this->count) {
            $this->fork();
        }

        while (true)
        {
            //sleep(1);
            pcntl_signal_dispatch(); // 信号分发
            $status = 0;
            $pid = pcntl_wait($status, WUNTRACED); // 堵塞直至获取子进程退出或中断信号或调用一个信号处理器，或者没有子进程时返回错误
            pcntl_signal_dispatch();


            if ($pid > 0) {
                // 子进程退出
                echo "子进程退出pid：{$pid}\n";
                unset($this->forkList[$pid]);
                // 关闭还是重启
                if (!$this->masterStop) {
                    // 重启
                    $this->fork();
                }
            }
        }
    }

    /**
     * 主进程处理信号
     * @param $sigNo
     */
    public function signalHandler($sigNo)
    {
        switch ($sigNo) {
            // Stop.
            case SIGTERM:
            case SIGINT:
                // 退出，先发送子进程信号关闭子进程，再等待主进程退出
                foreach ($this->forkList as $pid) {
                    echo "关闭子进程pid：{$pid}\n" ;
                    posix_kill($pid, SIGKILL);
                }
                unlink($this->masterPidFile);
                $this->masterStop = 1;
                exit(250);
                break;

            case SIGUSR1:
                // 重启，关闭当前存在但子进程，主进程会监视退出的子进程并重启一个新子进程
                foreach ($this->forkList as $pid) {
                    echo "关闭子进程pid：{$pid}\n" ;
                    posix_kill($pid, SIGKILL);
                }
                break;

            default:
                // 处理所有其他信号
        }
    }


    public function fork()
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            die('子进程创建失败');
        } else if ($pid == 0) {
            while (1)
            {
                echo '子进程: '.posix_getpid()."\n";
                sleep(rand(1, 10));
            }
        } else {
            $this->forkList[$pid] = $pid;
        }
    }


    /**
     * 发送命令给主进程
     * @param $command
     */
    public function sendSignalToMaster($command)
    {
        $masterPid = file_get_contents($this->masterPidFile);

        if ($masterPid) {
            switch ($command) {
                case 'stop':
                    posix_kill($masterPid, SIGINT);
                    break;
                case 'reload':
                    posix_kill($masterPid, SIGUSR1);
                    break;
            }
            exit;
        } else {
            echo "主进程不存在\n";
            exit;
        }
    }
}


// 解析命令
$command  = isset($argv[1]) ? trim($argv[1]) : '';
$available_commands = array(
    'start',
    'stop',
    'reload',
    'status',
);
$usage = "Usage: php index.php {" . implode('|', $available_commands) . "}\n";
if (empty($command) || !in_array($command, $available_commands)) {
    exit($usage);
}

$worker = new Worker();

switch ($command) {
    case 'start':
        $worker->start();
        break;
    case 'stop':
    case 'reload':
    case 'status':
        $worker->sendSignalToMaster($command);
        break;
}

