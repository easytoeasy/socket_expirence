<?php

include __DIR__ . '/../init.php';

/* 第一个demo
 * 阻塞和非阻塞的区别：
 * 阻塞模式下进程会在read、write等函数等待时处于挂起状态。而非阻塞模式下会立即返回，
 * 继续执行下面的逻辑。这是浅面的理解。
 * 
 * @see https://www.cnblogs.com/maxigang/p/9041080.html
 * 用户态：应用程序进程
 * 内核态：操作系统层面的指令
 * 
 * @see https://www.jianshu.com/p/b8203d46895c
 * 如果想装逼的话，那么就要理解的更加深层。应该这么说：
 * 阻塞模式下除了用户态的进程处于阻塞之外，其实内核态也会处于阻塞。
 * 内核态的阻塞可以分为两个部分：
 * - （a）等待数据：no datagram ready --> datagram ready
 * - （b）将数据复制到用户态：copy datagram --> copy complete
 * 
 * 比如client向server调用socket_read函数，在阻塞模式下的调用步骤如下：
 * 1）用户态系统调用内核态的方法，然后中断响应。用户进程被挂起。
 * 2）CPU保存用户态下一个执行指令，装载堆栈数据，切换到内核态调用系统方法。
 * 3）系统调用时等待(a)(b)步骤执行完成，然后切换到用户态继续执行下一个指令。
 * 非阻塞模式下的调用过程如下：
 * 1）用户态发生系统调用时，此时会切换到内核态。但是内核态会立即回复`no datagram ready`。
 * 2）然后又切回到用户态，继续执行下面的指令。
 * 3）可能下一次轮询，再来问内核态是否有数据。此时内核态可能已经准备好了数据，处于`datagram ready`.
 * 4）然后内核态会执行(b)步骤，阻塞等待将数据复制到内核态。
 * 5）复制完成，切换到用户态继续执行下一个指令。
 * 
 * 你可能已经发现，虽然是非阻塞但是在内核态下也会有一个阻塞等待复制数据到用户态的过程。
 * 有没有更快的呢？把复制数据这一块也非阻塞？那就是IO多路复用了。后面在讲。
 * 
 * 现在开始demo分解。
 */


if (($fd = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    getErrmsg();
    exit(3);
}

// 设置为非阻塞socket
socket_set_nonblock($fd);
socket_set_option($fd, SOL_SOCKET, SO_REUSEADDR, 1);
socket_set_option($fd, SOL_SOCKET, SO_REUSEPORT, 1);

if (socket_bind($fd, HOST, PORT) === false) {
    getErrmsg($fd);
    exit(3);
}

if (socket_listen($fd, BACKLOG) === false) {
    getErrmsg($fd);
    exit(3);
}

$client = [];
while (true) {
    /* 在阻塞Socket下：accept会一直等待客户端的连接。如果之前已经连接过的客户端而又没有释放连接，
     * 在后期也会接收不到消息。所以需要引入IO多路复用`select`，在接收到事件通知之后就从 
     * 内核态返回给用户态。只是`select`不会告知你是哪个fd有事件通知，因此需要循环一遍。
     * 
     * 在非阻塞Socket下：accept会立即返回 */
    if ($cfd = socket_accept($fd)) {
        $client[$cfd] = $cfd;
    }
    // echo "do't wait accept... \n";
    // 非阻塞模式下不知道哪个cfd有数据，所以循环处理所有的fd就会很低效。
    // 因此需要select
    foreach ($client as $c) {
        // 因为read在关闭连接时接收到的消息是空的，但是非阻塞模式下没有发送消息时接收到的是什么呢？
        // 接收到的是个空字符串
        $data = socket_read($c, 1024);
        if ($data) {
            var_dump($data);
            socket_write($c, 'i get!');
        } else {
            // 关闭和非阻塞模式下接收到的都是空的字符串，因此无法依据空字符串关闭连接。
            // unset($client[$c]);
            // socket_close($c);
        }
    }

    /* 阻塞模式下的写法
    $cfd = socket_accept($fd);
    $retval = socket_read($cfd, 1024);
    var_dump($retval);
    if ($retval == '' || $retval === false) {
        getErrmsg($cfd);
        free($cfd);
        break;
    }
    $buf = 'hello';
    $retval = socket_write($cfd, $buf, strlen($buf));
    if ($retval === false) {
        getErrmsg($cfd);
        free($cfd);
        break;
    }
    var_dump($retval);
    */
}

free($fd);
