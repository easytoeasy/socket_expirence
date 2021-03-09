<?php

include __DIR__ . '/../init.php';

$fd = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (socket_connect($fd, HOST, PORT) === false)
    return getErrmsg($fd);

socket_set_nonblock($fd);

pcntl_signal(SIGQUIT, function () use ($fd) {
    free($fd);
});

while (true) {
    printf("请输入：");
    $line = fgets(STDIN); //这里会被阻塞
    $cmd = preg_replace('/\s*?$/', '', $line);
    $cmd = preg_replace('/\s+$/', ' ', $cmd);
    if (empty($cmd)) continue;
    $arr = explode(' ', $cmd);
    if (!in_array($arr[0], ['get', 'set', 'getall'])) {
        printf("非法的命令：%s \n", $arr[0]);
        continue;
    }

    write($fd, $line);
    // $reply = read($fd);
    $reply = read($fd);
    printf("%s \n", $reply);

    unset($reply);
}

free($fd);

