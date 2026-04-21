<?php
require_once '../includes/header.php';
require_once '../config/db.php';

// variables to store messages to show to user
$error = '';
$success = '';
// checks the URL
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'login';

//allowed domains
$allowed_domains = [
    '.ac.za',
    'vossie.net',
    'student.up.ac.za',
    'tuks.co.za',
    'myuct.ac.za',
    'wits.ac.za',
    'sun.ac.za',
    'dut4life.ac.za',
    'mylife.unisa.ac.za',
    'stu.ukzn.ac.za'
];

// login logic
// click login button - form sends a post request
//check if the form was submitted via POST or from login form specifically 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']); //trim remoces any accidental spaces
    $password = $_POST['password']; //no trim for password 

    //validating input
    if (empty($email) || empty($password)) { //check if field is empty
        $error = "Please fill in all fields.";
        $active_tab = 'login';
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
        $active_tab = 'login';
    } else {
        $stmt = $conn->prepare("SELECT id, name, role, status, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        //find user with that email
        //does the password match
        //compares plain text password against hashed version in database
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'banned' || $user['status'] === 'suspended') { //check account status
                $error = "Your account has been suspended.";
                $active_tab = 'login';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                header('Location: /public/index.php');
                exit();
            }
        } else {
            $error = "Invalid email or password.";
            $active_tab = 'login';
        }
    }
}
}

// register logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $student_number = isset($_POST['student_number']) ? trim($_POST['student_number']) : '';
    $institution = trim($_POST['institution']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $active_tab = 'register';

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
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
        $active_tab = 'register';
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "An account with this email already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, student_number, institution, phone, password) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $name, $email, $student_number, $institution, $phone, $hashed_password);

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
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-5 col-lg-4">

        <!-- Title -->
        <div class="text-center mb-4">
            <h1 class="fw-bold">Booked.</h1>
            <p class="text-muted">Student textbook marketplace South Africa.</p>
        </div>

        <!-- Toggle -->
        <ul class="nav nav-pills nav-fill bg-light rounded-pill p-1 mb-4">
            <li class="nav-item">
                <a class="nav-link rounded-pill <?= $active_tab === 'login' ? 'active bg-dark' : 'text-muted' ?>" href="#" onclick="showTab('login', this)">Login</a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-pill <?= $active_tab === 'register' ? 'active bg-light' : 'text-muted' ?>" href="#" onclick="showTab('register', this)">Register</a>
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
                <div class="mb-3">
                    <label class="form-label">Student Email</label>
                    <input type="email" name="email" class="form-control" placeholder="example@institution.ac.za" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember">
                        <label class="form-check-label text-muted" for="remember">Remember me</label>
                    </div>
                    <a href="#" class="text-muted small text-decoration-none">Forgot Password?</a>
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
                <div class="mb-3">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Your full name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="example@institution.ac.za" required>
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
                    <input type="password" name="password" class="form-control" placeholder="At least 6 characters" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat your password" required>
                </div>
                <button type="submit" name="register" class="btn btn-dark w-100">Register</button>
                <p class="text-center text-muted small mt-3">
                    <i class="bi bi-check-circle-fill text-success"></i> Only verified university emails accepted.
                </p>
            </form>
        </div>

    </div>
</div>

//
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