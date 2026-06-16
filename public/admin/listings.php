<?php
require_once __DIR__ . '/../../includes/require_admin.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verify_csrf();

    $listing_id = (int)$_POST['listing_id'];
    $action     = $_POST['action'];

    if ($action == 'delete') {
        // Permanently remove the listing
        $stmt = $conn->prepare("DELETE FROM listings WHERE id = ?");
        $stmt->bind_param("i", $listing_id);
        $stmt->execute();
        $action_success = "Listing deleted successfully.";

    } elseif ($action == 'set_status') {
        // Whitelist allowed statuses before updating
        $new_status      = $_POST['new_status'] ?? '';
        $allowed_statuses = ['available', 'pending', 'draft', 'sold', 'flagged'];

        if (in_array($new_status, $allowed_statuses, true)) {
            $stmt = $conn->prepare("UPDATE listings SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $listing_id);
            $stmt->execute();
            $action_success = "Listing status updated to \"$new_status\".";
        } else {
            $action_error = "Invalid status value.";
        }
    }

    // redirect after POST to prevent resubmission on refresh
    $redirect = '/admin/listings.php';
    if (isset($action_success)) $redirect .= '?success=1';
    header('Location: ' . $redirect);
    exit();
}

//Search and Filter

$search        = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';


$where  = [];
$params = [];
$types  = '';

if ($search) {
    // Search by title 
    $where[]  = "(l.title LIKE ?)";
    $params[] = "%$search%";
    $types   .= 's';
}

if ($filter_status) {
    $where[]  = "l.status = ?";
    $params[] = $filter_status;
    $types   .= 's';
}

$sql = "
    SELECT
        l.id, l.title, l.price, l.status,
        l.condition, l.author, l.created_at,
        u.username AS seller_name,
        u.email    AS seller_email,
        c.name     AS category_name
    FROM listings l
    LEFT JOIN users      u ON l.user_id     = u.id
    LEFT JOIN categories c ON l.category_id = c.id
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY l.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total    = count($listings);

//status counts for the summary strip
$status_counts = [];
$count_result  = $conn->query("SELECT status, COUNT(*) AS total FROM listings GROUP BY status");
while ($row = $count_result->fetch_assoc()) {
    $status_counts[$row['status']] = (int)$row['total'];
}

require_once __DIR__ . '/../../includes/admin-header.php';


?>

<main class="main-content">

<!---- Page Header ---->
<div class="admin-page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">Listings</h1>
            <p class="admin-page-sub"><?= number_format($total) ?> listing<?= $total != 1 ? 's' : '' ?> found</p>
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
        ''          => ['label' => 'Total Listings', 'icon' => 'bi-collection'],
        'available' => ['label' => 'Available',      'icon' => 'bi-check-circle'],
        'pending'   => ['label' => 'Pending',         'icon' => 'bi-clock-history'],
        'sold'      => ['label' => 'Sold',             'icon' => 'bi-bag-check'],
        'draft'     => ['label' => 'Draft',            'icon' => 'bi-pencil'],
        'flagged'   => ['label' => 'Flagged',          'icon' => 'bi-flag'],
    ];
    $total_all = array_sum($status_counts);
    foreach ($strip_stats as $key => $meta):
        $count     = ($key === '') ? $total_all : ($status_counts[$key] ?? 0);
        $is_active = $filter_status === $key;
        $link      = '/admin/listings.php' . ($key ? '?status=' . $key : '');
    ?>
    <a href="<?= $link ?>" class="stat-card <?= $is_active ? 'stat-card--active' : '' ?>">
        <i class="bi <?= $meta['icon'] ?> stat-icon"></i>
        <p class="stat-label"><?= $meta['label'] ?></p>
        <p class="stat-value"><?= $count ?></p>
    </a>
    <?php endforeach; ?>
</div>
  

<!---- Search and Filter Bar ---->
<form method="GET" class="admin-filter-bar mb-4">
    <div class="admin-search-wrap">
        <i class="bi bi-search admin-search-icon"></i>
        <input type="text" name="search" class="admin-search-input"
               placeholder="Search by title"
               value="<?= htmlspecialchars($search) ?>">
    </div>

    <!---- Filter by Status ---->
    <select name="status" class="admin-select" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="available" <?= $filter_status == 'available' ? 'selected' : '' ?>>Available</option>
        <option value="pending"   <?= $filter_status == 'pending'   ? 'selected' : '' ?>>Pending</option>
        <option value="draft"     <?= $filter_status == 'draft'     ? 'selected' : '' ?>>Draft</option>
        <option value="sold"      <?= $filter_status == 'sold'      ? 'selected' : '' ?>>Sold</option>
        <option value="flagged"   <?= $filter_status == 'flagged'   ? 'selected' : '' ?>>Flagged</option>
    </select>

    <button type="submit" class="admin-btn admin-btn--dark">Search</button>

    <!---- Clear filters link (only shows if filters are active) ---->
    <?php if ($search || $filter_status): ?>
        <a href="/admin/listings.php" class="admin-btn admin-btn--outline">Clear</a>
    <?php endif; ?>
</form>

<!---- Listings Table ---->
<div class="admin-table-card">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Listing</th>
                <th>Seller</th>
                <th>Category</th>
                <th>Condition</th>
                <th>Price</th>
                <th>Status</th>
                <th>Listed</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
        <?php if (empty($listings)): ?>
            <tr>
                <td colspan="8" class="admin-table-empty">No listings found.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($listings as $l): ?>
            <tr>
                <!---- Title + module code ---->
                <td>
                    <p class="table-main-text"><?= htmlspecialchars($l['title']) ?></p>
                    <p class="table-sub-text"><?= htmlspecialchars($l['author'] ?? '-') ?></p>
                </td>

                <!---- Seller ---->
                <td>
                    <p class="table-main-text"><?= htmlspecialchars($l['seller_name'] ?? '—') ?></p>
                    <p class="table-sub-text"><?= htmlspecialchars($l['seller_email'] ?? '') ?></p>
                </td>

                <!---- Category ---->
                <td class="table-sub-text"><?= htmlspecialchars($l['category_name'] ?? '—') ?></td>

                <!---- Condition badge ---->
                <td>
                    <span class="admin-badge <?= match($l['condition_rating'] ?? '') {
                        'new'   => 'badge-success',
                        'good'  => 'badge-dark',
                        'fair'  => 'badge-warning',
                        'poor'  => 'badge-danger',
                        default => 'badge-light'
                    } ?>">
                        <?= htmlspecialchars($l['condition'] ?: '—') ?>
                    </span>
                </td>

                <!---- Price ---->
                <td class="table-main-text">R<?= number_format((float)$l['price'], 2) ?></td>

                <!---- Status badge ---->
                <td>
                    <span class="admin-badge <?= match($l['status']) {
                        'available' => 'badge-success',
                        'pending'   => 'badge-warning',
                        'sold'      => 'badge-dark',
                        'flagged'   => 'badge-danger',
                        'draft'     => 'badge-light',
                        default     => 'badge-light'
                    } ?>"><?= ucfirst($l['status']) ?></span>
                </td>

                <!---- Date listed ---->
                <td class="table-sub-text"><?= date('d M Y', strtotime($l['created_at'])) ?></td>

                <!---- Action buttons ---->
                <td>
                    <div class="action-buttons">

                        <!---- Status change forms (only show options different from current) ---->
                        <?php foreach (['available', 'pending', 'draft', 'sold', 'flagged'] as $s): ?>
                        <?php if ($s !== $l['status']): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="listing_id"  value="<?= (int)$l['id'] ?>">
                                <input type="hidden" name="action"      value="set_status">
                                <input type="hidden" name="new_status"  value="<?= $s ?>">
                                <button type="submit" class="admin-btn admin-btn--sm"><?= ucfirst($s) ?></button>
                            </form>
                        <?php endif; ?>
                        <?php endforeach; ?>

                        <!---- Delete (JS confirmation intercepts this) ---->
                        <form method="POST" class="delete-form">
                            <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="listing_id"  value="<?= (int)$l['id'] ?>">
                            <input type="hidden" name="action"      value="delete">
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

<script>
    // Intercept all delete form submissions and ask for confirmation
    document.querySelectorAll('.delete-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!confirm('Are you sure? This cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
</script>

<?php require_once '../../includes/admin-footer.php'; ?>