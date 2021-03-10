<?php

include __DIR__ . '/../init.php';


use Swoole\Coroutine;
use function Swoole\Coroutine\run;

run(function () {
    /* 返回Socket对象，这个对象中存有fd句柄。
     * 对这个fd到底有哪些默认的操作呢？值得一看源码 */
    $socket = new Coroutine\Socket(AF_INET, SOCK_STREAM, 0);
    $socket->bind(HOST, PORT);
    $socket->listen(128);

    while (true) {
        echo "Accept: \n";
        /* 调用此方法会立即挂起当前协程，并加入 EventLoop 监听可读事件，
         * 当 Socket 可读有到来的连接时自动唤醒该协程，并返回对应客户端连接的 Socket 对象。
         * 1) 立即挂起当前协程
         * 2）加入到EventLoop 监听可读事件
         * 3）返回了Socket对象 */
        $client = $socket->accept();
        /* 接下来的逻辑只有触发了accept才会继续往下走，
         * 当已经连接的client再次发送数据时就无法接收到，
         * 因为还在accept阻塞呢？需要IO多路复用解决这个问题。 */
        if ($client === false) {
            var_dump($socket->errCode, $socket->errMsg);
        } else {
            var_dump($client);
            $msg = $client->recv();
            var_dump($msg);
            $client->send('hi');
        }
    }
});
