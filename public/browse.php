<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$filter_institution = isset($_GET['institution']) ? trim($_GET['institution']) : '';
$filter_category = isset($_GET['category']) ? trim($_GET['category']) : '';
$filter_condition = isset($_GET['condition']) ? trim($_GET['condition']) : '';
$filter_price_max = isset($_GET['price_max']) ? (float)$_GET['price_max'] : '';
$filter_edition = isset($_GET['edition']) ? trim($_GET['edition']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : '';

$where = ["listings.status = 'available'", "users.status != 'suspended'"];
$params = [];
$types = '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search) {
    $where[] = "(listings.title LIKE ? OR listings.author LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}
if ($filter_institution) {
    $where[] = "listings.institution LIKE ?";
    $params[] = "%$filter_institution%";
    $types .= 's';
}
if ($filter_category) {
    $where[] = "listings.category_id = ?";
    $params[] = $filter_category;
    $types .= 'i';
}
if ($filter_condition) {
    $where[] = "listings.condition = ?";
    $params[] = $filter_condition;
    $types .= 's';
}
if ($filter_price_max !== '') {
    $where[] = "listings.price <= ?";
    $params[] = $filter_price_max;
    $types .= 'd';
}
if ($filter_edition) {
    $where[] = "listings.edition LIKE ?";
    $params[] = "%$filter_edition%";
    $types .= 's';
}

$order = match($sort) {
    'price_asc'  => 'listings.price ASC',
    'price_desc' => 'listings.price DESC',
    default      => 'listings.created_at DESC'
};

$count_sql = "SELECT COUNT(*) as total
              FROM listings
              JOIN users ON listings.user_id = users.id
              JOIN categories ON listings.category_id = categories.id
              WHERE " . implode(' AND ', $where);

$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total = (int)$count_stmt->get_result()->fetch_assoc()['total'];

$per_page = 20;
$page     = get_page();
$pag      = paginate($page, $per_page);

$sql = "SELECT listings.*, users.username AS seller_username, categories.name AS category_name
        FROM listings
        JOIN users ON listings.user_id = users.id
        JOIN categories ON listings.category_id = categories.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $order
        LIMIT ? OFFSET ?";

$paginated_params = array_merge($params, [$pag['limit'], $pag['offset']]);
$paginated_types  = $types . 'ii';

$stmt = $conn->prepare($sql);
if (!empty($paginated_params)) $stmt->bind_param($paginated_types, ...$paginated_params);
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$institutions_mob = $conn->query("SELECT DISTINCT institution FROM listings WHERE status='available' AND institution IS NOT NULL ORDER BY institution ASC");
$mob_categories   = $conn->query("SELECT * FROM categories ORDER BY name ASC");
$desk_institutions = $conn->query("SELECT DISTINCT institution FROM listings WHERE status='available' AND institution IS NOT NULL ORDER BY institution ASC");
$desk_categories   = $conn->query("SELECT * FROM categories ORDER BY name ASC");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-wrap">  
<!-- Mobile filter toggle -->
<button type="button" class="browse-filter-toggle mb-3" onclick="openFilters()">
    <i class="bi bi-sliders"></i> Filters
</button>

<!-- Overlay -->
<div class="browse-overlay" id="browse-overlay" onclick="closeFilters()"></div>

<!-- Mobile drawer -->
<div class="browse-drawer" id="browse-drawer">
    <div class="browse-drawer__head">
        <span class="fw-bold">Filters</span>
        <button type="button" onclick="closeFilters()" class="browse-drawer__close">
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
                <?php while ($inst = $institutions_mob->fetch_assoc()): ?>
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
                <?php while ($cat = $mob_categories->fetch_assoc()): ?>
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

        <button type="submit" class="b-btn--primary">Apply</button>
        <a href="/browse.php" class="b-btn--outline">Clear Filters</a>
    </form>
</div>

<!-- Main layout -->
<div class="browse-layout">

    <!-- Desktop sidebar -->
    <aside class="browse-sidebar">
        <p class="browse-sidebar__title">Filter</p>
        <form method="GET" id="filter-form-desktop">

            <div class="browse-filter-group">
                <div class="browse-filter-group__header" onclick="toggleSection('inst-desk', 'inst-desk-icon')">
                    <span class="browse-filter-group__label">Institution</span>
                    <i class="bi bi-chevron-down" id="inst-desk-icon"></i>
                </div>
                <div id="inst-desk" class="d-none">
                    <?php while ($inst = $desk_institutions->fetch_assoc()): ?>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="radio" name="institution"
                                   value="<?= htmlspecialchars($inst['institution']) ?>"
                                   id="desk_inst_<?= md5($inst['institution']) ?>"
                                   <?= $filter_institution === $inst['institution'] ? 'checked' : '' ?>
                                   onchange="this.form.submit()">
                            <label class="form-check-label" for="desk_inst_<?= md5($inst['institution']) ?>">
                                <?= htmlspecialchars($inst['institution']) ?>
                            </label>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="browse-filter-group">
                <div class="browse-filter-group__header" onclick="toggleSection('fac-desk', 'fac-desk-icon')">
                    <span class="browse-filter-group__label">Faculty</span>
                    <i class="bi bi-chevron-down" id="fac-desk-icon"></i>
                </div>
                <div id="fac-desk" class="d-none">
                    <?php while ($cat = $desk_categories->fetch_assoc()): ?>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="radio" name="category"
                                   value="<?= $cat['id'] ?>"
                                   id="desk_cat_<?= $cat['id'] ?>"
                                   <?= $filter_category == $cat['id'] ? 'checked' : '' ?>
                                   onchange="this.form.submit()">
                            <label class="form-check-label" for="desk_cat_<?= $cat['id'] ?>">
                                <?= htmlspecialchars($cat['name']) ?>
                            </label>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="browse-filter-group">
                <div class="browse-filter-group__header" onclick="toggleSection('cond-desk', 'cond-desk-icon')">
                    <span class="browse-filter-group__label">Condition</span>
                    <i class="bi bi-chevron-down" id="cond-desk-icon"></i>
                </div>
                <div id="cond-desk" class="d-none">
                    <?php foreach (['new' => 'New', 'like new' => 'Like New', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor'] as $val => $label): ?>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="radio" name="condition"
                                   value="<?= $val ?>"
                                   id="desk_cond_<?= str_replace(' ', '_', $val) ?>"
                                   <?= $filter_condition === $val ? 'checked' : '' ?>
                                   onchange="this.form.submit()">
                            <label class="form-check-label" for="desk_cond_<?= str_replace(' ', '_', $val) ?>">
                                <?= $label ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="browse-filter-group">
                <div class="browse-filter-group__header" onclick="toggleSection('ed-desk', 'ed-desk-icon')">
                    <span class="browse-filter-group__label">Edition</span>
                    <i class="bi bi-chevron-down" id="ed-desk-icon"></i>
                </div>
                <div id="ed-desk" class="d-none">
                    <input type="text" name="edition" class="form-control form-control-sm"
                           placeholder="e.g. 3rd"
                           value="<?= htmlspecialchars($filter_edition) ?>"
                           onchange="this.form.submit()">
                </div>
            </div>

            <div class="browse-filter-group" style="border-bottom:none;">
                <div class="browse-filter-group__header" onclick="toggleSection('price-desk', 'price-desk-icon')">
                    <span class="browse-filter-group__label">Price</span>
                    <i class="bi bi-chevron-down" id="price-desk-icon"></i>
                </div>
                <div id="price-desk" class="d-none">
                    <label class="form-label small">
                        Max: R<span id="price-max-val-desk"><?= $filter_price_max ?: 1000 ?></span>
                    </label>
                    <input type="range" class="form-range" name="price_max"
                           min="0" max="1000" step="50"
                           value="<?= $filter_price_max ?: 1000 ?>"
                           oninput="document.getElementById('price-max-val-desk').innerText=this.value">
                </div>
            </div>

            <button type="submit" class="b-btn b-btn--primary w-100 mt-3">Apply</button>
            <a href="/browse.php" class="b-btn b-btn--outline w-100 mt-2">Clear Filters</a>
        </form>
    </aside>

    <!-- Listings -->
    <div class="browse-listings">
    <div class="browse-listings__header">
        <div>
            <h2 class="browse-listings__title">All Listings</h2>
            <p class="browse-listings__count">
                <?= $total ?> books available
                <?php if ($search): ?>
                    for "<strong><?= htmlspecialchars($search) ?></strong>"
                <?php endif; ?>
            </p>
        </div>
        <div class="browse-listings__controls">
            <form method="GET" class="browse-search-form">
                <div style="display:flex; align-items:stretch;">
                    <input type="text" name="search" class="browse-search-input"
                           placeholder="Search title or author"
                           value="<?= htmlspecialchars($search) ?>">
                    <span class="browse-search-icon">
                        <i class="bi bi-search"></i>
                    </span>
                </div>
            </form>
            <select class="browse-sort-select"
                    onchange="window.location='?sort='+this.value+'&<?= http_build_query(array_filter(['institution' => $filter_institution, 'category' => $filter_category, 'condition' => $filter_condition, 'edition' => $filter_edition, 'price_max' => $filter_price_max])) ?>'">
                <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Newest</option>
                <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Price: Low–High</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High–Low</option>
            </select>
        </div>
    </div>
        <?php if (empty($listings)): ?>
            <p class="b-empty mt-3">No listings found matching your filters.</p>
        <?php else: ?>
            <div class="browse-grid">
                <?php foreach ($listings as $listing): ?>
                    <div class="b-book-card"
                         onclick="window.location='/listing.php?id=<?= $listing['id'] ?>&from=browse'">
                        <div class="b-book-card__cover">
                            <?php if ($listing['image']): ?>
                                <img src="/<?= htmlspecialchars($listing['image']) ?>"
                                     alt="<?= htmlspecialchars($listing['title']) ?>"
                                     class="b-book-card__img">
                            <?php else: ?>
                                <div class="b-book-card__no-image">
                                    <i class="bi bi-book"></i>
                                </div>
                            <?php endif; ?>
                            <span class="b-book-card__cond b-book-card__cond--<?= match($listing['condition']) {
                                'new', 'like new' => 'new',
                                'good'            => 'good',
                                'fair'            => 'fair',
                                default           => 'poor'
                            } ?>"><?= ucfirst($listing['condition']) ?></span>
                        </div>
                        <div class="b-book-card__info">
                            <p class="b-book-card__subj"><?= htmlspecialchars($listing['category_name'] ?? '') ?></p>
                            <p class="b-book-card__title"><?= htmlspecialchars($listing['title']) ?></p>
                            <p class="b-book-card__author"><?= htmlspecialchars($listing['author'] ?? '') ?></p>
                            <div class="b-book-card__foot">
                                <span class="b-book-card__price">R<?= number_format($listing['price'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?= pagination_links($total, $per_page, $page, '/browse.php') ?>
    </div>

</div>
</div>

<script>
function openFilters() {
    document.getElementById('browse-drawer').classList.add('browse-drawer--open');
    document.getElementById('browse-overlay').classList.add('browse-overlay--open');
    document.body.style.overflow = 'hidden';
}

function closeFilters() {
    document.getElementById('browse-drawer').classList.remove('browse-drawer--open');
    document.getElementById('browse-overlay').classList.remove('browse-overlay--open');
    document.body.style.overflow = '';
}

function toggleSection(sectionId, iconId) {
    const section = document.getElementById(sectionId);
    const icon    = document.getElementById(iconId);
    section.classList.toggle('d-none');
    icon.classList.toggle('bi-chevron-down');
    icon.classList.toggle('bi-chevron-up');
}
</script>

<?php require_once '../includes/footer.php'; ?>