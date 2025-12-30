<?php
// api/user/levels.php
require '../init.php';

// 注意：此接口不加 Auth::checkLogin()，因为注册页面也需要调用
// 获取所有启用的身份等级
try {
    $list = db()->query("SELECT id, name, price FROM user_levels ORDER BY id ASC");
    Response::success($list);
} catch (Exception $e) {
    Response::error('获取身份列表失败');
}