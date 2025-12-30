<?php
// api/user/logout.php
require '../init.php';

// 调用核心库的登出方法
Auth::logout();

Response::success([], '已退出登录');