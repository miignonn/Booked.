<?php
require_once '../../includes/functions.php';
 
 if (session_status() == PHP_SESSION_NONE){
    session_start();
 }

 if(empty($_SESSION['csrf_token'])){
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
 }

 require_once __DIR__ . '/../config/db.php';

 if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin'){
    header('Location: /login.php');
    exit();
 }

 $current = basename($_SERVER['PHP_SELF'] ?? '');

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Booked Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <link href="/assets/css/admin.css" rel="stylesheet">
    </head>
    
    <body class="admin-body">
      <div class="grid-container">

         <!--Sidebar-->
   <aside id="sidebar">

         <div class="sidebar-brand">
            <span class="sidebar-brand-name">Booked.</span>
            <span class="sidebar-brand-sub">Admin Panel</span>
         </div>

         <nav class="sidebar-nav">

         <p class="sidebar-section-label">Overview</p>

         <a href="/admin/dashboard.php" class="sidebar-link <?= $current == 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2"></i>
            <span>Dashboard</span>
         </a>

         <p class="sidebar-section-label">Management</p>

         <a href="/admin/users.php" class="sidebar-link <?= $current == 'users.php' ? 'active' :'' ?>">
            <i class="bi bi-people"></i>
            <span>Users</span>
         </a>

         <a href="/admin/listings.php" class="sidebar-link <?= $current == 'listings.php' ? 'active' :'' ?>">
            <i class="bi bi-collection"></i>
            <span>Listings</span>
         </a>

         <a href="/admin/orders.php" class="sidebar-link <?= $current == 'orders.php' ? 'active' :'' ?>">
            <i class="bi bi-check"></i>
            <span>Orders</span>
         </a>

         <a href="/admin/reports.php" class="sidebar-link <?= $current == 'reports.php' ? 'active' :'' ?>">
            <i class="bi bi-flag"></i>
            <span>Reports</span>
         </a>

         </nav>
      
         <div class="sidebar-bottom">
            <a href="/index.php" class="sidebar-link">
               <i class="bi bi-arrow-left"></i>
               <span>Back to Site</span>
            </a>
            <a href="/logout.php" class="sidebar-link sidebar-link-logout">
               <i class="bi bi-box-arrow-right"></i>
               <span>Logout</span>
            </a>
         </div>
   </aside>

   <!--Header-->
   <header class="admin-header">
      <div class="header-left">
         <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
         </button>
         <span class="header-page-title">
            <?php
            $page_title = [
               'dashboard.php' => '',
               'users.php' => 'Users',
               'listings.php' => 'Listings',
               'reports.php' => 'Reports',
            ];
            echo $page_title[$current] ?? 'Admin';
            ?>
         </span>
      </div>

      <div class="header-right">
         <div class="header-user">
            <div class="header-user-avatar">
               <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 2)) ?>
            </div>
            <span class="header-user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
         </div>
      </div>
   </header>

   <main class="main-content">
   
<script>
   function toggleSidebar(){
      document.querySelector('.grid-container').classList.toggle('sidebar-collapsed')
   }
</script>