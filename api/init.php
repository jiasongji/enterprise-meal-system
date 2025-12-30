<?php
// api/init.php

// 1. 设置错误报告
$config = require __DIR__ . '/../config/database.php';
if ($config['debug']) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
}

// 2. 设置时区
date_default_timezone_set($config['timezone']);

// 3. 简单的自动加载 (Autoload)
// 当使用 new Auth() 时，自动引入 core/Auth.php
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../core/' . $class . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// 4. 处理跨域 OPTIONS 请求
// 如果浏览器发起预检请求 (OPTIONS)，直接返回 200，不执行后续逻辑
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit(0);
}

// 5. 初始化 Session
Auth::init();

// 6. 获取 JSON 输入
// 因为 H5 使用 fetch post json，PHP $_POST 默认接收不到，需要手动解析
function getJsonInput() {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

// 定义一个快捷的 DB 实例访问器
function db() {
    return Database::getInstance();
}