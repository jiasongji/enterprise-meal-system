<?php
require '../init.php';
Auth::checkRole(['admin', 'finance']); 

$type = $_GET['type'] ?? 'meal'; // meal=报餐统计, analysis=统计分析

// 1. 报餐统计 (支持默认当天，支持筛选)
if ($type === 'meal') {
    // 默认显示今天
    $start = $_GET['start'] ?? date('Y-m-d');
    $end = $_GET['end'] ?? date('Y-m-d');

    $sql = "SELECT order_date, COUNT(*) as total_count, SUM(price) as total_amount 
            FROM meal_orders 
            WHERE status = 'ordered' AND order_date BETWEEN ? AND ?
            GROUP BY order_date ORDER BY order_date DESC";
    
    $data = db()->query($sql, [$start, $end]);
    Response::success($data);
}

// 2. 统计分析 (收入与资金)
elseif ($type === 'analysis') {
    $mode = $_GET['mode'] ?? 'day'; // day 或 month
    
    // A. 营业收入 (按日/月统计已消费的订单)
    $fmt = ($mode === 'month') ? '%Y-%m' : '%Y-%m-%d';
    
    $sqlIncome = "SELECT strftime(?, order_date) as d, SUM(price) as total 
                  FROM meal_orders WHERE status='ordered' 
                  GROUP BY d ORDER BY d DESC LIMIT 30";
    $income = db()->query($sqlIncome, [$fmt]);
    
    // B. 账户充值资金统计
    $sqlRecharge = "SELECT strftime(?, created_at) as d, SUM(amount) as total 
                    FROM recharge_logs 
                    GROUP BY d ORDER BY d DESC LIMIT 30";
    $recharge = db()->query($sqlRecharge, [$fmt]);

    // C. 当前总资金池
    $totalPool = db()->fetch("SELECT SUM(amount) as s FROM balances");

    Response::success([
        'income' => $income,
        'recharge' => $recharge,
        'pool' => $totalPool['s']
    ]);
}