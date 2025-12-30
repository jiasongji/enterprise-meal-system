<?php
require 'init.php';

// 测试 DB
$configs = db()->query("SELECT * FROM system_config");
// 测试 Response
Response::success($configs);