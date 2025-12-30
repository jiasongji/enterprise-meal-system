<?php
// api/admin/config.php
require '../init.php';

// 获取配置
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Auth::checkLogin();
    $data = db()->query("SELECT * FROM system_config");
    $conf = [];
    foreach ($data as $r) $conf[$r['key']] = $r['value'];
    Response::success($conf);
}

// 修改配置 (仅管理员)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::checkRole('admin');
    $input = getJsonInput();
    
    // 1. 公告内容
    if (isset($input['sys_notice'])) {
        db()->execute("INSERT OR REPLACE INTO system_config (key, value, desc) VALUES ('sys_notice', ?, '系统公告')", [$input['sys_notice']]);
    }

    // 2. 公告对齐方式 (新增)
    if (isset($input['sys_notice_align'])) {
        $align = in_array($input['sys_notice_align'], ['left', 'center', 'right']) ? $input['sys_notice_align'] : 'left';
        db()->execute("INSERT OR REPLACE INTO system_config (key, value, desc) VALUES ('sys_notice_align', ?, '公告对齐')", [$align]);
    }
    
    // 3. 截止时间
    if (isset($input['deadline_lunch'])) {
        db()->execute("INSERT OR REPLACE INTO system_config (key, value, desc) VALUES ('deadline_lunch', ?, '报餐截止时间')", [$input['deadline_lunch']]);
    }
    
    Response::success([], '设置已更新');
}