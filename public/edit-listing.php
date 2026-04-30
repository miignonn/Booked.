<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id == 0) {
    header('Location: /my-listings.php');
    exit();
}

// fetch existing listing
$stmt = $conn->prepare("SELECT * FROM listings WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $id, $_SESSION['user_id']);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();

if (!$listing) {
    header('Location: /my-listings.php');
    exit();
}

// fetch categories
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC");

// keep existing image by default
$primary_image = $listing['image'];
$image_paths = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title       = trim($_POST['title']);
    $author      = trim($_POST['author']);
    $isbn        = trim($_POST['isbn']);
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
            SET title = ?, author = ?, isbn = ?, institution = ?, 
                description = ?, price = ?, `condition` = ?, 
                category_id = ?, status = ?, image = ?
            WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sssssdssssii",
            $title, $author, $isbn, $institution,
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
            header('Location: /my-listings.php');
            exit();
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>



<?php if($error): ?>
    <div class="alert alert-danger"><?=  htmlspecialchars($error) ?></div>
    <?php endif; ?>

   
<form method="POST" enctype="multipart/form-data">
    <div class="row">
        <div class="col-md-6">

            <!-- Image Upload -->
            <div class="border rounded-3 p-4 text-center mb-3">
                <i class="bi bi-cloud-upload fs-2 text-muted"></i>
                <p class="fw-bold mt-2">Replace Photos</p>
                <p class="text-muted small">Leave empty to keep existing photos</p>
                <input type="file" name="images[]" class="form-control mt-2"
                    accept="image/*" multiple onchange="previewImages(this)">
                <div id="image-preview" class="d-flex flex-wrap gap-2 mt-3"></div>

                <?php if ($listing['image']): ?>
                    <p class="text-muted small mt-2">Current image:</p>
                    <img src="/<?= htmlspecialchars($listing['image']) ?>" 
                         style="width:80px;height:80px;object-fit:cover;border-radius:8px;">
                <?php endif; ?>
            </div>

            <!-- Condition -->
            <div class="mb-3">
                <label class="form-label fw-bold">Condition <span class="text-danger">*</span></label>
                <select name="condition" class="form-select" required>
                    <option value="">Select condition</option>
                    <option value="new" <?= $listing['condition'] === 'new' ? 'selected' : '' ?>>New</option>
                    <option value="like new" <?= $listing['condition'] === 'like new' ? 'selected' : '' ?>>Like New</option>
                    <option value="good" <?= $listing['condition'] === 'good' ? 'selected' : '' ?>>Good - Minimal Wear</option>
                    <option value="fair" <?= $listing['condition'] === 'fair' ? 'selected' : '' ?>>Fair - Some Wear</option>
                    <option value="poor" <?= $listing['condition'] === 'poor' ? 'selected' : '' ?>>Poor - Heavy Wear</option>
                </select>
            </div>

            <!-- Price -->
            <div class="mb-3">
                <label class="form-label fw-bold">Asking Price <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">R</span>
                    <input type="text" name="price" class="form-control" 
                        value="<?= htmlspecialchars($listing['price']) ?>" required>
                </div>
            </div>

            <!-- Description -->
            <div class="mb-3">
                <label class="form-label fw-bold">Description</label>
                <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($listing['description']) ?></textarea>
            </div>

        </div>

        <div class="col-md-6">

            <!-- Title -->
            <div class="mb-3">
                <label class="form-label fw-bold">Book Title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" 
                    value="<?= htmlspecialchars($listing['title']) ?>" required>
            </div>

            <!-- Author -->
            <div class="mb-3">
                <label class="form-label fw-bold">Author(s)</label>
                <input type="text" name="author" class="form-control" 
                    value="<?= htmlspecialchars($listing['author']) ?>">
            </div>

            <!-- ISBN -->
            <div class="mb-3">
                <label class="form-label fw-bold">ISBN</label>
                <input type="text" name="isbn" class="form-control" 
                    value="<?= htmlspecialchars($listing['isbn']) ?>">
            </div>

            <!-- Category -->
            <div class="mb-3">
                <label class="form-label fw-bold">Subject / Faculty <span class="text-danger">*</span></label>
                <select name="category_id" class="form-select" required>
                    <option value="">Select faculty</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= $cat['id'] ?>" 
                            <?= $listing['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Institution -->
            <div class="mb-3">
                <label class="form-label fw-bold">Institution</label>
                <input type="text" name="institution" class="form-control" 
                    value="<?= htmlspecialchars($listing['institution']) ?>">
            </div>

            <!-- Status -->
            <input type="hidden" name="status" id="status" value="active">
              <div class="d-flex flex-column gap-2 mt-2">
                <button type="submit" class="btn btn-dark w-100"
                 onclick="document.getElementById('status').value='active'">
                 Publish Listing
                </button>
               <button type="submit" class="btn btn-outline-dark w-100"
                onclick="document.getElementById('status').value='draft'">
                Save as Draft
               </button>
             </div>

             <button type="submit" class="btn btn-outline-dark w-100 mt-2">Save Changes</button>

 
        </div>
    </div>
</form>

<script>
    function previewImages(input){
        const preview = document.getElementbyID('image-preview');
            preview.innerHTML = '';
            const files = Array.from(input.files). slice(0,4);
            files.forEach(file => {
                const readr = new FileReader();
                reader.onload = e => {
                    const wrapper = document.createElement('div');
                    wrapper.style.cssText = 'position:relative;display:inline-block';
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.cssText = 'wodth:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid white:box-shadow:0 1px 4px rgba(0,0,0,0.15)';
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

 
