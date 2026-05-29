<?php

if (session_status() === PHP_SESSION_NONE){
   ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_httponly', '1');
    session_start(); 
}
$servername = getenv('DB_HOST');
$username   = getenv('DB_USER');
$password   = getenv('DB_PASS');
$dbname     = getenv('DB_NAME');
$port       = getenv('DB_PORT') ?: 3306;

$conn = new mysqli($servername, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    error_log("DB connection failed: " . $conn->connect_error);
    die("Service temporarily unavailable");
}
$conn->set_charset('utf8mb4');