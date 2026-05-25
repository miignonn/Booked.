<?php
require_once __DIR__ .'/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ .'/../includes/functions.php';

$error = '';
$success = '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id == 0) {
    set_flash('danger', 'Listing not found.');
    header('Location: /my-listings.php');
    exit();
}

// fetch existing listing
$stmt = $conn->prepare("SELECT * FROM listings WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $id, $_SESSION['user_id']);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();

if ($listing['status'] == 'sold') {
    set_flash('danger', 'You are not authorised to edit this listing.');
    header('Location: /my-listings.php');
    exit();
}

//block suspended/banned users
$user_stmt = $conn->prepare("SELECT status FROM users WHERE id = ?");
$user_stmt->bind_param("i", $_SESSION['user_id']);
$user_stmt->execute();
$current_user = $user_stmt->get_result()->fetch_assoc();

if($current_user['status'] === 'suspended'){
    set_flash('danger', 'Your account is suspended. You cannot edit listings for 30 days.');
    header('Location: /my-listings.php');
    exit();
}

if ($current_user['status'] === 'banned'){
    session_destroy();
    header('Location: /login.php?message=banned');
    exit();
}
// fetch categories
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC");

// keep existing image by default
$primary_image = $listing['image'];
$image_paths = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verify_csrf();
    $title       = trim($_POST['title']);
    $author      = trim($_POST['author']);
    $edition        = trim($_POST['edition']);
    $institution = trim($_POST['institution']);
    $description = trim($_POST['description']);
    $price       = trim($_POST['price']);
    $condition   = $_POST['condition'];
    $category_id = $_POST['category_id'];
    $status      = $_POST['status'];

    if (empty($title) || empty($price) || empty($condition) || empty($category_id)) {
        $error = "Please fill in all required fields.";
    } elseif (!is_numeric($price) || $price <= 0) {
        $error = "Please enter a valid price.";
    }

    // handle image upload
    if (empty($error) && isset($_FILES['images']) && !empty(array_filter($_FILES['images']['name']))) {
        $allowed_types  = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $uploaded_count = count(array_filter($_FILES['images']['name']));

        if ($uploaded_count > 4) {
            $error = "You can upload a maximum of 4 images.";
        } else {
            // delete old images
            $del_imgs = $conn->prepare("DELETE FROM listing_images WHERE listing_id = ?");
            $del_imgs->bind_param("i", $id);
            $del_imgs->execute();

            for ($i = 0; $i < $uploaded_count; $i++) {
                $file_type = $_FILES['images']['type'][$i];
                $file_size = $_FILES['images']['size'][$i];
                $tmp_name  = $_FILES['images']['tmp_name'][$i];

                if (!in_array($file_type, $allowed_types)) {
                    $error = "Only JPG, PNG, and WEBP images are allowed.";
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
            $primary_image = $image_paths[0] ?? $listing['image'];
        }
    }

    if (empty($error)) {
        $stmt = $conn->prepare("UPDATE listings 
            SET title = ?, author = ?, edition = ?, institution = ?, 
                description = ?, price = ?, `condition` = ?, 
                category_id = ?, status = ?, image = ?
            WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sssssdssssii",
            $title, $author, $edition, $institution,
            $description, $price, $condition,
            $category_id, $status, $primary_image,
            $id, $_SESSION['user_id']);

        if ($stmt->execute()) {
            if (!empty($image_paths)) {
                foreach ($image_paths as $path) {
                    $img_stmt = $conn->prepare("INSERT INTO listing_images (listing_id, image_path) VALUES (?, ?)");
                    $img_stmt->bind_param("is", $id, $path);
                    $img_stmt->execute();
                }
            }
            set_flash('success', 'Listing updated successfully!');
            header('Location: /my-listings.php');
            exit();
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>



<?php if($error): ?>
    <div class="alert alert-danger"><?=  htmlspecialchars($error) ?></div>
    <?php endif; ?>
<form method="POST" enctype="multipart/form-data" class="create-listing-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

    <div class="create-listing-grid">

        <!-- LEFT column -->
        <div class="create-listing-col">

            <!-- Image upload -->
            <div class="upload-box">
                <i class="bi bi-cloud-upload upload-box__icon"></i>
                <p class="upload-box__title">Replace Photos</p>
                <p class="upload-box__hint">Leave empty to keep existing photos</p>
                <input type="file" name="images[]" id="images" class="upload-box__input"
                    accept="image/*" multiple required onchange="previewImages(this)">
                <div id="image-preview" class="upload-box__preview"></div>

                <?php if ($listing['image']): ?>
                    <p class="upload-box__hint" style="margin-top:12px;">Current image:</p>
                    <img src="/<?= htmlspecialchars($listing['image']) ?>"
                         class="upload-box__current">
                <?php endif; ?>
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
                <button type="submit" class="btn-checkout"
                        onclick="document.getElementById('status').value='available'">
                    Publish Listing
                </button>
                <button type="submit" class="btn-browse"
                        onclick="document.getElementById('status').value='draft'">
                    Save as Draft
                </button>
            </div>

        </div>
    </div>
</form>

<script>
    function previewImages(input){
        const preview = document.getElementById('image-preview');
            preview.innerHTML = '';
            const files = Array.from(input.files). slice(0,4);
            files.forEach(file => {
                const reader = new FileReader();
                reader.onload = e => {
                    const wrapper = document.createElement('div');
                    wrapper.style.cssText = 'position:relative;display:inline-block';
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid white:box-shadow:0 1px 4px rgba(0,0,0,0.15)';
                    const btn = document.createElement('button');
                    btn.innerHTML = 'x';
                    btn.type = 'button';
                    btn.style.cssText = 'position:absolute;top:-6px;right:-6px;background:black;color:white;border:none;border-radius: 50%;width:20px;height:20px;font-size:12px;line-height:1;cursor:pointer;padding:0';
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

 
