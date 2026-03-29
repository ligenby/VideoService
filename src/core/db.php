<?php

date_default_timezone_set('Europe/Moscow');

$host = "localhost";
$user = "root";
$pass = "";
$db   = "videoservice"; 

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}
?>
 
