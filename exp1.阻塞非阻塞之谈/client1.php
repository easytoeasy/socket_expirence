<?php

include __DIR__ . '/../init.php';


if (($fd = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    getErrmsg();
    exit(3);
}

if (socket_connect($fd, HOST, PORT) === false) {
    getErrmsg($fd);
    exit(3);
}

$buf = 'hi';
$retval = socket_write($fd, $buf, strlen($buf));
if ($retval === false) {
    getErrmsg($fd);
    free($fd);
    exit(3);
}
var_dump($retval);

$retval = socket_read($fd, 1024);
if ($retval === '' || $retval === false) {
    getErrmsg($fd);
    free($fd);
    exit(3);
}
var_dump($retval);

free($fd);