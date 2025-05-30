<?php
    session_start();
    
    // 清除 session
    $_SESSION = array();
    session_destroy();
    
    // 清除 cookie
    setcookie("login", "", time() - 3600, "/");
    setcookie("username", "", time() - 3600, "/");
    
    // 重定向到登入頁面
    header("Location: ../web/login.html");
    exit;
?> 