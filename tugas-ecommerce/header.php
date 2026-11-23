<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-hogwarts shadow-sm sticky-top shop-navbar">
    <div class="container shop-navbar-inner">
        <!-- Logo -->
        <a class="navbar-brand font-weight-bold shop-navbar-brand text-white" href="index.php">
            <i class="fa fa-bolt" style="color: #c59b27;"></i> Hogwarts Shop
        </a>
        
        <button class="navbar-toggler shop-navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Buka menu navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse shop-navbar-collapse" id="mainNavbar">
            <!-- Form Pencarian -->
            <form class="form-inline shop-search" action="index.php" method="GET" role="search">
                <div class="input-group">
                    <input type="search" class="form-control" name="search" aria-label="Cari produk"
                           placeholder="Cari Jubah, Tongkat Sihir, Buku Mantra..."
                           value="<?php echo isset($_GET['search']) && is_string($_GET['search']) ? htmlspecialchars($_GET['search'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                    <div class="input-group-append">
                        <button class="btn btn-hogwarts-search" type="submit" aria-label="Cari" style="background-color: #c59b27; color: #1c1817; border: 0;"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </form>

            <ul class="navbar-nav ml-auto align-items-center shop-navbar-actions">
                <?php if(isset($_SESSION['Name']) || isset($_SESSION['user_id'])): ?>
                    <!-- Keranjang Cuma Tampil Pas User Udah Login -->
                    <li class="nav-item shop-cart-item">
                        <a class="nav-link text-white font-weight-bold shop-cart-link" href="cart.php" aria-label="Keranjang belanja">
                            <i class="fa fa-shopping-cart fa-lg"></i> 
                            <span class="badge badge-warning badge-pill shop-cart-count"><?php echo isset($cart_count) ? (int)$cart_count : 0; ?></span>
                        </a>
                    </li>
                    
                    <!-- Dropdown User -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white font-weight-bold shop-account-link" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fa fa-user-circle fa-lg mr-1"></i> <span class="shop-account-name"><?php echo htmlspecialchars($_SESSION['Name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow border-0" aria-labelledby="userDropdown">
                            <a class="dropdown-item" href="user.php">
                                <i class="fa fa-user mr-2 text-primary"></i> Profil Saya & Pesanan
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="fa fa-sign-out mr-2"></i> Keluar
                            </a>
                        </div>
                    </li>
                <?php else: ?>
                    <!-- Tampilan Sebelum Login (Keranjang Di-hide) -->
                    <li class="nav-item"><a class="nav-link text-white font-weight-bold" href="register.php">Daftar</a></li>
                    <li class="nav-item border-left border-light ml-2 pl-2"><a class="nav-link text-white font-weight-bold" href="login.php">Masuk</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
