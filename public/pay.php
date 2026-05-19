<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

//must be logged in as buyer
if(!isset($_SESSION['user_id'])){
header('Location: /login.php');
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id){
    header('Location: /checkout.php');
}

$env = parse_ini_file(__DIR__ . '/../.env');
$paystackPublicKey = $env['PAYSTACK_PUBLIC_KEY'];

//fetch order with listings title and buyer email
//buyer_id session prevents buyer from loading someone else's order 
$stmt = $conn->prepare("
SELECT o.*, l.title AS listing_title, u.email AS buyer_email
FROM orders o
JOIN listings l ON o.listing_id = l.id
JOIN users u ON o.buyer_id = u.id 
WHERE o.id = ? AND o.buyer_id = ?
");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order){
    header('Location: checkout.php');
    exit;
}

//only allow payments on pending ordrs
//prevents paying twice for a completed order
if ($order['status'] !== 'pending'){
    header('Location: order-confirmed.php?order_id=' . $order_id);
    exit();
}

$amoutnInCents = (int)($order['total_price'] * 100);
?>

<?php require_once '../includes/header.php' ?>

<div class="payment-page">
    <div class="payment-col">

    <div class="step-bar">
            <div class="step">
                <div class="step-circle step-circle--muted">1</div>
                <span class="step-label step-label--muted">Cart</span>
            </div>
            <div class="step-divider"></div>
            <div class="step">
                <div class="step-circle step-circle--muted">2</div>
                <span class="step-label step-label--muted">Checkout</span>
            </div>
            <div class="step-divider"></div>
            <div class="step">
                <div class="step-circle">3</div>
                <span class="step-label">Payment</span>
            </div>
            <div class="step-divider"></div>
            <div class="step">
                <div class="step-circle step-circle--muted">4</div>
                <span class="step-label step-label--muted">Confirmed</span>
            </div>
        </div>

        <!-- Order Summary --> 
         <div class="summary-card">
            <p class="summary-card--label">Order Summary</p>
            <div class="summary-row">
                <span class="summary-row--key">Book</span>
                <span class="summary-row--val">
                    <?= htmlspecialchars($order['listing_title']) ?>
                </span>
            </div>

            <div class="summary-row">
                <span class="summary-row--key">Campus</span>
                <span class="summary-row--val">
                    <?= htmlspecialchars($order['campus']) ?>
                </span>
            </div>

            <div class="summary-row">
                <span class="summary-row--key">Preferred Time</span>
                <span class="summary-row--val">
                    <?= htmlspecialchars($order['preferred_time']) ?>
                </span>
            </div>

            <div class="summary-row">
                <span class="summary-row--key">Order</span>
                <span class="summary-row--val">
                    <?= str_pad($order_id, 5, '0', STR_PAD_LEFT) ?>
                </span>
            </div>

            <div class="summary-row">
                <span class="summary-row--key">Total</span>
                <span class="summary-row--val">
                    <?= number_format($order['total_price'], 2) ?>
                </span>
            </div>
         </div>

         <!--- Security Notice --->
         <div class="safety-notice">
            <p class="saftey-notice--title">
                <i class="bi bi-shield-check"></i> Secure Payment</p>
                Your card details go directly to Paystack. Booked never seems them.
         </div>

         <!--- Paystack JS library---> 
         <script src="https://js.paystack.co/v1/inline.js"></script>

         <button type="button" class="pay-btn" onclick="payWithPaystack()">
            Pay R<?= number_format($order['total_price'], 2) ?>
         </button>

         <a href="checkout.php" class="pay-btn pay-btn--outlin text-center mt-2">
            Back to Checkout.
         </a>

    </div>
</div>

<script>
    function payWithPaystack(){
        var handler= PaystackPop.setup({

        key: '<?= $paystackPublicKey ?>',

        //buyers email
        email: '<?= htmlspecialchars($order['buyer_email']) ?>',

        amount: <?= $amoutnInCents ?>,

        currency: 'ZAR',

        //unique reference
        //order_id makes it traceable in paystack dashboard
        //random suffix prevents duplicate refs on retry
        ref: 'BOOKED_<?= $order_id ?>_' + Math.floor(Math.random() * 1000000),

        metadata:{
            custom_fields:[
                {
                    display_name: "Book",
                    variable_name: "listing_title",
                    value: "<?= htmlspecialchars($order['listing_title']) ?>"
                },
                {
                    display_name: "Order ID",
                    variable_name: "order_id",
                    value: "<?= $order_id ?>"
                }
            ]
        },

        //paystack confirmed on user end
        //still verify server-side in payment_success.php
        //before updating anything in DB
        callback: function(response){
            window.location.href = 'payment_success.php'
            + '?reference=' + response.reference
            + '&order_id=<?= $order_id ?>';
        },

        //buyer closed popup - order stays pendng in DB
        //pay again to retry
        onClose: function(){
            alert('Payment cancelled. Click Pay to try again.');
        }
        });
        handler.openIframe();
    }
</script>