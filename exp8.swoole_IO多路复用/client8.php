<?php

include __DIR__ . '/../init.php';

use Swoole\Coroutine;
use function Swoole\Coroutine\run;


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
