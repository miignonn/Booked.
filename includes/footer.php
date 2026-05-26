    </div> <!-- end container -->

<footer class="site-footer">
    <div class="site-footer__inner">
 
        <div class="site-footer__brand">
            <span class="site-footer__logo">Booked.</span>
            <p class="site-footer__tagline">A student-to-student second-hand textbook exchange. Built for South African campuses.</p>
        </div>
 
        <div class="site-footer__col">
            <p class="site-footer__col-title">Browse</p>
            <a href="/index.php" class="site-footer__link">Home</a>
            <a href="/browse.php" class="site-footer__link">All Listings</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/create-listing.php" class="site-footer__link">Sell a Book</a>
                <a href="/my-listings.php" class="site-footer__link">My Listings</a>
            <?php endif; ?>
        </div>
 
        <div class="site-footer__col">
            <p class="site-footer__col-title">Account</p>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/profile.php" class="site-footer__link">Profile</a>
                <a href="/orders.php" class="site-footer__link">My Orders</a>
                <a href="/logout.php" class="site-footer__link">Logout</a>
            <?php else: ?>
                <a href="/login.php" class="site-footer__link">Login</a>
                <a href="/login.php?tab=register" class="site-footer__link">Register</a>
            <?php endif; ?>
        </div>
 
    </div>
 
    <div class="site-footer__bottom">
        <p class="site-footer__copy">&copy; <?= date('Y') ?> Booked. All rights reserved.</p>
        <p class="site-footer__copy">Built for South African students.</p>
    </div>
</footer>
 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Nav dropdown
const btn = document.getElementById('navDropdownBtn');
const menu = document.getElementById('navDropdownMenu');
if (btn && menu) {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        menu.classList.toggle('site-nav__dropdown-menu--open');
    });
    document.addEventListener('click', function() {
        menu.classList.remove('site-nav__dropdown-menu--open');
    });
}
 
// Mobile hamburger
const hamburger = document.getElementById('navHamburger');
const mobileMenu = document.getElementById('navMobile');
if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', function() {
        mobileMenu.classList.toggle('site-nav__mobile--open');
    });
}
</script>
</body>
</html>
 
















