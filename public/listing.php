<?php
require_once '../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

//handle report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_reason'])) {
    verify_csrf();

    $listing_id = (int)$_POST['listing_id'];
    $reason = trim($_POST['report_reason']);
    $reported_by = $_SESSION['user_id'];

    //check if user is not reportin their own listing
    $check_own = $conn->prepare("SELECT user_id FROM listings WHERE id =?");
    $check_own->bind_param("i", $listing_id);
    $check_own->execute();
    $listing_owner = $check_own->get_result()->fetch_assoc();

    if($listing_owner['user_id'] == $reported_by){
        $report_error = "You cannot report your own listing";

    } else {
        //check if already reported
        $check_dup = $conn->prepare("SELECT id FROM reports WHERE listing_id = ? AND reported_by = ?");
        $check_dup->bind_param("ii", $listing_id, $reported_by);
        $check_dup->execute();
        $check_dup->store_result();

        if($check_dup->num_rows > 0){
            $report_error = "You have already reported this listing";
        } else {
            $stmt = $conn->prepare("INSERT INTO reports (listing_id, reported_by, reason) VALUES (?,?,?)");
            $stmt->bind_param("iis", $listing_id, $reported_by, $reason);
            if ($stmt->execute()){
                $report_success = "Thank you. Your report has been submitted for review.";
            } else {
                $report_error = "Something went wrong. Please try again.";
            }
        }
    }
}
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id == 0){
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
WHERE listings.id = ? AND listings.status = 'active'
");

$stmt->bind_param("i", $id);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();

if (!$listing){
    header('Location: /index.php');
    exit();
}

//fetch all images for this listing
$img_stmt = $conn->prepare("SELECT image_path FROM listing_images WHERE listing_id = ?");
$img_stmt->bind_param("i", $id);
$img_stmt->execute();
$images = $img_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$from = isset($_GET['from']) ? $_GET['from'] : 'home'; 
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

<div class="row g-5">

   
    <div class="col-md-6">
        <?php if (!empty($images)): ?>
            <img src="/<?= htmlspecialchars($images[0]['image_path']) ?>" 
                 class="img-fluid rounded-3 w-100" style="max-height:480px;object-fit:cover;"
                 id="main-image">
            <?php if (count($images) > 1): ?>
                <div class="d-flex gap-2 mt-2">
                    <?php foreach ($images as $img): ?>
                        <img src="/<?= htmlspecialchars($img['image_path']) ?>"
                             onclick="document.getElementById('main-image').src=this.src"
                             class="rounded-2" style="width:70px;height:70px;object-fit:cover;cursor:pointer;border:2px solid transparent;"
                             onmouseover="this.style.borderColor='#000'" 
                             onmouseout="this.style.borderColor='transparent'">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="bg-light rounded-3 d-flex align-items-center justify-content-center" 
                 style="height:480px;">
                <i class="bi bi-book fs-1 text-muted"></i>
            </div>
        <?php endif; ?>

       
        <div class="d-flex align-items-center gap-3 mt-4 pt-3 border-top">
            <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                 style="width:48px;height:48px;">
                <?= strtoupper(substr($listing['seller_username'], 0, 1)) ?>
            </div>
            <div>
                <p class="fw-bold mb-0"><?= htmlspecialchars($listing['seller_username']) ?></p>
                <p class="text-muted small mb-0"><?= htmlspecialchars($listing['seller_institution']) ?></p>
            </div>
        </div>
    </div>

    
    <div class="col-md-6">
        <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> Added to cart! <a href="/cart.php">View cart</a>
        </div>
       <?php endif; ?>
    
       <?php if (isset($report_success)) :?>
        <div class="alert alert-success mt-3">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($report_success) ?>
        </div>
        <?php endif; ?>
        <?php if (isset($report_error)) :?>
            <div class="alert alert-danger mt-3">
                <?= htmlspecialchars($report_error) ?>
        </div>
        <?php endif; ?>
    

    <p class="text-muted small mb-1 fw-semibold"><?= htmlspecialchars($listing['category_name']) ?></p>
        <h2 class="fw-bold"><?= htmlspecialchars($listing['title']) ?></h2>
        <p class="text-muted"><?= htmlspecialchars($listing['author']) ?></p>
        <h3 class="fw-bold mt-3">R<?= number_format($listing['price'], 2) ?></h3>

        <div class="mt-4">
            <div class="d-flex justify-content-between border-bottom py-2">
                <span class="text-muted">Condition</span>
                <span class="fw-bold"><?= ucfirst(htmlspecialchars($listing['condition'])) ?></span>
            </div>
            <div class="d-flex justify-content-between border-bottom py-2">
                <span class="text-muted">Institution</span>
                <span class="fw-semibold"><?= htmlspecialchars($listing['institution']) ?></span>
            </div>
            <div class="d-flex justify-content-between border-bottom py-2">
                <span class="text-muted">ISBN</span>
                <span class="fw-semibold"><?= htmlspecialchars($listing['isbn']) ?></span>
            </div>
            <div class="d-flex justify-content-between py-2">
                <span class="text-muted">Listed</span>
                <span class="fw-semibold"><?= date('d M Y', strtotime($listing['created_at'])) ?></span>
            </div>
        </div>

        <?php if ($listing['description']): ?>
            <p class="mt-3 text-muted"><?= nl2br(htmlspecialchars($listing['description'])) ?></p>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
            <form method="POST" action="/cart.php">
           <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">
           <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <button type="submit" class="btn btn-dark w-100 mt-4">
            <i class="bi bi-cart-plus"></i> Add to Cart
        </button>
        </form>
        <?php else: ?>
        <div class="alert alert-dark mt-4">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-cart fs-3"></i>
                <div>
                    <p class="fw-bold mb-1">Books don't add themselves.</p>
                    <p class="small mb-2">Looks like you're browsing as a guest. Join thousands of students already saving on textbooks.</p>
                    <a href="/login.php" class="btn btn-dark btn-sm me-2">Login</a>
                    <a href="/login.php?tab=register" class="btn btn-dark btn-sm">Register - it's free!</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== $listing['user_id']): ?>
            <button class="btn btn-link text-muted text-decoration-none mt-2 w-100"
                data-bs-toggle="modal" data-bs-target="#reportModal">
                <i class="bi bi-flag"></i> Report this Listing
            </button>
        <?php endif; ?>
    </div>


<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Report this Listing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Help keep Booked. safe. Let us know what's wrong with this listing.</p>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">

                    <div class="mb-3">
                        <label class="form-lable fw-bold">Reason <span class="text-danger">*</span></label>
                        <select name="report_reason" class="form-select" required>
                            <option value="">Select a reason</option>
                            <option value="Suspected scam">Suspected spam</option>
                            <option value="Incorrect Information">Incorrect information</option>
                            <option value="Inappropriate content">Inappropriate content</option>
                            <option value="Already sold">Already sold/unavailable</option>
                            <option value="Duplicate Listing">Duplicate listing</option>
                            <option value="Other">Other</option>

                        </select>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancel
                        </button>
                        <button type="submit" class="btn btn-danger">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
        

<?php require_once '../includes/footer.php'; ?>

