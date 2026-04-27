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

<div class="container mt-4">
    <?php if (isset($_GET['message']) && $_GET['message'] == 'logged_out'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            You have been logged out successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
    <h4 class="fw-bold mb-4">New Listings</h4>
    <div class="row g-3"

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($listing =$result -> fetch_assoc()): ?>
            <div class="col-md-4 col-sm-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="card-title fw-bold"><?=  htmlspecialchars($listing['title']) ?></h6> 
                        <p class="text-muted small mb-1"><?=  htmlspecialchars($listing['author']) ?></p>
                        <p class="text-muted small mb-2">Condition: <?=  htmlspecialchars($listing['condition']) ?></p>
                        <p class="fw-bold text-dark">R<?= number_format($listing['price'], 2) ?></p>
                        <a href="/listing.php?id=<?= $listing['id'] ?>" class="btn btn-dark btn-sm w-100">View</a>
                    </div>
                </div>
            </div>

            <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <p class="text-muted">No listings available yet. Be the first to <a href="/create-listing.php"> sell a book</a>!</p>
                </div>
                <?php endif; ?>
            </div>
            </div>

<?php require_once '../includes/footer.php'; ?>