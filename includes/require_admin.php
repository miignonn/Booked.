<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

//refresh session status from DB
if (isset($_SESSION['user_id'])){
    refresh_user_status($conn);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
http_response_code(403);
header('Location: /login.php');
exit();
}

?>