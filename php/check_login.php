<?php
// 引入配置文件
require_once 'config.php';

// 啟動會話
session_start();

// 設置回應類型為 JSON
header('Content-Type: application/json');

// 檢查用戶是否已登入
$isLoggedIn = isset($_SESSION["login"]) && $_SESSION["login"] === true;

// 獲取用戶名（如果已登入）
$username = $isLoggedIn && isset($_SESSION["username"]) ? $_SESSION["username"] : "";

// 回傳登入狀態和用戶名
echo json_encode([
    "isLoggedIn" => $isLoggedIn,
    "username" => $username
]);
?>