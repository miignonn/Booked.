<?php 
require_once '../includes/header.php';
require_once '../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

$stmt =$conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST'){
    $name = trim($_POST['name']);
    $username= trim($_POST['username']);
    $email = trim($_POST['email']);
    $institution = trim($_POST['institution']);
    $phone = trim($_POST['phone']);

    if (empty($name) || empty($username)){
        $error = "Name and username are required.";

    } else {
        //check if username is not taken yet
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->bind_param("si", $username, $user_id);
        $check->execute();
        $check->store_result();

        if($check->num_rows > 0){
            $error = "That username is already taken.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, username = ?, institution = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $username, $institution, $phone, $user_id);
        
            if($stmt->execute()){
                $_SESSION['name'] = $name;
                $_SESSION['username'] = $username;
                $success = "Profile updated successfully!";
                $user['name'] = $name;
                $user['username'] = $username;
                $user['institution'] = $institution;
                $user['phone'] = $phone;
            } else {
                $error = "Something went wrong. Please try again";
            }
        
        }

    }
}

// handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password     = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all password fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $user_id);
        if ($stmt->execute()) {
            $success = "Password updated successfully!";
        } else {
            $error = "Something went wrong. Please try again.";
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

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Profile Form -->
        <form method="POST">
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

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
