<?php
require_once 'Http.php';

class Worker
{
    /**
     * version
     * @var string
     */
    const VERSION = '0.0.1';

    /**
     * Emitted when data is received
     * @var callable
     */
    public $onMessage = NULL;

    /**
     * 子进程的数量
     * @var int
     */
    public $count = 1;

    /**
     * Listening socket
     * @var resource
     */
    public $socket = NULL;

    /**
     * Socket name The format is like this 0.0.0.0:8000
     * @var string
     */
    protected $_socketName = '';

    /**
     * 连接socket
     * @var resource
     */
    protected $_connSocket = NULL;

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

    public function __construct($socketName, $options = [])
    {
        $this->_socketName = $socketName;
    }

    public function init()
    {
        $this->parseCommand();
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

        $this->listen();

        // 创建count个子进程，用于接受请求和处理数据
        while(count($this->forkList) < $this->count) {
            $this->forkWokrer();
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
                    $this->forkWokrer();
                }
            }
        }
    }

    public function listen()
    {
        // 主进程创建tcp服务器
        $errno = 0;
        $errmsg = '';
        $socket = stream_socket_server($this->_socketName, $errno, $errmsg);

        // 尝试打开KeepAlive TCP和禁用Nagle算法。
        if (function_exists('socket_import_stream'))
        {
            $socketImport = socket_import_stream($socket);
            @socket_set_option($socketImport, SOL_SOCKET, SO_KEEPALIVE, 1);
            @socket_set_option($socketImport, SOL_TCP, TCP_NODELAY, 1);
        }
        // Non blocking.
        stream_set_blocking($socket, 0);

        $this->socket = $socket;
    }

    public function accept()
    {
        $base = event_base_new();
        $event = event_new();

        event_set($event, $this->socket, EV_READ | EV_PERSIST, [$this, 'acceptConnect'], [$event, $base]);
        event_base_set($event, $base);
        event_add($event);
        event_base_loop($base);
    }


    public function acceptConnect($socket, $events, $arg)
    {
        $newSocket = @stream_socket_accept($socket, 0);

        if (!$newSocket)
        {
            return;
        }

        echo "acceptConnect\n";

        stream_set_blocking($newSocket, 0);

        // 子进程添加一个事件在newSocket文件描述符上
        $event = event_new();
        // 设置event监听事件，监听newSocket文件描述符，事件为EV_READ：可读，EV_PERSIST：持续化（断开连接可被监听到）
        event_set($event, $newSocket, EV_READ | EV_PERSIST, array($this, "handleData"), array($event, $arg[1]));
        event_base_set($event, $arg[1]);
        event_add($event);
    }

    public function handleData($newSocket, $events, $arg)
    {
        $this->_connSocket = $newSocket;
        $buffer = @fread($newSocket,65535);

        if ($buffer === '' || $buffer === false) {
            if (feof($newSocket) || !is_resource($newSocket) || $buffer === false) {
                echo "客户端关闭\n";
                event_del($arg[0]); //关闭连接事件
                @fclose($this->_connSocket); // 关闭连接
                return;
            }
        }
        
        call_user_func($this->onMessage, $this); // 调用处理函数
    }


    public function send($data)
    {
        $msg = Http::encode($data); // http编码
        fwrite($this->_connSocket, $msg, 8192);
        return true;
    }

    public function forkWokrer()
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            die('子进程创建失败');
        } else if ($pid == 0) {
            //处理逻辑
            $this->accept();
        } else {
            $this->forkList[$pid] = $pid;
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

    protected function parseCommand()
    {
        global $argv;

        $available_commands = array(
            'start',
            'stop',
            'restart',
            'reload',
            'status',
            'connections',
        );
        $command  = isset($argv[1]) ? trim($argv[1]) : '';
        $usage = "Usage: php index.php {" . implode('|', $available_commands) . "}\n";
        if (empty($command) || !in_array($command, $available_commands)) {
            exit($usage);
        }

        switch ($command) {
            case 'start':
                $this->start();
                break;
            case 'stop':
            case 'reload':
            case 'status':
                $this->sendSignalToMaster($command);
                break;
        }
    }
}