<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$user_id = $_SESSION['user_id'];

//handle add to cart
//check if form was submitted
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['listing_id'])){
    $listing_id = (int)$_POST['listing_id'];


    //check if already in cart
    $check = $conn->prepare("SELECT id FROM cart WHERE user_id = ? AND listing_id =?");
    $check->bind_param("ii", $user_id, $listing_id);
    $check->execute();
    $check->store_result();

    if($check->num_rows == 0){
        $insert = $conn->prepare("INSERT INTO cart (user_id, listing_id) VALUES (?,?)");
        $insert->bind_param("ii", $user_id, $listing_id);
        $insert->execute();
    }
    header('Location: /listing.php?id=' . $listing_id . '&added=1');
    exit();
}

//handle remove from cart
if(isset($_GET['remove'])){
    $remove_id = (int)$_GET['remove'];
    $del = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $del->bind_param("ii", $remove_id, $user_id);
    $del->execute();
    header('Location: /cart.php');
    exit();
}

//fetch cart items
$stmt = $conn->prepare("
SELECT cart.id AS cart_id, listings.*, users.email AS seller_email
FROM cart
JOIN listings on cart.listing_id = listings.id
JOIN users ON listings.user_id = users.id

WHERE cart.user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total = array_sum(array_column($cart_items, 'price'));

?>

<!--Progress bar-->
<div class="d-flex align-items-center mb-5">
    <div class="d-flex align-items-center gap-2">
        <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center fw-bold"
             style="width:32px;height:32px;">1</div>
        <span class="fw-bold">Cart</span>
    </div>

    <div class="flex-grow-1 border-top mx-3"></div>
    <div class="d-flex align-items-center gap-2">
        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center"
             style="width:32px;height:32px;">2</div>
        <span class="text-muted">Checkout</span>
    </div>

    <div class="flex-grow-1 border-top mx-3"></div>
    <div class="d-flex align-items-center gap-2">
        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center"
             style="width:32px;height:32px;">3</div>
        <span class="text-muted">Confirmed</span>
    </div>

</div>


<?php if (empty($cart_items)): ?>
   <div class="text-center py-5">
    <i class="bi bi-book fs-1 text-muted mb-3 d-block"></i>
    <h5 class="fw-bold">Your shelf is empty</h5>
    <p class="text-muted">Add some textbooks and get ready for the semester!</p>
    <a href="/browse.php" class="btn btn-dark mt-2">Start Browsing</a>
   </div>
<?php else: ?>

<p class="text-muted mb-4"><?= count($cart_items) ?> book<?= count($cart_items) > 1 ? 's' : '' ?> — review before checkout.</p>

<div class="row g-4">

    <!-- LEFT: Cart Items -->
    <div class="col-md-7">
        <div class="bg-light rounded-3 p-4">
            <p class="text-muted small fw-bold mb-3 text-uppercase">Books in your cart</p>
            <?php foreach ($cart_items as $item): ?>
                <div class="bg-white rounded-3 p-3 mb-3 d-flex align-items-center gap-3">
                    
                    <!-- Image -->
                    <div style="width:70px;height:70px;flex-shrink:0;">
                        <?php if ($item['image']): ?>
                            <img src="/<?= htmlspecialchars($item['image']) ?>"
                                 class="rounded-2 w-100 h-100" style="object-fit:cover;">
                        <?php else: ?>
                            <div class="bg-light rounded-2 w-100 h-100 d-flex align-items-center justify-content-center">
                                <i class="bi bi-book text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Info -->
                    <div class="flex-grow-1">
                        <p class="fw-bold mb-1"><?= htmlspecialchars($item['title']) ?></p>
                        <p class="text-muted small mb-1"><?= htmlspecialchars($item['author']) ?></p>
                        <span class="badge bg-<?= match($item['condition']) {
                            'new' => 'success',
                            'like new' => 'success',
                            'good' => 'info',
                            'fair' => 'warning',
                            'poor' => 'danger',
                            default => 'secondary'
                        } ?>"><?= ucfirst($item['condition']) ?></span>
                    </div>

                    <!-- Price & Remove -->
                    <div class="text-end">
                        <p class="fw-bold mb-2">R<?= number_format($item['price'], 2) ?></p>
                       <button onclick="confirmRemove(<?= $item['cart_id'] ?>)" class="btn btn-link text-danger p-0">
                         <i class="bi bi-trash"></i>
                    </button>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- RIGHT: Order Summary -->
    <div class="col-md-5">
        <div class="bg-light rounded-3 p-4 mb-3">
            <p class="text-muted small fw-bold mb-3 text-uppercase">Order Summary</p>
            <?php foreach ($cart_items as $item): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small"><?= htmlspecialchars($item['title']) ?></span>
                    <span class="fw-bold small">R<?= number_format($item['price'], 2) ?></span>
                </div>
            <?php endforeach; ?>
            <hr>
            <div class="d-flex justify-content-between">
                <span class="fw-bold">Total</span>
                <span class="fw-bold fs-5">R<?= number_format($total, 2) ?></span>
            </div>
        </div>

        <a href="/checkout.php" class="btn btn-dark w-100 mb-2">Proceed to Checkout</a>
        <a href="/browse.php" class="btn btn-outline-secondary w-100">Continue Browsing</a>
    </div>

</div>

<?php endif; ?>
<div class="modal fade" id="removeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3">
            <div class="modal-body text-center p-4">
                <i class="bi bi-trash fs-1 text-danger"></i>
                <h5 class="fw-bold mt-3">Remove this book?</h5>
                <p class="text-muted">It will be removed from your cart.</p>
                <div class="d-flex gap-2 justify-content-center mt-3">
                    <button type="button" class="btn btn-outline-secondary" 
                        data-bs-dismiss="modal">Cancel</button>
                    <a id="remove-link" href="#" class="btn btn-danger">Yes, Remove</a>
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