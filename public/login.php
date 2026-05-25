<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ .'/../includes/functions.php';

$error = '';
$success = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'login';

$allowed_domains = [
    '.ac.za',
    'vossie.net',
    'student.up.ac.za',
    'unisa.ac.za',
    'tuks.co.za',
    'uct.ac.za',
    'ufh.ac.za',
    'ufs.ac.za',
    'ukzn.ac.za',
    'ul.ac.za',
    'nwu.ac.za',
    'wits.ac.za',
    'sun.ac.za',
    'up.ac.za',
    'ru.ac.za',
    'uj.ac.za'
    ];

// LOGIN LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    verify_csrf();
    $login_input = trim($_POST['login_input']);
    $password    = $_POST['password'];

    if (empty($login_input) || empty($password)) {
        $error      = "Please fill in all fields.";
        $active_tab = 'login';
    } else {
        if (str_contains($login_input, '@')) {
            $stmt = $conn->prepare("SELECT id, name, username, role, status, password FROM users WHERE email = ?");
        } else {
            $stmt = $conn->prepare("SELECT id, name, username, role, status, password FROM users WHERE username = ?");
        }
        $stmt->bind_param("s", $login_input);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {

            //auto-unsuspend after 30 days
            if ($user['status'] === 'suspended') {
                $sus_stmt = $conn->prepare("
                    SELECT MAX(r.created_at) AS last_warned
                    FROM reports r
                    JOIN listings l ON r.listing_id = l.id
                    WHERE l.user_id = ? AND r.status = 'reviewed'
                ");
                $sus_stmt->bind_param("i", $user['id']);
                $sus_stmt->execute();
                $sus_row     = $sus_stmt->get_result()->fetch_assoc();
                $last_warned = $sus_row['last_warned'] ?? $user['created_at'];

                if (strtotime($last_warned . ' + 30 days') < time()) {
                    $unsuspend = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                    $unsuspend->bind_param("i", $user['id']);
                    $unsuspend->execute();
                    $user['status'] = 'active';
                }
            }

            if ($user['status'] === 'banned') {
                $error      = "Your account has been permanently banned.";
                $active_tab = 'login';
            } elseif ($user['status'] === 'suspended') {
                $error      = "Your account has been suspended for 30 days.";
                $active_tab = 'login';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['name']     = $user['name'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                header('Location: /index.php');
                exit();
            }

        } else {
            $error      = "Invalid email/username or password.";
            $active_tab = 'login';
        }
    }
}



// REGISTER LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    verify_csrf();
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $institution = trim($_POST['institution']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $active_tab = 'register';

    if (empty($name) || empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all required fields.";
    } else {
        $email_allowed = false;
        foreach ($allowed_domains as $domain) {
            if (str_ends_with($email, $domain)) {
                $email_allowed = true;
                break;
            }
        }
        if (!$email_allowed) {
            $error = "Only South African student emails are accepted.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
        } elseif ($password !== $confirm_password){
            $error = "Passwords do not match.";
        } else {
            // check email not taken
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $check->bind_param("ss", $email, $username);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Email or username already taken.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, username, email, institution, phone, password) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $name, $username, $email, $institution, $phone, $hashed_password);

                if ($stmt->execute()) {
                    $success = "Account created! You can now log in.";
                    $active_tab = 'login';
                } else {
                    $error = "Something went wrong. Please try again.";
                }
            }
        }
    }
}
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-5 col-lg-4">

        <div class="text-center mb-4">
            <h1 class="fw-bold">Booked.</h1>
            <p class="text-muted">Student textbook marketplace South Africa.</p>
        </div>

        <ul class="nav nav-pills nav-fill bg-light rounded-pill p-1 mb-4">
            <li class="nav-item">
                <a class="nav-link rounded-pill <?= $active_tab === 'login' ? 'active bg-dark text-white' : 'text-muted' ?>" href="#" onclick="showTab('login', this)">Login</a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-pill <?= $active_tab === 'register' ? 'active bg-dark text-white' : 'text-muted' ?>" href="#" onclick="showTab('register', this)">Register</a>
            </li>
        </ul>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- LOGIN FORM -->
        <div id="login-form" class="<?= $active_tab === 'login' ? '' : 'd-none' ?>">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="mb-3">
                    <label class="form-label">Email or Username</label>
                    <input type="text" name="login_input" class="form-control" placeholder="Email or username" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
                <div class="d-flex justify-content-end mb-3">
                    <a href="#" class="text-muted small">Forgot Password?</a>
                </div>
                <button type="submit" name="login" class="btn btn-dark w-100">Login</button>
                <p class="text-center text-muted small mt-3">
                    <i class="bi bi-check-circle-fill text-success"></i> Only verified university emails accepted.
                </p>
            </form>
        </div>

        <!-- REGISTER FORM -->
        <div id="register-form" class="<?= $active_tab === 'register' ? '' : 'd-none' ?>">
            <form method="POST">
                 <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="mb-3">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Your full name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" placeholder="e.g. booked123" autocomplete="off" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="example@institution.ac.za" autocomplete="off" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Institution</label>
                    <input type="text" name="institution" class="form-control" placeholder="e.g. Eduvos">
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="e.g. 082 123 4567">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" placeholder="At least 6 characters" autocomplete="new-password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat your password" autocomplete="new-password" required>
                </div>
                <button type="submit" name="register" class="btn btn-dark w-100">Register</button>
                <p class="text-center text-muted small mt-3">
                    <i class="bi bi-check-circle-fill text-success"></i> Only verified university emails accepted.
                </p>
            </form>
        </div>

    </div>
</div>

<script>
function showTab(tab, el) {
    event.preventDefault();
    document.getElementById('login-form').classList.toggle('d-none', tab !== 'login');
    document.getElementById('register-form').classList.toggle('d-none', tab !== 'register');
    document.querySelectorAll('.nav-link').forEach(a => {
        a.classList.remove('active', 'bg-dark', 'text-white');
        a.classList.add('text-muted');
    });
    el.classList.add('active', 'bg-dark', 'text-white');
    el.classList.remove('text-muted');
}
</script>

<?php require_once '../includes/footer.php'; ?>