<?php

include __DIR__ . '/../init.php';

/** 第6个demo
 * 何为可靠的接收和发送数据？
 * 这是基于多进程下而提到的，因为在多进程下的消息发送和接收在并发时可能会出现数据错乱的问题。
 * 
 * 那么这个问题在实际应用中的体现呢？
 * 比如AMQP，CS在发送完数据之后需要确认自己已经发送成功。那么在client发送完数据之后就要得到
 * 服务端的ACK。那么AMQP就要考虑数据发送和接收的可靠。除了保证单进程下没问题，多进程下也要保证没问题。
 * 再比如Beanstalk，在多进程下就会出现客户端接收服务端的ACK时数据错乱的问题。
 * 
 * 能在什么场景中用到这个呢？
 * 其实就是做一个讨论，以备不时之需。
 * 
 * 我在这里是模拟了Redis的set/get操作，当然只是简单的表面上的设置获取数据而已。
 * client在set后希望得到Server一个明确的正确的ACK，get时也要等待Server准确的答案。
 * 单进程下怎么搞都不会出错，但是多进程下就需要注意了。
 * 
 * 我这里先假设有三种解决的方案：
 * 1）client发送数据时非阻塞，client在接收数据时阻塞。
 * 2）server将client发送的数据保存到缓存在一次性发送给client。
 * 3）就像Redis一样，server将client的请求串行，然后在依次回复。
 * 
 * 开始demo
 */

pcntl_signal(SIGPIPE, SIG_IGN);

if (($fd = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    return getErrmsg($fd);
}

if (socket_bind($fd, HOST, PORT) === false) {
    return getErrmsg($fd);
}

socket_set_nonblock($fd);

// 依然会报错：Address already in use
socket_setopt($fd, SOL_SOCKET, SO_REUSEADDR, 1);
socket_setopt($fd, SOL_SOCKET, SO_REUSEPORT, 1);
/* 如果在socket_close时还有数据没有传输完，默认情况下发送所有没有发送的数据。*/
socket_setopt($fd, SOL_SOCKET, SO_LINGER, array('l_onoff' => 0, 'l_linger' => 0));
/* 是否禁用TCP的 `Nagle` 算法 */
// socket_setopt($fd, SOL_TCP, TCP_NODELAY, 1);
socket_setopt($fd, SOL_SOCKET, SO_RCVTIMEO, ['sec'=>1, 'usec'=>0]);
socket_setopt($fd, SOL_SOCKET, SO_SNDTIMEO, ['sec'=>1, 'usec'=>0]);

if (socket_listen($fd, BACKLOG) === false) {
    getErrmsg($fd);
    return false;
}

// DB里的数据
$array = [
    'key1' => 'value1',
    'key2' => 'value2',
];

$client = [$fd];
while (true) {
    $read = $client;
    if (($num = socket_select($read, $write, $except, 60)) === false) {
        getErrmsg();
        free($fd);
        exit(3);
    }
    if ($num < 1) continue;

    foreach ($read as $r) {
        if (
            $r == $fd &&
            ($cfd = socket_accept($fd)) !== false
        ) {
            $client[] = $cfd;
            continue;
        }

        if (($cmd = readNormal($r)) === false) {
            release($r, $client);
            continue;
        } else {
            $cmd = preg_replace('/\s*?$/', '', $cmd);
            $cmd = preg_replace('/\s+$/', ' ', $cmd);
        }

        if ($cmd)
            printf("cmd:%s$ \n", $cmd);

        if ($cmd == 'exit' || $cmd == 'quit') {
            free($r, $client);
            continue;
        }

        $reply = '';
        $arr = explode(' ', $cmd);
        if ($arr[0] == 'get') {
            $key = $arr[1];
            printf("key:%s$\n", $key);
            if (isset($array[$key])) {
                $reply = $array[$key] . CRLF;
            } else {
                $reply = '(nil)' . CRLF;
            }
        } elseif ($arr[0] == 'set') {
            $key = $arr[1];
            $value = isset($arr[2]) ? $arr[2] : '';
            $array[$key] = $value;
            $reply = 'OK' . CRLF;
        }

        if ($reply && write($r, $reply) === false) {
            printf("reply error \n");
            release($r, $client);
        }

        // echo PHP_EOL;
    }
}

free($fd);