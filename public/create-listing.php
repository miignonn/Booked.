<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';
require_once '../config/db.php';

$error = '';
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title       = trim($_POST['title']);
    $author      = trim($_POST['author']);
    $isbn        = trim($_POST['isbn']);
    $institution = trim($_POST['institution']);
    $description = trim($_POST['description']);
    $price       = trim($_POST['price']);
    $condition   = trim($_POST['condition']);
    $category_id = trim($_POST['category_id']);
    $status      = trim($_POST['status']);
    $user_id     = $_SESSION['user_id'];


    if (empty($title) || empty($price) || empty($condition) || empty($category_id)) {
        $error = "Please fill in all required fields.";
    } elseif (!is_numeric($price) || $price <= 0) {
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
            (user_id, category_id, title, author, isbn, institution, description, price, `condition`, status, image) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssssdsss",
            $user_id, $category_id, $title, $author, $isbn,
            $institution, $description, $price, $condition, $status, $primary_image);

        if ($stmt->execute()) {
            $listing_id = $conn->insert_id;

            foreach ($image_paths as $path) {
                $img_stmt = $conn->prepare("INSERT INTO listing_images (listing_id, image_path) VALUES (?, ?)");
                $img_stmt->bind_param("is", $listing_id, $path);
                $img_stmt->execute();
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

    <div class="container mt-4">
    <form method="POST" enctype="multipart/form-data">
        <div class="row">

        <div class="col-md-6">

        <!-- Image Upload -->
<div class="border rounded-3 p-4 text-center mb-3" id="upload-box">
    <i class="bi bi-cloud-upload fs-2 text-muted"></i>
    <p class="fw-bold mt-2">Upload Book Photo</p>
    <p class="text-muted small">Up to 4 • PNG or JPG</p>
    <input type="file" name="images[]" id="images" class="form-control mt-2"
    accept="image/*" multiple onchange="previewImages(this)">
    <div id="image-preview" class="d-flex flex-wrap gap-2 mt-3"></div>
</div>

<!-- Photo Tips — outside the upload box -->
<div class="bg-light rounded-3 p-3 mb-3">
    <p class="fw-bold small mb-1"><i class="bi bi-camera"></i> Photo Tips</p>
    <ul class="text-muted small mb-0">
        <li>Place the book on a flat, clean surface.</li>
        <li>Make sure the cover is clearly visible.</li>
        <li>Use natural lighting.</li>
        <li>Include photos of any damage or wear.</li>
    </ul>
</div>

            <div class="mb-3">
                <label class="form-label fw-bold">Condition<span class="text-danger">*</span></label>
                <select name="condition" class="form-select" required>
                    <option value="">Select condition</option>
                    <option value="new" <?= (isset($condition) && $condition === 'new') ? 'selected' : '' ?>>New</option>
                    <option value="like New" <?= (isset($condition) && $condition === 'like new') ? 'selected' : '' ?>>Like New</option>
                    <option value="good" <?= (isset($condition) && $condition === 'good') ? 'selected' : '' ?>>Good - Minimal Wear</option>
                    <option value="fair" <?= (isset($condition) && $condition === 'fair') ? 'selected' : '' ?>>Fair - Some Wear</option>
                    <option value="poor"<?= (isset($condition) && $condition === 'poor') ? 'selected' : '' ?>>Poor - Heavy Wear</option>
                 </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Asking Price <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group=text">R</span>
                    <input type="number" name="price" class="form-control"
                    value="<?= isset($price) ? htmlspecialchars($price) : '' ?>"
                    placeholder="0.00"
                    required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Description</label>
                <textarea name="description" class="form-control" rows="4"
                placeholder="Describe the book's condition, any highlights, missing pages etc."> <?= isset($description) ? htmlspecialchars($description) : '' ?>
            </textarea>
            </div>

         </div>

         <div class="col-md-6">

         <div class="mb-3">
            <label class="form-label fw-bold">Book Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control"
            value="<?= isset($title) ? htmlspecialchars($title) : '' ?>"
            placeholder="e.g. Database System Concepts" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Author(s) <span class="text-danger">*</span></label>
            <input type="text" name="author" class="form-control" 
             value="<?= isset($author) ? htmlspecialchars($author) : '' ?>"
             placeholder="e.g. Williams, Koch" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">ISBN <span class="text-danger">*</span></label>
            <input type="text" name="isbn" class="form-control" 
            value="<?= isset($isbn) ? htmlspecialchars($isbn) : '' ?>"
            placeholder="e.g. 977-" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Subject/Faculty <span class="text-danger">*</span></label>
            <select name="category_id" class="form-select" required>
    <option value="">Select faculty</option>
    <?php while ($cat = $categories->fetch_assoc()): ?>
        <option value="<?= $cat['id'] ?>" <?= (isset($category_id) && $category_id == $cat['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['name']) ?>
        </option>
    <?php endwhile; ?>
</select>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Institution <span class="text-danger">*</span></label>
            <input type="text" name="institution" class="form-control" 
            value="<?= isset($institution) ? htmlspecialchars($institution) : '' ?>"
            placeholder="e.g. Eduvos" required>
        </div>
        
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
        

        </div>
        </div>
    </form>
    </div>

    <script>
        function previewImages(input) {
    const preview = document.getElementById('image-preview');
    preview.innerHTML = '';
    const files = Array.from(input.files).slice(0, 4);
    files.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = e => {
            const wrapper = document.createElement('div');
            wrapper.style.cssText = 'position:relative;display:inline-block';

            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid white;box-shadow:0 1px 4px rgba(0,0,0,0.15)';

            const btn = document.createElement('button');
            btn.innerHTML = '×';
            btn.type = 'button';
            btn.style.cssText = 'position:absolute;top:-6px;right:-6px;background:black;color:white;border:none;border-radius:50%;width:20px;height:20px;font-size:14px;line-height:1;cursor:pointer;padding:0';
            btn.onclick = () => wrapper.remove();

            wrapper.appendChild(img);
            wrapper.appendChild(btn);
            preview.appendChild(wrapper);
        };
        reader.readAsDataURL(file);
    });
}
    </script>