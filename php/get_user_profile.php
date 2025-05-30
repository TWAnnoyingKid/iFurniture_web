<?php
    session_start();
    header('Content-Type: application/json');
    
    // 引入配置文件
    require_once 'config.php';
    
    // 檢查用戶是否已登入
    if (!isset($_SESSION["login"]) || $_SESSION["login"] !== true) {
        echo json_encode(["success" => false, "message" => "用戶未登入"]);
        exit;
    }
    
    // 獲取登入的用戶名
    $username = isset($_SESSION["username"]) ? $_SESSION["username"] : "";
    
    if (empty($username)) {
        echo json_encode(["success" => false, "message" => "無法獲取用戶名"]);
        exit;
    }
    
    // 檢查是否安裝了 MongoDB 擴展
    if (!extension_loaded('mongodb')) {
        // 如果沒有安裝 MongoDB 擴展，返回模擬數據
        echo json_encode([
            "success" => true,
            "message" => "MongoDB not install",
            "user" => [
                "account" => "MongoDB not install",
            ]
        ]);
        exit;
    }
    
    // 使用 MongoDB 擴展的基本 API (而不是 MongoDB\Client)
    try {
        // 連接到 MongoDB - 使用配置文件中的連接字符串
        $manager = new MongoDB\Driver\Manager(config('databases.mongodb.connection_string'));
        
        // 建立查詢
        $filter = ["account" => $username];
        $options = [];
        $query = new MongoDB\Driver\Query($filter, $options);
        
        // 執行查詢
        $cursor = $manager->executeQuery(
            config('databases.mongodb.dbname') . ".profiles", 
            $query
        );
        
        $userProfile = null;
        
        // 獲取第一個匹配的文檔
        foreach ($cursor as $document) {
            $userProfile = $document;
            break;
        }
        
        if ($userProfile) {
            // 轉換 MongoDB 文檔為 PHP 陣列
            $userData = json_decode(json_encode($userProfile), true);
            echo json_encode(["success" => true, "user" => $userData]);
        } else {
            // 找不到用戶，返回模擬數據
            echo json_encode([
                "success" => true,
                "message" => "找不到用戶",
                "user" => [
                    "account" => "找不到用戶",
                ]
            ]);
        }
    } catch (Exception $e) {
        // 任何錯誤，返回模擬數據
        echo json_encode([
            "success" => true,
            "message" => "MongoDB 錯誤: " . $e->getMessage(),
            "user" => [
                "account" => "MongoDB錯誤",
            ]
        ]);
    }
?>