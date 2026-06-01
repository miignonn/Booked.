<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ .'/../config/db.php';
require_once __DIR__ .'/../includes/functions.php';

// block suspended/banned users
$user_stmt = $conn->prepare("SELECT status FROM users WHERE id = ?");
$user_stmt->bind_param("i", $_SESSION['user_id']);
$user_stmt->execute();
$current_user = $user_stmt->get_result()->fetch_assoc();

if ($current_user['status'] === 'suspended') {
    set_flash('danger', 'Your account is suspended. You cannot create listings for 30 days.');
    header('Location: /my-listings.php');
    exit();
}

if ($current_user['status'] === 'banned') {
    session_destroy();
    header('Location: /login.php?message=banned');
    exit();
}

$error = '';
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verify_csrf();
    $title       = trim($_POST['title']);
    $author      = trim($_POST['author']);
    $edition     = trim($_POST['edition']);
    $institution = trim($_POST['institution']);
    $description = trim($_POST['description']);
    $price       = trim($_POST['price']);
    $condition   = trim($_POST['condition']);
    $category_id = trim($_POST['category_id']);
    $status      = trim($_POST['status']);
    $user_id     = $_SESSION['user_id'];

    if (empty($title) || empty($price) || empty($condition) || empty($category_id)) {
        $error = "Please fill in all required fields.";

    } elseif(empty(array_filter($_FILES['images']['name']))){
        $error = "Please upload at least one photo";
    }
    elseif (!is_numeric($price) || $price <= 0) {
        $error = "Please enter a valid price.";
    }

    $image_paths   = [];
    $primary_image = null;

    if (empty($error) && isset($_FILES['images']) && count($_FILES['images']['name']) > 0) {
        $allowed_types  = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $uploaded_count = count(array_filter($_FILES['images']['name']));

        if ($uploaded_count > 4) {
            $error = "You can upload a maximum of 4 images.";
        } else {
            for ($i = 0; $i < $uploaded_count; $i++) {
                $file_type = $_FILES['images']['type'][$i];
                $file_size = $_FILES['images']['size'][$i];
                $tmp_name  = $_FILES['images']['tmp_name'][$i];

                if (!in_array($file_type, $allowed_types)) {
                    $error = "Only JPG, PNG, and WEBP images are allowed.";
                    break;
                }
                //verify it is a real image, not renamed file
                if (getimagesize($tmp_name) === false){
                    $error = "Uploaded file is not a valid image.";
                    break;
                }

                if ($file_size > 2 * 1024 * 1024) {
                    $error = "Each image must be under 2MB.";
                    break;
                }

                $ext         = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                $filename    = uniqid('listing_', true) . '.' . $ext;
                $upload_path = __DIR__ . '/assets/images/' . $filename;
                move_uploaded_file($tmp_name, $upload_path);
                $image_paths[] = 'assets/images/' . $filename;
            }
            $primary_image = $image_paths[0] ?? null;
        }
    }

    if (empty($error)) {
        $stmt = $conn->prepare("INSERT INTO listings
            (user_id, category_id, title, author, edition, institution, description, price, `condition`, status, image)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssssssss",
            $user_id, $category_id, $title, $author, $edition,
            $institution, $description, $price, $condition, $status, $primary_image);
       
        if ($stmt->execute()) {
            $listing_id = $conn->insert_id;

            foreach ($image_paths as $path) {
                $img_stmt = $conn->prepare("INSERT INTO listing_images (listing_id, image_path) VALUES (?, ?)");
                $img_stmt->bind_param("is", $listing_id, $path);
                $img_stmt->execute();
            }

            set_flash('success', 'Listing created successfully!');
            header('Location: /my-listings.php');
            exit();
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
require_once __DIR__ .'/../includes/header.php';
?>

<?php if ($error): ?>
    <div class="form-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="create-listing-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

        <h1 class="create-listing-heading">List a Book</h1> 
        <p class="create-listing-sub">Fill in the details below to publish your listing</p>

    <div class="create-listing-grid">

        <!-- LEFT column -->
        <div class="create-listing-col">
            

            <!-- Image upload -->
            <div class="upload-box" id="upload-box">
                <i class="bi bi-cloud-upload upload-box__icon"></i>
                <p class="upload-box__title">Upload Book Photo</p>
                <p class="upload-box__hint">Up to 4 &bull; PNG or JPG</p>
                <input type="file" name="images[]" id="images" class="upload-box__input"
                       accept="image/*" multiple required onchange="previewImages(this)">
                <div id="image-preview" class="upload-box__preview"></div>
            </div>

            <!-- Condition -->
            <div class="listing-field">
                <label class="listing-label">Condition <span class="listing-required">*</span></label>
                <select name="condition" class="form-select" required>
                    <option value="">Select condition</option>
                    <option value="new"      <?= (isset($condition) && $condition === 'new')      ? 'selected' : '' ?>>New</option>
                    <option value="like new" <?= (isset($condition) && $condition === 'like new') ? 'selected' : '' ?>>Like New</option>
                    <option value="good"     <?= (isset($condition) && $condition === 'good')     ? 'selected' : '' ?>>Good - Minimal Wear</option>
                    <option value="fair"     <?= (isset($condition) && $condition === 'fair')     ? 'selected' : '' ?>>Fair - Some Wear</option>
                    <option value="poor"     <?= (isset($condition) && $condition === 'poor')     ? 'selected' : '' ?>>Poor - Heavy Wear</option>
                </select>
            </div>

            <!-- Price -->
            <div class="listing-field">
                <label class="listing-label">Asking Price <span class="listing-required">*</span></label>
                <div class="listing-price-wrap">
                    <span class="listing-price-prefix">R</span>
                    <input type="number" name="price" class="form-control"
                           value="<?= isset($price) ? htmlspecialchars($price) : '' ?>"
                           placeholder="0.00" required>
                </div>
            </div>

            <!-- Description -->
            <div class="listing-field">
                <label class="listing-label">Description</label>
                <textarea name="description" class="form-control" rows="4"
                          placeholder="Describe the book's condition, any highlights, missing pages etc."><?= isset($description) ? htmlspecialchars($description) : '' ?></textarea>
            </div>

        </div>

        <!-- RIGHT column -->
        <div class="create-listing-col">

            <!-- Title -->
            <div class="listing-field">
                <label class="listing-label">Book Title <span class="listing-required">*</span></label>
                <input type="text" name="title" class="form-control"
                       value="<?= isset($title) ? htmlspecialchars($title) : '' ?>"
                       placeholder="e.g. Database System Concepts" required>
            </div>
            
            <!--- Author & Edition ---> 
            <div class="listing-field-row">
                <div class="listing-field">
                    <label class="listing-label">Author(s) <span class="listing-required">*</span></label>
                    <input type="text" name="author" class="form-control"
                    value="<?= isset($author) ? htmlspecialchars($author) : '' ?>"
                    placeholder="Silberschatz, Korth and Sudarshan" required>
                </div>
                <div class="listing-field">
                    <label class="listing-label">Edition <span class="listing-required">*</span></label>
                    <input type="text" name="edition" class="form-control" 
                    value="<?= isset($edition) ? htmlspecialchars($edition) : '' ?>"
                    placeholder="e.g. 3rd" required>
                </div>
            </div>

            <!-- Faculty -->
            <div class="listing-field">
                <label class="listing-label">Subject/Faculty <span class="listing-required">*</span></label>
                <select name="category_id" class="form-select" required>
                    <option value="">Select faculty</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= $cat['id'] ?>" <?= (isset($category_id) && $category_id == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Institution -->
            <div class="listing-field">
                <label class="listing-label">Institution <span class="listing-required">*</span></label>
                <input type="text" name="institution" class="form-control"
                       value="<?= isset($institution) ? htmlspecialchars($institution) : '' ?>"
                       placeholder="e.g. Eduvos" required>
            </div>

            <!-- Submit buttons -->
            <input type="hidden" name="status" id="status" value="available">
            <div class="listing-actions">
                <button type="submit" class="b-btn b-btn--primary"
                        onclick="document.getElementById('status').value='available'">
                    Publish Listing
                </button>
                <button type="submit" class="b-btn b-btn--outline"
                        onclick="document.getElementById('status').value='draft'">
                    Save as Draft
                </button>
            </div>

        </div>
    </div>
</form>

<script>
function previewImages(input) {
    const preview = document.getElementById('image-preview');
    preview.innerHTML = '';
    const files = Array.from(input.files).slice(0, 4);
    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const wrapper = document.createElement('div');
            wrapper.className = 'upload-box__thumb';

            const img = document.createElement('img');
            img.src = e.target.result;

            const btn = document.createElement('button');
            btn.innerHTML = '×';
            btn.type = 'button';
            btn.className = 'upload-box__remove';
            btn.onclick = () => wrapper.remove();

            wrapper.appendChild(img);
            wrapper.appendChild(btn);
            preview.appendChild(wrapper);
        };
        reader.readAsDataURL(file);
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>