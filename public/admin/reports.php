<?php
require_once __DIR__ . '/../../includes/require_admin.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verify_csrf();
 
    $report_id = (int)$_POST['report_id'];
    $action    = $_POST['action'];
 
    if ($report_id > 0) {
 
        if ($action == 'dismiss') {
            //mark report as dismissed 
            $stmt = $conn->prepare("UPDATE reports SET status = 'dismissed' WHERE id = ?");
            $stmt->bind_param("i", $report_id);
            $stmt->execute();
            $action_success = "Report dismissed.";
 
        } elseif ($action == 'warn') {
            //get the listing owner from the report
            $stmt = $conn->prepare("
                SELECT r.id, l.user_id
                FROM reports r
                JOIN listings l ON r.listing_id = l.id
                WHERE r.id = ?
                LIMIT 1
            ");
            $stmt->bind_param("i", $report_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
 
            if ($result) {
                $user_id = (int)$result['user_id'];
 
                // increment warning count on the user
                $stmt = $conn->prepare("UPDATE users SET warnings = warnings + 1 WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
 
                // check new warning count (auto-suspend at 2+)
                $stmt = $conn->prepare("SELECT warnings FROM users WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
 
                if ($user['warnings'] >= 2) {
                    $stmt = $conn->prepare("UPDATE users SET status = 'suspended', suspended_at = NOW() WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $action_success = "User warned and automatically suspended (2+ warnings).";
                } else {
                    $action_success = "User has been warned ({$user['warnings']} warning(s) total).";
                }
 
                //mark report as reviewed
                $stmt = $conn->prepare("UPDATE reports SET status = 'reviewed' WHERE id = ?");
                $stmt->bind_param("i", $report_id);
                $stmt->execute();
            }
 
        } elseif ($action == 'delete') {
            //permanently remove the report
            $stmt = $conn->prepare("DELETE FROM reports WHERE id = ?");
            $stmt->bind_param("i", $report_id);
            $stmt->execute();
            $action_success = "Report deleted.";
        }
    }
 
    //redirect after POST to prevent resubmission 
    $redirect = '/admin/reports.php';
    if (isset($action_success)) $redirect .= '?success=1';
    header('Location: ' . $redirect);
    exit();
}
 
//search and filter
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
 
$where  = [];
$params = [];
$types  = '';
 
if ($filter_status) {
    $where[]  = "r.status = ?";
    $params[] = $filter_status;
    $types   .= 's';
}
 
$sql = "
    SELECT
        r.id, r.reason, r.status, r.created_at,
        l.id    AS listing_id,
        l.title AS listing_title,
        l.price AS listing_price,
        reporter.username AS reported_by_name,
        reporter.email    AS reported_by_email,
        seller.username   AS seller_name,
        seller.email      AS seller_email,
        seller.warnings   AS seller_warnings,
        seller.status     AS seller_status
    FROM reports r
    JOIN listings l         ON r.listing_id  = l.id
    JOIN users    reporter  ON r.reported_by  = reporter.id
    JOIN users    seller    ON l.user_id      = seller.id
";
 
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
 
$sql .= " ORDER BY r.created_at DESC";
 
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total   = count($reports);
 
//status counts for summary strip
$status_counts = [];
$count_result  = $conn->query("SELECT status, COUNT(*) AS total FROM reports GROUP BY status");
while ($row = $count_result->fetch_assoc()) {
    $status_counts[$row['status']] = (int)$row['total'];
}

require_once __DIR__. '/../../includes/admin-header.php';
?>

<main class="main-content">

<!---- Page Header ---->
<div class="admin-page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">Reports</h1>
            <p class="admin-page-sub"><?= number_format($total) ?> report<?= $total != 1 ? 's' : '' ?> found</p>
        </div>
    </div>
</div>

<!---- Success Alert ---->
<?php if (isset($_GET['success'])): ?>
    <div class="admin-alert admin-alert--success mb-4">
        <i class="bi bi-check-circle"></i> Action completed successfully.
    </div>
<?php endif; ?>

<!--- Status summary strip ----> 
<div class="stat-grid stat-grid-4 mb-4">
    <?php
    $strip_stats = [
        ''        => ['label' => 'Total', 'icon' => 'bi-flag'],
        'pending' => ['label' => 'Pending',          'icon' => 'bi-hourglass'],
        'reviewed' => ['label' => 'Reviewed',       'icon' => 'bi-check-circle'],
        'dismissed' => ['label' => 'Dismissed',            'icon' => 'bi-check-circle'],
    ];
    $total_all = array_sum($status_counts);
    foreach ($strip_stats as $key => $meta):
        $count     = ($key === '') ? $total_all : ($status_counts[$key] ?? 0);
        $is_active = $filter_status === $key;
        $link      = '/admin/reports.php' . ($key ? '?status=' . $key : '');
    ?>
    <a href="<?= $link ?>" class="stat-card <?= $is_active ? 'stat-card--active' : '' ?>">
        <i class="bi <?= $meta['icon'] ?> stat-icon"></i>
        <p class="stat-label"><?= $meta['label'] ?></p>
        <p class="stat-value"><?= $count ?></p>
    </a>
    <?php endforeach; ?>
</div>

<!---  Filter Bar ----> 
<form method="GET" class="admin-filter-bar mb-4">
    <select name="status" class="admin-select" onchange="this.form.submit()">
        <option value="">All Statusses</option>
        <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="reviewed" <?= $filter_status == 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
        <option value="dismissed" <?= $filter_status == 'dismissed' ? 'selected' : '' ?>>Dismissed</option>
    </select>

    <?php if ($filter_status): ?>
        <a href="reports.php" class="admin-btn--outline">Clear</a>
    <?php endif; ?>
</form>

<!---- Reports Table ---> 
<div class="admin-table-card">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Reported Listing</th>
                <th>Seller</th>
                <th>Reported By</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
 
        <tbody>
        <?php if (empty($reports)): ?>
            <tr>
                <td colspan="7" class="admin-table-empty">No reports found.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($reports as $r): ?>
            <tr>
                <!---- Listing ---->
                <td>
                    <p class="table-main-text"><?= htmlspecialchars($r['listing_title']) ?></p>
                    <p class="table-sub-text">R<?= number_format((float)$r['listing_price'], 2) ?></p>
                </td>
 
                <!---- Seller + warning count ---->
                <td>
                    <p class="table-main-text"><?= htmlspecialchars($r['seller_name'] ?? '—') ?></p>
                    <p class="table-sub-text"><?= htmlspecialchars($r['seller_email'] ?? '') ?></p>
                    <?php if ($r['seller_warnings'] > 0): ?>
                        <span class="admin-badge badge-warning">
                            <?= $r['seller_warnings'] ?> warning<?= $r['seller_warnings'] != 1 ? 's' : '' ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($r['seller_status'] === 'suspended'): ?>
                        <span class="admin-badge badge-danger">Suspended</span>
                    <?php endif; ?>
                </td>
 
                <!---- Reported by ---->
                <td>
                    <p class="table-main-text"><?= htmlspecialchars($r['reported_by_name'] ?? '—') ?></p>
                    <p class="table-sub-text"><?= htmlspecialchars($r['reported_by_email'] ?? '') ?></p>
                </td>
 
                <!---- Reason ---->
                <td style="max-width: 200px;">
                    <p class="table-sub-text" style="white-space: normal;">
                        <?= htmlspecialchars($r['reason']) ?>
                    </p>
                </td>

                <!--- Status Badge --->
                <td>
                    <span class="admin-badge <?= match ($r['status']){
                        'pending'   => 'badge-warning',
                        'reviewed'  => 'badge-success',
                        'dismissed' => 'badge-light',
                        default     => 'badge-light'
                    } ?>">
                    <?= ucfirst($r['status'] ?? 'pending') ?>
                    </span>
                </td>
                <!---- Date ---->
                <td class="table-sub-text"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
 
                <!---- Actions ---->
                <td>
                    <div class="action-buttons">
 
                        <?php if ($r['status'] === 'pending'): ?>
 
                            <!---- Warn user ---->
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="action"    value="warn">
                                <button type="submit" class="admin-btn admin-btn--sm">Warn</button>
                            </form>
 
                            <!---- Dismiss ---->
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="action"    value="dismiss">
                                <button type="submit" class="admin-btn admin-btn--sm">Dismiss</button>
                            </form>
 
                        <?php endif; ?>
 
                        <!---- Delete (always available) ---->
                        <form method="POST" class="delete-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="action"    value="delete">
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
    document.querySelectorAll('.delete-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!confirm('Delete this report? This cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
</script>
 
<?php require_once '../../includes/admin-footer.php'; ?>
 