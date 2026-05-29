<?php
require_once __DIR__ .'/../includes/auth_check.php';
require_once __DIR__. '/../config/db.php';
require_once __DIR__ .'/../includes/functions.php';

$user_id = $_SESSION['user_id'];

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['listing_id'])) {
    verify_csrf();
    $listing_id = (int)$_POST['listing_id'];

    // Prevent adding own listing
    $owner = $conn->prepare("SELECT user_id FROM listings WHERE id = ?");
    $owner->bind_param("i", $listing_id);
    $owner->execute();
    $owner_row = $owner->get_result()->fetch_assoc();

    if ($owner_row && (int)$owner_row['user_id'] === (int)$user_id) {
        set_flash('danger', 'You cannot add your own listing to cart.');
        header('Location: /listing.php?id=' . $listing_id);
        exit();
    }

    // Check if already in cart
    $check = $conn->prepare("SELECT id FROM cart WHERE user_id = ? AND listing_id = ?");
    $check->bind_param("ii", $user_id, $listing_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows == 0) {
        $insert = $conn->prepare("INSERT INTO cart (user_id, listing_id) VALUES (?,?)");
        $insert->bind_param("ii", $user_id, $listing_id);
        $insert->execute();

        // Lock the listing so no one else can add it
        $lock = $conn->prepare("UPDATE listings SET status = 'pending' WHERE id = ? AND status = 'available'");
        $lock->bind_param("i", $listing_id);
        $lock->execute();
    }

    set_flash('success', 'Item added to cart!');
    header('Location: /listing.php?id=' . $listing_id . '&added=1');
    exit();
}

// Handle remove from cart
if (isset($_GET['remove'])) {
    $remove_id = (int)$_GET['remove'];

    // Get listing_id before deleting
    $get = $conn->prepare("SELECT listing_id FROM cart WHERE id = ? AND user_id = ?");
    $get->bind_param("ii", $remove_id, $user_id);
    $get->execute();
    $cart_row = $get->get_result()->fetch_assoc();

    $del = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $del->bind_param("ii", $remove_id, $user_id);
    $del->execute();

    // Restore listing to available
    if ($cart_row) {
        $restore = $conn->prepare("UPDATE listings SET status = 'available' WHERE id = ?");
        $restore->bind_param("i", $cart_row['listing_id']);
        $restore->execute();
    }

    set_flash('success', 'Item removed from cart.');
    header('Location: /cart.php');
    exit();
}

// Fetch cart items
$stmt = $conn->prepare("
    SELECT cart.id AS cart_id, listings.*, users.email AS seller_email
    FROM cart
    JOIN listings ON cart.listing_id = listings.id
    JOIN users ON listings.user_id = users.id
    WHERE cart.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total = array_sum(array_column($cart_items, 'price'));

require_once __DIR__. '/../includes/header.php';
?>

<!--Step bar-->
<div class="step-bar">
    <div class="step">
        <div class="step-circle">1</div>
        <span class="step-label">Cart</span>
    </div>
    <div class="step-divider"></div>
    <div class="step">
        <div class="step-circle step-circle--muted">2</div>
        <span class="step-label step-label--muted">Checkout</span>
    </div>
    <div class="step-divider"></div>
    <div class="step">
        <div class="step-circle step-circle--muted">3</div>
        <span class="step-label step-label--muted">Confirmed</span>
    </div>
</div>

<?php if (empty($cart_items)): ?>
    <div class="cart__empty">
        <i class="bi bi-book cart__empty-icon"></i>
        <h5 class="cart__empty-title">Your shelf is empty</h5>
        <p class="cart__empty-sub">Add some textbooks and get ready for the semester!</p>
        <a href="/browse.php" class="btn-checkout">Start Browsing</a>
    </div>
<?php else: ?>
    <p class="cart__count"><?= count($cart_items) ?> book<?= count($cart_items) > 1 ? 's' : '' ?> — review before checkout.</p>

    <div class="cart-layout">

        <!-- Left: Cart items -->
        <div class="cart-section">
            <p class="cart-section__label">Books in your cart</p>

            <?php foreach ($cart_items as $item): ?>
                <div class="cart-item">

                    <?php if ($item['status'] !== 'available' && $item['status'] !== 'pending'): ?>
                        <div class="form-error cart-item__unavailable">
                            This listing is no longer available and cannot be checked out.
                        </div>
                    <?php endif; ?>

                    <div class="cart-item__thumb">
                        <?php if ($item['image']): ?>
                            <img src="/<?= htmlspecialchars($item['image']) ?>" alt="">
                        <?php else: ?>
                            <i class="bi bi-book cart-item__no-image"></i>
                        <?php endif; ?>
                    </div>

                    <div class="cart-item__info">
                        <p class="cart-item__title"><?= htmlspecialchars($item['title']) ?></p>
                        <p class="cart-item__author"><?= htmlspecialchars($item['author']) ?></p>
                        <span class="condition-badge condition-badge--<?= match($item['condition']) {
                            'new'      => 'success',
                            'like new' => 'success',
                            'good'     => 'info',
                            'fair'     => 'warning',
                            'poor'     => 'danger',
                            default    => 'secondary'
                        } ?>"><?= ucfirst($item['condition']) ?></span>
                    </div>

                    <div class="cart-item__price-col">
                        <p class="cart-item__price">R<?= number_format($item['price'], 2) ?></p>
                        <button onclick="confirmRemove(<?= $item['cart_id'] ?>)" class="cart-item__remove">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

        <!-- Right: Order summary -->
        <div class="cart-sidebar">
            <div class="cart-section">
                <p class="cart-section__label">Order Summary</p>

                <?php foreach ($cart_items as $item): ?>
                    <div class="cart-summary__row">
                        <span class="cart-summary__label"><?= htmlspecialchars($item['title']) ?></span>
                        <span class="cart-summary__val">R<?= number_format($item['price'], 2) ?></span>
                    </div>
                <?php endforeach; ?>

                <hr class="cart-summary__divider">

                <div class="cart-summary__total">
                    <span class="cart-summary__total-label">Total</span>
                    <span class="cart-summary__total-val">R<?= number_format($total, 2) ?></span>
                </div>
            </div>

            <?php $has_unavailable = array_filter($cart_items, fn($i) => $i['status'] !== 'available' && $i['status'] !== 'pending'); ?>
            <?php if ($has_unavailable): ?>
                <button class="btn-checkout btn-checkout--disabled" disabled>
                    Remove unavailable items first
                </button>
            <?php else: ?>
                <a href="/checkout.php" class="btn-checkout">Proceed to Checkout</a>
            <?php endif; ?>
            <a href="/browse.php" class="btn-browse">Continue Browsing</a>
        </div>

    </div>
<?php endif; ?>

<!-- Remove confirmation modal -->
<div class="modal fade" id="removeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3">
            <div class="cart-modal__body">
                <i class="bi bi-trash cart-modal__icon"></i>
                <h5 class="cart-modal__title">Remove this book?</h5>
                <p class="cart-modal__sub">It will be removed from your cart.</p>
                <div class="cart-modal__actions">
                    <button type="button" class="btn-browse" data-bs-dismiss="modal">Cancel</button>
                    <a id="remove-link" href="#" class="btn-danger-action">Yes, Remove</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmRemove(cartId) {
    document.getElementById('remove-link').href = '/cart.php?remove=' + cartId;
    new bootstrap.Modal(document.getElementById('removeModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>