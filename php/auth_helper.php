<?php
/**
 * 認證輔助函數
 * 提供通用的登入狀態檢查和恢復功能
 */

/**
 * 檢查並恢復用戶登入狀態
 * 優先檢查 SESSION，如果沒有則嘗試從 COOKIE 恢復
 * 
 * @return array 包含 isLoggedIn 和 username 的陣列
 */
function checkAndRestoreLoginStatus() {
    // 確保 session 已啟動
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // 首先檢查 SESSION 是否有登入狀態
    $isLoggedIn = isset($_SESSION["login"]) && $_SESSION["login"] === true;
    $username = $isLoggedIn && isset($_SESSION["username"]) ? $_SESSION["username"] : "";
    
    // 如果 SESSION 沒有登入狀態，但 COOKIE 有，則嘗試恢復登入狀態
    if (!$isLoggedIn && isset($_COOKIE["login"]) && $_COOKIE["login"] === "true" && isset($_COOKIE["username"])) {
        $cookieUsername = $_COOKIE["username"];
        
        // 驗證 COOKIE 中的用戶名是否有效
        if (!empty($cookieUsername)) {
            // 恢復 SESSION 狀態
            $_SESSION["login"] = true;
            $_SESSION["username"] = $cookieUsername;
            
            $isLoggedIn = true;
            $username = $cookieUsername;
            
            error_log("從 COOKIE 恢復登入狀態: " . $cookieUsername);
        }
    }
    
    return [
        'isLoggedIn' => $isLoggedIn,
        'username' => $username
    ];
}

/**
 * 檢查用戶是否已登入，如果未登入則輸出錯誤並終止執行
 * 
 * @param bool $returnJson 是否以JSON格式返回錯誤訊息，如果為false則拋出異常
 * @return string|null 如果已登入則返回用戶名，否則終止執行或拋出異常
 */
function requireLogin($returnJson = true) {
    $authStatus = checkAndRestoreLoginStatus();
    
    if (!$authStatus['isLoggedIn']) {
        if ($returnJson) {
            header('Content-Type: application/json');
            echo json_encode(["success" => false, "message" => "用戶未登入"]);
            exit;
        } else {
            throw new Exception("用戶未登入");
        }
    }
    
    if (empty($authStatus['username'])) {
        if ($returnJson) {
            header('Content-Type: application/json');
            echo json_encode(["success" => false, "message" => "無法獲取用戶名"]);
            exit;
        } else {
            throw new Exception("無法獲取用戶名");
        }
    }
    
    return $authStatus['username'];
}
?> 