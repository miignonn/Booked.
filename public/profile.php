<?php 
require_once '../includes/header.php';
require_once '../includes/auth_check.php';
require_once __DIR__ .'/../includes/functions.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

$stmt =$conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])){
verify_csrf();
$current_password = $_POST['current_password'];
$new_password = $_POST['new_password'];
$confirm_password = $_POST['confirm_password'];

if(empty($current_password) || empty($new_password) || empty($confirm_password)){
    $error = "Please fill in all password fields.";
} elseif ($new_password != $confirm_password){
    $error = "Passwords do not match.";

} elseif (strlen($new_password) < 6){
    $error = "New password must be at least 6 characters.";
} elseif (!password_verify($current_password, $user['password'])){
    $error = "Current password is incorrect.";
} else {
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed, $user_id);
    if ($stmt->execute()){
        $success = "Password updated successfully!";
    } else {
        $error = "Something went wrong. Please try again.";
    }
}

} elseif ($_SERVER['REQUEST_METHOD' ] == 'POST'){
    verify_csrf();
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $institution = trim($_POST['institution']);
    $phone = trim($_POST['phone']);

    if(empty($name) || empty($username)){
        $error = "Name and username are required.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->bind_param("si", $username, $user_id);
        $check->execute();
        $check->store_result();

        if($check->num_rows > 0){
            $error = "This username is already taken";
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, username = ?, institution = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $username, $institution, $phone, $user_id);

            if ($stmt->execute()){
                $_SESSION['name'] = $name;
                $_SESSION['username'] = $username;
                $success = "Profile updated successfully!";
                $user['name'] = $name;
                $user['username'] = $username;
                $user['institution'] = $institution;
                $user['phone'] = $phone;

            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">

        <!-- Avatar -->
        <div class="text-center mb-4">
            <div class="bg-dark rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold mb-3"
                 style="width:80px;height:80px;font-size:2rem;">
                <?= strtoupper(substr($user['username'], 0, 1)) ?>
            </div>
            <h4 class="fw-bold mb-0"><?= htmlspecialchars($user['name']) ?></h4>
            <p class="text-muted small">@<?= htmlspecialchars($user['username']) ?></p>
            <p class="text-muted small">Member since <?= date('M Y', strtotime($user['created_at'])) ?></p>
        </div>

        <?php if ($error && !isset($_POST['change_password'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success && !isset($_POST['change_password'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Profile Form -->
        <form method="POST">
             <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="mb-3">
                <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control"
                    value="<?= htmlspecialchars($user['name']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Username <span class="text-danger">*</span></label>
                <input type="text" name="username" class="form-control"
                    value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Email Address</label>
                <input type="email" class="form-control" 
                    value="<?= htmlspecialchars($user['email']) ?>" disabled>
                <div class="form-text">Email cannot be changed.</div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Institution <span class="text-danger">*</span></label>
                <input type="text" name="institution" class="form-control"
                    value="<?= htmlspecialchars($user['institution'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Phone Number <span class="text-danger">*</span></label>
                <input type="text" name="phone" class="form-control"
                    value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
            </div>
            <button type="submit" class="btn btn-dark w-100">Save Changes</button>
        </form>

        <hr class="my-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <p class="fw-bold mb-0">Password</p>
                <p class="text-muted small mb-0">Last changed: unknown</p>
            </div>
            <button class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#passwordModal">
                Change Password
            </button>
        </div>
    </div>
</div>

<!-- Password Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($error && isset($_POST['change_password'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success && isset($_POST['change_password'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Current Password</label>
                        <input type="password" name="current_password" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Password</label>
                        <input type="password" name="new_password" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="change_password" class="btn btn-dark w-100">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_POST['change_password']) && ($error || $success)): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        new bootstrap.Modal(document.getElementById('passwordModal')).show();
    });
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>