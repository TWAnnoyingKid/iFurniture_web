<?php
    session_start();
    require_once('mysql.php');
    $username = $_POST['username'];
    $password = $_POST['password'];

    if($_SERVER["REQUEST_METHOD"]=="POST"){
        if($username==null||$password==null){
            echo"<script type='text/javascript'> alert('您的使用者名稱欄或密碼欄尚未填寫');</script>";
            header('refresh:0.1;url = ../web/login.html');
        }
        else{
            $sql="SELECT * FROM users WHERE username = '$username'";
            $result = mysqli_query($conn,$sql);
            if(mysqli_num_rows($result)==1 && $password == mysqli_fetch_assoc($result)["password"]){
                session_start();
                $_SESSION["login"]=true;
                $_SESSION["username"]=$username;
                
                // 設定 cookie 記住登入 7 天
                setcookie("login", "true", time() + (7 * 24 * 60 * 60), "/"); // 7天
                setcookie("username", $username, time() + (7 * 24 * 60 * 60), "/"); // 7天
                
                header("Location: ../web/index.html");
                exit; // 確保後續程式碼不會執行
            }else{
                echo"'使用者名稱或密碼不正確！";
                header('refresh:3;url = ../web/login.html');
            }
        }
    }
    else{
        echo "Something wrong";
        header('refresh:3;url = ../web/login.html');
    }
    mysqli_close($conn);
?>