<?php

class Base
{

    public static function connect()
    {
        Other::connect();
    }

    public function getOther()
    {
        return Other::getOther();
    }

    public function getTest()
    {
        
        return 'test';
    }

    public static function getStaticTest()
    {
        return 'static test';
    }

}