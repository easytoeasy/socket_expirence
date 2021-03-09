<?php

$str = '你好';
printf("8bit：%s \n", mb_strlen($str, '8bit'));
printf("UTF-8：%s \n", mb_strlen($str, 'utf-8'));
printf("strlen：%s \n", strlen($str));