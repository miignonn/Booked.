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
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/index.php">
            Booked.
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
    <a class="nav-link" href="/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/index.php">Browse</a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item">
                   <a class="nav-link" href="/create-listing.php">Create Listing</a>
                </li>
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <li class="nav-item">
            <a class="nav-link text-warning" href="/admin/dashboard.php">
                <i class="bi bi-shield-lock"></i> Admin
            </a>
        </li>
    <?php endif; ?>
    
    
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" 
           href="#" role="button" data-bs-toggle="dropdown">
            <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                 style="width:32px;height:32px;font-size:0.85rem;">
                <?= strtoupper(substr($_SESSION['username'] ?? $_SESSION['name'], 0, 1)) ?>
            </div>
            <?= htmlspecialchars($_SESSION['username'] ?? $_SESSION['name']) ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="/profile.php">
                <i class="bi bi-person"></i> Profile
            </a></li>
            <li><a class="dropdown-item" href="/my-listings.php">
                <i class="bi bi-list-ul"></i> My Listings
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="/logout.php">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a></li>
        </ul>
    </li>

<?php else: ?>
    <li class="nav-item">
        <a class="nav-link" href="/login.php">Login</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="/login.php?tab=register">Register</a>
    </li>
<?php endif; ?>
                   
                 
            </ul>
        </div> 
    </div> 
</nav>

<div class="container mt-4">