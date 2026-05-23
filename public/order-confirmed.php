<?php 
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ .'/../config/db.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if($order_id == 0){
    header('Location: /index.php');
    exit();
}

$stmt = $conn->prepare("
SELECT orders.*, listings.title, listings.price AS listing_price,
users.username AS seller_username, users.email AS seller_email
FROM orders
JOIN listings ON orders.listing_id = listings.id
JOIN users ON orders.seller_id = users.id
WHERE orders.id = ? AND orders.buyer_id = ?
");

$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order){
    header('Location: /index.php');
    exit();
}

require_once __DIR__ . '/../includes/header.php';
?>

<!--- Step bar --->

<div class="step-bar">
    <div class="step">
        <div class="step-circle step-circle--muted">1</div>
        <span class="step-label step-label--muted">Cart</span>
    </div>
    <div class="step-divider"></div>
    <div class="step">
        <div class="step-circle step-circle--muted">2</div>
        <span class="step-label step-label--muted">Checkout</span>
    </div>
    <div class="step-divider"></div>
    <div class="step">
        <div class="step-circle step-circle--muted">3</div>
        <span class="step-label step-label--muted">Payment</span>
    </div>
    <div class="step-divider"></div>
    <div class="step">
        <div class="step-circle">4</div>
        <span class="step-label">Confirmed</span>
    </div>
</div>

<div class="text-center mb-5">
    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
    style="width:60px;height:60px;">
    <i class="bi bi-check-lg fs-3"></i>
    </div>
    <h4 class="fw-bold">You're all booked!</h4>
    <p class="text-muted">Your order has been placed. Contact the seller to arrange collection.</p>
</div>

<div class="safety-notice">
    <p class="safety-notice__title"><i class="bi bi-shield-exclamation"></i> Stay Safe</p>
    <p class="mb-0">Always meet in a public place on campus to collect your textbook. Never transfer money before seeing the book in person. Booked will never ask you for payment outside the platform.</p>
</div>

<div class="confirmed-cols d-flex gap-4">
 
    <!-- Order details -->
    <div class="flex-fill">
        <div class="summary-card">
            <p class="summary-card__label">Order Details</p>
            <div class="summary-row">
                <span class="summary-row__key">Order Number</span>
                <span class="summary-row__val">#<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-row__key">Date Placed</span>
                <span class="summary-row__val"><?= date('d M Y', strtotime($order['created_at'])) ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-row__key">Book</span>
                <span class="summary-row__val"><?= htmlspecialchars($order['title']) ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-row__key">Total Paid</span>
                <span class="summary-row__val">R<?= number_format($order['total_price'], 2) ?></span>
            </div>
        </div>
    </div>
 
    <!-- Collection details -->
    <div class="flex-fill">
        <div class="summary-card">
            <p class="summary-card__label">Collection Details</p>
            <div class="summary-row">
                <span class="summary-row__key">Campus</span>
                <span class="summary-row__val"><?= htmlspecialchars($order['campus']) ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-row__key">Preferred Time</span>
                <span class="summary-row__val"><?= date('d M Y H:i', strtotime($order['preferred_time'])) ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-row__key">Seller</span>
                <span class="summary-row__val">@<?= htmlspecialchars($order['seller_username']) ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-row__key">Seller Email</span>
                <span class="summary-row__val"><?= htmlspecialchars($order['seller_email']) ?></span>
            </div>
        </div>
    </div>
 
</div>
 
<div class="text-center mt-4">
    <a href="/browse.php" class="btn btn-dark">Continue Browsing</a>
</div>
 
<?php require_once '../includes/footer.php'; ?>