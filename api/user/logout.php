<?php
// api/user/logout.php
require '../init.php';

// 销毁 Session
Auth::logout();

Response::success([], '已退出登录');