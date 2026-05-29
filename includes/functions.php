<?php

if(session_status() === PHP_SESSION_NONE){
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_httponly', '1');
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
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION ['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }

    return null;
}

//Pagination
//returns current page from URL
function get_page(int $default = 1) : int {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : $default;
    return max(1, $page);
}

//returns limit and offset for query
function paginate(int $page, int $per_page = 20) : array{
    return [
        'limit' => $per_page,
        'offset' => ($page -1) * $per_page,
    ];
}

//render pagination links
function pagination_links(int $total, int $per_page, int $current_page, string $base_url): string {
    $total_pages = (int)ceil($total / $per_page);
    if ($total_pages <= 1) return '';

    // preserve existing query params except page
    $params = $_GET;
    unset($params['page']);
    $query = $params ? '&' . http_build_query($params) : '';

    $html = '<div class="pagination-wrap">';
    for ($i = 1; $i <= $total_pages; $i++) {
        $active = $i === $current_page ? 'pagination-link--active' : '';
        $html .= "<a href=\"{$base_url}?page={$i}{$query}\" class=\"pagination-link {$active}\">{$i}</a>";
    }
    $html .= '</div>';
    return $html;
}

function refresh_user_status(mysqli $conn): void{
    if (!isset($_SESSION['user_id'])) return;
    $stmt = $conn->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row){
        $_SESSION['status'] = $row['status'];
    }
}

function expire_old_listings(mysqli $conn): void{
    $conn->query("
    UPDATE listings SET status = 'expired'
    WHERE status = 'available'
    AND created_at < NOW() - INTERVAL 60 DAY
    ");
}
?>