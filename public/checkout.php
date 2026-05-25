<?php 
require_once __DIR__ .'/../includes/auth_check.php';
require_once __DIR__. '/../config/db.php';
require_once __DIR__ .'/../includes/functions.php';

$error = '';
$user_id = $_SESSION['user_id'];

$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

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

if ($_SERVER['REQUEST_METHOD'] == 'POST'){
    verify_csrf();
    $campus = trim($_POST['campus']);
    $preferred_time = trim($_POST['preferred_time']);

    if(empty($campus) || empty($preferred_time)){
        $error = "Please fill in all required fields";
    } else {
        // stock check - verify all items still available
        foreach($cart_items as $item){
            if ($item['status'] !== 'available'){
                $error = "\"" . htmlspecialchars($item['title']) . "\" is no longer available. Please remove it from your cart.";
                break;
            }
        }

        if (!$error){
            $order_id = 0;
            foreach($cart_items as $item){
                $seller_id    = $item['user_id'];
                $listing_id   = $item['id'];
                $seller_email = $item['seller_email'];
                $price        = $item['price'];

                $order_stmt = $conn->prepare("
                    INSERT INTO orders
                    (listing_id, buyer_id, seller_id, total_price, campus, preferred_time, seller_email, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $order_stmt->bind_param("iiidsss", $listing_id, $user_id, $seller_id, $price, $campus, $preferred_time, $seller_email);
                $order_stmt->execute();

                if ($order_stmt->error){
                    $error = "Order failed: " . $order_stmt->error;
                    break;
                }
                $order_id = $conn->insert_id;

                $lock = $conn->prepare("UPDATE listings SET status = 'pending' WHERE id = ?");
                $lock->bind_param("i", $listing_id);
                $lock->execute();

                $clear = $conn->prepare("DELETE FROM cart WHERE listing_id = ? AND user_id = ?");
                $clear->bind_param("ii", $listing_id, $user_id);
                $clear->execute();
            }

            if (!$error) {
                header('Location: /order-confirmed.php?order_id=' . $order_id);
                exit();
            }
        }
    }
}


require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($error): ?>
    <div class="form-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Step bar -->
<div class="step-bar">
    <div class="step">
        <div class="step-circle step-circle--muted">1</div>
        <span class="step-label step-label--muted">Cart</span>
    </div>
    <div class="step-divider"></div>
    <div class="step">
        <div class="step-circle">2</div>
        <span class="step-label">Checkout</span>
    </div>
    <div class="step-divider"></div>
    <div class="step">
        <div class="step-circle step-circle--muted">3</div>
        <span class="step-label step-label--muted">Confirmed</span>
    </div>
</div>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

    <div class="cart-layout">

        <!-- LEFT: Collection details -->
        <div class="cart-section">
            <p class="cart-section__label">Collection Details</p>

            <div class="checkout-field">
                <label class="checkout-label">Full Name</label>
                <input type="text" class="form-control"
                    value="<?= htmlspecialchars($user['name']) ?>" disabled>
            </div>

            <div class="checkout-field">
                <label class="checkout-label">Student Email</label>
                <input type="email" class="form-control"
                    value="<?= htmlspecialchars($user['email']) ?>" disabled>
            </div>

            <div class="checkout-field">
                <label class="checkout-label">
                    Collection Campus <span class="checkout-required">*</span>
                </label>
                <input type="text" name="campus" class="form-control"
                    placeholder="e.g. Eduvos Pretoria" required>
            </div>

            <div class="checkout-field">
                <label class="checkout-label">
                    Preferred Collection Time <span class="checkout-required">*</span>
                </label>
                <input type="datetime-local" name="preferred_time" class="form-control" required>
            </div>
        </div>

        <!-- RIGHT: Order summary -->
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

            <button type="submit" class="btn-checkout">Place Order</button>
            <a href="/cart.php" class="btn-browse">Back to Cart</a>
        </div>

    </div>
</form>

<?php require_once '../includes/footer.php'; ?>