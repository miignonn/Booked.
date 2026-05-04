<?php

require_once '../includes/header.php';
require_once __DIR__ . '/../config/db.php';

//get filter values from URL
$filter_institution = isset($_GET['institution']) ? trim($_GET['institution']) : '';
$filter_category = isset($_GET['category']) ? trim($_GET['category']) : '';
$filter_condition = isset($_GET['condition']) ? trim($_GET['condition']) : '';
$filter_price_max = isset($_GET['price_max']) ? (float)$_GET['price_max'] : '';
$filter_edition = isset($_GET['edition']) ? trim($_GET['edition']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : '';

//build query dynamically
$where = ["listings.status = 'active'"];
$params = [];
$types = '';

if ($filter_institution) {
    $where[] = "listings.institution LIKE ?";
    $params[] = "%$filter_institution%";  // missing $ before filter_institution
    $types .= 's';
}

if($filter_category){
    $where[] = "listings.category_id = ?";
    $params[] = $filter_category;
    $types .= 'i';
}

if ($filter_condition){
    $where[] = "listings.condition = ?";
    $params[] = $filter_condition;
    $types .= 's';
}


if ($filter_price_max !== ''){
    $where[] = "listings.price <= ?";
    $params[] = $filter_price_max;
    $types .= 'd';
}

if($filter_edition){
    $where[] = "listings.edition LIKE ?";
    $params[] = "%$filter_edition%";
    $types .= 's';
}

$order = match($sort){
    'price_asc' => 'listings.price ASC',
    'price_desc' => 'listings.price DESC',
    default => 'listings.created_at DESC'
};

$sql = "SELECT listings.*, users.username AS seller_username, categories.name AS category_name
        FROM listings
        JOIN users ON listings.user_id = users.id
        JOIN categories ON listings.category_id = categories.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $order";

$stmt = $conn->prepare($sql);
if (!empty($params)){
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total = count($listings);

//fetch categories for filter dropdown
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC");

?>

<h6 class="fw-bold mb-3">Filter</h6>
<div class="d-flex gap-4">


    <!-- Filter Sidebar -->
    <div id="filter-sidebar" class="flex-shrink-0" style="width:240px;">
       <form method="GET" id="filter-form">

    <!-- Institution -->
    <div class="border-bottom pb-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2" 
             onclick="toggleSection('institution-options')" style="cursor:pointer;">
            <span class="fw-bold">Institution</span>
            <i class="bi bi-chevron-up" id="institution-icon"></i>
        </div>
        <div id="institution-options">
            <?php
            $institutions = $conn->query("SELECT DISTINCT institution FROM listings WHERE status='active' AND institution IS NOT NULL ORDER BY institution ASC");
            while ($inst = $institutions->fetch_assoc()):
            ?>
                <div class="form-check mb-1">
                    <input class="form-check-input" type="radio" name="institution" 
                           value="<?= htmlspecialchars($inst['institution']) ?>"
                           id="inst_<?= md5($inst['institution']) ?>"
                           <?= $filter_institution === $inst['institution'] ? 'checked' : '' ?>
                           onchange="this.form.submit()">
                    <label class="form-check-label" for="inst_<?= md5($inst['institution']) ?>">
                        <?= htmlspecialchars($inst['institution']) ?>
                    </label>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Faculty -->
    <div class="border-bottom pb-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2"
             onclick="toggleSection('faculty-options')" style="cursor:pointer;">
            <span class="fw-bold">Faculty</span>
            <i class="bi bi-chevron-up" id="faculty-icon"></i>
        </div>
        <div id="faculty-options">
            <?php while ($cat = $categories->fetch_assoc()): ?>
                <div class="form-check mb-1">
                    <input class="form-check-input" type="radio" name="category"
                           value="<?= $cat['id'] ?>"
                           id="cat_<?= $cat['id'] ?>"
                           <?= $filter_category == $cat['id'] ? 'checked' : '' ?>
                           onchange="this.form.submit()">
                    <label class="form-check-label" for="cat_<?= $cat['id'] ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </label>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Condition -->
    <div class="border-bottom pb-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2"
             onclick="toggleSection('condition-options')" style="cursor:pointer;">
            <span class="fw-bold">Condition</span>
            <i class="bi bi-chevron-up" id="condition-icon"></i>
        </div>
        <div id="condition-options">
            <?php foreach (['new' => 'New', 'like new' => 'Like New', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor'] as $val => $label): ?>
                <div class="form-check mb-1">
                    <input class="form-check-input" type="radio" name="condition"
                           value="<?= $val ?>"
                           id="cond_<?= str_replace(' ', '_', $val) ?>"
                           <?= $filter_condition === $val ? 'checked' : '' ?>
                           onchange="this.form.submit()">
                    <label class="form-check-label" for="cond_<?= str_replace(' ', '_', $val) ?>">
                        <?= $label ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Edition -->
    <div class="border-bottom pb-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2"
             onclick="toggleSection('edition-options')" style="cursor:pointer;">
            <span class="fw-bold">Edition</span>
            <i class="bi bi-chevron-up" id="edition-icon"></i>
        </div>
        <div id="edition-options">
            <input type="text" name="edition" class="form-control form-control-sm"
                placeholder="e.g. 3rd" value="<?= htmlspecialchars($filter_edition) ?>"
                onchange="this.form.submit()">
        </div>
    </div>

    <!-- Price -->
    <div class="pb-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2"
             onclick="toggleSection('price-options')" style="cursor:pointer;">
            <span class="fw-bold">Price</span>
            <i class="bi bi-chevron-up" id="price-icon"></i>
        </div>
        <div id="price-options">
            <label class="form-label small">Max: R<span id="price-max-val"><?= $filter_price_max ?: 1000 ?></span></label>
            <input type="range" class="form-range" name="price_max"
                min="0" max="1000" step="50" value="<?= $filter_price_max ?: 1000 ?>"
                oninput="document.getElementById('price-max-val').innerText=this.value">
            <button type="submit" class="btn btn-dark btn-sm w-100 mt-2">Apply</button>
        </div>
    </div>

    <?php if (!empty(array_filter([$filter_institution, $filter_category, $filter_condition, $filter_edition, $filter_price_max]))): ?>
        <a href="/browse.php" class="btn btn-outline-secondary btn-sm w-100">Clear Filters</a>
    <?php endif; ?>

</form>
    </div>

    <!-- Listings Grid -->
    <div class="flex-grow-1">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-0">All Listings</h5>
                <p class="text-muted small mb-0"><?= $total ?> books available</p>
            </div>
            <div>
                <select class="form-select form-select-sm" onchange="window.location='?sort='+this.value+'&<?= http_build_query(array_filter(['institution'=>$filter_institution,'faculty'=>$filter_category,'condition'=>$filter_condition,'edition'=>$filter_edition,'price_max'=>$filter_price_max])) ?>'">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Sort: Newest</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                </select>
            </div>
        </div>

        <!-- Cards Grid -->
        <?php if (empty($listings)): ?>
            <p class="text-muted">No listings found matching your filters.</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($listings as $listing): ?>
                    <div class="col-md-4 col-sm-6">
                        <div class="listing-card w-100" onclick="window.location='/listing.php?id=<?= $listing['id'] ?>&from=browse'">
                            <div class="listing-img-wrap w-100" style="height:240px;">
                                <?php if ($listing['image']): ?>
                                    <img src="/<?= htmlspecialchars($listing['image']) ?>"
                                         alt="<?= htmlspecialchars($listing['title']) ?>">
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
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// hide sidebar when filters are applied, show toggle button
const sidebar = document.getElementById('filter-sidebar');
const hasFilters = <?= !empty(array_filter([$filter_institution, $filter_category, $filter_condition, $filter_edition, $filter_price_max])) ? 'true' : 'false' ?>;


function toggleSection(id) {
    const section = document.getElementById(id);
    const sectionId = id.replace('-options', '');
    const icon = document.getElementById(sectionId + '-icon');
    section.classList.toggle('d-none');
    icon.classList.toggle('bi-chevron-up');
    icon.classList.toggle('bi-chevron-down');
}

</script>

<?php require_once '../includes/footer.php' ?>


