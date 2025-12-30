<?php
// public/install.php
// 这是一个幂等脚本：多次运行不会破坏现有数据，只会补充缺失的表或字段。

header('Content-Type: text/html; charset=utf-8');

// 1. 环境检查
$dataDir = __DIR__ . '/../data';
$dbFile = $dataDir . '/meal.db';

echo "<h2>正在初始化/更新数据库...</h2>";

if (!is_dir($dataDir)) {
    if (!mkdir($dataDir, 0777, true)) {
        die("<p style='color:red'>❌ 无法创建 /data 目录，请检查权限。</p>");
    }
}

// 检查目录写权限
if (!is_writable($dataDir)) {
    die("<p style='color:red'>❌ /data 目录不可写。Linux请执行: chmod -R 777 data</p>");
}

// 2. 连接数据库
try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p>✅ 数据库连接成功</p>";
} catch (PDOException $e) {
    die("<p style='color:red'>❌ 数据库连接失败: " . $e->getMessage() . "</p>");
}

// 3. 定义表结构 (包含所有最新字段)
$tables = [
    // 用户表
    "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        phone TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        name TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'user', -- user, finance, admin
        level_id INTEGER DEFAULT 1,        -- 关联 user_levels
        status INTEGER DEFAULT 0,          -- 0:待审, 1:正常
        created_at DATETIME DEFAULT (datetime('now', 'localtime'))
    )",
    
    // 身份等级表 (新功能)
    "CREATE TABLE IF NOT EXISTS user_levels (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        price INTEGER NOT NULL -- 单位: 分
    )",

    // 余额表
    "CREATE TABLE IF NOT EXISTS balances (
        user_id INTEGER PRIMARY KEY,
        amount INTEGER DEFAULT 0,
        updated_at DATETIME DEFAULT (datetime('now', 'localtime'))
    )",

    // 订单表
    "CREATE TABLE IF NOT EXISTS meal_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        order_date TEXT NOT NULL, -- YYYY-MM-DD
        meal_type TEXT DEFAULT 'lunch',
        price INTEGER NOT NULL,   -- 下单时的快照价格
        status TEXT DEFAULT 'ordered', -- ordered, cancelled
        created_at DATETIME DEFAULT (datetime('now', 'localtime')),
        UNIQUE(user_id, order_date)
    )",

    // 排餐表
    "CREATE TABLE IF NOT EXISTS meal_schedules (
        date TEXT PRIMARY KEY, -- YYYY-MM-DD
        menu_text TEXT,
        created_at DATETIME DEFAULT (datetime('now', 'localtime'))
    )",

    // 充值流水表
    "CREATE TABLE IF NOT EXISTS recharge_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        amount INTEGER NOT NULL,
        operator_id INTEGER,
        created_at DATETIME DEFAULT (datetime('now', 'localtime'))
    )",

    // 系统配置表 (新功能: 公告、截止时间、对齐方式)
    "CREATE TABLE IF NOT EXISTS system_config (
        key TEXT PRIMARY KEY,
        value TEXT,
        desc TEXT
    )",

    // 反馈表
    "CREATE TABLE IF NOT EXISTS feedbacks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT (datetime('now', 'localtime'))
    )"
];

// 4. 执行建表
foreach ($tables as $sql) {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        echo "<p style='color:orange'>⚠️ 建表警告: " . $e->getMessage() . "</p>";
    }
}
echo "<p>✅ 数据表结构检查完毕</p>";

// 5. 初始化基础数据 (如果不存在则插入)

// A. 初始化身份等级 (如果是旧系统升级，这里会补充)
$count = $pdo->query("SELECT count(*) FROM user_levels")->fetchColumn();
if ($count == 0) {
    $pdo->exec("INSERT INTO user_levels (name, price) VALUES ('内部员工', 1000)"); // 10元
    $pdo->exec("INSERT INTO user_levels (name, price) VALUES ('外部人员', 1500)"); // 15元
    echo "<p>✅ 初始化默认身份等级完成</p>";
}

// B. 初始化系统配置 (补充新配置项)
$configs = [
    'deadline_lunch' => ['10:30', '午餐截止时间'],
    'sys_notice' => ['欢迎使用企业报餐系统！', '系统公告'],
    'sys_notice_align' => ['left', '公告对齐方式'] // 新增
];

foreach ($configs as $key => $val) {
    // 使用 INSERT OR IGNORE 避免覆盖用户已修改的设置
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO system_config (key, value, desc) VALUES (?, ?, ?)");
    $stmt->execute([$key, $val[0], $val[1]]);
}
echo "<p>✅ 系统配置项检查完毕</p>";

// C. 创建默认管理员 (如果不存在)
$stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
$stmt->execute(['13800138000']);
if (!$stmt->fetch()) {
    $pwd = password_hash('admin123', PASSWORD_DEFAULT);
    // 创建管理员
    $pdo->prepare("INSERT INTO users (phone, password, name, role, level_id, status) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute(['13800138000', $pwd, '超级管理员', 'admin', 1, 1]);
    
    // 初始化管理员余额
    $uid = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO balances (user_id, amount) VALUES (?, 0)")->execute([$uid]);
    
    echo "<p>✅ 默认管理员账号创建成功 (13800138000 / admin123)</p>";
}

// 6. 权限再次确认
chmod($dbFile, 0666); // 确保数据库文件可读写

echo "<hr>";
echo "<h2 style='color:green'>🎉 数据库更新/安装成功！</h2>";
echo "<p>请立即删除 <code>public/install.php</code> 文件以确保安全。</p>";
echo "<p><a href='index.html'>点击这里进入系统首页</a></p>";
?>