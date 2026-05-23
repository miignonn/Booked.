<?php
require_once __DIR__. '/../includes/auth_check.php';
require_once __DIR__ .'/../config/db.php';
require_once __DIR__ .'/../includes/functions.php';

$user_id = $_SESSION['user_id'];
$filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// handle delete FIRST before any output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_id'])) {
    verify_csrf();
    $delete_id = (int)$_POST['delete_id'];
    $del = $conn->prepare("DELETE FROM listings WHERE id = ? AND user_id = ?");
    $del->bind_param("ii", $delete_id, $user_id);
    $del->execute();
    set_flash('warning', 'Listing deleted');
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

// count each status for tab badges
$counts = $conn->prepare("SELECT status, COUNT(*) as count FROM listings WHERE user_id = ? GROUP BY status");
$counts->bind_param("i", $user_id);
$counts->execute();
$count_result = $counts->get_result();
$status_counts = ['active' => 0, 'draft' => 0, 'sold' => 0];
while ($row = $count_result->fetch_assoc()) {
    $status_counts[$row['status']] = $row['count'];
}
$total = array_sum($status_counts);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page header -->
<div class="my-listings-header">
    <h4 class="fw-bold mb-0">My Listings</h4>
    <a href="/create-listing.php" class="btn btn-dark btn-sm">+ New Listing</a>
</div>
 
<!-- Status tabs -->
<div class="my-listings-tabs">
    <a href="/my-listings.php"
       class="btn btn-sm <?= $filter === 'all' ? 'btn-dark' : 'btn-outline-secondary' ?>">
        All (<?= $total ?>)
    </a>
    <a href="/my-listings.php?status=active"
       class="btn btn-sm <?= $filter === 'active' ? 'btn-dark' : 'btn-outline-secondary' ?>">
        Active (<?= $status_counts['active'] ?>)
    </a>
    <a href="/my-listings.php?status=sold"
       class="btn btn-sm <?= $filter === 'sold' ? 'btn-dark' : 'btn-outline-secondary' ?>">
        Sold (<?= $status_counts['sold'] ?>)
    </a>
    <a href="/my-listings.php?status=draft"
       class="btn btn-sm <?= $filter === 'draft' ? 'btn-dark' : 'btn-outline-secondary' ?>">
        Drafts (<?= $status_counts['draft'] ?>)
    </a>
</div>
 
<?php if (empty($listings)): ?>
    <p class="text-muted">No listings found. <a href="/create-listing.php">Create one!</a></p>
<?php else: ?>
    <?php foreach ($listings as $listing): ?>
        <div class="my-listing-card">
 
            <!-- Thumbnail -->
            <div class="my-listing-card__thumb">
                <?php if ($listing['image']): ?>
                    <img src="/<?= htmlspecialchars($listing['image']) ?>" alt="">
                <?php else: ?>
                    <i class="bi bi-book text-muted"></i>
                <?php endif; ?>
            </div>
 
            <!-- Info -->
            <div class="my-listing-card__info">
                <p class="my-listing-card__title"><?= htmlspecialchars($listing['title']) ?></p>
                <p class="my-listing-card__author"><?= htmlspecialchars($listing['author']) ?></p>
                <p class="my-listing-card__price">R<?= number_format($listing['price'], 2) ?></p>
            </div>
 
            <!-- Status badge -->
            <?php
            $badge = match($listing['status']) {
                'active' => 'success',
                'draft'  => 'secondary',
                'sold'   => 'info',
                default  => 'secondary'
            };
            ?>
            <span class="badge bg-<?= $badge ?> align-self-center">
                <?= ucfirst($listing['status']) ?>
            </span>
 
            <!-- Actions -->
            <div class="my-listing-card__actions">
                <?php if ($listing['status'] === 'sold'): ?>
                    <button class="btn btn-sm btn-outline-secondary" disabled>Edit</button>
                <?php else: ?>
                    <a href="/edit-listing.php?id=<?= $listing['id'] ?>"
                       class="btn btn-sm btn-outline-dark">Edit</a>
                <?php endif; ?>
 
                <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="confirmDelete(<?= $listing['id'] ?>)">Delete</button>
            </div>
 
        </div>
    <?php endforeach; ?>
<?php endif; ?>
 
<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3">
            <div class="modal-body text-center p-4">
                <i class="bi bi-trash fs-1 text-danger"></i>
                <h5 class="fw-bold mt-3">Delete this listing?</h5>
                <p class="text-muted">This action cannot be undone.</p>
                <form method="POST" id="delete-form">
                    <input type="hidden" name="delete_id" id="delete-id-input">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <button type="button" class="btn btn-outline-secondary"
                                data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Yes, Delete</button>
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