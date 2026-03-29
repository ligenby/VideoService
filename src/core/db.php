<?php

date_default_timezone_set('Europe/Moscow');

$host = "localhost";
$user = "videoservice_user";
$pass = "lsyN4d";
$db   = "videoservice"; 

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}
?>
 