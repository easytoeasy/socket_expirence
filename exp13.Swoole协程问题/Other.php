<?php

class Other
{

    public static function getOther()
    {
        return 'other';
    }

    public static function connect()
    {
        $mysqli = new mysqli('127.0.0.1', 'root', '123456', 'lottery_match');
        $result = $mysqli->query("SELECT count(1) FROM `e_asia`");
        echo $result->num_rows . PHP_EOL;
        $result->close();
    }

}