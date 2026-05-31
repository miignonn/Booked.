<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['csrf_token'])){
$_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
}
require_once __DIR__ . '/../config/db.php';

if (isset($_SESSION['user_id'])) refresh_user_status($conn);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booked</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Arapey:ital@0;1&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Taviraj:ital,wght@0,300;0,400;1,300;1,400&family=Rasa:ital,wght@0,300;0,400;1,300;1,400&display=swap" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="site-nav">
    <div class="site-nav__inner">
 
        <a class="site-nav__logo" href="/index.php">Booked.</a>
 
        <div class="site-nav__links">
            <a class="site-nav__link" href="/index.php">Home</a>
            <a class="site-nav__link" href="/browse.php">Browse</a>
 
            <?php if (isset($_SESSION['user_id'])): ?>
 
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a class="site-nav__link site-nav__link--admin" href="/admin/dashboard.php">
                        <i class="bi bi-shield-lock"></i> Admin
                    </a>
                <?php endif; ?>
 
                <a class="site-nav__link site-nav__cart" href="/cart.php">
                      <span class="site-nav__cart-wrap">
                      <i class="bi bi-bag"></i>
                    <?php
                    $cart_count = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
                    $cart_count->bind_param("i", $_SESSION['user_id']);
                    $cart_count->execute();
                    $count = $cart_count->get_result()->fetch_assoc()['count'];
                    if ($count > 0) echo '<span class="site-nav__badge">' . $count . '</span>';
                    ?>
                </span>
                </a>
 
                <div class="site-nav__dropdown">
                    <button class="site-nav__avatar" id="navDropdownBtn">
                        <?= strtoupper(substr($_SESSION['username'] ?? $_SESSION['name'], 0, 1)) ?>
                    </button>
                    <div class="site-nav__dropdown-menu" id="navDropdownMenu">
                        <a href="/profile.php" class="site-nav__dropdown-item">
                            <i class="bi bi-person"></i> Profile
                        </a>
                        <a href="/orders.php" class="site-nav__dropdown-item">
                            <i class="bi bi-bag"></i> My Orders
                        </a>
                        <a href="/my-listings.php" class="site-nav__dropdown-item">
                            <i class="bi bi-list-ul"></i> My Listings
                        </a>
                        <a href="/create-listing.php" class="site-nav__dropdown-item">
                            <i class="bi bi-plus-circle"></i> Create Listing
                        </a>
                        <div class="site-nav__dropdown-divider"></div>
                        <a href="/logout.php" class="site-nav__dropdown-item site-nav__dropdown-item--danger">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                </div>
 
            <?php else: ?>
                <a class="site-nav__link" href="/login.php">Login</a>
                <a class="site-nav__btn" href="/login.php?tab=register">Register</a>
            <?php endif; ?>
        </div>
 
        <button class="site-nav__hamburger" id="navHamburger" aria-label="Menu">
            <i class="bi bi-list"></i>
        </button>
    </div>
 
    <!-- Mobile menu -->
    <div class="site-nav__mobile" id="navMobile">
        <a class="site-nav__mobile-link" href="/index.php">Home</a>
        <a class="site-nav__mobile-link" href="/browse.php">Browse</a>
        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'): ?>
            <a class="site-nav__mobile-link site-nav__mobile-link--admin" href="/admin/dashboard.php">
              <i class="bi bi-shield-lock"></i> Admin
            </a>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a class="site-nav__mobile-link" href="/cart.php">
               <i class="bi bi-bag"></i> Cart
        <?php if ($count > 0): ?>
        <span class="site-nav__badge" style="position:relative; top:0; right:0; margin-left:6px;">
            <?= $count ?>
        </span>
         <?php endif; ?>
        </a>
            <a class="site-nav__mobile-link" href="/profile.php">Profile</a>
            <a class="site-nav__mobile-link" href="/orders.php">My Orders</a>
            <a class="site-nav__mobile-link" href="/my-listings.php">My Listings</a>
            <a class="site-nav__mobile-link" href="/create-listing.php">Create Listing</a>
            <a class="site-nav__mobile-link site-nav__mobile-link--danger" href="/logout.php">Logout</a>
        <?php else: ?>
            <a class="site-nav__mobile-link" href="/login.php">Login</a>
            <a class="site-nav__mobile-link" href="/login.php?tab=register">Register</a>
        <?php endif; ?>
    </div>
</nav>
 
<?php $flash = get_flash(); if ($flash): ?>
<div class="flash-alert flash-alert--<?= $flash['type'] ?>" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button class="flash-alert__close" onclick="this.parentElement.remove()">×</button>
</div>
<?php endif; ?>
 
 
    
    
                   
                 
           

