<?php

class LocalProxy
{
    protected $client = null;
    protected $debug = true;
    protected $local_server = null;
    protected $local_ip = '';
    protected $local_port = 0;
    protected $remote_ip = '';
    protected $remote_port = 0;

    public function __construct($conf)
    {
        $this->remote_ip = $conf['server_ip'] ?? '0.0.0.0';
        $this->remote_port = $conf['server_port'] ?? 9988;
        $this->local_ip = $conf['local_ip'] ?? '0.0.0.0';
        $this->local_port = $conf['local_port'] ?? 9527;

        $host = $this->remote_ip;
        $port = $this->remote_port;
        $client = new Swoole\Client(SWOOLE_SOCK_TCP);
        if (!$client->connect($host, $port, -1))
        {
            exit("connect failed. Error: {$client->errCode}\n");
        }
        $this->client = $client;
    }

    public function start()
    {
        $data = [0x05, 0x01, 0x00];
        $data = pack('C*', ...$data);
        $this->client->send($data);
        $res = $this->client->recv();
        $res = $this->parseData($res);

        $ip = $this->local_ip;
        $port = $this->local_port;
        $this->local_server = new Swoole\Server($ip, $port, SWOOLE_BASE);
        $this->local_server->on('Start', [$this, 'onStart']);
        $this->local_server->on('Connect', [$this, 'onConnect']);
        $this->local_server->on('Receive', [$this, 'onReceive']);
        $this->local_server->on('Close', [$this, 'onClose']);
        $this->local_server->start();
    }

    public function onStart(Swoole\Server $server)
    {
        echo sprintf("Local Proxy Start\nIP:\t%s\nPort:\t%d\n", $this->local_ip, $this->local_port);
    }

    public function onConnect(Swoole\Server $server, int $fd, int $from_id)
    {
        $this->log($fd, 'onConnect');
    }

    public function onClose(Swoole\Server $server, int $fd, int $reactorId)
    {
        $this->log($fd, ' onClose ');
    }

    public function onReceive(Swoole\Server $server, int $fd, int $reactor_id, string $data)
    {
        $this->log($fd, 'onReceive');
        $sendData = $this->getHostPort($data);
        $sendData = array_merge([0x05, 0x01, 0x00], $sendData);
        $sendData = pack('C*', ...$sendData);
        $this->client->send($sendData);
        $res = $this->client->recv();
        $res = $this->parseData($res);
        $this->client->send($data);
        $res = $this->client->recv();
        $server->send($fd, $res);
    }

    protected function getHostPort($data)
    {
        $header_arr = explode("\n", $data);
        $second_line = $header_arr[1] ?? ''; 
        list($hold, $url) = explode(' ', $second_line);
        $_arr = explode(':', $url);
        $host = trim($_arr[0]);
        $port = $_arr[1] ?? 80;
        $port = intval($port);
        $isDomain = preg_match('/[a-zA-Z]/', $host) ? true : false;
        
        $ret = [];
        if($isDomain){
            $ret[] = 0x03;
            $ret[] = strlen($host);
            $strs = str_split($host);
            foreach($strs as $item)
            {
                $ret[] = ord($item);
            }
        }
        else
        {
            $ret[] = 0x01;
            $digs = explode('.', $host);
            foreach($digs as $item)
            {
                $ret[] = intval($item);
            }
        }
        $ret[] = hexdec(dechex($port >> 8));
        $ret[] = hexdec(dechex($port & 0x00ff));        
        return $ret;
    }

    protected function parseData($data){
        $clen = strlen($data);
        $ds = unpack('C' . $clen, $data);
    
        $ret = []; 
        foreach($ds as $item){
            $ret[] = dechex($item);
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
