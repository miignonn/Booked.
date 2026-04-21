<?php

session_start();
$_SESSION = [];

// delete session cookie
if (ini_get("session.use_cookies")){
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["hhtponly"]
    );
}

session_destroy();

header("Location: /public/login.php");
exit();
?>