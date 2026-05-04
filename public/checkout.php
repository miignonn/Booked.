<?php 
require_once '../includes/header.php';
require_once '../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$error = '';

$user_id = $_SESSION['user_id'];
//fetch user details for pre-filling
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();


//fetch cart items
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
$total = array_sum(array_column($cart_items,'price'));


if ($_SERVER['REQUEST_METHOD'] == 'POST'){
    $campus = trim($_POST['campus']);
    $preferred_time = trim($_POST['preferred_time']);

    //validate
    if(empty($campus) || empty($preferred_time)){
        $error = "Please fill in all required fields";
    } else {

    $order_id = 0;
        //loop through cart and create orders
        foreach($cart_items as $item){
            $seller_id = $item['user_id'];
            $listing_id = $item['id'];
            $seller_email = $item['seller_email'];
            $price = $item['price'];

            $order_stmt = $conn->prepare("INSERT INTO orders (listing_id, buyer_id, seller_id, total_price, campus, preferred_time, seller_email, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            $order_stmt->bind_param("iiidsss", $listing_id, $user_id, $seller_id, $price, $campus, $preferred_time, $seller_email);
            $order_stmt->execute();
            $order_id = $conn->insert_id;

            $sold_stmt = $conn->prepare("UPDATE listings SET status = 'sold' WHERE id = ?");
            $sold_stmt->bind_param("i", $listing_id);
            $sold_stmt->execute();

            $cart_stmt = $conn->prepare("DELETE FROM cart WHERE listing_id = ? AND user_id = ?");
            $cart_stmt->bind_param("ii", $listing_id, $user_id);
            $cart_stmt->execute();
        }
        header('Location: /order-confirmed.php?order_id=' . $order_id);
        exit();
    }
}

?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="d-flex align-items-center mb-5">
    <div class="d-flex align-items-center gap-2">
        <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center fw-bold"
             style="width:32px;height:32px;">1</div>
        <span class="fw-bold">Cart</span>
    </div>

    <div class="flex-grow-1 border-top mx-3"></div>
    <div class="d-flex align-items-center gap-2">
        <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center"
             style="width:32px;height:32px;">2</div>
        <span class="fw-bold">Checkout</span>
    </div>

    <div class="flex-grow-1 border-top mx-3"></div>
    <div class="d-flex align-items-center gap-2">
        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center"
             style="width:32px;height:32px;">3</div>
        <span class="text-muted">Confirmed</span>
    </div>

</div>

<form method="POST">
<div class="row g-4">

    <!-- LEFT: Collection Details -->
    <div class="col-md-6">
        <div class="bg-light rounded-3 p-4">
            <p class="text-muted small fw-bold mb-4 text-uppercase">Collection Details</p>

            <div class="mb-3">
                <label class="form-label fw-bold">Full Name</label>
                <input type="text" class="form-control" 
                    value="<?= htmlspecialchars($user['name']) ?>" disabled>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Student Email</label>
                <input type="email" class="form-control" 
                    value="<?= htmlspecialchars($user['email']) ?>" disabled>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Collection Campus <span class="text-danger">*</span></label>
                <input type="text" name="campus" class="form-control" 
                    placeholder="e.g. Eduvos Pretoria" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Preferred Collection Time <span class="text-danger">*</span></label>
                <input type="datetime-local" name="preferred_time" class="form-control" required>
            </div>
        </div>
    </div>

    <!-- RIGHT: Order Summary -->
    <div class="col-md-6">
        <div class="bg-light rounded-3 p-4 mb-3">
            <p class="text-muted small fw-bold mb-4 text-uppercase">Order Summary</p>

            <?php foreach ($cart_items as $item): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted"><?= htmlspecialchars($item['title']) ?></span>
                    <span class="fw-bold">R<?= number_format($item['price'], 2) ?></span>
                </div>
            <?php endforeach; ?>

            <hr>
            <div class="d-flex justify-content-between">
                <span class="fw-bold">Total</span>
                <span class="fw-bold fs-5">R<?= number_format($total, 2) ?></span>
            </div>
        </div>

        <button type="submit" class="btn btn-dark w-100 mb-2">Place Order</button>
        <a href="/cart.php" class="btn btn-outline-secondary w-100">Back to Cart</a>
    </div>

</div>
</form>

<?php require_once '../includes/footer.php'; ?>

            