<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booked</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/public/index.php">
            Booked.
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
    <a class="nav-link" href="/public/index.php">Home</a>
</li>
                <li class="nav-item">
                    <a class="nav-link" href="/public/index.php">Browse</a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/public/create-listing.php">Create Listing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/public/my-listings.php">My Listings</a>
                    </li>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link text-warning" href="/public/admin/dashboard.php">
                                <i class="bi bi-shield-lock"></i> Admin
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/public/profile.php">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['name']) ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="/public/logout.php">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/public/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/public/register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div> 
    </div> 
</nav>

<div class="container mt-4">