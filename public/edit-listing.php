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

if (!$listing) {
    set_flash('danger', 'Listing not found.');
    header('Location: /my-listings.php');
    exit();
}

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
    $edition     = trim($_POST['edition']);
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
        // Whitelisted extensions and their corresponding real MIME types
        $allowed_extensions = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $uploaded_count = count(array_filter($_FILES['images']['name']));

        if ($uploaded_count > 4) {
            $error = "You can upload a maximum of 4 images.";
        } else {
            // delete old images
            $del_imgs = $conn->prepare("DELETE FROM listing_images WHERE listing_id = ?");
            $del_imgs->bind_param("i", $id);
            $del_imgs->execute();

            for ($i = 0; $i < $uploaded_count; $i++) {
                $file_size = $_FILES['images']['size'][$i];
                $tmp_name  = $_FILES['images']['tmp_name'][$i];

                //whitelist the extension from the original filename
                $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                if (!array_key_exists($ext, $allowed_extensions)) {
                    $error = "Only JPG, PNG, and WEBP images are allowed.";
                    break;
                }

                //verify the real MIME type from the file itself
                $real_mime = mime_content_type($tmp_name);
                if ($real_mime !== $allowed_extensions[$ext]) {
                    $error = "File content does not match its extension.";
                    break;
                }

                //verify it is a real image via GD
                if (getimagesize($tmp_name) === false) {
                    $error = "Uploaded file is not a valid image.";
                    break;
                }

                if ($file_size > 2 * 1024 * 1024) {
                    $error = "Each image must be under 2MB.";
                    break;
                }

                //use only the whitelisted extension
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
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="create-listing-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

    <h1 class="create-listing-heading">Edit Listing</h1>
    <p class="create-listing-sub">Update the details below and republish your listing.</p>

    <!-- Full width upload box -->
    <div class="upload-box edit-upload-box">
        <i class="bi bi-cloud-upload upload-box__icon"></i>
        <p class="upload-box__title">Replace Photos</p>
        <p class="upload-box__hint">Leave empty to keep existing photos</p>
        <input type="file" name="images[]" id="images" class="upload-box__input"
               accept="image/*" multiple onchange="previewImages(this)">
        <div id="image-preview" class="upload-box__preview"></div>
        <?php if ($listing['image']): ?>
            <p class="upload-box__hint" style="margin-top:12px;">Current Image(s):</p>
            <img src="/<?= htmlspecialchars($listing['image']) ?>" class="upload-box__current">
        <?php endif; ?>
    </div>

    <!-- Two column grid for all fields -->
    <div class="create-listing-grid" style="margin-top: 1.5rem;">

        <!-- LEFT -->
        <div class="create-listing-col">
            <div class="listing-field">
                <label class="listing-label">Book Title <span class="listing-required">*</span></label>
                <input type="text" name="title" class="form-control"
                       value="<?= isset($title) ? htmlspecialchars($title) : htmlspecialchars($listing['title']) ?>"
                       placeholder="e.g. Database System Concepts" required>
            </div>
            <div class="listing-field-row">
                <div class="listing-field">
                    <label class="listing-label">Author(s) <span class="listing-required">*</span></label>
                    <input type="text" name="author" class="form-control"
                           value="<?= isset($author) ? htmlspecialchars($author) : htmlspecialchars($listing['author']) ?>"
                           placeholder="e.g. Silberschatz" required>
                </div>
                <div class="listing-field">
                    <label class="listing-label">Edition <span class="listing-required">*</span></label>
                    <input type="text" name="edition" class="form-control"
                           value="<?= isset($edition) ? htmlspecialchars($edition) : htmlspecialchars($listing['edition']) ?>"
                           placeholder="e.g. 3rd" required>
                </div>
            </div>
            <div class="listing-field">
                <label class="listing-label">Subject/Faculty <span class="listing-required">*</span></label>
                <select name="category_id" class="form-select" required>
                    <option value="">Select faculty</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= $cat['id'] ?>" <?= (isset($category_id) ? $category_id : $listing['category_id']) == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="listing-field">
                <label class="listing-label">Institution <span class="listing-required">*</span></label>
                <input type="text" name="institution" class="form-control"
                       value="<?= isset($institution) ? htmlspecialchars($institution) : htmlspecialchars($listing['institution']) ?>"
                       placeholder="e.g. Eduvos" required>
            </div>
        </div>

        <!-- RIGHT -->
        <div class="create-listing-col">
            <div class="listing-field">
                <label class="listing-label">Condition <span class="listing-required">*</span></label>
                <select name="condition" class="form-select" required>
                    <option value="">Select condition</option>
                    <?php foreach (['new' => 'New', 'like new' => 'Like New', 'good' => 'Good - Minimal Wear', 'fair' => 'Fair - Some Wear', 'poor' => 'Poor - Heavy Wear'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= (isset($condition) ? $condition : $listing['condition']) === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="listing-field">
                <label class="listing-label">Asking Price <span class="listing-required">*</span></label>
                <div class="listing-price-wrap">
                    <span class="listing-price-prefix">R</span>
                    <input type="number" name="price" class="form-control"
                           value="<?= isset($price) ? htmlspecialchars($price) : htmlspecialchars($listing['price']) ?>"
                           placeholder="0.00" required>
                </div>
            </div>
            <div class="listing-field">
                <label class="listing-label">Description</label>
                <textarea name="description" class="form-control" rows="5"
                          placeholder="Describe the book's condition, any highlights, missing pages etc."><?= isset($description) ? htmlspecialchars($description) : htmlspecialchars($listing['description'] ?? '') ?></textarea>
            </div>
            <input type="hidden" name="status" id="status" value="available">        
        </div>
    </div>
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
            wrapper.style.cssText = 'position:relative;display:inline-block';
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid white;box-shadow:0 1px 4px rgba(0,0,0,0.15)';
            const btn = document.createElement('button');
            btn.innerHTML = 'x';
            btn.type = 'button';
            btn.style.cssText = 'position:absolute;top:-6px;right:-6px;background:black;color:white;border:none;border-radius:50%;width:20px;height:20px;font-size:12px;line-height:1;cursor:pointer;padding:0';
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