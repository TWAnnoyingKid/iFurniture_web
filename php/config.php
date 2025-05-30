<?php
/**
 * 系統配置文件 - 雙資料庫版本
 * 支援用戶認證和商品資料分離
 */

// 服務器設置
$CONFIG = [
    // API 服務器設置
    'api' => [
        'host' => 'localhost',  // API 服務器主機名/IP
        'port' => 5008,             // API 服務器端口
        'protocol' => 'http',       // 協議（http 或 https）
        'base_url' => null,         // 會被自動生成
    ],
    
    // 資料庫設置
    'databases' => [
        // 用戶認證 MongoDB 設置
        'mongodb' => [
            'host' => '192.168.0.106',
            'port' => 27017,
            'dbname' => 'users',  // 用戶認證資料庫
            'username' => '',  
            'password' => '', 
            'connection_string' => null  // 會被自動生成
        ],
        
        // 商品資料 MongoDB 設置
        'mongodb_furniture' => [
            'host' => '192.168.0.106',
            'port' => 27017,
            'dbname' => 'furniture_db',  // 商品資料庫
            'username' => '',
            'password' => '',
            'connection_string' => null  // 會被自動生成
        ],
        
        // MySQL 設置
        'mysql' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'dbname' => 'iFurniture',
            'username' => 'root', 
            'password' => 'zmxcnbv',
            'charset' => 'utf8mb4',
        ]
    ],
    
    // 其他設置
    'settings' => [
        'debug' => true,  // 是否開啟調試模式
        'timezone' => 'Asia/Taipei',  // 時區設置
    ]
];

// 生成完整 API URL
$CONFIG['api']['base_url'] = "{$CONFIG['api']['protocol']}://{$CONFIG['api']['host']}:{$CONFIG['api']['port']}";

// **【修改部分】** 生成用戶認證 MongoDB 連接字符串
if (empty($CONFIG['databases']['mongodb']['username'])) {
    $CONFIG['databases']['mongodb']['connection_string'] = 
        "mongodb://{$CONFIG['databases']['mongodb']['host']}:{$CONFIG['databases']['mongodb']['port']}/";
} else {
    $CONFIG['databases']['mongodb']['connection_string'] = 
        "mongodb://{$CONFIG['databases']['mongodb']['username']}:{$CONFIG['databases']['mongodb']['password']}@" .
        "{$CONFIG['databases']['mongodb']['host']}:{$CONFIG['databases']['mongodb']['port']}/";
}

// **【新增部分】** 生成商品資料 MongoDB 連接字符串
if (empty($CONFIG['databases']['mongodb_furniture']['username'])) {
    $CONFIG['databases']['mongodb_furniture']['connection_string'] = 
        "mongodb://{$CONFIG['databases']['mongodb_furniture']['host']}:{$CONFIG['databases']['mongodb_furniture']['port']}/";
} else {
    $CONFIG['databases']['mongodb_furniture']['connection_string'] = 
        "mongodb://{$CONFIG['databases']['mongodb_furniture']['username']}:{$CONFIG['databases']['mongodb_furniture']['password']}@" .
        "{$CONFIG['databases']['mongodb_furniture']['host']}:{$CONFIG['databases']['mongodb_furniture']['port']}/";
}

// 設置時區
date_default_timezone_set($CONFIG['settings']['timezone']);

/**
 * 獲取配置值的輔助函數
 * 
 * @param string $key 配置鍵，使用點符號來訪問嵌套配置，例如：api.host
 * @param mixed $default 如果找不到配置，返回的默認值
 * @return mixed 配置值
 */
function config($key, $default = null) {
    global $CONFIG;
    
    $keys = explode('.', $key);
    $value = $CONFIG;
    
    foreach ($keys as $segment) {
        if (!isset($value[$segment])) {
            return $default;
        }
        $value = $value[$segment];
    }
    
    return $value;
}

//輔助函數
/**
 * 獲取用戶認證資料庫連接
 */
function getUserDbConnection() {
    return new MongoDB\Driver\Manager(config('databases.mongodb.connection_string'));
}

/**
 * 獲取商品資料庫連接
 */
function getFurnitureDbConnection() {
    return new MongoDB\Driver\Manager(config('databases.mongodb_furniture.connection_string'));
}

/**
 * 獲取用戶認證資料庫名稱
 */
function getUserDbName() {
    return config('databases.mongodb.dbname');
}

/**
 * 獲取商品資料庫名稱
 */
function getFurnitureDbName() {
    return config('databases.mongodb_furniture.dbname');
}