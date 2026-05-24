<?php 
require_once __DIR__ .'/../includes/auth_check.php';
require_once __DIR__ .'/../config/db.php';
require_once __DIR__ .'/../includes/functions.php';

$user_id = $_SESSION['user_id'];

//seller confirms they handed the book over
if ($_SERVER['REQUEST_METHOD'] ===  'POST' && isset($_POST['confirm_handover'])){
    verify_csrf();
    $oder_id = (int)$_POST['order_id'];

    $stmt = $conn->prepare("
    UPDATE orders SET status = 'handed_over'
    WHERE id = ? AND seller_id = ? AND status = 'pending'
    ");

    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    
    header('Location: /orders.php?tab=selling');
    exit();
}

//buyer confirms they received the book
if ($_SERVER['REQUEST_METHOD'] ===' POST' && isset($_POST['mark_received'])){
    verify_csrf();
    $order_id = (int)$_POST['order_id'];

    //update order to completed
    $stmt = $conn->prepare("
    UPDATE orders SET status = 'completed'
    WHERE id = ? AND buyer_id = ? AND status = 'handed_over'
    ");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();

    $sold = $conn->prepare("
    UPDATE lsitings SET status = 'sold'
    WHERE id = (SELECT listing_id FROM orders WHERE id = ?)
    ");
    $sold->bind_param("i", $order_id);
    header('Location: /orders.php?tab=buying');
    exit();
} 

//fetch orders as buyer
$buying_stmt = $conn->prepare("
SELECT orders.*, listings.title, listings.image, 
users.username AS seller_username, users.email AS seller_email
FROM orders
JOIN listings ON orders.listing_id = listings.id
JOIN users ON orders.seller_id = users.id
WHERE orders.buyer_id = ?
ORDER BY orders.created_at DESC
");

$buying_stmt->bind_param("i", $user_id);
$buying_stmt->execute();
$buying_orders = $buying_stmt->get_result()->fetch_all(MYSQLI_ASSOC);


//fetch orders as the seller
$selling_stmt = $conn->prepare("
SELECT orders.*, listings.title, listings.image,
users.username AS buyer_username, users.email AS buyer_email
FROM orders
JOIN listings ON orders.listing_id = listings.id
JOIN users ON orders.buyer_id = users.id
WHERE orders.seller_id = ?
ORDER BY orders.created_at DESC
");

$selling_stmt->bind_param("i", $user_id);
$selling_stmt->execute();
$selling_orders = $selling_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$active_tab = isset($_GET['tab']) && $_GET['tab'] === 'selling' ? 'selling' : 'buying';

require_once __DIR__ . '/../includes/header.php';

function status_badge(string $status): string{
 return match($status){
    'completed' => 'success',
    'handed_over'     => 'info',
    'pending'         => 'warning text-dark',
    'pending_payment' => 'secondary',
    'cancelled'       => 'danger',
    default           => 'secondary',
 };
}


?>
<h4 class="orders__title">My Orders</h4>
<p class="orders__sub">Track your buying and selling activity</p>

<div class="orders__tabs">
    <button class="order-tab <?= $active_tab === 'buying' ? 'active' : '' ?>"
    onclick="switchTab('buying', this)">
    Bought <pan class="order-tab-count"><?= count($buying_orders) ?></span>
</button>
<button class="order-tab <?= $active_tab === 'selling' ? 'active' : '' ?>"
    onclick="switchTab('selling', this)">
    Sold <span class="order-tab-count"><?= count($selling_orders) ?></span>
</button>
</div>


<!--- Buying Panel -----> 
<div id="buying" class="order-panel" <?= $active_tab === 'selling' ? 'style="display:none;"' : '' ?>>
    <?php if (empty($buying_orders)): ?>
        <div class="orders__empty">
            <i class="bi bi-bag orders__empty-icon"></i>
            <p class="orders__empty-title">No purchases yet</p>
            <p class="orders__empty-sub">Books you buy will appear here.</p>
            <a href="/browse.php" class="btn-checkout">Start Browsing</a>
        </div>
    <?php else: ?>
        <?php foreach ($buying_orders as $order): ?>
            <div class="order-card">
 
                <div class="order-card__thumb">
                    <?php if ($order['image']): ?>
                        <img src="/<?= htmlspecialchars($order['image']) ?>" alt="">
                    <?php else: ?>
                        <i class="bi bi-book order-card__no-image"></i>
                    <?php endif; ?>
                </div>
 
                <div class="order-card__info">
                    <p class="order-card__title"><?= htmlspecialchars($order['title']) ?></p>
                    <p class="order-card__meta">Seller: @<?= htmlspecialchars($order['seller_username']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($order['campus']) ?></p>
                    <p class="order-card__meta"><?= date('d M Y', strtotime($order['created_at'])) ?> &nbsp;·&nbsp; Collect: <?= date('d M, H:i', strtotime($order['preferred_time'])) ?></p>
                    <p class="order-card__meta"><i class="bi bi-envelope"></i> <?= htmlspecialchars($order['seller_email']) ?></p>
                </div>
 
                <div class="order-card__price-col">
                    <p class="order-card__price">R<?= number_format($order['total_price'], 2) ?></p>
                    <span class="badge bg-<?= status_badge($order['status']) ?> mb-2">
                        <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                    </span>
                    <?php if ($order['status'] === 'handed_over'): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <button type="submit" name="mark_received" class="btn-order-action">
                                Mark as Received
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
 
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
 
<!-- Selling Panel -->
<div id="selling" class="order-panel" <?= $active_tab === 'buying' ? 'style="display:none;"' : '' ?>>
    <?php if (empty($selling_orders)): ?>
        <div class="orders__empty">
            <i class="bi bi-shop orders__empty-icon"></i>
            <p class="orders__empty-title">No sales yet</p>
            <p class="orders__empty-sub">Books you sell will appear here.</p>
            <a href="/create-listing.php" class="btn-checkout">Create a Listing</a>
        </div>
    <?php else: ?>
        <?php foreach ($selling_orders as $order): ?>
            <div class="order-card">
 
                <div class="order-card__thumb">
                    <?php if ($order['image']): ?>
                        <img src="/<?= htmlspecialchars($order['image']) ?>" alt="">
                    <?php else: ?>
                        <i class="bi bi-book order-card__no-image"></i>
                    <?php endif; ?>
                </div>
 
                <div class="order-card__info">
                    <p class="order-card__title"><?= htmlspecialchars($order['title']) ?></p>
                    <p class="order-card__meta">Buyer: @<?= htmlspecialchars($order['buyer_username']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($order['campus']) ?></p>
                    <p class="order-card__meta"><?= date('d M Y', strtotime($order['created_at'])) ?> &nbsp;·&nbsp; Collect: <?= date('d M, H:i', strtotime($order['preferred_time'])) ?></p>
                    <p class="order-card__meta"><i class="bi bi-envelope"></i> <?= htmlspecialchars($order['buyer_email']) ?></p>
                </div>
 
                <div class="order-card__price-col">
                    <p class="order-card__price">R<?= number_format($order['total_price'], 2) ?></p>
                    <span class="badge bg-<?= status_badge($order['status']) ?> mb-2">
                        <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                    </span>
                    <?php if ($order['status'] === 'pending'): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <button type="submit" name="confirm_handover" class="btn-order-action">
                                Confirm Handover
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
 
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function switchTab(id, el) {
    document.querySelectorAll('.order-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.order-tab').forEach(t => t.classList.remove('active'));
    document.getElementById(id).style.display = 'block';
    el.classList.add('active');
}
</script>

<?php require_once '../includes/footer.php'; ?>