# 协程客户端
刚开始，我们针对每种类型的客户端都做了一个协程客户端，详见协程客户端，但这样做有 3 个问题：
- 实现复杂，每个客户端细枝末节的协议都很复杂，想都完美的支持工作量巨大。
- 用户需要更改的代码比较多，比如原来查询 MySQL 是用的 PHP 原生的 PDO，那么现在需要用 Swoole\Coroutine\MySQL 的方法。
- 我们很难覆盖到所有的操作，比如 proc_open()、sleep() 函数等等也可能阻塞住导致程序变成同步阻塞的。

针对上述问题，我们换了实现思路，采用 Hook 原生 PHP 函数的方式实现协程客户端，通过一行代码就可以让原来的同步 IO 的代码变成可以协程调度的异步 IO，即一键协程化。

## 一键协程化
协程的好处：增强高并发处理
比如MySQL在查询某条记录时，会处于阻塞等待状态，直到MySQL数据返回然后才会继续往下走。当有了协程，就无需傻傻的等待MySQL的数据返回了，而是继续往下处理。
`todo 如果下面的逻辑依赖于上面的内容输出呢？这样的话协程是不是就没有意义了？`

怎么一键协程化呢？https://wiki.swoole.com/#/runtime

1）对所有的目前Swoole支持的函数HOOK：
```php
Co::set(['hook_flags'=> SWOOLE_HOOK_ALL]); // v4.4+版本使用此方法。
// 或 
Swoole\Runtime::enableCoroutine($flags = SWOOLE_HOOK_ALL);
```
从 v4.5.4 版本起，SWOOLE_HOOK_ALL 包括 SWOOLE_HOOK_CURL，所以在之前的版本需要这么写：
```php
Co::set(['hook_flags'=> SWOOLE_HOOK_ALL | SWOOLE_HOOK_CURL]);
```

2) 既然Swoole只是对大部分的原生函数支持协程化，并不是全部。所以就很好奇到底支持的是哪些原生函数可以协程化呢？

- SWOOLE_HOOK_TCP
- SWOOLE_HOOK_UNIX
- SWOOLE_HOOK_UDP
- SWOOLE_HOOK_UDG
- SWOOLE_HOOK_SSL
- SWOOLE_HOOK_TLS
- SWOOLE_HOOK_SLEEP
- SWOOLE_HOOK_FILE
- SWOOLE_HOOK_STREAM_FUNCTION
- SWOOLE_HOOK_BLOCKING_FUNCTION
- SWOOLE_HOOK_PROC
- SWOOLE_HOOK_CURL
- SWOOLE_HOOK_NATIVE_CURL
- SWOOLE_HOOK_SOCKETS
- SWOOLE_HOOK_STDIO

### SWOOLE_HOOK_TCP
> https://wiki.swoole.com/#/runtime?id=swoole_hook_tcp
v4.1 开始支持，TCP Socket 类型的 stream，包括最常见的 Redis、PDO、Mysqli 以及用 PHP 的 streams 系列函数操作 TCP 连接的操作，都可以 Hook。

这很恐怖，因为我只要开启协程之后，原来看似简单的过程化的逻辑，其实已经是协程处理了。在涉及到TCP处理的地方，都会被协程化，而且很可能我都不知道或都没在意到就已经完成协程了。最近也有看到有人分享`yield`的协程用法，会不会和这个有异曲同工之妙？接下来我能用它做什么呢？高并发、高性能是很多服务端开发都要具备的一种能力，可是似乎现在却唾手可得，听起来是不是很不可思议？更让我好奇的是：底层究竟是如何完成的呢？

```php
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);//此处产生协程调度，cpu切到下一个协程(下一个请求)，不会阻塞进程
$redis->get('key');//此处产生协程调度，cpu切到下一个协程(下一个请求)，不会阻塞进程
```

### SWOOLE_HOOK_UNIX
v4.2 开始支持。Unix Stream Socket 类型的 stream。

```php
$socket = stream_socket_server(
        'unix://swoole.sock',
        $errno,
        $errstr,
        STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
    );
```

### SWOOLE_HOOK_UDP
v4.2 开始支持。UDP Socket 类型的 stream。
```php
$socket = stream_socket_server(
        'udp://0.0.0.0:6666',
        $errno,
        $errstr,
        STREAM_SERVER_BIND
    );
```

### SWOOLE_HOOK_UDG
v4.2 开始支持。Unix Dgram Socket 类型的 stream，示例：
```php
$socket = stream_socket_server(
        'udg://swoole.sock',
        $errno,
        $errstr,
        STREAM_SERVER_BIND
    );
```

### SWOOLE_HOOK_SSL
v4.2 开始支持。SSL Socket 类型的 stream，示例：
```php
$fp = stream_socket_client(
        'ssl://' . $host . ':' . $port,
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT,
        $context
    )
```

### SWOOLE_HOOK_TLS
v4.2 开始支持。TLS Socket 类型的 stream

### SWOOLE_HOOK_SLEEP
v4.2 开始支持。sleep 函数的 Hook，包括了 sleep、usleep、time_nanosleep、time_sleep_until，由于底层的定时器最小粒度是 1ms，因此使用 usleep 等高精度睡眠函数时，如果设置为低于 1ms 时，将直接使用 sleep 系统调用。可能会引起非常短暂的睡眠阻塞。示例：
```php
Co::set(['hook_flags' => SWOOLE_HOOK_SLEEP]);

Co\run(function () {
    go(function () {
        sleep(1); //让出CPU，继续执行下一个协程。
        echo '1' . PHP_EOL;
    });
    go(function () {
        echo '2' . PHP_EOL;
    });
});
//输出 
2
1
```

### SWOOLE_HOOK_FILE
v4.3 开始支持。
文件操作的 Hook，支持的函数有：

- fopen
- fread/fgets
- fwrite/fputs
- file_get_contents、file_put_contents
- unlink
- mkdir
- rmdir

### SWOOLE_HOOK_STREAM_FUNCTION
> https://wiki.swoole.com/#/runtime?id=swoole_hook_stream_function

v4.4 开始支持。stream_select() 的 Hook，示例：
```php

```

### SWOOLE_HOOK_BLOCKING_FUNCTION
v4.4 开始支持。这里的 blocking function 包括了：gethostbyname、exec、shell_exec，示例：
```php
Co::set(['hook_flags' => SWOOLE_HOOK_BLOCKING_FUNCTION]);

Co\run(function () {
    // 做了一个测试，这个函数是阻塞执行的，会等待脚本的执行结束在返回。
    echo shell_exec('ls');
});
```

### SWOOLE_HOOK_PROC
v4.4 开始支持。Hook proc* 函数，包括了：proc_open、proc_close、proc_get_status、proc_terminate。

### SWOOLE_HOOK_CURL
v4.4LTS 后或 v4.5 开始正式支持。
CURL 的 HOOK，支持的函数有：

- curl_init
- curl_setopt
- curl_exec
- curl_multi_getcontent
- curl_setopt_array
- curl_error
- curl_getinfo
- curl_errno
- curl_close
- curl_reset

### SWOOLE_HOOK_NATIVE_CURL
对原生 CURL 的 Hook。
> Swoole 版本 >= v4.6.0 可用
使用前需要在编译时开启 --enable-swoole-curl 选项；
开启该选项后将自动设置 SWOOLE_HOOK_NATIVE_CURL，关闭 SWOOLE_HOOK_CURL；
同时 SWOOLE_HOOK_ALL 包含 SWOOLE_HOOK_NATIVE_CURL

### SWOOLE_HOOK_SOCKETS
对 sockets 扩展的 Hook。

> Swoole 版本 >= v4.6.0 可用

### SWOOLE_HOOK_STDIO
对 STDIO 的 Hook。

> Swoole 版本 >= v4.6.2 可用

`todo STDIO 是啥？`

## 协程客户端

- TCP/UDP客户端
- Socket客户端
- HTTP/Websocket客户端
- HTTP2客户端
- PostgreSQL客户端
- Fastcgi客户端
- MySQL客户端
- Redis客户端

### TCP/UDP客户端

### Socket客户端
