# 🛠️ 企业内部报餐系统 - 开发与维护手册 (v1.2.0)

## 1. 项目概述 (Project Overview)

本项目是一个轻量级、前后端分离的企业食堂报餐管理系统。
**核心设计理念**：极简架构。不依赖 Composer、不使用复杂的 PHP 框架（如 Laravel/ThinkPHP），仅使用 PHP 原生语法和 SQLite 嵌入式数据库。这使得系统具有部署极快、零依赖迁移、适合内网环境等特点。

### 1.1 技术栈 (Tech Stack)
* **服务端**：PHP 7.4+ (必需扩展: `pdo`, `pdo_sqlite`, `json`, `mbstring`, `session`)
* **数据库**：SQLite 3 (单文件存储，支持事务，无需 MySQL 服务)
* **前端**：原生 HTML5 + CSS3 + Vanilla JavaScript (ES6+)
* **通信协议**：RESTful 风格 API (JSON 格式)
* **架构模式**：SPA (单页应用) + API Server

---

## 2. 目录结构详解 (Directory Structure)

项目采用严格的分层结构，确保安全性与可维护性。

```text
/project_root
├── /api                 # [后端] API 接口层 (前端唯一可访问的后端入口)
│   ├── /admin           # -> 管理员专用接口 (头部需 Auth::checkRole('admin'))
│   │   ├── config.php   # 公告内容、对齐方式、截止时间配置
│   │   ├── schedule.php # 排餐管理 (批量排餐/删除)
│   │   ├── settings.php # 身份等级与价格管理 (增删改)
│   │   ├── stats.php    # 报表统计 (报餐数据/资金流水)
│   │   └── users.php    # 人员管理 (审核/编辑/权限/删除)
│   ├── /finance         # -> 财务专用接口 (头部需 Auth::checkRole(['admin','finance']))
│   │   ├── logs.php     # 充值记录查询
│   │   └── recharge.php # 余额充值处理
│   ├── /user            # -> 普通用户/公共接口 (头部需 Auth::checkLogin)
│   │   ├── feedback.php # 问题反馈 (提交/查看/删除)
│   │   ├── info.php     # 获取个人信息与余额
│   │   ├── levels.php   # 获取身份列表 (公开接口，用于注册和编辑)
│   │   ├── login.php    # 登录
│   │   ├── logout.php   # 登出
│   │   ├── menu.php     # 获取餐单列表 (含状态判断)
│   │   ├── order.php    # 核心：报餐与退餐逻辑
│   │   ├── profile.php  # 修改个人资料 (姓名/手机/密码)
│   │   └── register.php # 注册
│   └── init.php         # [引导] 所有接口必引文件 (加载配置、类库、Session启动、CORS处理)
│
├── /config              # [配置层]
│   └── database.php     # -> 定义 SQLite 文件路径、时区、调试模式
│
├── /core                # [核心层] 基础类库 (微框架内核)
│   ├── Auth.php         # -> Session 管理、登录状态检查、角色权限控制
│   ├── Database.php     # -> PDO 单例模式封装、预处理 SQL 执行
│   └── Response.php     # -> 标准化 JSON 输出 ({code, msg, data})
│
├── /data                # [数据层] 存放数据库文件
│   ├── meal.db          # -> SQLite 数据文件 (由 install.php 自动生成)
│   └── .htaccess        # -> Apache 配置 (拒绝所有 Web 访问，保护数据安全)
│
├── /public              # [表现层] Web 根目录
│   ├── static           # -> CSS 样式与通用 JS 函数 (app.js)
│   ├── index.html       # -> 系统的唯一入口页面 (H5 SPA)
│   └── install.php      # -> 初始化安装脚本 (部署完成后必须删除)
│
└── DEVELOPMENT.md       # 开发说明文档

```

---

## 3. 数据库设计 (Database Schema)

系统使用 SQLite，所有关键业务逻辑（报餐、充值）均使用 **DB Transaction** 保证一致性。
**特别注意**：系统中所有涉及金额的字段，单位统一为 **“分” (Integer)**，前端显示时除以 100 转换为元。

### 3.1 数据表详情

| 表名 | 用途 | 关键字段说明 | 备注 |
| --- | --- | --- | --- |
| **`users`** | 用户表 | `id`, `phone` (账号, UNIQUE), `password` (Hash), `role` ('user' | 'finance' |
| **`user_levels`** | 身份等级 | `id`, `name` (如: 内部员工), `price` (该身份餐费-分) | 支持动态增删 |
| **`balances`** | 余额表 | `user_id` (PK), `amount` (当前余额-分) | 1对1关联 users |
| **`meal_orders`** | 订单表 | `user_id`, `order_date` (YYYY-MM-DD), `status` ('ordered' | 'cancelled'), `price` (下单时快照价) |
| **`meal_schedules`** | 排餐表 | `date` (PK, YYYY-MM-DD), `menu_text` (菜品描述) | 只有排餐的日期才可预订 |
| **`recharge_logs`** | 充值记录 | `id`, `user_id`, `operator_id` (操作人), `amount` (金额), `created_at` | 资金流水凭证 |
| **`system_config`** | 系统配置 | `key` (PK), `value`, `desc` | 存公告、截止时间等 KV 数据 |
| **`feedbacks`** | 问题反馈 | `id`, `user_id`, `content`, `created_at` | 简单的留言板 |

### 3.2 关键配置项 (`system_config`)

* `deadline_lunch`: 每日报餐截止时间 (格式 `HH:mm`，如 `10:30`)。
* `sys_notice`: 首页公告内容。
* `sys_notice_align`: 公告对齐方式 (`left` / `center` / `right`)。

---

## 4. 核心业务逻辑实现 (Core Logic)

### 4.1 鉴权与会话 (`core/Auth.php`)

* **Session**：使用 PHP 原生 `session_start()`。
* **拦截器**：
* `Auth::checkLogin()`: 检查 `$_SESSION['user']` 是否存在。
* `Auth::checkRole($role)`: 检查当前用户角色是否在允许列表中。



### 4.2 报餐与退餐 (`api/user/order.php`)

这是系统最核心的事务逻辑，处理流程如下：

1. **全局校验**：
* 检查 `meal_schedules`：当日是否有排餐。
* 检查 `system_config['deadline_lunch']`：当前时间是否已过截止时间。


2. **报餐 (Action: book)**：
* 开启事务。
* 锁定查询用户余额与等级价格。
* 判断余额是否充足 (`balance >= price`)。
* 扣除余额 (`UPDATE balances`)。
* 写入订单：
* 若无记录：`INSERT INTO meal_orders`.
* 若有记录且状态为 `cancelled`：`UPDATE meal_orders SET status='ordered'`.
* 若有记录且状态为 `ordered`：抛出异常（重复报餐）。


* 提交事务。


3. **退餐 (Action: cancel)**：
* 开启事务。
* 检查订单是否存在且状态为 `ordered`。
* 退还余额 (`UPDATE balances SET amount = amount + price`)。
* 标记订单 (`UPDATE meal_orders SET status='cancelled'`)。
* 提交事务。



### 4.3 动态身份与价格 (`api/admin/settings.php` & `api/user/levels.php`)

* 系统不再硬编码“内部/外部”两种身份。
* **注册时**：前端请求 `/api/user/levels.php` 获取所有启用的身份列表供用户选择。
* **管理时**：管理员可动态添加新身份（如“专线人员”），并设定对应餐费。删除身份时会检查该身份下是否有用户，防止数据孤岛。

---

## 5. 前端架构说明 (Frontend Architecture)

前端位于 `/public` 目录，是一个基于 **原生 JS** 的 SPA（单页应用）。

### 5.1 视图路由 (View Router)

没有引入 Vue-Router 等库，而是通过 DOM 操作显隐 `div` 容器实现页面切换：

* **`#view-home`**: 首页/公告/九宫格菜单。
* **`#view-order`**: 报餐列表页。
* **`#view-profile`**: 个人中心页。
* **`#sub-page`**: 全屏弹窗（用于二级页面，如充值、编辑资料）。

### 5.2 核心 JS 方法 (`public/index.html` script 区域)

* **`request(url, method, data)`**: 统一封装 `fetch` 请求，处理 JSON 解析、错误提示和 **401 未登录自动跳转**。
* **`renderHome()`**: 权限控制核心。根据 `curUser.role` 动态拼接 HTML 字符串，决定首页显示哪些功能图标（1-9 功能块）。
* **`loadOrderList()`**: 渲染日历卡片。根据后端返回的 `is_booked` (已订)、`actionable` (未截止) 字段，动态渲染 **红色退餐按钮**、**蓝色报餐按钮** 或 **灰色截止标签**。
* **`toggleNotice()`**: 控制公告栏的 CSS 类名 (`collapsed`/`expanded`) 实现展开/收起效果。

---

## 6. 部署与安装指南 (Deployment)

### 6.1 环境准备

* OS: Linux (推荐 Ubuntu/CentOS) 或 Windows (WAMP/XAMPP)。
* PHP: 7.4 或 8.x。
* Web Server: Nginx 或 Apache。

### 6.2 权限设置 (关键)

SQLite 数据库文件需要写入权限。

```bash
# 假设网站根目录为 /www/wwwroot/meal
cd /www/wwwroot/meal
chmod -R 777 data

```

### 6.3 Nginx 配置安全加固

为了防止直接下载数据库或源代码，需屏蔽敏感目录：

```nginx
server {
    listen 80;
    server_name meal.company.com;
    root /www/wwwroot/meal;
    index public/index.html;

    # 禁止访问核心目录
    location ~ ^/(data|config|core|api)/ {
        return 403;
    }

    # 首页重定向
    location = / {
        rewrite ^/$ /public/index.html last;
    }
}

```

### 6.4 初始化系统

1. 浏览器访问 `http://your-domain.com/public/install.php`。
2. 看到“数据库结构已更新”提示。
3. **务必删除 `public/install.php` 文件**。
4. 默认管理员：`13800138000` / `admin123`。

---

## 7. 常见问题 (FAQ)

**Q: 报餐时提示 `SQLSTATE[HY000]: database is locked`?**
A: 这是 SQLite 的并发写锁机制。通常因权限不足导致 PHP 写文件慢，或并发量极大。请检查 `/data` 目录权限是否为 777。

**Q: 修改了 PHP 代码不生效？**
A: PHP 是即时解释的，修改立即生效。如果是修改了 `index.html` 里的 JS/CSS，请尝试清理浏览器缓存 (Ctrl+F5) 或在隐身模式下测试。

**Q: 如何修改数据库路径？**
A: 编辑 `/config/database.php` 中的 `db_path`。

**Q: 公告换行不显示？**
A: 确保前端 CSS 中 `.notice-content` 包含 `white-space: pre-wrap;` 属性。

---

## 8. 二次开发规范

1. **新增接口**：
* 在 `/api` 对应目录下创建 PHP 文件。
* 必须包含 `require '../init.php';`。
* 成功返回：`Response::success($data)`。
* 失败返回：`Response::error($msg)`。


2. **前端修改**：
* 尽量保持单文件 (`index.html`) 结构，便于热更新部署。
* 所有的 API 调用统一使用 `request()` 函数。


3. **安全性**：
* 所有 SQL 操作必须通过 `db()->execute()` 或 `db()->query()` 使用预处理参数（`?` 占位符），严禁拼接 SQL 字符串。
