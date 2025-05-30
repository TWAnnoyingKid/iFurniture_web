<?php
    $hn = '127.0.0.1';
    $db = 'iFurniture';
    $un = 'root';
    $pw = 'zmxcnbv';
    $conn = mysqli_connect($hn,$un,$pw,$db);
    if($conn->connect_error){
        echo "ERROR: ". $conn->connect_error;
    }

?>