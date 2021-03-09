<?php

include __DIR__ . '/../init.php';

$fd = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (socket_connect($fd, HOST, PORT) === false)
    return getErrmsg($fd);

socket_set_nonblock($fd);
// 客户端设置成阻塞模式，等待服务端的回复。
// socket_set_block($fd);
/* 设置响应|发送超时时长 
 * 非阻塞模式下得到了报错：Resource temporarily unavailable 
 * */
// socket_setopt($fd, SOL_SOCKET, SO_RCVTIMEO, ['sec'=>1, 'usec'=>0]);
// socket_setopt($fd, SOL_SOCKET, SO_SNDTIMEO, ['sec'=>1, 'usec'=>0]);

// socket_setopt($fd, SOL_SOCKET, SO_KEEPALIVE, 1);

// 开启了3个子进程去模拟并发
for ($i = 0; $i < 2; $i++) {
    $pid = pcntl_fork();
    if ($pid < 0) {
        exit(2);
    } elseif ($pid == 0) {
        sendOrReply($fd);
    } else {
    }
}

// 防止子进程结束关闭了fd
sleep(2);
free($fd);
exit(0);


function sendOrReply($fd)
{
    $i = 0;
    while ($i++ < 5) {
        $cmd = 'get key1' . CRLF;
        $arr = explode(' ', $cmd);
        if (!in_array($arr[0], ['get', 'set', 'getall'])) {
            printf("非法的命令：%s \n", $arr[0]);
            continue;
        }

        if (write($fd, $cmd) === false) {
            exit(3);
        }

        if (($reply = read($fd)) === false) {
            exit(3);
        }
        printf("%s \n", $reply);

        unset($reply, $cmd);
    }
}
