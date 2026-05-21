
<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$reference = isset($_GET['reference']) ? trim($_GET['reference']) : '';
$order_id  = isset($_GET['order_id'])  ? (int)$_GET['order_id']  : 0;

if (!$reference || !$order_id) {
    header("Location: index.php");
    exit;
}

$env = parse_ini_file(__DIR__ . '/../.env');
$paystackSecretKey = $env['PAYSTACK_SECRET_KEY'];

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL            => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer " . $paystackSecretKey,
        "Cache-Control: no-cache",
    ],
]);
$response = curl_exec($curl);
$result = json_decode($response, true);

if (!$result['status'] || $result['data']['status'] !== 'success') {
    header("Location: pay.php?order_id=" . $order_id . "&error=payment_failed");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND buyer_id = ?");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: index.php");
    exit;
}

$update = $conn->prepare("
    UPDATE orders SET status = 'completed', paystack_reference = ?
    WHERE id = ?
");
$update->bind_param("si", $reference, $order_id);
$update->execute();

$sold = $conn->prepare("UPDATE listings SET status = 'sold' WHERE id = ?");
$sold->bind_param("i", $order['listing_id']);
$sold->execute();

$clear = $conn->prepare("DELETE FROM cart WHERE listing_id = ? AND user_id = ?");
$clear->bind_param("ii", $order['listing_id'], $_SESSION['user_id']);
$clear->execute();

header("Location: order-confirmed.php?order_id=" . $order_id);
exit;

