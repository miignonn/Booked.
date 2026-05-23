<?php 
require_once __DIR__ .'/../includes/auth_check.php';
require_once __DIR__ .'/../config/db.php';
require_once __DIR__ .'/../includes/functions.php';

$user_id = $_SESSION['user_id'];

//orders as the buyer
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


//orders as the seller
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

require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="fw-bold mb-1">My Orders</h4>
<p class="text-muted small mb-0">Track your buying and selling activity</p>

<!-- Tabs -->
<div class="d-flex border-bottom mb-4 mt-3">
    <button class="order-tab active me-4" onclick="switchTab('buying', this)">
        Bought <span class="tab-count"><?= count($buying_orders) ?></span>
    </button>
    <button class="order-tab" onclick="switchTab('selling', this)">
        Sold <span class="tab-count"><?= count($selling_orders) ?></span>
    </button>
</div>

<!-- Buying Panel--> 
<div id="buying" class="order-panel">
    <?php if (empty($buying_orders)): ?>
        <div class="text-center py-5">
            <i class="bi bi-bag fs-1 text-muted d-block mb-3"></i>
            <p class="fw-bold mb-1">No purchases yet</p>
            <p class="text-muted small">Books you buy will appear here.</p>
            <a href="/browse.php" class="btn btn-dark btn-sm">Start Browsing</a>
        </div>

        <?php else: ?>
        <?php foreach ($buying_orders as $order): ?>
            <div class="d-flex align-items-center gap-3 border rounded-3 p-3 mb-3">
                <div style="width:48px;height:64px;flex-shrink:0;">
                    <?php if ($order['image']): ?>
                        <img src="/<?= htmlspecialchars($order['image']) ?>"
                             class="rounded-2 w-100 h-100" style="object-fit:cover;">
                    <?php else: ?>
                        <div class="bg-light rounded-2 w-100 h-100 d-flex align-items-center justify-content-center">
                            <i class="bi bi-book text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1">
                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($order['title']) ?></h6>
                    <p class="text-muted small mb-1">Seller: @<?= htmlspecialchars($order['seller_username']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($order['campus']) ?></p>
                    <p class="text-muted small mb-1"><?= date('d M Y', strtotime($order['created_at'])) ?> &nbsp;·&nbsp; Collect: <?= date('d M, H:i', strtotime($order['preferred_time'])) ?></p>
                    <p class="text-muted small mb-0"><i class="bi bi-envelope"></i> <?= htmlspecialchars($order['seller_email']) ?></p>
                </div>
                <div class="text-end">
                    <p class="fw-bold mb-1">R<?= number_format($order['total_price'], 2) ?></p>
                    <span class="badge bg-<?= $order['status'] === 'completed' ? 'success' : ($order['status'] == 'handed_over' ? 'info' : 'warning text-dark') ?> mb-2">
                        <?= ucfirst(str_replace('_',' ', $order['status'])) ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Selling Panel -->
<div id="selling" class="order-panel" style="display:none;">
    <?php if (empty($selling_orders)): ?>
        <div class="text-center py-5">
            <i class="bi bi-shop fs-1 text-muted d-block mb-3"></i>
            <p class="fw-bold mb-1">No sales yet</p>
            <p class="text-muted small">Books you sell will appear here.</p>
            <a href="/create-listing.php" class="btn btn-dark btn-sm">Create a Listing</a>
        </div>
    <?php else: ?>
        <?php foreach ($selling_orders as $order): ?>
            <div class="d-flex align-items-center gap-3 border rounded-3 p-3 mb-3">
                <div style="width:48px;height:64px;flex-shrink:0;">
                    <?php if ($order['image']): ?>
                        <img src="/<?= htmlspecialchars($order['image']) ?>"
                             class="rounded-2 w-100 h-100" style="object-fit:cover;">
                    <?php else: ?>
                        <div class="bg-light rounded-2 w-100 h-100 d-flex align-items-center justify-content-center">
                            <i class="bi bi-book text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1">
                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($order['title']) ?></h6>
                    <p class="text-muted small mb-1">Buyer: @<?= htmlspecialchars($order['buyer_username']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($order['campus']) ?></p>
                    <p class="text-muted small mb-1"><?= date('d M Y', strtotime($order['created_at'])) ?> &nbsp;·&nbsp; Collect: <?= date('d M, H:i', strtotime($order['preferred_time'])) ?></p>
                    <p class="text-muted small mb-0"><i class="bi bi-envelope"></i> <?= htmlspecialchars($order['buyer_email']) ?></p>
                </div>

                <!-- Confirm handover-->
                <div class="text-end">
                    <p class="fw-bold mb-1">R<?= number_format($order['total_price'], 2) ?></p>
                    <span class="badge bg-<?= $order['status'] == 'completed' ? 'success' : ($order['status'] == 'handed_over' ? 'info' : 'warning text-dark') ?> mb-2">
                        <?= ucfirst(str_replace('_',' ', $order['status'])) ?>
                    </span>
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