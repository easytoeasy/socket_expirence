<?php

const HOST = '127.0.0.1';
const PORT = 10932;
const BACKLOG = 100;

const CRLF = "\r\n";
const MAXLEN = 1 * 1024 * 5; //5KB

function write($fd, $data)
{
    while (!empty($data)) {
        $written = socket_write($fd, $data, strlen($data));
        if ($written === false) {
            $errno = socket_last_error($fd);
            if ($errno == 4 || $errno == 35) continue;
            getErrmsg($fd);
            return false;
        }
        $data = substr($data, $written);
    }
    return strlen($data);
}

/** 客户端在阻塞模式下等待服务端的响应
 * 客户端在阻塞模式下读取服务端的响应是不会有问题的，
 * 但是如果在非阻塞模式下读取，如果没有while(empty($reply))
 * 就会出现问题，数据会发现串起来的现象。 */
function read($fd)
{
    $reply = $buf = '';
    while (empty($reply)) {
        if (($buf = socket_read($fd, MAXLEN)) === false) {
            $errno = socket_last_error($fd);
            if ($errno == 4 || $errno == 35) continue;
            getErrmsg($fd);
            unset($buf, $reply);
            free($fd);
            return false;
        }
        // 连接已关闭，这就是read函数有问题的地方。
        if ($buf === '' || $buf === -1) {
            echo 'buf:' . $buf . PHP_EOL;
            return false;
        }
        $reply .= $buf;
    }
    return $reply;
}

/** 服务端读取客户端的命令 */
function readNormal($fd)
{
    $reply = $buf = '';
    while (substr($buf, -1, 1) !== "\n") {
        if (($buf = socket_read($fd, 1024, PHP_NORMAL_READ)) === false) {
            $errno = socket_last_error($fd);
            if ($errno == 4 || $errno == 35) continue;
            getErrmsg($fd);
            unset($buf, $reply);
            free($fd);
            return false;
        }
        // 连接已关闭，这就是read函数有问题的地方。
        if ($buf === '' || $buf === -1) {
            echo 'buf:' . $buf . PHP_EOL;
            return false;
        }
        $reply .= $buf;
    }
    return $reply;
}

function getErrmsg($fd = null)
{
    $errno = socket_last_error($fd);
    $msg = socket_strerror($errno);
    printf("errno:%s, errmsg:%s \n", $errno, $msg);
}

function freeSockets(array $clients)
{
    return array_walk($clients, function ($c) {
        printf("关闭连接：%s \n", $c);
        socket_close($c);
    });
}

function release($cfd, &$clients)
{
    if ($clients && ($index = array_search($cfd, $clients)) !== false) {
        unset($clients[$index]);
        printf("关闭连接：%s \n", $cfd);
        is_resource($cfd) && socket_close($cfd);
    }
}

function free($fd)
{
    printf("关闭连接：%s \n", $fd);
    is_resource($fd) && socket_close($fd);
}
