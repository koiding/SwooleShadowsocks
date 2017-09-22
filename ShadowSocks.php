<?php
define('SOCKS_VERSION_5', 0x05);
define('SOCKS_IPV4', 0x01);
define('SOCKS_DOMAIN', 0x03);
define('SOCKS_IPV6', 0x04);

class ShadowSocks
{
    protected $debug = true;
    protected $server = null;
    protected $fdset = [];
    protected $clientset = [];
    protected $server_ip = '';
    protected $server_port = 0;

    public function __construct($conf)
    {
        $ip = $conf['server_id'] ?? '0.0.0.0';
        $port = $conf['server_port'] ?? 9988;
        $this->server_ip = $ip;
        $this->server_port = $port;
        $this->server = new Swoole\Server($ip, $port, SWOOLE_BASE);
        $this->server->on('Start', [$this, 'onStart']);
        $this->server->on('Connect', [$this, 'onConnect']);
        $this->server->on('Receive', [$this, 'onReceive']);
        $this->server->on('Close', [$this, 'onClose']);
        $this->server->start();
    }

    public function onStart(Swoole\Server $server)
    {
        echo sprintf("Server Start\nIP:\t%s\nPort:\t%d\n", $this->server_ip, $this->server_port);
    }

    public function onConnect(Swoole\Server $server, int $fd, int $from_id)
    {
        $this->log($fd, ' onConnect ');
    }

    public function onReceive(Swoole\Server $server, int $fd, int $reactor_id, string $data)
    {
        $origin_data = $data;
        $this->log($fd, ' onReceive ');
        $data = $this->parsedata($data);
        $len = count($data);
        
        if(isset($this->fdset[$fd]))
        {
            if($client = $this->clientset[$fd] ?? null)
            {
                $client->send($origin_data);
                $res = $client->recv();
                $server->send($fd, $res);
            }
            else
            {
                switch($data[3])
                {
                    case SOCKS_IPV4://ipv4
                        $host = sprintf("%d.%d.%d.%d", $data[4] , $data[5] , $data[6] , $data[7]);           
                        break;
                    case SOCKS_DOMAIN://domain
                        $host = '';
                        $domains = array_slice($data, 5, -2);
                        foreach($domains as $item)
                        {
                            $host .= chr($item);
                        }
                        break;
                    case SOCKS_IPV6://ipv6
                        break;
                }
    
                $port = $data[$len - 2] << 8 | $data[$len - 1];
                $client = new Swoole\Client(SWOOLE_SOCK_TCP);
                if (!$client->connect($host, $port, -1))
                {
                    exit("connect failed. Error: {$client->errCode}\n");
                }
                $this->clientset[$fd] = $client;
                $server->send($fd, pack('C*', 0x05, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00));    
            }
            
        }
        else//第一次身份确认步骤
        {
            $this->fdset[$fd] = 1;
            $version = $data[0];
            if($version === SOCKS_VERSION_5){
                $server->send($fd, pack("C*", 0x05, 0x00));
            }            
        }
        
    }

    public function onClose(Swoole\Server $server, int $fd)
    {
        $this->log($fd, ' onClose ');
        
    }

    protected function parsedata(string $data)
    {
        $len = strlen($data);
        $all = unpack('C' . $len, $data);//获取所有的数据

        $ret = [];
        foreach($all as $item){
            //$ret[] = dechex($item);//十进制转成16进制
            $ret[] = ($item);
        }
        return $ret;
    }

    protected function log(int $fd, $data)
    {
        if($this->debug)
        {
            echo sprintf("[fd: %d] %s\r\n", $fd, $data);        
        }
    }
}
