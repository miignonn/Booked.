<?php
require_once __DIR__ .'/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/require_admin.php';

//number of registered users
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];

//total active listings
$active_listings = $conn->query("SELECT COUNT(*) as count FROM listings WHERE status='available'")->fetch_assoc()['count'];

//total completed orders
$completed_sales = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status='completed'")->fetch_assoc()['count'];

//total pending orders
$pending_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status='pending'")->fetch_assoc()['count'];

//new users who registered
$new_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())")->fetch_assoc()['count'];


//total unresolved reports for admin review
$flagged_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status='pending'")->fetch_assoc()['count'];

//total revenue
$revenue = $conn->query("SELECT COALESCE(SUM(total_price), 0) as total FROM orders WHERE status='completed'")->fetch_assoc()['total'];


//Recent tables queries
//last 5 users who registered
$recent_users = $conn->query("
SELECT id, name, username, email, institution, role, status, created_at
FROM users
ORDER by created_at
LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

//last 5 reports submitted

$recent_reports = $conn->query("SELECT reports.id, reports.reason, reports.status, 
reports.created_at, 
listings.title AS listing_title, 
users.username AS reported_by 
FROM reports 
LEFT JOIN listings ON reports.listing_id = listings.id 
LEFT JOIN users ON reports.reported_by = users.id 
ORDER BY reports.created_at 
DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);


//last 5 orders placed 
$recent_orders = $conn->query("
SELECT orders.id, orders.status, orders.total_price, orders.created_at,
listings.title AS listing_title,
buyers.username AS buyer,
sellers.username AS seller
FROM orders
JOIN listings ON orders.listing_id = listings.id
JOIN users AS buyers ON orders.buyer_id = buyers.id
JOIN users AS sellers ON orders.seller_id = sellers.id
WHERE orders.seller_id IS NOT NULL
ORDER BY orders.created_at DESC
LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>



<!-- main section start -->
 <main class="main-content">

 <!-- Heading --> 
  <div class="admin-page-header">
    <h1 class="admin-page-title">Dashboard</h1>
    <p class="admin-page-sub">Here's what's happening on Booked.</p>
  </div>

  <!--- primary stat cards ---> 
  <!---- stat cards (platform metrics) ----> 
  <div class="stat-grid mb-4">

  <!--- total users registered ----> 
  <div class="stat-card">
    <div class="stat-icon"><i class="bi bi-people"></i></div>
    <div class="stat-label">Total Users</div>
    <div class="stat-value"><?= number_format($total_users) ?></div>
  </div>

  <!---- active listings ---->
  <div class="stat-card">
    <div class="stat-icon"><i class="bi bi-collection"></i></div>
    <div class="stat-label"> Active Listings</div>
    <div class="stat-value"><?=  number_format($active_listings) ?></div>
  </div>

  <!-- completed sales (full transaction flow done) --> 
   <div class="stat-card">
    <div class="stat-icon"><i class="bi bi-bag-check"></i></div>
    <div class="stat-label">Completed Sales </div>
    <div class="stat-value"><?= number_format($completed_sales) ?></div>
  </div>

  <!--- Total revenue from completed order ---> 
  <div class="stat-card">
    <div class="stat-icon"><i class="bi bi-cash"></i></div>
    <div class="stat-label">Revenue Processed</div>
    <div class="stat-value">R<?= number_format($revenue) ?></div>
  </div>
</div>

  <!--- secondary stat cards ---> 
  <div class="stat-grid stat-grid-3 mb-5">

  <!--- orders placed but not yet handed over--->
  <div class="stat-card stat-card--warning">
    <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
    <div class="stat-label">Pending Orders</div>
    <div class="stat-value"><?= number_format($pending_orders) ?></div> 
  </div>

  <!-- reports submitted ---> 
  <div class="stat-card stat-card--danger">
    <div class="stat-icon"><i class="bi bi-flag"></i></div>
    <div class="stat-label">Flagged Reports</div>
    <div class="stat-value"><?= number_format($flagged_reports) ?></div>
  </div>

  <!-- new user registrations this month ---> 
  <div class="stat-card stat-card--success">
    <div class="stat-icon"><i class="bi bi-person-plus"></i></div>
    <div class="stat-label">New Users This Month</div>
    <div class="stat-value"><?= number_format($new_users) ?></div> 
  </div>

  </div>

  <!--- recent activity tables --->
  <div class="admin-tables-grid">

  <!-- left table: recent registration-->
   
    <div class="admin-table-card">
    <div class="admin-table-header">
        <h2 class="admin-table-title">Recent Registrations</h2>
        <a href="/admin/users.php" class="admin-table-link">View all</a>
    </div>
    <table class="admin-table" style="table-layout:fixed;">
        <thead>
            <tr>
                <th style="width:40%;">User</th>
                <th style="width:30%;">Institution</th>
                <th style="width:30%;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recent_users)): ?>
                <tr><td colspan="3" class="admin-table-empty">No users yet.</td></tr>
            <?php else: ?>
                <?php foreach ($recent_users as $u): ?>
                    <tr>
                        <td>
                            <p class="table-main-text"><?= htmlspecialchars($u['username']) ?></p>
                            <p class="table-sub-text"><?= htmlspecialchars($u['email']) ?></p>
                        </td>
                        <td class="table-sub-text"><?= htmlspecialchars($u['institution'] ?? '—') ?></td>
                        <td>
                            <span class="admin-badge <?= match($u['status']) {
                                'active'    => 'badge-success',
                                'suspended' => 'badge-warning',
                                'banned'    => 'badge-danger',
                                 default     => 'badge-light'
                                } ?>">
                                <?= ucfirst($u['status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

   <!--- right table: recent reports --->
   <div class="admin-table-card">
    <div class="admin-table-header">
        <h2 class ="admin-table-title">Recent Reports</h2>
        <a href="/admin/reports.php" class="admin-table-link">View all</a>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Listing</th>
                <th>Reported By</th>
                <th>Reason</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($recent_reports)) :?>
                <tr>
                    <td colspan="4" class="admin-table-empty">No reports yet.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($recent_reports as $r): ?>
                <tr>
                    <td class="table-main-text"><?= htmlspecialchars($r['listing_title'] ?? 'Deleted listing') ?></td>
                    <td class="table-sub-text">@<?= htmlspecialchars($r['reported_by'] ?? '-') ?></td>
                    <td class="table-main-text"><?= htmlspecialchars($r['reason'] ?? '-') ?></td>

                    <td>
                        <!--- Status badge ---->
                        <span class="admin-badge <?= $r['status'] == 'resolved'? 'badge-success' : 'badge-warning' ?>">
                            <?= ucfirst($r['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                
        </tbody>
    </table>
   </div>

   <!---- Recent orders ---> 
   <div class="admin-table-card">
    <div class="admin-table-header">
        <h2 class="admin-table-title">Recent Orders</h2>
        <a href="/admin/orders.php" class="admin-table-link">View all</a>
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Listing</th>
                <th>Buyer</th>
                <th>Seller</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
    <?php if(empty($recent_orders)): ?>
        <tr><td colspan="7" class="admin-table-empty">No orders yet.</td></tr>
    <?php else: ?>
        <?php foreach ($recent_orders as $o): ?>
            <tr>
                <td class="table-sub-text">#<?= str_pad($o['id'], 5, '0', STR_PAD_LEFT) ?></td>
                <td class="table-sub-text"><?= htmlspecialchars($o['listing_title'] ?? '-') ?></td>
                <td class="table-sub-text">@<?= htmlspecialchars($o['buyer']) ?></td>
                <td class="table-sub-text">@<?= htmlspecialchars($o['seller']) ?></td>
                <td class="table-sub-text">R<?= number_format($o['total_price'], 2) ?></td>
                <td>
                    <span class="admin-badge <?= match($o['status']){
                        'completed'   => 'badge-success',
                        'handed_over' => 'badge-dark',
                        'pending'     => 'badge-warning',
                        'cancelled'   => 'badge-danger',
                        default       => 'badge-light',
                    } ?>">
                    <?= ucfirst(str_replace('_', ' ', $o['status'])) ?>
                </span>
                </td>
                <td class="table-sub-text"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</tbody>
</table>
</div>



  </div>

 </main>

<?php require_once '../../includes/admin-footer.php'; ?>