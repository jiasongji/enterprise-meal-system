<?php
// config/database.php

return [
    // 数据库文件绝对路径
    // __DIR__ 是 config 目录，向上两级找到 data 目录
    'db_path' => realpath(__DIR__ . '/../data/meal.db'),
    
    // 是否开启调试模式 (开发阶段设为 true，上线设为 false)
    'debug' => true,
    
    // 时区设置
    'timezone' => 'Asia/Shanghai'
];