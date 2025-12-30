<?php
require '../init.php';
Auth::checkLogin();
$uid = Auth::id();
$user = Auth::user();

// 1. 获取截止时间
$conf = db()->fetch("SELECT value FROM system_config WHERE key='deadline_lunch'");
$deadlineTime = $conf['value'] ?? '10:30';

// 2. 获取用户价格
$level = db()->fetch("SELECT price FROM user_levels WHERE id = ?", [$user['level_id']]);
$price = $level['price'];

// 3. 获取未来排餐 (展示今天及未来30天)
$today = date('Y-m-d');
$maxDate = date('Y-m-d', strtotime('+30 days'));

$schedules = db()->query("SELECT * FROM meal_schedules WHERE date BETWEEN ? AND ? ORDER BY date ASC", [$today, $maxDate]);

// 4. 获取用户已报的记录
$orders = db()->query("SELECT order_date FROM meal_orders WHERE user_id = ? AND status = 'ordered' AND order_date >= ?", [$uid, $today]);
$bookedDates = array_column($orders, 'order_date');

$list = [];
$now = time();

foreach ($schedules as $s) {
    $date = $s['date'];
    $deadlineTs = strtotime("$date $deadlineTime");
    
    // 状态判断
    $isBooked = in_array($date, $bookedDates);
    $isExpired = $now > $deadlineTs;
    
    $list[] = [
        'date' => $date,
        'week' => ['日','一','二','三','四','五','六'][date('w', strtotime($date))],
        'menu' => $s['menu_text'],
        'price' => $price,
        'is_booked' => $isBooked,
        'is_expired' => $isExpired,
        'actionable' => !$isExpired
    ];
}

Response::success(['list' => $list, 'deadline' => $deadlineTime]);