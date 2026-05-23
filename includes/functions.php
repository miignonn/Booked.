<?php

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

function csrf_token(): string{
    return $_SESSION['csrf_token'] ??'';
}

function verify_csrf(): void{
    if (!isset($_POST['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])){
        http_response_code(403);
        die('Invalid request. CSRF token mismatch');
    }

}

//flash success messages
function set_flash(string $type, string $message) : void {
 $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash() : ?array{
    if (isset($_SESSION['flahs'])) {
        $flash = $_SESSION ['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }

    return null;
}
?>