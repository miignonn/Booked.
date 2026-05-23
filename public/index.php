<?php  
require_once __DIR__ . '/../config/db.php';

$sql = "SELECT listings.*, users.name AS seller_name 
FROM listings
JOIN users ON listings.user_id = users.id 
WHERE listings.status = 'active'
ORDER BY listings.created_at DESC";

$result = $conn->query($sql);
require_once __DIR__. '/../includes/header.php';
?>

<div>
    <?php if (isset($_GET['message']) && $_GET['message'] == 'logged_out'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            You have been logged out successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

<header class="hero">
    <div class="hero__text">
        <h1 class="hero__heading">Buy Smart<br>Sell Easy<br>Stay Booked.</h1>
        <p class="hero__sub">The second-hand textbook marketplace for South African students.</p>
        <a href="/browse.php" class="btn btn-dark">
            Browse the shelves <i class="bi bi-arrow-right"></i>
        </a>
    </div>
    <div class="hero__image">
        <img src="/assets/images/logo.png" alt="Booked illustration" />
    </div>
</header>
 
<h4 class="fw-bold mb-3">New Listings</h4>
<div class="listings-scroll">
    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($listing = $result->fetch_assoc()): ?>
            <div class="listing-card" onclick="window.location='/listing.php?id=<?= $listing['id'] ?>&from=home'">
 
                <div class="listing-card__image-wrap">
                    <?php if ($listing['image']): ?>
                        <img src="/<?= htmlspecialchars($listing['image']) ?>"
                             alt="<?= htmlspecialchars($listing['title']) ?>">
                    <?php else: ?>
                        <div class="listing-card__no-image">
                            <i class="bi bi-book fs-1 text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>
 
                <div class="listing-card__info">
                    <p class="listing-card__title"><?= htmlspecialchars($listing['title']) ?></p>
                    <p class="listing-card__author"><?= htmlspecialchars($listing['author']) ?></p>
                    <p class="listing-card__price">R<?= number_format($listing['price'], 2) ?></p>
                </div>
 
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="text-muted">No listings yet. Be the first to <a href="/create-listing.php">sell a book</a>!</p>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>