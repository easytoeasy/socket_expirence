# IO多路复用
IO多路复用：select、poll、epoll、kqueue。这里看到swoole封装了epoll的基本用法。

> Swoole 扩展还提供了直接操作底层 epoll/kqueue 事件循环的接口。可将其他扩展创建的 socket，PHP 代码中 stream/socket 扩展创建的 socket 等加入到 Swoole 的 EventLoop 中， 否则第三方的 $fd 如果是同步 IO 会导致 Swoole 的 EventLoop 得不到执行.

> 什么是EventLoop？
所谓 EventLoop，即事件循环，可以简单的理解为 epoll_wait，我们会把所有要发生事件的句柄（fd）加入到 epoll_wait 中，这些事件包括可读，可写，出错等。 我们的进程就阻塞在 epoll_wait 这个内核函数上，当发生了事件 (或超时) 后 epoll_wait 这个函数就会结束阻塞返回结果，就可以回调相应的 PHP 函数，例如，收到客户端发来的数据，回调 onReceive 回调函数。
当有大量的 fd 放入到了 epoll_wait 中，并且同时产生了大量的事件，epoll_wait 函数返回的时候我们就会挨个调用相应的回调函数，叫做一轮事件循环，即 IO 多路复用，然后再次阻塞调用 epoll_wait 进行下一轮事件循环。

看到这里的时候突然觉得很庆幸，因为自己前段时间看的Redis源码是有收益的。在这里就很好的体现了。因为我在看到EventLoop后，脑海里就会很清晰的想到Redis是如何处理的文件事件。进而我会对Swoole的文件事件是如何进行的有一些猜想。这些猜想我觉得是好事，将会引领我去看懂Swoole的源码。

## 事件优先级
通过 Process::signal 设置的信号处理回调函数
通过 Timer::tick 和 Timer::after 设置的定时器回调函数
通过 Event::defer 设置的延迟执行函数
通过 Event::cycle 设置的周期回调函数


## 方法使用

那么看看如何使用？

- add()：将一个 socket 加入到底层的 reactor 事件监听中。此函数可以用在 Server 或 Client 模式下。
    `Swoole\Event::add(mixed $sock, callable $read_callback, callable $write_callback = null, int $flags = null): bool`
    - mixed $sock：文件描述符、stream 资源、sockets 资源、object
    - callable $read_callback：可读事件回调函数
    - callable $write_callback：为可写事件回调函数【此参数可以是字符串函数名、对象 + 方法、类静态方法或匿名函数，当此 socket 可读或者可写时回调指定的函数。】
    - int $flags：事件类型的掩码【可选择关闭 / 开启可读可写事件，如 SWOOLE_EVENT_READ、SWOOLE_EVENT_WRITE 或者 SWOOLE_EVENT_READ|SWOOLE_EVENT_WRITE】

    socket资源类型：
    - int：文件描述符，包括 Swoole\Client->$sock、Swoole\Process->$pipe 或者其他 fd
    - stream资源：stream_socket_client/fsockopen 创建的资源
    - socket资源：sockets 扩展中 socket_create 创建的资源。
    - object：Swoole\Process 或 Swoole\Client，底层自动转换为 UnixSocket（Process）或客户端连接的 socket（Swoole\Client）

- set()：修改事件监听的回调函数和掩码。
    `Swoole\Event::set($fd, mixed $read_callback, mixed $write_callback, int $flags): bool`
    与Event::add的参数完全相同。
    > 注意：
    - $read_callback 或者 $write_callback 设置为null，并不会释放掉原来已经监听的回调函数，只是告诉Event不去修改回调函数。
    - 如果监听了 SWOOLE_EVENT_READ 事件，而当前并未设置 read_callback，底层会直接返回 false，添加失败。SWOOLE_EVENT_WRITE 同理。（什么意思呢？就是原来设置了flag为`SWOOLE_EVENT_READ`，但是现在又未设置`read_callback`，那么就会返回false。）

- isset()：检测传入的 $fd 是否已加入了事件监听。
    `Swoole\Event::isset(mixed $fd, int $events = SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE): bool`
    - mixed $fd：
    - int $events：`SWOOLE_EVENT_READ`、`SWOOLE_EVENT_WRITE`、`SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE`

- write()：用于 PHP 自带 stream/sockets 扩展创建的 socket，使用 fwrite/socket_send 等函数向对端发送数据。当发送的数据量较大，socket 写缓存区已满，就会发送阻塞等待或者返回 EAGAIN 错误。
    - mixed $fd：任意的 socket 文件描述符【参考 Event::add 文档】
    - miexd $data：要发送的数据 【发送数据的长度不得超过 Socket 缓存区尺寸】

    > 注意：
    Event::write 不能用于 SSL/TLS 等有隧道加密的 stream/sockets 资源
    Event::write 操作成功后，会自动将该 $socket 设置为非阻塞模式

- del()：从 reactor 中移除监听的 socket。 Event::del 应当与 Event::add 成对使用。
    `Swoole\Event::del(mixed $sock): bool`
    > 注意：必须在 socket 的 close 操作前使用 Event::del 移除事件监听，否则可能会产生内存泄漏

- exit()：退出事件轮询。
    `Swoole\Event::exit(): void`
    > 注意：此函数仅在 Client 程序中有效

- defer()：在下一个事件循环开始时执行函数。
    `Swoole\Event::defer(mixed $callback_function);`
    - mixed $callback_function：时间到期后所执行的函数 【必须是可以调用的。回调函数不接受任何参数，可以使用匿名函数的 use 语法传递参数到回调函数中；在 $callback_function 函数执行过程中添加新的 defer 任务，仍然会在本轮事件循环内执行完成】

    > 注意：Event::defer 的回调函数会在当前 EventLoop 的事件循环结束、下一次事件循环开始前执行。

- cycle()：定义事件循环周期执行函数。此函数会在每一轮事件循环结束时调用。
    `Swoole\Event::cycle(callable $callback, bool $before = false): bool`
    - callable $callback_function：要设置的回调函数 【$callback 为 null 时表示清除 cycle 函数，已设置 cycle 函数，重新设置时会覆盖上一次的设定】
    - bool $before：在 EventLoop 之前调用该函数
    > 注意：可以同时存在 before=true 和 before=false 两个回调函数。

- wait()：启动事件监听。
    `Swoole\Event::wait();`
    > 注意：请将此函数放置于 PHP 程序末尾

- dispatch()：启动事件监听。
    `Swoole\Event::dispatch();`
    > 注意：
    仅执行一次 reactor->wait 操作，在 Linux 平台下相当手动调用一次 epoll_wait。与 Event::dispatch 不同的是，Event::wait 在底层内部维持了循环。

其实在使用这些函数之前，应该先去了解epoll/kqueue的使用，然后再来看这个可能会好点。

