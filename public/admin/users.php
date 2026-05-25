<?php 
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

//Handle actions
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    verify_csrf();
    
    $target_id = (int)$_POST['user_id'];
    $action = $_POST['action'];

    //prevent admin from acting on own account
    if ($target_id == (int)$_SESSION['user_id']){
        $action_error = "You cannot perform this action on your own account.";
    } else {

    if ($action == 'suspend'){
        //set user status to suspend (can no longer log in)
        $stmt = $conn->prepare("UPDATE users SET status = 'suspended', warnings = warnings + 1, suspended_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $action_success = "User suspended sucessfully";

    } elseif ($action == 'ban'){
        //set user status to banned (permanent block)
        $stmt = $conn->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $action_success = "User banned successfully.";
        
    } elseif ($action == 'activate'){
        //restore a suspended or banned user back to active
        $stmt= $conn->prepare("UPDATE users SET status = 'active', suspended_ at = NULL WHERE id = ?");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $action_success = "User reactivated successfully.";

    } elseif ($action == 'delete'){
        //permanently delete user from database
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $action_success = "User deleted successfully.";

    } elseif ($action == 'make_admin'){
        //promote user to admin role
        $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $action_success = "User promoted to admin.";
    }
    }

    //redirect to prevent from resubmission
    $redirect = '/admin/users.php';
    if (isset($action_success)) $redirect .= '?success=1';
    header('Location: ' . $redirect);
    exit();
}

//Search and Filter
//Get filter values from URL
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_role = isset($_GET['role']) ? trim($_GET['role']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

//Build WHERE clause dynamically
$where = []; 
$params = [];
$types = '';

if ($search) {
    //search by username, name, or email
    $where [] = "(name LIKE ? OR username LIKE ? OR email LIKE ?)";
    $params [] = "%$search%";
    $params [] = "%$search%";
    $params [] = "%$search%";
    $types .= 'sss';

}

if ($filter_role){
    //filter by role
    $where [] = "role = ?";
    $params[] = $filter_role;
    $types .= 's';
}

//filter by status (active, suspended, banned)
if ($filter_status){
    
    $where [] = "status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

$sql = "SELECT * FROM users";
if (!empty($where)){
    $sql .= " WHERE " .implode(' AND ', $where);
}
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)){
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total = count($users);

require_once __DIR__ . '/../../includes/admin-header.php';
?>

<main class="main-content">

<!---- Header --->
<div class="admin-page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">Users</h1>
            <p class="admin-page-sub"><?= number_format($total) ?> user<?= $total != 1 ? 's' : '' ?> found</p>
        </div>
    </div>
</div>

<!--- Success and Error Alerts ----> 
<?php if (isset($_GET['success'])) : ?>
    <div class="admin-alert admin-alert--success mb-4">
        <i class="bi bi-check-circle"></i> Action completed successfully.
    </div>
<?php endif; ?>

<?php if (isset($action_error)) : ?>
    <div class="admin-alert admin-alert--danger mb-4">
        <i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($action_error)?>
    </div>
<?php endif; ?>

<!---- Search and Filter Bar ----> 
<form method="GET" class="admin-filter-bar mb-4">
    <div class="admin-search-wrap">
        <i class="bi bi-search admin-search-icon"></i>
        <input type="text" name="search" class="admin-search-input"
        placeholder="Search by name, username, or email"
        value="<?= htmlspecialchars($search) ?>">
    </div>

    <!---Filter by Role ---->
    <select name="role" class="admin-select" onchange="this.form.submit()">
        <option value="">All Roles</option>
        <option value="user" <?= $filter_role == 'user' ? 'selected' : '' ?>>Student</option>
        <option value="admin" <?= $filter_role == 'admin'? 'selected' : '' ?>>Admin</option>
    </select>

    <!--- Filter by Status --->
     <select name="status" class="admin-select" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="active" <?= $filter_status == 'active' ? 'selected' : '' ?>>Active</option>
        <option value="suspended" <?= $filter_status == 'suspended'? 'selected' : '' ?>>Suspended</option>
        <option value="banned" <?= $filter_status == 'banned'? 'selected' : '' ?>>Banned</option>
    </select>

    <button type="submit" class="admin-btn admin-btn--dark">Search</button>

    <!--- Clear filters link (only shows if filters are active) ---> 
    <?php if ($search || $filter_role || $filter_status) :?>
        <a href="/admin/users.php" class="admin-btn admin-btn--outline">Clear</a>
        <?php endif; ?>
</form>

<!--- Users Table ---> 
<div class="admin-table-card">
    <table class="admin-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Institution</th>
                <th>Role</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
            
    <tbody>
    <?php if (empty($users)): ?>
        <tr>
            <td colspan="6" class="admin-table-empty">No users found.</td>
        </tr>
    <?php else: ?>
        <?php foreach ($users as $u): ?>
        <tr>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <div class="user-avatar">
                        <?= strtoupper(substr($u['username'], 0, 1)) ?>
                    </div>
                    <div>
                        <p class="table-main-text"><?= htmlspecialchars($u['username']) ?></p>
                        <p class="table-sub-text"><?= htmlspecialchars($u['email']) ?></p>
                    </div>
                </div>
            </td>
            <td class="table-sub-text"><?= htmlspecialchars($u['institution'] ?? '—') ?></td>
            <td>
                <span class="admin-badge <?= $u['role'] === 'admin' ? 'badge-dark' : 'badge-light' ?>">
                    <?= in_array($u['role'], ['user', 'student']) ? 'Student' : ucfirst($u['role']) ?>
                </span>
            </td>
            <td>
                <span class="admin-badge <?= match($u['status']) {
                    'active'    => 'badge-success',
                    'suspended' => 'badge-warning',
                    'banned'    => 'badge-danger',
                    default     => 'badge-light'
                } ?>">
                    <?= ucfirst($u['status']) ?>
                </span>
            </td>
            <td class="table-sub-text"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            <td>
                <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                    <div class="action-buttons">
                        <?php if ($u['status'] !== 'active'): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="action" value="activate">
                                <button type="submit" class="admin-btn admin-btn--sm">Activate</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($u['status'] === 'active'): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="action" value="suspend">
                                <button type="submit" class="admin-btn admin-btn--sm">Suspend</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($u['status'] !== 'banned'): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="action" value="ban">
                                <button type="submit" class="admin-btn admin-btn--sm">Ban</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" class="delete-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="admin-btn admin-btn--sm">Delete</button>
                        </form>
                    </div>
                <?php else: ?>
                    <span class="table-sub-text">You</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</tbody>
    
</table>
</div>
</main>

<script>
    //intercept all delete form submissons
    document.querySelectorAll('.delete-form').forEach(function(form){
        form.addEventListener('submit', function(e){
            if (!confirm('Are you sure? This cannot be undone.')){
                e.preventDefault(); 
            }
        });
    });
</script>
<?php require_once '../../includes/admin-footer.php'; ?>