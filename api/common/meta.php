<?php
// api/common/meta.php
require '../init.php';

// 此接口公开，无需登录即可访问（用于注册页面的部门选择）
$depts = db()->query("SELECT * FROM departments");
$levels = db()->query("SELECT * FROM user_levels");

Response::success([
    'departments' => $depts,
    'levels' => $levels
]);