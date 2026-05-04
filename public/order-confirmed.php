<?php 
require_once '../includes/header.php';
require_once '../includes/auth_check.php';
require_once __DIR__. '/../config/db.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if($order_id == 0){
    header('Location: index/php');
    exit();
}

$stmt = $conn->prepare("
SELECT orders.*, listings.title, listings.price AS listing_price,
users.username AS seller_username
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
?>

<div class="d-flex align-items-center mb-5">
    <div class="d-flex align-items-center gap-2">
        <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center fw-bold"
             style="width:32px;height:32px;">1</div>
        <span class="fw-bold">Cart</span>
    </div>

    <div class="flex-grow-1 border-top mx-3"></div>
    <div class="d-flex align-items-center gap-2">
        <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center"
             style="width:32px;height:32px;">2</div>
        <span class="fw-bold">Checkout</span>
    </div>

    <div class="flex-grow-1 border-top mx-3"></div>
    <div class="d-flex align-items-center gap-2">
        <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center"
             style="width:32px;height:32px;">3</div>
        <span class="fw-bold">Confirmed</span>
    </div>

</div>

<div class="text-center mb-5">
    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
    style="width:60px;height:60px;">
    <i class="bi bi-check-lg fs-3"></i>
    </div>
    <h4 class="fw-bold">You're all booked!</h4>
    <p class="text-muted">Your order has been placed. Contact the seller to ararnge collection.</p>
</div>

<div class="alert alert-warning mb-4">
    <p class="fw-bold mb-1"><i class="bi bi-shield-exclamation"></i> Stay Safe</p>
    <p class="small mb-0">Always meet in a public place on campus to collect your textbook. Never transfer money before seeing the book in person. Booked will never ask you for payment outside the platform.</p>
</div>

<div class="row g-4">

    <!-- Order Details -->
    <div class="col-md-6">
        <div class="bg-light rounded-3 p-4">
            <p class="text-muted small fw-bold mb-3 text-uppercase">Order Details</p>
            <div class="d-flex justify-content-between border-bottom py-2">
                <span class="text-muted">Order Number</span>
                <span class="fw-bold">#<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></span>
            </div>
            <div class="d-flex justify-content-between border-bottom py-2">
                <span class="text-muted">Date Placed</span>
                <span class="fw-bold"><?= date('d M Y', strtotime($order['created_at'])) ?></span>
            </div>
            <div class="d-flex justify-content-between border-bottom py-2">
                <span class="text-muted">Book</span>
                <span class="fw-bold"><?= htmlspecialchars($order['title']) ?></span>
            </div>
            <div class="d-flex justify-content-between py-2">
                <span class="text-muted">Total Paid</span>
                <span class="fw-bold">R<?= number_format($order['total_price'], 2) ?></span>
            </div>
        </div>
    </div>

    <!-- Collection Details -->
    <div class="col-md-6">
        <div class="bg-light rounded-3 p-4">
            <p class="text-muted small fw-bold mb-3 text-uppercase">Collection Details</p>
            <div class="d-flex justify-content-between border-bottom py-2">
                <span class="text-muted">Campus</span>
                <span class="fw-bold"><?= htmlspecialchars($order['campus']) ?></span>
            </div>
            <div class="d-flex justify-content-between border-bottom py-2">
                <span class="text-muted">Preferred Time</span>
                <span class="fw-bold"><?= date('d M Y H:i', strtotime($order['preferred_time'])) ?></span>
            </div>
            <div class="d-flex justify-content-between border-bottom py-2">
                <span class="text-muted">Seller</span>
                <span class="fw-bold">@<?= htmlspecialchars($order['seller_username']) ?></span>
            </div>
            <div class="d-flex justify-content-between py-2">
                <span class="text-muted">Seller Email</span>
                <span class="fw-bold"><?= htmlspecialchars($order['seller_email']) ?></span>
            </div>
        </div>
    </div>

</div>

<div class="text-center mt-4">
    <a href="/browse.php" class="btn btn-dark">Continue Browsing</a>
</div>

<?php require_once '../includes/footer.php'; ?>