<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$user_id = $_SESSION['user_id'];
$filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// handle delete FIRST before any output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    $del = $conn->prepare("DELETE FROM listings WHERE id = ? AND user_id = ?");
    $del->bind_param("ii", $delete_id, $user_id);
    $del->execute();
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
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">My Listings</h4>
    <a href="/create-listing.php" class="btn btn-dark btn-sm">+ New Listing</a>
</div>

<div class="d-flex gap-2 mb-4 flex-wrap">
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
        <div class="d-flex align-items-center gap-3 border rounded-3 p-3 mb-3">

            <!-- Image -->
            <div style="width:80px;height:80px;flex-shrink:0;">
                <?php if ($listing['image']): ?>
                    <img src="/<?= htmlspecialchars($listing['image']) ?>" 
                         class="rounded-2 w-100 h-100" style="object-fit:cover;">
                <?php else: ?>
                    <div class="bg-light rounded-2 w-100 h-100 d-flex align-items-center justify-content-center">
                        <i class="bi bi-book text-muted"></i>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Info -->
            <div class="flex-grow-1">
                <h6 class="fw-bold mb-1"><?= htmlspecialchars($listing['title']) ?></h6>
                <p class="text-muted small mb-1"><?= htmlspecialchars($listing['author']) ?></p>
                <p class="fw-bold mb-0">R<?= number_format($listing['price'], 2) ?></p>
            </div>

           
            <div class="text-center" style="min-width:70px;">
                <?php
                $badge = match($listing['status']) {
                    'active' => 'success',
                    'draft'  => 'secondary',
                    'sold'   => 'info',
                    default  => 'secondary'
                };
                ?>
                <span class="badge bg-<?= $badge ?>"><?= ucfirst($listing['status']) ?></span>
            </div>

            <!-- Actions -->
            <div class="d-flex gap-2">
                <a href="/edit-listing.php?id=<?= $listing['id'] ?>" 
                   class="btn btn-sm btn-outline-dark">Edit</a>
                <form method="POST" onsubmit="return confirm('Delete this listing?')">
                    <input type="hidden" name="delete_id" value="<?= $listing['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
            </div>

        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>