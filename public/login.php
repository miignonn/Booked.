<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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

    //rate limiting
    if (!isset($_SESSION['login_attempts'])){
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_time'] = time();
    }
    
    if (time() - $_SESSION['login_time'] > 900){
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_time'] = time();
    }

    if ($_SESSION['login_attempts'] >= 5){
        $error = "Too many login attempts. Please wait 15 minutes";
        $active_tab = 'login';
    } else {
        $_SESSION['login_attempts']++;
    }

    $login_input = trim($_POST['login_input']);
    $password    = $_POST['password'];

    if (empty($login_input) || empty($password)) {
        $error      = "Please fill in all fields.";
        $active_tab = 'login';
    } else {
        if (str_contains($login_input, '@')) {
            $stmt = $conn->prepare("SELECT id, name, username, role, status, password, created_at, suspended_at FROM users WHERE email = ?");
        } else {
            $stmt = $conn->prepare("SELECT id, name, username, role, status, password, created_at, suspended_at FROM users WHERE username = ?");
        }
        $stmt->bind_param("s", $login_input);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {

            //auto-unsuspend after 30 days
            if($user['status'] === 'suspended'){
                $suspended_at = $user['suspended_at'] ?? $user['created_at'];
                if (strtotime($suspended_at . ' + 30 days') < time()){
                    $unsuspend = $conn->prepare("UPDATE users SET status = 'active', suspended_at = NULL WHERE id = ?");
                    $unsuspend->bind_param("i", $user['id']);
                    $unsuspend->execute();
                    $user['status'] = 'active';
                }
            }

            if ($user['status'] === 'banned') {
                $error      = "Your account has been permanently banned.";
                $active_tab = 'login';
            }  else {
                session_regenerate_id(true);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['name']     = $user['name'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                $_SESSION['status'] = $user['status'];
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

    //rate-limiting
    if (!isset($_SESSION['register_attempts'])){
        $_SESSION['register_attempts'] = 0;
        $_SESSION['register_time'] = time();
    }
    if (time() - $_SESSION['register_attempts'] > 3600){
        $_SESSION['register_attempts'] = 0;
        $_SESSION['register_time'] = time();
    }
    if ($_SESSION['register_attempts'] >= 3){
        $error= "Too many registration attempts. Please wait an hour.";
        $active_tab = 'register';
    } else {
        $_SESSION['register_attempts']++;
    }
    
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
</div><!-- close page-wrap from header -->

<div class="auth-wrap">
    <h1 class="auth-wrap__logo">Booked.</h1>
    <p class="auth-wrap__sub">South Africa's Student Textbook Marketplace</p>

    <div class="auth-tabs">
        <a class="auth-tab <?= $active_tab === 'login' ? 'active' : '' ?>"
           href="#" onclick="showTab('login', this)">Login</a>
        <a class="auth-tab <?= $active_tab === 'register' ? 'active' : '' ?>"
           href="#" onclick="showTab('register', this)">Register</a>
    </div>

    <?php if ($error): ?>
        <div class="flash-alert flash-alert--danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="flash-alert flash-alert--success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- LOGIN FORM -->
    <div id="login-form" class="<?= $active_tab === 'login' ? '' : 'd-none' ?>">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="auth-field">
                <label class="b-eyebrow">Email or Username</label>
                <input type="text" name="login_input" class="auth-input"
                       placeholder="Email or username" required>
            </div>
            <div class="auth-field">
                <label class="b-eyebrow">Password</label>
                <input type="password" name="password" class="auth-input"
                       placeholder="Enter password" required>
            </div>
            <button type="submit" name="login" class="b-btn b-btn--primary w-100">Login</button>
            <p class="auth-note">
                <i class="bi bi-check-circle-fill"></i>
                Only university emails accepted.
            </p>
        </form>
    </div>

    <!-- REGISTER FORM -->
    <div id="register-form" class="<?= $active_tab === 'register' ? '' : 'd-none' ?>">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="auth-field">
                <label class="b-eyebrow">Full Name <span class="auth-required">*</span></label>
                <input type="text" name="name" class="auth-input"
                     value="<?= isset($name) ? htmlspecialchars($name) : '' ?>"
                     placeholder="Your full name" required>
            </div>
            <div class="auth-field">
                <label class="b-eyebrow">Username <span class="auth-required">*</span></label>
                <input type="text" name="username" class="auth-input"
                  value="<?= isset($username) ? htmlspecialchars($username) : '' ?>"
                  placeholder="user123" autocomplete="off" required>
            </div>
            <input type="email" name="email" class="auth-input"
                  value="<?= isset($email) ? htmlspecialchars($email) : '' ?>"
                  placeholder="example@institution.ac.za" autocomplete="off" required>
            </div>
            <input type="text" name="institution" class="auth-input"
                   value="<?= isset($institution) ? htmlspecialchars($institution) : '' ?>"
                   placeholder="e.g. Eduvos">
            </div>
            <div class="auth-field">
                <label class="b-eyebrow">Password <span class="auth-required">*</span></label>
                <input type="password" name="password" class="auth-input"
                       placeholder="At least 6 characters" autocomplete="new-password" required>
            </div>
            <div class="auth-field">
                <label class="b-eyebrow">Confirm Password <span class="auth-required">*</span></label>
                <input type="password" name="confirm_password" class="auth-input"
                       placeholder="Repeat your password" autocomplete="new-password" required>
            </div>
            <button type="submit" name="register" class="b-btn b-btn--primary w-100">Register</button>
            <p class="auth-note">
                <i class="bi bi-check-circle-fill"></i>
                Only verified university emails accepted.
            </p>
        </form>
    </div>

</div>

<div class="page-wrap"></div>
   
<script>
function showTab(tab, el) {
    event.preventDefault();
    document.getElementById('login-form').classList.toggle('d-none', tab !== 'login');
    document.getElementById('register-form').classList.toggle('d-none', tab !== 'register');
    document.querySelectorAll('.auth-tab').forEach(a => a.classList.remove('active'));
    el.classList.add('active');
}

</script>
<?php require_once '../includes/footer.php'; ?>