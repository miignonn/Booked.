<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';


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
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search){
    $where[] = "(listings.title LIKE ? OR listings.author LIKE ?)";
    $params[] = "%$search%";
     $params[] = "%$search%";
     $types .= 'ss';


}
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

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Filter toggle button --> 
 <button class="browse-filter-toggle mb-3" onclick="openFilters()">
    <i class="bi bi-sliders"></i> Filters
</button>

<!-- Overlay backdrop --> 
 <div class="browse-overlay" id="browse-overlay" onclick="closeFilters()"></div>

<!--- Filter drawer ---> 
<div class="browse-drawer" id="browse-drawer">
    <div class="browse-drawer__head">
        <span class="fw-bold">Filters</span>
        <button onclick="closeFilters()" class="browse-drawer__close">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
 
    <form method="GET" id="filter-form">
<div class="browse-filter-group">
            <div class="browse-filter-group__header" onclick="toggleSection('institution-options', 'institution-icon')">
                <span class="browse-filter-group__label">Institution</span>
                <i class="bi bi-chevron-down" id="institution-icon"></i>
            </div>
            <div id="institution-options" class="d-none">
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
 
        <div class="browse-filter-group">
            <div class="browse-filter-group__header" onclick="toggleSection('faculty-options', 'faculty-icon')">
                <span class="browse-filter-group__label">Faculty</span>
                <i class="bi bi-chevron-down" id="faculty-icon"></i>
            </div>
            <div id="faculty-options" class="d-none">
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
 
        <div class="browse-filter-group">
            <div class="browse-filter-group__header" onclick="toggleSection('condition-options', 'condition-icon')">
                <span class="browse-filter-group__label">Condition</span>
                <i class="bi bi-chevron-down" id="condition-icon"></i>
            </div>
            <div id="condition-options" class="d-none">
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
 
        <div class="browse-filter-group">
            <div class="browse-filter-group__header" onclick="toggleSection('edition-options', 'edition-icon')">
                <span class="browse-filter-group__label">Edition</span>
                <i class="bi bi-chevron-down" id="edition-icon"></i>
            </div>
            <div id="edition-options" class="d-none">
                <input type="text" name="edition" class="form-control form-control-sm"
                       placeholder="e.g. 3rd"
                       value="<?= htmlspecialchars($filter_edition) ?>"
                       onchange="this.form.submit()">
            </div>
        </div>
 
        <div class="browse-filter-group" style="border-bottom:none;">
            <div class="browse-filter-group__header" onclick="toggleSection('price-options', 'price-icon')">
                <span class="browse-filter-group__label">Price</span>
                <i class="bi bi-chevron-down" id="price-icon"></i>
            </div>
            <div id="price-options" class="d-none">
                <label class="form-label small">
                    Max: R<span id="price-max-val"><?= $filter_price_max ?: 1000 ?></span>
                </label>
                <input type="range" class="form-range" name="price_max"
                       min="0" max="1000" step="50"
                       value="<?= $filter_price_max ?: 1000 ?>"
                       oninput="document.getElementById('price-max-val').innerText=this.value">
            </div>
        </div>
 
        <button type="submit" class="btn btn-dark btn-sm w-100 mt-3">Apply</button>
        <a href="/browse.php" class="btn btn-outline-secondary btn-sm w-100 mt-2">Clear Filters</a>
 
    </form>
</div>
 
<!--- Listings ---> 
<div class="browse-listings">
 
    <div class="browse-listings__header">
        <div>
            <h5 class="fw-bold mb-0">All Listings</h5>
            <p class="text-muted small mb-0">
                <?= $total ?> books available
                <?php if ($search): ?>
                    for "<strong><?= htmlspecialchars($search) ?></strong>"
                <?php endif; ?>
            </p>
        </div>
        <div class="browse-listings__controls">
            <form method="GET" class="browse-search-form">
                <div class="input-group">
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Search title or author"
                           value="<?= htmlspecialchars($search) ?>">
                    <span class="input-group-text bg-light border-light">
                        <i class="bi bi-search"></i>
                    </span>
                </div>
            </form>
            <select class="form-select form-select-sm browse-sort-select"
                    onchange="window.location='?sort='+this.value+'&<?= http_build_query(array_filter(['institution' => $filter_institution, 'category' => $filter_category, 'condition' => $filter_condition, 'edition' => $filter_edition, 'price_max' => $filter_price_max])) ?>'">
                <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Newest</option>
                <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Price: Low–High</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High–Low</option>
            </select>
        </div>
    </div>
 
    <?php if (empty($listings)): ?>
        <p class="text-muted mt-3">No listings found matching your filters.</p>
    <?php else: ?>
        <div class="browse-grid">
            <?php foreach ($listings as $listing): ?>
                <div class="listing-card"
                     onclick="window.location='/listing.php?id=<?= $listing['id'] ?>&from=browse'">
                    <div class="listing-card__image-wrap">
                        <?php if ($listing['image']): ?>
                            <img src="/<?= htmlspecialchars($listing['image']) ?>"
                                 alt="<?= htmlspecialchars($listing['title']) ?>">
                        <?php else: ?>
                            <div class="listing-card__no-image">
                                <i class="bi bi-book fs-1 text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="listing-card__info">
                        <p class="listing-card__title"><?= htmlspecialchars($listing['title']) ?></p>
                        <p class="listing-card__author"><?= htmlspecialchars($listing['author']) ?></p>
                        <p class="listing-card__price">R<?= number_format($listing['price'], 2) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
 
</div>
 
<script>

    function openFilters(){
    document.getElementById('browse-drawer');
    document.getElementById('browse-overlay');
    drawer.style.cssText = 'display: flex !important; flex-direction: column;';
    overlay.style.display = 'block';
    document.body.style.overflow = 'hidden';
    }

    function closeFilter(){
    document.getElementById('browse-drawer').style.display = 'none';
    document.getElementById('browse-overlay').style.display = 'none';
    document.body.style.overflow = '';
    }

    function toggleSection(sectionId, iconId){
    const section = document.getElementById(sectionId);
    const icon    = document.getElementById(iconId);
    section.classList.toggle('d-none');
    icon.classList.toggle('bi-chevron-down');
    icon.classList.toggle('bi-chevron-up');
    }

</script>
<?php require_once '../includes/footer.php' ?>


