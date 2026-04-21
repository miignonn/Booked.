<?php
require_once '../includes/header.php';
require_once '../config/db.php';
?>


<h1>Welcome <?= htmlspecialchars($_SESSION['name']) ?></h1>

<?php require_once '../includes/footer.php'; ?>