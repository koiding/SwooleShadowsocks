# SwooleShadowsocks
- 用php实现的Shadowsocks， 先挖个坑, 现在只是个demo
- 目前实现了简单的ss服务端和客户端，没有做任何加密
- 程序有bug

#依赖[Swoole](https://github.com/swoole/swoole-src)扩展
安装方法
```
pecl install swoole
```
#socks5协议详情
[SOCKS Protocol Version 5](https://tools.ietf.org/html/rfc1928)

#用法
```
php start_ss_server.php //先运行Shadowsocks的server端

php start_ss_local.php //运行Shadowsocks的客户端

curl http://httpbin.org/ip -x 本地ip:本地端口
```
收获
- 学习了php中对二进制数据的处理方法。[pack](http://www.php.net/manual/zh/function.pack.php) [unpack](http://www.php.net/manual/zh/function.unpack.php)
- 学习到了php中的解构方法，即将一个数组作为可变参数传递给函数。[...(三个点)](http://php.net/manual/zh/functions.arguments.php)



