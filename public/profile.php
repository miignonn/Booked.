<?php 
require_once __DIR__ .'/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ .'/../includes/functions.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

$stmt =$conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

//if banned - delete account and log out
if ($user['status'] === 'banned'){
    $del = $conn->prepare("DELETE FROM users WHERE id = ?");
    $del->bind_param("i", $user_id);
    $del->execute();
    session_destroy();
    header('Location: /login.php?message=banned');
    exit();
}

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
    $stmt = $conn->prepare("UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?");
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

//suspension details
$is_suspended = $user['status'] === 'suspended';
$suspended_until = null;
$can_list = true;

if ($is_suspended){
    //find when most recent warning was issued via reports
    $sus_stmt = $conn->prepare("
    SELECT MAX(r.created_at) AS last_warned
    FROM reports r
    JOIN listings l ON r.listing_id = l.id
    WHERE l.user_id = ? AND r.status = 'reviewed'
    ");

    $sus_stmt->bind_param("i", $user_id);
    $sus_stmt->execute();
    $sus_row = $sus_stmt->get_result()->fetch_assoc();
    $last_warned = $sus_row['last_warned'] ?? $user['created_at'];
    $suspended_until = date('d M Y', strtotime($last_warned. ' + 30 days'));
    $can_list = strtotime($last_warned. ' + 30 days') < time();
}

require_once __DIR__ . '/../includes/header.php';
?>
 
    <!-- Warning banner -->
    <?php if (!empty($user['warnings']) && $user['warnings'] > 0 && !$is_suspended): ?>
        <div class="profile-warning-banner">
            <i class="bi bi-exclamation-triangle-fill profile-warning-banner__icon"></i>
            <div>
                <p class="profile-warning-banner__title">
                    You have <?= (int)$user['warnings'] ?> warning<?= $user['warnings'] != 1 ? 's' : '' ?>
                </p>
                <p class="profile-warning-banner__text">
                    One more warning will result in a 30-day suspension from creating listings.
                </p>
            </div>
        </div>
    <?php endif; ?>
 
    <!-- Suspended banner -->
    <?php if ($is_suspended): ?>
        <div class="profile-suspended-banner">
            <i class="bi bi-slash-circle-fill me-2"></i>
            <div>
                <p class="profile-suspended-banner__title">Account suspended</p>
                <p class="profile-suspended-banner__text">
                    You cannot create or edit listings until <strong><?= $suspended_until ?></strong>.
                    You account was suspended due to <?= (int)$user['warnings'] ?> warning<?= $user['warnings'] != 1 ? 's' : '' ?> 
                </p>
            </div>
        </div>
    <?php endif; ?>
 
    <!-- Avatar -->
    <div class="profile-avatar-wrap">
        <div class="profile-avatar">
            <?= strtoupper(substr($user['username'], 0, 1)) ?>
        </div>
        <h4 class="profile-name"><?= htmlspecialchars($user['name']) ?></h4>
        <p class="profile-username">@<?= htmlspecialchars($user['username']) ?></p>
        <p class="profile-since">Member since <?= date('M Y', strtotime($user['created_at'])) ?></p>
    </div>
 
    <?php if ($error && !isset($_POST['change_password'])): ?>
        <div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success && !isset($_POST['change_password'])): ?>
        <div class="alert alert-success mb-3"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
 
    <!-- Profile form -->
    <form method="POST" class="profile-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
 
        <div class="listing-field-row">
    <div class="profile-field">
        <label class="profile-label">Full Name <span class="profile-required">*</span></label>
        <input type="text" name="name" class="form-control"
               value="<?= htmlspecialchars($user['name']) ?>" required>
    </div>
    <div class="profile-field">
        <label class="profile-label">Username <span class="profile-required">*</span></label>
        <input type="text" name="username" class="form-control"
               value="<?= htmlspecialchars($user['username']) ?>" required>
    </div>
</div>

<div class="profile-field">
    <label class="profile-label">Email Address</label>
    <input type="email" class="form-control"
           value="<?= htmlspecialchars($user['email']) ?>" disabled>
    <p class="profile-field-hint">Email cannot be changed.</p>
</div>

<div class="listing-field-row">
    <div class="profile-field">
        <label class="profile-label">Institution <span class="profile-required">*</span></label>
        <input type="text" name="institution" class="form-control"
               value="<?= htmlspecialchars($user['institution'] ?? '') ?>" required>
    </div>
    <div class="profile-field">
        <label class="profile-label">Phone Number <span class="profile-required">*</span></label>
        <input type="text" name="phone" class="form-control"
               value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
    </div>
</div>
 
        <button type="submit" class="btn btn-dark w-100">Save Changes</button>
    </form>
 
    <hr class="profile-divider">
 
    <!-- Password row -->
    <div class="profile-password-row">
        <div>
            <p class="profile-password-label">Password</p>
            <p class="profile-password-sub">Last changed: <?= $user['password_changed_at'] ? date('d M Y', strtotime($user['password_changed_at'])) : 'Never' ?></p>
        </div>
        <button class="btn btn-outline-dark btn-sm"
                data-bs-toggle="modal" data-bs-target="#passwordModal">
            Change Password
        </button>
    </div>
 
</div>
 
<!-- Password modal -->
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
                        <label class="profile-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="profile-label">New Password</label>
                        <input type="password" name="new_password" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="profile-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary w-100"
                                data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="change_password"
                                class="btn btn-dark w-100">Update Password</button>
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