<?php
// core/Database.php

class Database {
    private static $instance = null;
    private $pdo;

    // 私有构造函数，防止外部 new
    private function __construct() {
        $config = require __DIR__ . '/../config/database.php';
        
        if (!file_exists($config['db_path'])) {
            throw new Exception("数据库文件不存在: " . $config['db_path']);
        }

        try {
            // 连接 SQLite
            $dsn = 'sqlite:' . $config['db_path'];
            $this->pdo = new PDO($dsn);
            
            // 设置错误模式为抛出异常
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // 默认获取关联数组
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // 开启外键支持
            $this->pdo->exec("PRAGMA foreign_keys = ON;");
            
        } catch (PDOException $e) {
            die("数据库连接失败: " . $e->getMessage());
        }
    }

    // 获取单例实例
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 执行查询并返回所有结果 (SELECT)
     * @param string $sql SQL语句
     * @param array $params 绑定参数
     * @return array
     */
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 执行查询并返回单行结果 (SELECT ONE)
     * @param string $sql
     * @param array $params
     * @return array|false
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * 执行增删改 (INSERT, UPDATE, DELETE)
     * @param string $sql
     * @param array $params
     * @return int 受影响行数
     */
    public function execute($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * 获取最后插入的 ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    // 获取原生 PDO 对象（用于事务处理等）
    public function getPdo() {
        return $this->pdo;
    }
}