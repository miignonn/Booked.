<?php 
require_once '../includes/header.php'; 
require_once __DIR__ . '/../config/db.php';

$sql = "SELECT listings.*, users.name AS seller_name 
FROM listings
JOIN users ON listings.user_id = users.id 
WHERE listings.status = 'active'
ORDER BY listings.created_at DESC";

$result = $conn->query($sql);
?>

<div>
    <?php if (isset($_GET['message']) && $_GET['message'] == 'logged_out'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            You have been logged out successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <header>
        <div class="left">
        <h1>Buy Smart</h1>
        <h1>Sell Easy</h1>
        <h1>Stay Booked.</h1>
        <p class="sub-heading">Student Textbook Marketplace South Africa.
        </p>

        <div class="buttons">
            <a href="/browse.php" class="btn btn-dark">Browse the shelves
                <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>

    <div class="right">
        <img src="assets/images/logo.png" alt="" />
    </div>

    <div class="bg-color"></div>
    
    </header>


    <h4 class="fw-bold mb-3">New Listings</h4>
    <div class="listings-scroll d-flex gap-3 pb-3">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($listing = $result->fetch_assoc()): ?>
                <div class="listing-card flex-shrink-0" onclick="window.location='/listing.php?id=<?= $listing['id'] ?>&from=home'">

                    <div class="listing-img-wrap">
                        <?php if ($listing['image']): ?>
                            <img src="/<?= htmlspecialchars($listing['image']) ?>" alt="<?= htmlspecialchars($listing['title']) ?>">
                        <?php else: ?>
                            <div class="no-image">
                                <i class="bi bi-book fs-1 text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="listing-info">
                        <p class="listing-title"><?= htmlspecialchars($listing['title']) ?></p>
                        <p class="listing-author"><?= htmlspecialchars($listing['author']) ?></p>
                        <p class="listing-price">R<?= number_format($listing['price'], 2) ?></p>
                    </div>

                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-muted">No listings yet. Be the first to <a href="/create-listing.php">sell a book</a>!</p>
        <?php endif; ?>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>