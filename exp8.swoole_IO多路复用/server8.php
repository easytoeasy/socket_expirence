<?php

include __DIR__ . '/../init.php';

/**
 * exp4是使用的select IO多路复用模式，将它改写成Swoole封装的Event试试。
 */

/* 通过`socket_create`生成的fd在Event::add时报错：unknow fd type 
 if (($fd = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
     getErrmsg();
     exit(3);
 }

 if (socket_bind($fd, HOST, PORT) === false) {
     getErrmsg($fd);
     exit(3);
 }

 if (socket_listen($fd, BACKLOG) === false) {
    getErrmsg($fd);
    exit(3);
 } */

use Swoole\Coroutine;

/* 通过Swoole的方式生成的Socket可以增加事件 */

$socket = new Coroutine\Socket(AF_INET, SOCK_STREAM, 0);
$socket->bind(HOST, PORT);
$socket->listen(128);

function acceptHandler($socket)
{
    // $cfd = socket_accept($fd);
    $client = $socket->accept();
    Swoole\Event::add($client, 'readFromClient', null, SWOOLE_EVENT_READ);
}

function readFromClient($client)
{
    /* $client->fd获取到的是int型的文件描述符，不是资源了。
     * 下面的写法就是错误的。
        var_dump($client->fd);
        $retval = socket_read($client->fd, 1024);
     */
    $retval = $client->recv();
    if ($retval === false || $retval === '') {
        Swoole\Event::del($client);
        $client->close();
    } else {
        var_dump('readFromClient:' . $retval . PHP_EOL);
        /* 按Swoole的说法：$client已经添加到了Event，再次添加会返回false 
            $flag = Swoole\Event::add($client, null, 'writeToClient', SWOOLE_EVENT_WRITE);
         */

        /*  */
        if (Swoole\Event::isset($client, SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE)) {
            /* 在可写 ($write_callback) 事件回调函数中，写入 socket 之后必须调用 Swoole\Event::del 
             * 移除事件监听，否则可写事件会持续触发 */
            if (Swoole\Event::set($client, null, 'writeToClient', SWOOLE_EVENT_WRITE) === false) {
                echo swoole_strerror(swoole_last_error(), 9) . PHP_EOL;
            }
        }
    }
}

function writeToClient($client)
{
    $buf = 'hi,client' . $client->fd;
    var_dump($buf);
    Swoole\Event::write($client, $buf);

    /* 我这里尝试当接收到写事件之后，设置Event只监听$client的读事件，
     * 从结果看来并没有发现什么异样。*/
    if (Swoole\Event::isset($client, SWOOLE_EVENT_WRITE)) {
        Swoole\Event::set($client, null, null, SWOOLE_EVENT_READ);
    }

    /* 这里就是写入socket之后，必须调用Event::del移除事件监听。
     * 那么原来添加的读client的事件监听也会被移除了？*/
    // Swoole\Event::del($client);
}

/* Event::add会自动将底层改成非阻塞模式 */
if (Swoole\Event::add($socket, 'acceptHandler') === false) {
    echo swoole_strerror(swoole_last_error(), 9) . PHP_EOL;
    exit(3);
}

while (true) {
    Swoole\Event::dispatch();
}




/* 以下是Swoole关于IO事件的代码演示，而我想写的是CS模式的简单交互
$fp = stream_socket_client("tcp://www.qq.com:80", $errno, $errstr, 30);
fwrite($fp,"GET / HTTP/1.1\r\nHost: www.qq.com\r\n\r\n");

Swoole\Event::add($fp, function($fp) {
    $resp = fread($fp, 8192);
    //socket处理完成后，从epoll事件中移除socket
    Swoole\Event::del($fp);
    fclose($fp);
});
echo "Finish\n";  //Swoole\Event::add 不会阻塞进程，这行代码会顺序执行
*/