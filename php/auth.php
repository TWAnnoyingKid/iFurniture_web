<?php
/**
 * 統一的用戶認證處理系統
 * 整合登入、登出、狀態檢查、用戶檔案獲取功能
 */

session_start();
header('Content-Type: application/json');

// 引入配置文件
require_once 'config.php';
require_once 'auth_helper.php';

// 獲取請求方法和動作
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'check_login';

try {
    switch ($action) {
        case 'check_login':
            handleCheckLogin();
            break;
        case 'login':
            handleLogin();
            break;
        case 'logout':
            handleLogout();
            break;
        case 'get_profile':
            handleGetProfile();
            break;
        default:
            throw new Exception("不支援的操作: " . $action);
    }
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

//檢查登入狀態
function handleCheckLogin() {
    $authStatus = checkAndRestoreLoginStatus();
    
    echo json_encode([
        "isLoggedIn" => $authStatus['isLoggedIn'],
        "username" => $authStatus['username']
    ]);
}

//處理登入請求
function handleLogin() {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("僅支援 POST 請求");
    }
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo "<script type='text/javascript'> alert('您的使用者名稱欄或密碼欄尚未填寫');</script>";
        header('refresh:0.1;url = ../web/login.html');
        return;
    }
    
    // 使用config.php中的MySQL配置進行登入驗證
    try {
        // 建立MySQL連接
        $mysqlHost = config('databases.mysql.host');
        $mysqlPort = config('databases.mysql.port');
        $mysqlDbname = config('databases.mysql.dbname');
        $mysqlUsername = config('databases.mysql.username');
        $mysqlPassword = config('databases.mysql.password');
        $mysqlCharset = config('databases.mysql.charset');
        
        // 建立連接
        $conn = new mysqli($mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDbname, $mysqlPort);
        
        // 檢查連接
        if ($conn->connect_error) {
            throw new Exception("MySQL連接失敗: " . $conn->connect_error);
        }
        
        // 設定字符集
        $conn->set_charset($mysqlCharset);
        
        // 使用預處理語句防止SQL注入
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // 驗證密碼
            if ($password === $user["password"]) {
                $_SESSION["login"] = true;
                $_SESSION["username"] = $username;
                
                // 設定 cookie 記住登入 7 天
                setcookie("login", "true", time() + (7 * 24 * 60 * 60), "/");
                setcookie("username", $username, time() + (7 * 24 * 60 * 60), "/");
                
                $stmt->close();
                $conn->close();
                
                header("Location: ../web/index.html");
                exit;
            } else {
                $stmt->close();
                $conn->close();
                echo "'使用者名稱或密碼不正確！";
                header('refresh:3;url = ../web/login.html');
            }
        } else {
            $stmt->close();
            $conn->close();
            echo "'使用者名稱或密碼不正確！";
            header('refresh:3;url = ../web/login.html');
        }
        
    } catch (Exception $e) {
        error_log("MySQL登入錯誤: " . $e->getMessage());
        echo "<script type='text/javascript'> alert('登入系統發生錯誤，請稍後再試');</script>";
        header('refresh:2;url = ../web/login.html');
    }
}

//處理登出請求
function handleLogout() {
    // 清除 session
    $_SESSION = array();
    session_destroy();
    
    // 清除 cookie
    setcookie("login", "", time() - 3600, "/");
    setcookie("username", "", time() - 3600, "/");
    
    // 如果是AJAX請求，返回JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(["success" => true, "message" => "登出成功"]);
    } else {
        // 重定向到登入頁面
        header("Location: ../web/login.html");
    }
    exit;
}

//獲取用戶檔案資訊
function handleGetProfile() {
    // 使用輔助函數檢查並恢復登入狀態
    $username = requireLogin(true);
    
    // 檢查是否安裝了 MongoDB 擴展
    if (!extension_loaded('mongodb')) {
        echo json_encode([
            "success" => true,
            "message" => "MongoDB not install",
            "user" => [
                "account" => "MongoDB not install",
            ]
        ]);
        return;
    }
    
    try {
        // 連接到 MongoDB
        $manager = new MongoDB\Driver\Manager(config('databases.mongodb.connection_string'));
        
        // 建立查詢
        $filter = ["account" => $username];
        $query = new MongoDB\Driver\Query($filter, []);
        
        // 執行查詢
        $cursor = $manager->executeQuery(
            config('databases.mongodb.dbname') . ".profiles", 
            $query
        );
        
        $userProfile = null;
        foreach ($cursor as $document) {
            $userProfile = $document;
            break;
        }
        
        if ($userProfile) {
            $userData = json_decode(json_encode($userProfile), true);
            echo json_encode(["success" => true, "user" => $userData]);
        } else {
            echo json_encode([
                "success" => true,
                "message" => "找不到用戶",
                "user" => [
                    "account" => "找不到用戶",
                ]
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            "success" => true,
            "message" => "MongoDB 錯誤: " . $e->getMessage(),
            "user" => [
                "account" => "MongoDB錯誤",
            ]
        ]);
    }
}
?>