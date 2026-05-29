<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

//run expiry check once per session
if (!isset($_SESSION['expiry_checked'])){
    expire_old_listings($conn);
    $_SESSION['expiry_checked'] = true;
}

// New listings
$listings_sql = "SELECT listings.*, users.name AS seller_name, categories.name AS category_name
FROM listings
JOIN users ON listings.user_id = users.id
JOIN categories ON listings.category_id = categories.id
WHERE listings.status = 'available'
AND users.status != 'suspended'
ORDER BY listings.created_at DESC
LIMIT 8";
$result = $conn->query($listings_sql);

// Live stats
$total_listings = $conn->query("SELECT COUNT(*) as c FROM listings WHERE status = 'available'")->fetch_assoc()['c'];
$total_users    = $conn->query("SELECT COUNT(*) as c FROM users WHERE status = 'active'")->fetch_assoc()['c'];

// Categories with counts
$categories_sql = "SELECT categories.id, categories.name,
    COUNT(listings.id) as listing_count
    FROM categories
    LEFT JOIN listings ON listings.category_id = categories.id
    AND listings.status = 'available'
    GROUP BY categories.id, categories.name
    ORDER BY listing_count DESC";
$categories_result = $conn->query($categories_sql);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_GET['message']) && $_GET['message'] == 'logged_out'): ?>
    <div class="flash-alert flash-alert--success" role="alert">
        You have been logged out successfully.
        <button class="flash-alert__close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['status']) && $_SESSION['status'] === 'suspended'): ?>
    <div class="flash-alert flash-alert--danger" role="alert">
        <span><strong>Account suspended.</strong> You can browse and view listings, but you cannot create or edit listings until your suspension is lifted.</span>
        <button class="flash-alert__close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<!-- HERO -->
<section class="b-hero">
    <div class="b-hero__left">
        <p class="b-eyebrow">South Africa's student textbook marketplace</p>
        <h1 class="b-hero__heading">
            Buy Smart<br>
            Sell Easy<br>
            <span class="b-hero__highlight">Stay Booked.</span>
        </h1>
        <p class="b-hero__sub">Second-hand textbooks from students who've been there. Save money, pass it forward.</p>
        <div class="b-hero__actions">
            <a href="/browse.php" class="b-btn b-btn--primary">Browse the shelves →</a>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="/create-listing.php" class="b-btn b-btn--outline">Sell a book</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="b-hero__right">
        <img src="/assets/images/booked_logo.png" alt="Stack of textbooks" class="b-hero__books-img">
    </div>
</section>

<!-- NEW LISTINGS -->
<section class="b-section b-section--dark">
    <div class="b-section__inner">
        <div class="b-section__header">
            <div>
                <p class="b-eyebrow">Just added</p>
                <h2 class="b-section__title">New <em>listings</em></h2>
            </div>
            <a href="/browse.php" class="b-view-all">View all →</a>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <div class="b-book-grid">
                <?php while ($listing = $result->fetch_assoc()): ?>
                    <div class="b-book-card" onclick="window.location='/listing.php?id=<?= $listing['id'] ?>&from=home'">
                        <div class="b-book-card__cover">
                            <?php if ($listing['image']): ?>
                                <img src="/<?= htmlspecialchars($listing['image']) ?>"
                                     alt="<?= htmlspecialchars($listing['title']) ?>"
                                     class="b-book-card__img"
                                     loading="lazy">
                            <?php else: ?>
                                <div class="b-book-card__no-image">
                                    <i class="bi bi-book"></i>
                                </div>
                            <?php endif; ?>
                            <span class="b-book-card__cond b-book-card__cond--<?= match($listing['condition']) {
                                'new', 'like new' => 'new',
                                'good'            => 'good',
                                'fair'            => 'fair',
                                default           => 'poor'
                            } ?>"><?= ucfirst($listing['condition']) ?></span>
                        </div>
                        <div class="b-book-card__info">
                            <p class="b-book-card__subj"><?= htmlspecialchars($listing['category_name'] ?? '') ?></p>
                            <p class="b-book-card__title"><?= htmlspecialchars($listing['title']) ?></p>
                            <p class="b-book-card__author"><?= htmlspecialchars($listing['author'] ?? '') ?></p>
                            <div class="b-book-card__foot">
                                <span class="b-book-card__price">R<?= number_format($listing['price'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="b-empty">No listings yet. Be the first to <a href="/create-listing.php">sell a book</a>!</p>
        <?php endif; ?>
    </div>
</section>

<!-- SELL STRIP -->
<section class="b-sell">
    <div class="b-sell__inner">
        <div class="b-sell__text">
            <p class="b-sell__eyebrow">For sellers</p>
            <h2 class="b-sell__title">Your old textbooks are<br>someone's <em>lifeline.</em></h2>
            <p class="b-sell__body">List in under two minutes. Set your own price. Connect directly with students at your campus.</p>
            <div class="b-sell__steps">
                <div class="b-sell__step">
                    <span class="b-sell__step-num">01</span>
                    <span class="b-sell__step-lbl">Take a photo</span>
                </div>
                <div class="b-sell__step">
                    <span class="b-sell__step-num">02</span>
                    <span class="b-sell__step-lbl">Set a price</span>
                </div>
                <div class="b-sell__step">
                    <span class="b-sell__step-num">03</span>
                    <span class="b-sell__step-lbl">Get paid</span>
                </div>
            </div>
        </div>
        <a href="/create-listing.php" class="b-btn b-btn--straw">List a book now →</a>
    </div>
</section>

<!-- CATEGORIES -->
<section class="b-section b-section--paper">
    <div class="b-section__inner">
        <div class="b-section__header">
            <div>
                <p class="b-eyebrow">Browse by subject</p>
                <h2 class="b-section__title">Find your <em>faculty</em></h2>
            </div>
        </div>
        <div class="b-cat-grid">
            <?php foreach ($categories as $i => $cat): ?>
                <a href="/browse.php?category=<?= $cat['id'] ?>" class="b-cat-item">
                    <span class="b-cat-item__name"><?= htmlspecialchars($cat['name']) ?></span>
                    <span class="b-cat-item__count"><?= $cat['listing_count'] ?> listing<?= $cat['listing_count'] != 1 ? 's' : '' ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once '../includes/footer.php'; ?>