<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// handle report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_reason'])) {
    verify_csrf();

    $listing_id  = (int)$_POST['listing_id'];
    $reason      = trim($_POST['report_reason']);
    $reported_by = $_SESSION['user_id'];

    $check_own = $conn->prepare("SELECT user_id FROM listings WHERE id = ?");
    $check_own->bind_param("i", $listing_id);
    $check_own->execute();
    $listing_owner = $check_own->get_result()->fetch_assoc();

    if ($listing_owner['user_id'] == $reported_by) {
        $report_error = "You cannot report your own listing.";
    } else {
        $check_dup = $conn->prepare("SELECT id FROM reports WHERE listing_id = ? AND reported_by = ?");
        $check_dup->bind_param("ii", $listing_id, $reported_by);
        $check_dup->execute();
        $check_dup->store_result();

        if ($check_dup->num_rows > 0) {
            $report_error = "You have already reported this listing.";
        } else {
            $stmt = $conn->prepare("INSERT INTO reports (listing_id, reported_by, reason) VALUES (?,?,?)");
            $stmt->bind_param("iis", $listing_id, $reported_by, $reason);
            if ($stmt->execute()) {
                $report_success = "Thank you. Your report has been submitted for review.";
            } else {
                $report_error = "Something went wrong. Please try again.";
            }
        }
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id == 0) {
    header('Location: /index.php');
    exit();
}

$stmt = $conn->prepare("
    SELECT listings.*,
    users.username AS seller_username,
    users.institution AS seller_institution,
    categories.name AS category_name
    FROM listings
    JOIN users ON listings.user_id = users.id
    JOIN categories ON listings.category_id = categories.id
    WHERE listings.id = ? AND listings.status IN ('available', 'pending')
");
$stmt->bind_param("i", $id);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();

if (!$listing) {
    header('Location: /index.php');
    exit();
}

$img_stmt = $conn->prepare("SELECT image_path FROM listing_images WHERE listing_id = ?");
$img_stmt->bind_param("i", $id);
$img_stmt->execute();
$images = $img_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$from = isset($_GET['from']) ? $_GET['from'] : 'home';

require_once __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <?php if ($from === 'browse'): ?>
            <li class="breadcrumb-item"><a href="/browse.php">Browse</a></li>
        <?php else: ?>
            <li class="breadcrumb-item"><a href="/index.php">Home</a></li>
        <?php endif; ?>
        <li class="breadcrumb-item"><?= htmlspecialchars($listing['category_name']) ?></li>
        <li class="breadcrumb-item active"><?= htmlspecialchars($listing['title']) ?></li>
    </ol>
</nav>

<div class="listing-page">

    <!-- Left: images + seller -->
    <div class="listing-page__media">

        <?php if (!empty($images)): ?>
            <img src="/<?= htmlspecialchars($images[0]['image_path']) ?>"
                 class="listing-page__main-image" id="main-image">
            <?php if (count($images) > 1): ?>
                <div class="listing-page__thumbs">
                    <?php foreach ($images as $img): ?>
                        <img src="/<?= htmlspecialchars($img['image_path']) ?>"
                             class="listing-page__thumb"
                             onclick="document.getElementById('main-image').src=this.src">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="listing-page__no-image">
                <i class="bi bi-book"></i>
            </div>
        <?php endif; ?>

        <!-- Seller info -->
        <div class="listing-page__seller">
            <div class="listing-page__seller-avatar">
                <?= strtoupper(substr($listing['seller_username'], 0, 1)) ?>
            </div>
            <div>
                <p class="listing-page__seller-name"><?= htmlspecialchars($listing['seller_username']) ?></p>
                <p class="listing-page__seller-inst"><?= htmlspecialchars($listing['seller_institution']) ?></p>
            </div>
        </div>

    </div>

    <!-- Right: details + actions -->
    <div class="listing-page__details">

        <?php if (isset($report_success)): ?>
            <div class="listing-page__alert listing-page__alert--success">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($report_success) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($report_error)): ?>
            <div class="listing-page__alert listing-page__alert--danger">
                <?= htmlspecialchars($report_error) ?>
            </div>
        <?php endif; ?>

        <p class="listing-page__category"><?= htmlspecialchars($listing['category_name']) ?></p>
        <h2 class="listing-page__title"><?= htmlspecialchars($listing['title']) ?></h2>
        <p class="listing-page__author"><?= htmlspecialchars($listing['author']) ?></p>
        <p class="listing-page__price">R<?= number_format($listing['price'], 2) ?></p>

        <!-- Specs -->
        <div class="listing-page__specs">
            <div class="listing-page__spec-row">
                <span class="listing-page__spec-key">Condition</span>
                <span class="listing-page__spec-val"><?= ucfirst(htmlspecialchars($listing['condition'])) ?></span>
            </div>
            <div class="listing-page__spec-row">
                <span class="listing-page__spec-key">Institution</span>
                <span class="listing-page__spec-val"><?= htmlspecialchars($listing['institution']) ?></span>
            </div>
            <?php if (!empty($listing['edition'])): ?>
            <div class="listing-page__spec-row">
                <span class="listing-page__spec-key">Edition</span>
                <span class="listing-page__spec-val"><?= htmlspecialchars($listing['edition']) ?></span>
            </div>
            <?php endif; ?>
            <div class="listing-page__spec-row listing-page__spec-row--last">
                <span class="listing-page__spec-key">Listed</span>
                <span class="listing-page__spec-val"><?= date('d M Y', strtotime($listing['created_at'])) ?></span>
            </div>
        </div>

        <?php if ($listing['description']): ?>
            <p class="listing-page__description"><?= nl2br(htmlspecialchars($listing['description'])) ?></p>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
            <?php if ($listing['status'] === 'pending'): ?>
                <button class="b-btn b-btn--primary w-100" disabled style="opacity:0.5; cursor:not-allowed;">
                    No Longer Available
                </button>
            <?php else: ?>
                <form method="POST" action="/cart.php">
                    <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <button type="submit" class="b-btn b-btn--primary w-100">
                        <i class="bi bi-cart-plus"></i> Add to Cart
                    </button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <div class="listing-page__guest">
                <i class="bi bi-cart listing-page__guest-icon"></i>
                <div>
                    <p class="listing-page__guest-title">Books don't add themselves.</p>
                    <p class="listing-page__guest-sub">Looks like you're browsing as a guest. Join thousands of students already saving on textbooks.</p>
                    <div class="listing-page__guest-actions">
                        <a href="/login.php" class="b-btn b-btn--primary">Login</a>
                        <a href="/login.php?tab=register" class="b-btn b-btn--outline" style="color: var(--page); border-color: rgba(245,242,237,0.3);">Register — it's free!</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== $listing['user_id']): ?>
            <button class="listing-page__report-btn"
                    data-bs-toggle="modal" data-bs-target="#reportModal">
                <i class="bi bi-flag"></i> Report this Listing
            </button>
        <?php endif; ?>

    </div>
</div>

<!-- Report modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:0; border: 0.5px solid var(--border);">
            <div class="modal-header border-0">
                <h5 class="modal-title" style="font-family: var(--font-serif); font-weight:400;">Report this Listing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="listing-page__report-hint">Help keep Booked safe. Let us know what's wrong with this listing.</p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">
                    <div class="listing-field">
                        <label class="listing-label">Reason <span class="listing-required">*</span></label>
                        <select name="report_reason" class="form-select" required>
                            <option value="">Select a reason</option>
                            <option value="Suspected scam">Suspected scam</option>
                            <option value="Incorrect Information">Incorrect information</option>
                            <option value="Inappropriate content">Inappropriate content</option>
                            <option value="Duplicate Listing">Duplicate listing</option>
                            <option value="Price mismatch">Price does not match item condition</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="listing-page__report-actions">
                        <button type="button" class="b-btn b-btn--outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="b-btn b-btn--primary" style="background: var(--blush);">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>