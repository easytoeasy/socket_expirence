<?php

class AutoRegister
{
    public static function loader($name)
    {
        echo 'require ' . $name . PHP_EOL;
        require './' . $name . '.php';
    }
}

spl_autoload_register(['AutoRegister', 'loader']);
