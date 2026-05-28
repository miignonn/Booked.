<?php
require_once __DIR__. '/../includes/auth_check.php';
require_once __DIR__ .'/../config/db.php';
require_once __DIR__ .'/../includes/functions.php';

$user_id = $_SESSION['user_id'];
$filter = isset($_GET['status']) ? $_GET['status'] : 'all';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_id'])){
    verify_csrf();
    $delete_id = (int)$_POST['delete_id'];

    $check = $conn->prepare("SELECT status FROM listings WHERE id = ? AND user_id = ?");
    $check->bind_param("ii", $delete_id, $user_id);
    $check->execute();
    $check_listing = $check->get_result()->fetch_assoc();

    if (!$check_listing){
        set_flash('danger', 'Listing not found');
    } elseif ($check_listing['status'] === 'sold'){
        set_flash('danger', 'Sold listings cannot be deleted.');
    } else {
        $del = $conn->prepare("DELETE FROM listings WHERE id = ? AND user_id = ?");
        $del->bind_param("ii", $delete_id, $user_id);
        $del->execute();
        set_flash('warning', 'Listing deleted successfully.');
    }
    header('Location: /my-listings.php');
    exit();
}

if ($filter == 'all') {
    $stmt = $conn->prepare("SELECT * FROM listings WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM listings WHERE user_id = ? AND status = ? ORDER BY created_at DESC");
    $stmt->bind_param("is", $user_id, $filter);
}

$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$counts = $conn->prepare("SELECT status, COUNT(*) as count FROM listings WHERE user_id = ? GROUP BY status");
$counts->bind_param("i", $user_id);
$counts->execute();
$count_result = $counts->get_result();
$status_counts = ['available' => 0, 'draft' => 0, 'sold' => 0, 'pending' => 0];
while ($row = $count_result->fetch_assoc()) {
    $status_counts[$row['status']] = $row['count'];
}
$total = array_sum($status_counts);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="orders__title-row">
    <h1 class="orders__title" style="padding:0;">My Listings</h1>
    <a href="/create-listing.php" class="b-btn b-btn--primary">+ New Listing</a>
</div>

<div class="orders__tabs" style="margin-top: 1.5rem;">
    <a href="/my-listings.php" class="order-tab <?= $filter === 'all' ? 'active' : '' ?>">
        All <span class="order-tab-count"><?= $total ?></span>
    </a>
    <a href="/my-listings.php?status=available" class="order-tab <?= $filter === 'available' ? 'active' : '' ?>">
        Active <span class="order-tab-count"><?= $status_counts['available'] ?></span>
    </a>
    <a href="/my-listings.php?status=sold" class="order-tab <?= $filter === 'sold' ? 'active' : '' ?>">
        Sold <span class="order-tab-count"><?= $status_counts['sold'] ?></span>
    </a>
    <a href="/my-listings.php?status=draft" class="order-tab <?= $filter === 'draft' ? 'active' : '' ?>">
        Drafts <span class="order-tab-count"><?= $status_counts['draft'] ?></span>
    </a>
</div>

<div class="order-panel">
    <?php if (empty($listings)): ?>
        <div class="orders__empty">
            <i class="bi bi-journal-x orders__empty-icon"></i>
            <p class="orders__empty-title">No listings found</p>
            <p class="orders__empty-sub">Listings you create will appear here.</p>
            <a href="/create-listing.php" class="b-btn b-btn--primary">Create a Listing</a>
        </div>
    <?php else: ?>
        <?php foreach ($listings as $listing): ?>
            <?php $is_suspended = isset($_SESSION['status']) && $_SESSION['status'] === 'suspended'; ?>
            <div class="order-card">

                <div class="order-card__thumb">
                    <?php if ($listing['image']): ?>
                        <img src="/<?= htmlspecialchars($listing['image']) ?>" alt="">
                    <?php else: ?>
                        <i class="bi bi-book order-card__no-image"></i>
                    <?php endif; ?>
                </div>

                <div class="order-card__info">
                    <p class="order-card__title"><?= htmlspecialchars($listing['title']) ?></p>
                    <div class="order-card__meta-grid">
                        <span class="order-card__meta"><i class="bi bi-person"></i> <?= htmlspecialchars($listing['author']) ?></span>
                        <span class="order-card__meta"><i class="bi bi-calendar"></i> <?= date('d M Y', strtotime($listing['created_at'])) ?></span>
                        <?php if ($is_suspended && $listing['status'] === 'available'): ?>
                            <span class="order-card__meta" style="color: var(--blush)">
                                <i class="bi bi-eye-slash"></i> Hidden (suspended)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="order-card__price-col">
                    <div class="order-card__price-left">
                        <p class="order-card__price">R<?= number_format($listing['price'], 2) ?></p>
                        <span class="order-status order-status--<?= $listing['status'] ?>">
                            <?= ucfirst($listing['status']) ?>
                        </span>
                    </div>
                    <div class="order-card__price-actions">
                        <?php if ($listing['status'] === 'sold' || $listing['status'] === 'pending'): ?>
                            <button class="b-btn b-btn--outline" disabled style="opacity:0.4; cursor:not-allowed;">Edit</button>
                            <button class="b-btn b-btn--outline" disabled style="opacity:0.4; cursor:not-allowed;">Delete</button>
                        <?php else: ?>
                            <a href="/edit-listing.php?id=<?= $listing['id'] ?>" class="b-btn b-btn--outline">Edit</a>
                            <button type="button" class="b-btn b-btn--outline listing-delete-btn"
                                    onclick="confirmDelete(<?= $listing['id'] ?>)">Delete</button>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Delete modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:0; border: 0.5px solid var(--border);">
            <div class="cart-modal__body">
                <i class="bi bi-trash cart-modal__icon"></i>
                <h5 class="cart-modal__title">Delete this listing?</h5>
                <p class="cart-modal__sub">This action cannot be undone.</p>
                <form method="POST" id="delete-form">
                    <input type="hidden" name="delete_id" id="delete-id-input">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <div class="cart-modal__actions">
                        <button type="button" class="b-btn b-btn--outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="b-btn b-btn--primary" style="background: var(--blush);">Yes, Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    document.getElementById('delete-id-input').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>