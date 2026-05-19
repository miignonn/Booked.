<?php
require_once '../../includes/admin-header.php';


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verify_csrf();

    $order_id = (int)$_POST['order_id'];
    $action   = $_POST['action'];

    if ($order_id > 0) {

        if ($action == 'set_status') {
            $new_status      = $_POST['new_status'] ?? '';
            $allowed_statuses = ['pending', 'handed_over', 'completed', 'cancelled'];

            if (in_array($new_status, $allowed_statuses, true)) {
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $new_status, $order_id);
                $stmt->execute();
                $action_success = "Order status updated to \"$new_status\".";
            } else {
                $action_error = "Invalid status value.";
            }

        } elseif ($action == 'delete') {
            $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $action_success = "Order deleted successfully.";
        }
    }

    $redirect = '/admin/orders.php';
    if (isset($action_success)) $redirect .= '?success=1';
    header('Location: ' . $redirect);
    exit();
}

$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

$where  = [];
$params = [];
$types  = '';

if ($filter_status) {
    $where[]  = "o.status = ?";
    $params[] = $filter_status;
    $types   .= 's';
}

$sql = "
    SELECT
        o.id, o.total_price, o.campus, o.preferred_time,
        o.seller_email, o.created_at, o.status, o.paystack_reference,
        l.title         AS listing_title,
        l.author        AS listing_author,
        l.price         AS listing_price,
        l.condition     AS listing_condition,
        buyer.username  AS buyer_name,
        buyer.email     AS buyer_email,
        buyer.phone     AS buyer_phone,
        seller.username AS seller_name,
        seller.email    AS seller_email_account,
        seller.phone    AS seller_phone
    FROM orders o
    JOIN listings l       ON o.listing_id = l.id
    JOIN users    buyer   ON o.buyer_id   = buyer.id
    JOIN users    seller  ON o.seller_id  = seller.id
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY o.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total  = count($orders);

//status counts for summary strip
$status_counts = [];
$count_result  = $conn->query("SELECT status, COUNT(*) AS total FROM orders GROUP BY status");
while ($row = $count_result->fetch_assoc()) {
    $status_counts[$row['status']] = (int)$row['total'];
}

?>

<main class="main-content">

<!---- Page Header ---->
<div class="admin-page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">Orders</h1>
            <p class="admin-page-sub"><?= number_format($total) ?> order<?= $total != 1 ? 's' : '' ?> found</p>
        </div>
    </div>
</div>

<!---- Success and Error Alerts ---->
<?php if (isset($_GET['success'])): ?>
    <div class="admin-alert admin-alert--success mb-4">
        <i class="bi bi-check-circle"></i> Action completed successfully.
    </div>
<?php endif; ?>

<?php if (isset($action_error)): ?>
    <div class="admin-alert admin-alert--danger mb-4">
        <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($action_error) ?>
    </div>
<?php endif; ?>

<!---- Status Summary Strip ---->
<div class="stat-grid stat-grid-3 mb-4">
    <?php
    $strip_stats = [
        ''            => ['label' => 'Total',       'icon' => 'bi-bag'],
        'pending'     => ['label' => 'Pending',      'icon' => 'bi-hourglass'],
        'handed_over' => ['label' => 'Handed Over',  'icon' => 'bi-box-arrow-right'],
        'completed'   => ['label' => 'Completed',    'icon' => 'bi-check-circle'],
        'cancelled'   => ['label' => 'Cancelled',    'icon' => 'bi-x-circle'],
    ];
    $total_all = array_sum($status_counts);
    foreach ($strip_stats as $key => $meta):
        $count     = ($key === '') ? $total_all : ($status_counts[$key] ?? 0);
        $is_active = $filter_status === $key;
        $link      = 'orders.php' . ($key ? '?status=' . $key : '');
    ?>
    <a href="<?= $link ?>" class="stat-card <?= $is_active ? 'stat-card--active' : '' ?>">
        <i class="bi <?= $meta['icon'] ?> stat-icon"></i>
        <p class="stat-label"><?= $meta['label'] ?></p>
        <p class="stat-value"><?= $count ?></p>
    </a>
    <?php endforeach; ?>
</div>

<!---- Filter Bar ---->
<form method="GET" class="admin-filter-bar mb-4">
    <select name="status" class="admin-select" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="pending"     <?= $filter_status == 'pending'     ? 'selected' : '' ?>>Pending</option>
        <option value="handed_over" <?= $filter_status == 'handed_over' ? 'selected' : '' ?>>Handed Over</option>
        <option value="completed"   <?= $filter_status == 'completed'   ? 'selected' : '' ?>>Completed</option>
        <option value="cancelled"   <?= $filter_status == 'cancelled'   ? 'selected' : '' ?>>Cancelled</option>
    </select>

    <?php if ($filter_status): ?>
        <a href="orders.php" class="admin-btn admin-btn--outline">Clear</a>
    <?php endif; ?>
</form>

<!---- Orders Table ---->
<div class="admin-table-card">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Listing</th>
                <th>Buyer</th>
                <th>Seller</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
        <?php if (empty($orders)): ?>
            <tr>
                <td colspan="8" class="admin-table-empty">No orders found.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($orders as $o): ?>
            <tr>
                <!---- Order ID ---->
                <td>
                    <p class="table-main-text">#<?= $o['id'] ?></p>
                    <p class="table-sub-text"><?= $o['paystack_reference'] ? htmlspecialchars($o['paystack_reference']) : 'No ref' ?></p>
                </td>

                <!---- Listing ---->
                <td>
                    <p class="table-main-text"><?= htmlspecialchars($o['listing_title']) ?></p>
                    <p class="table-sub-text">by <?= htmlspecialchars($o['listing_author'] ?? '—') ?></p>
                </td>

                <!---- Buyer ---->
                <td>
                    <p class="table-main-text"><?= htmlspecialchars($o['buyer_name']) ?></p>
                    <p class="table-sub-text"><?= htmlspecialchars($o['buyer_email']) ?></p>
                </td>

                <!---- Seller ---->
                <td>
                    <p class="table-main-text"><?= htmlspecialchars($o['seller_name']) ?></p>
                    <p class="table-sub-text"><?= htmlspecialchars($o['seller_email_account']) ?></p>
                </td>

                <!---- Amount ---->
                <td class="table-main-text">R<?= number_format((float)$o['total_price'], 2) ?></td>

                <!---- Status badge ---->
                <td>
                    <span class="admin-badge <?= match($o['status']) {
                        'pending'     => 'badge-warning',
                        'handed_over' => 'badge-dark',
                        'completed'   => 'badge-success',
                        'cancelled'   => 'badge-danger',
                        default       => 'badge-light'
                    } ?>">
                        <?= ucfirst(str_replace('_', ' ', $o['status'] ?? 'pending')) ?>
                    </span>
                </td>

                <!---- Date ---->
                <td class="table-sub-text"><?= date('d M Y', strtotime($o['created_at'])) ?></td>

                <!---- Actions ---->
                <td>
                    <div class="action-buttons">

                        <!---- View details button (triggers modal) ---->
                        <button
                            class="admin-btn admin-btn--sm"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#orderModal"
                            data-order-id="<?= $o['id'] ?>"
                            data-listing-title="<?= htmlspecialchars($o['listing_title'], ENT_QUOTES) ?>"
                            data-listing-author="<?= htmlspecialchars($o['listing_author'] ?? '—', ENT_QUOTES) ?>"
                            data-listing-condition="<?= htmlspecialchars($o['listing_condition'] ?? '—', ENT_QUOTES) ?>"
                            data-listing-price="<?= number_format((float)$o['listing_price'], 2) ?>"
                            data-buyer-name="<?= htmlspecialchars($o['buyer_name'], ENT_QUOTES) ?>"
                            data-buyer-email="<?= htmlspecialchars($o['buyer_email'], ENT_QUOTES) ?>"
                            data-buyer-phone="<?= htmlspecialchars($o['buyer_phone'] ?? '—', ENT_QUOTES) ?>"
                            data-seller-name="<?= htmlspecialchars($o['seller_name'], ENT_QUOTES) ?>"
                            data-seller-email="<?= htmlspecialchars($o['seller_email_account'], ENT_QUOTES) ?>"
                            data-seller-phone="<?= htmlspecialchars($o['seller_phone'] ?? '—', ENT_QUOTES) ?>"
                            data-campus="<?= htmlspecialchars($o['campus'] ?? '—', ENT_QUOTES) ?>"
                            data-preferred-time="<?= htmlspecialchars($o['preferred_time'] ?? '—', ENT_QUOTES) ?>"
                            data-paystack="<?= htmlspecialchars($o['paystack_reference'] ?? 'N/A', ENT_QUOTES) ?>"
                            data-total="<?= number_format((float)$o['total_price'], 2) ?>"
                            data-status="<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $o['status'])), ENT_QUOTES) ?>"
                            data-date="<?= date('d M Y', strtotime($o['created_at'])) ?>">
                            View
                        </button>

                        <!---- Status change forms ---->
                        <?php foreach (['pending', 'handed_over', 'completed', 'cancelled'] as $s): ?>
                        <?php if ($s !== $o['status']): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="order_id"    value="<?= (int)$o['id'] ?>">
                                <input type="hidden" name="action"      value="set_status">
                                <input type="hidden" name="new_status"  value="<?= $s ?>">
                                <button type="submit" class="admin-btn admin-btn--sm">
                                    <?= ucfirst(str_replace('_', ' ', $s)) ?>
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php endforeach; ?>

                        <!---- Delete ---->
                        <form method="POST" class="delete-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="order_id"   value="<?= (int)$o['id'] ?>">
                            <input type="hidden" name="action"     value="delete">
                            <button type="submit" class="admin-btn admin-btn--sm">Delete</button>
                        </form>

                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>

    </table>
</div>

</main>

<!---- Order Details Modal ---> 
<div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background: var(--off-white); border: 1px solid var(--border); border-radius: 12px;">

            <div class="modal-header" style="border-bottom: 1px solid var(--border);">
                <h5 class="modal-title fw-bold" id="orderModalLabel"
                    style="font-family: 'Playfair Display', Georgia, serif;">
                    Order <span id="modal-order-id"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="row g-4">

                    <!---- Listing details ---->
                    <div class="col-12">
                        <p class="stat-label mb-2">Listing</p>
                        <div class="admin-table-card p-3">
                            <p class="table-main-text mb-1" id="modal-listing-title"></p>
                            <p class="table-sub-text mb-1">Author: <span id="modal-listing-author"></span></p>
                            <p class="table-sub-text mb-1">Condition: <span id="modal-listing-condition"></span></p>
                            <p class="table-sub-text mb-0">Listed price: R<span id="modal-listing-price"></span></p>
                        </div>
                    </div>

                    <!---- Buyer and Seller side by side ---->
                    <div class="col-md-6">
                        <p class="stat-label mb-2">Buyer</p>
                        <div class="admin-table-card p-3">
                            <p class="table-main-text mb-1" id="modal-buyer-name"></p>
                            <p class="table-sub-text mb-1" id="modal-buyer-email"></p>
                            <p class="table-sub-text mb-0">Phone: <span id="modal-buyer-phone"></span></p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <p class="stat-label mb-2">Seller</p>
                        <div class="admin-table-card p-3">
                            <p class="table-main-text mb-1" id="modal-seller-name"></p>
                            <p class="table-sub-text mb-1" id="modal-seller-email"></p>
                            <p class="table-sub-text mb-0">Phone: <span id="modal-seller-phone"></span></p>
                        </div>
                    </div>

                    <!---- Handover details ---->
                    <div class="col-md-6">
                        <p class="stat-label mb-2">Handover Details</p>
                        <div class="admin-table-card p-3">
                            <p class="table-sub-text mb-1">Campus: <span id="modal-campus"></span></p>
                            <p class="table-sub-text mb-0">Preferred time: <span id="modal-preferred-time"></span></p>
                        </div>
                    </div>

                    <!---- Payment details ---->
                    <div class="col-md-6">
                        <p class="stat-label mb-2">Payment</p>
                        <div class="admin-table-card p-3">
                            <p class="table-sub-text mb-1">Total: <strong>R<span id="modal-total"></span></strong></p>
                            <p class="table-sub-text mb-1">Paystack ref: <span id="modal-paystack"></span></p>
                            <p class="table-sub-text mb-0">Status: <span id="modal-status"></span></p>
                        </div>
                    </div>

                </div>
            </div>

            <div class="modal-footer" style="border-top: 1px solid var(--border);">
                <p class="table-sub-text me-auto mb-0">Placed: <span id="modal-date"></span></p>
                <button type="button" class="admin-btn admin-btn--outline" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>

<script>
    //populate the order details modal from the clicked button's data attributes
    document.getElementById('orderModal').addEventListener('show.bs.modal', function(event) {
        const btn = event.relatedTarget;
        document.getElementById('modal-order-id').textContent         = '#' + btn.dataset.orderId;
        document.getElementById('modal-listing-title').textContent     = btn.dataset.listingTitle;
        document.getElementById('modal-listing-author').textContent    = btn.dataset.listingAuthor;
        document.getElementById('modal-listing-condition').textContent = btn.dataset.listingCondition;
        document.getElementById('modal-listing-price').textContent     = btn.dataset.listingPrice;
        document.getElementById('modal-buyer-name').textContent        = btn.dataset.buyerName;
        document.getElementById('modal-buyer-email').textContent       = btn.dataset.buyerEmail;
        document.getElementById('modal-buyer-phone').textContent       = btn.dataset.buyerPhone;
        document.getElementById('modal-seller-name').textContent       = btn.dataset.sellerName;
        document.getElementById('modal-seller-email').textContent      = btn.dataset.sellerEmail;
        document.getElementById('modal-seller-phone').textContent      = btn.dataset.sellerPhone;
        document.getElementById('modal-campus').textContent            = btn.dataset.campus;
        document.getElementById('modal-preferred-time').textContent    = btn.dataset.preferredTime;
        document.getElementById('modal-paystack').textContent          = btn.dataset.paystack;
        document.getElementById('modal-total').textContent             = btn.dataset.total;
        document.getElementById('modal-status').textContent            = btn.dataset.status;
        document.getElementById('modal-date').textContent              = btn.dataset.date;
    });

    //delete confirmation
    document.querySelectorAll('.delete-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!confirm('Delete this order? This cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
</script>

<?php require_once '../../includes/admin-footer.php'; ?>