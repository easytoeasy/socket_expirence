<?php

/* 协程下，父子协程的上下文 
 * 为了解决问题：在SinaDagger框架下使用Swoole时报错如下。
 * error: ==Fatal error==Uncaught Error: Class 'BaseModelDBConnect' 
 * not found in model/BaseModelDB.php 
 * 测试知道：spl_autoload_register自动注册的函数是在子协程也起作用的。
 * 测试发现：原来是因为类调用静态方法则无法自动加载。如：$class::func().
 * 解决方案：经过多次测试后发现只要引入了`BaseModelDBConnect`类问题就解决了。
 * 
 * 那为什么会出现这个问题呢？
 * 在调用`BaseModelDBConnect::connect`之后，会连接MySQL实例。会触发yield、协程调度。
 * 难道是因为BaseModelDBConnect还没有被引入？ */


require './require.php';

Co::set(['hook_flags' => SWOOLE_HOOK_ALL]);

/* 正常输出：test
$base = new Base();
$test = $base->getTest();
echo $test . PHP_EOL;
*/

/* 测试自动加载下有协程调度的情况 */
Co\run(function () {
    for ($i = 0; $i < 2; $i++)
        go(function () {
            Base::connect();
        });
        
        go(function() {
            echo "test2 \n";
        });
});

/* 多层级的require调用，也没问题 
Co\run(function () {
    $base = new Base;
    $test = $base->getOther();
    echo $test . PHP_EOL;
    go(function () {
        $base = new Base;
        $test = $base->getOther();
        echo $test . PHP_EOL;
    });
});
*/

/* 类调用静态方法也可以被自动加载
Co\run(function () {
    $test = Base::getStaticTest();
    echo $test . PHP_EOL;
    go(function () {
        $test = Base::getStaticTest();
        echo $test . PHP_EOL;
    });
});
*/

/* 父子协程下都能够自动加载
Co\run(function () {
    $base = new Base;
    $test = $base->getTest();
    echo $test . PHP_EOL;
    go(function () {
        $base = new Base;
        $test = $base->getTest();
        echo $test . PHP_EOL;
    });
});
*/

/* 静态变量父子协程都共享
const TEST = 'test';

Co\run(function() {
    echo TEST . PHP_EOL;
    go(function() {
        echo TEST . PHP_EOL;
    });
});
*/