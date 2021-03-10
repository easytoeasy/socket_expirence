<?php

include __DIR__ . '/../init.php';

use Swoole\Coroutine;
use function Swoole\Coroutine\run;
/**
 * 1）run方法是干什么的？
 * 2）Coroutine是什么？
 *  是协程。协程是什么？就是当某个操作碰到了IO处理就会让出自己的CPU，去处理其他的操作。
 *  而之前的IO事件就会被注册到EventLoop监听。
 * 
 * 本文档中的大部分示例都使用了 Co\run() 来创建一个协程容器。
 * 使用 Coroutine::create 或 go 方法创建协程 (参考别名小节)，在创建的协程中才能使用协程 API，
 * 而协程必须创建在协程容器里面
 */
run(function () {
    $socket = new Coroutine\Socket(AF_INET, SOCK_STREAM, 0);

    $retval = $socket->connect(HOST, PORT);
    echo 'retval:' . $retval . PHP_EOL;
    while ($retval) {
        $n = $socket->send('hello');
        var_dump($n);

        $data = $socket->recv();
        var_dump($data);

        //发生错误或对端关闭连接，本端也需要关闭
        /* 这里的recv方法和socket_read函数的用法类似，
         * 无数据返回和连接断开都是返回的空字符串。 */
        if ($data === '' || $data === false) {
            echo "errCode: {$socket->errCode}\n";
            $socket->close();
            break;
        }
        /* 和sleep的区别是什么？ */
        Coroutine::sleep(1.0);
    }

    var_dump($retval, $socket->errCode, $socket->errMsg);
});
