<?php 
require_once __DIR__ .'/../includes/auth_check.php';
require_once __DIR__ .'/../config/db.php';
require_once __DIR__ .'/../includes/functions.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_handover'])) {
    verify_csrf();
    $order_id = (int)$_POST['order_id'];
    $stmt = $conn->prepare("
        UPDATE orders SET status = 'handed_over'
        WHERE id = ? AND seller_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    header('Location: /orders.php?tab=selling');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_received'])) {
    verify_csrf();
    $order_id = (int)$_POST['order_id'];
    $stmt = $conn->prepare("
        UPDATE orders SET status = 'completed'
        WHERE id = ? AND buyer_id = ? AND status = 'handed_over'
    ");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $sold = $conn->prepare("
        UPDATE listings SET status = 'sold'
        WHERE id = (SELECT listing_id FROM orders WHERE id = ?)
    ");
    $sold->bind_param("i", $order_id);
    $sold->execute();
    header('Location: /orders.php?tab=buying');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    verify_csrf();
    $order_id = (int)$_POST['order_id'];

    // only buyer can cancel, only before handover
    $stmt = $conn->prepare("
        UPDATE orders SET status = 'cancelled'
        WHERE id = ? AND buyer_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();

    // restore listing to available so seller can get another buyer
    if ($stmt->affected_rows > 0) {
        $restore = $conn->prepare("
            UPDATE listings SET status = 'available'
            WHERE id = (SELECT listing_id FROM orders WHERE id = ?)
        ");
        $restore->bind_param("i", $order_id);
        $restore->execute();
    }

    header('Location: /orders.php?tab=buying');
    exit();
}

$buying_stmt = $conn->prepare("
SELECT orders.*, listings.title, listings.image,
users.username AS seller_username, users.email AS seller_email,
users.institution AS seller_institution
FROM orders
JOIN listings ON orders.listing_id = listings.id
JOIN users ON orders.seller_id = users.id
WHERE orders.buyer_id = ?
ORDER BY orders.created_at DESC
");
$buying_stmt->bind_param("i", $user_id);
$buying_stmt->execute();
$buying_orders = $buying_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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

function status_badge(string $status): string {
    return match($status) {
        'completed'   => 'success',
        'handed_over' => 'info',
        'pending'     => 'warning text-dark',
        'cancelled'   => 'danger',
        default       => 'secondary',
    };
}
?>

<h4 class="orders__title">My Orders</h4>
<p class="orders__sub">Track your buying and selling activity</p>

<div class="orders__tabs">
    <button class="order-tab <?= $active_tab === 'buying' ? 'active' : '' ?>"
            onclick="switchTab('buying', this)">
        Bought <span class="order-tab-count"><?= count($buying_orders) ?></span>
    </button>
    <button class="order-tab <?= $active_tab === 'selling' ? 'active' : '' ?>"
            onclick="switchTab('selling', this)">
        Sold <span class="order-tab-count"><?= count($selling_orders) ?></span>
    </button>
</div>

<!-- Buying Panel -->
<div id="buying" class="order-panel" <?= $active_tab === 'selling' ? 'style="display:none;"' : '' ?>>
    <?php if (empty($buying_orders)): ?>
        <div class="orders__empty">
            <i class="bi bi-bag orders__empty-icon"></i>
            <p class="orders__empty-title">No purchases yet</p>
            <p class="orders__empty-sub">Books you buy will appear here.</p>
            <a href="/browse.php" class="b-btn b-btn--primary">Start Browsing</a>
            
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
                    <div class="order-card__meta-grid--orders">
                      <span class="order-card__meta"><i class="bi bi-person"></i> @<?= htmlspecialchars($order['seller_username']) ?></span>
                      <span class="order-card__meta"><i class="bi bi-building"></i> <?= htmlspecialchars($order['seller_institution']) ?></span>
                      <span class="order-card__meta"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($order['campus']) ?></span>
                      <span class="order-card__meta"><i class="bi bi-clock"></i> <?= date('d M, H:i', strtotime($order['preferred_time'])) ?></span>
                      <span class="order-card__meta"><i class="bi bi-envelope"></i> <?= htmlspecialchars($order['seller_email']) ?></span>
                      <span class="order-card__meta"><i class="bi bi-calendar"></i> <?= date('d M Y', strtotime($order['created_at'])) ?></span>
                    </div>
                </div>

            <div class="order-card__price-col">
                  <p class="order-card__price">R<?= number_format($order['total_price'], 2) ?></p>
                   <span class="order-status order-status--<?= $order['status'] ?>">
                    <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                   </span>
                  <?php if ($order['status'] === 'handed_over'): ?>
                  <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <button type="submit" name="mark_received" class="b-btn b-btn--primary">
                    Mark as Received
                    </button>
                  </form>
                 <?php endif; ?>
                 <?php if ($order['status'] === 'pending'): ?>
                    <button type="button" class="b-btn b-btn--outline"
                     onclick="confirmCancel(<?= $order['id'] ?>)">
                     Cancel Order
                    </button>
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
            <a href="/create-listing.php" class="b-btn b-btn--primary">Create a Listing</a>
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
                    <div class="order-card__meta-grid--orders">
                      <span class="order-card__meta"><i class="bi bi-person"></i> @<?= htmlspecialchars($order['buyer_username']) ?></span>
                      <span class="order-card__meta"><i class="bi bi-envelope"></i> <?= htmlspecialchars($order['buyer_email']) ?></span>
                      <span class="order-card__meta"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($order['campus']) ?></span>
                      <span class="order-card__meta"><i class="bi bi-clock"></i> <?= date('d M, H:i', strtotime($order['preferred_time'])) ?></span>
                      <span class="order-card__meta"><i class="bi bi-calendar"></i> <?= date('d M Y', strtotime($order['created_at'])) ?></span>
                </div>
                </div>

                <div class="order-card__price-col">
                    <p class="order-card__price">R<?= number_format($order['total_price'], 2) ?></p>
                    <span class="order-status order-status--<?= ($order['status']) ?> mb-2">
                        <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                    </span>
                    <?php if ($order['status'] === 'pending'): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <button type="submit" name="confirm_handover" class="b-btn b-btn--primary">
                                Confirm Handover
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Cancel confirmation modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3">
            <div class="cart-modal__body">
                <i class="bi bi-x-circle cart-modal__icon cart-modal__icon--cancel"></i>
                <h5 class="cart-modal__title">Cancel this order?</h5>
                <p class="cart-modal__sub">The listing will be made available again for other buyers.</p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="order_id" id="cancel-order-id" value="">
                    <div class="cart-modal__actions">
                        <button type="button" class="btn-browse" data-bs-dismiss="modal">Keep Order</button>
                        <button type="submit" name="cancel_order" class="btn-danger-action">Yes, Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmCancel(orderId) {
    document.getElementById('cancel-order-id').value = orderId;
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}

function switchTab(id, el) {
    document.querySelectorAll('.order-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.order-tab').forEach(t => t.classList.remove('active'));
    document.getElementById(id).style.display = 'block';
    el.classList.add('active');
}
</script>

<?php require_once '../includes/footer.php'; ?>