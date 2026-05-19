<?php
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
?>