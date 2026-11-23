<?php
$search_term = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';
$is_search_results = $search_term !== '';
$hero_products = [];
$hero_product_query = null;
if (!$is_search_results) {
    $hero_product_query = mysqli_query($con, "SELECT product_title, product_image FROM products ORDER BY product_id DESC LIMIT 3");
    if ($hero_product_query) {
        while ($hero_product = mysqli_fetch_assoc($hero_product_query)) {
            $hero_products[] = $hero_product;
        }
    } else {
        error_log('Unable to load featured products for homepage banner: ' . mysqli_error($con));
    }
}
?>
<div class="shop-homepage mb-5<?php echo $is_search_results ? ' shop-search-results' : ''; ?>">
    <?php if (!$is_search_results): ?>
    
    <!-- 1. Banner promo bergaya slider -->
    <div id="hogwartsPromotions" class="carousel slide shop-hero mb-4 overflow-hidden" data-ride="carousel" data-interval="6500">
        <ol class="carousel-indicators">
            <li data-target="#hogwartsPromotions" data-slide-to="0" class="active"></li>
            <li data-target="#hogwartsPromotions" data-slide-to="1"></li>
            <li data-target="#hogwartsPromotions" data-slide-to="2"></li>
        </ol>
        <div class="carousel-inner">
            <div class="carousel-item active">
                <div class="shop-hero-slide shop-hero-welcome">
                    <div class="shop-hero-copy">
                        <span class="shop-hero-eyebrow">HOGWARTS SHOP · TOKO PERLENGKAPAN SIHIR</span>
                        <?php if(isset($_SESSION['Name'])): ?>
                            <h2>Selamat datang kembali, <?php echo htmlspecialchars($_SESSION['Name'], ENT_QUOTES, 'UTF-8'); ?>!</h2>
                            <p>Temukan perlengkapan untuk petualangan sihirmu berikutnya.</p>
                            <a href="#katalog-produk" class="btn btn-hogwarts-hero font-weight-bold px-4">Lanjutkan Belanja</a>
                        <?php else: ?>
                            <h2>Temukan koleksi sihirmu</h2>
                            <p>Jelajahi koleksi jubah, buku mantra, dan aksesori pilihan.</p>
                            <a href="register.php" class="btn btn-hogwarts-hero font-weight-bold px-4">Daftar Sekarang</a>
                            <a href="#katalog-produk" class="btn btn-outline-light font-weight-bold px-4 ml-2">Lihat Produk</a>
                        <?php endif; ?>
                    </div>
                    <div class="shop-hero-art" aria-hidden="true">
                        <?php if (!empty($hero_products[0])): ?>
                            <div class="shop-hero-art-frame">
                                <img src="product_images/<?php echo rawurlencode(basename($hero_products[0]['product_image'])); ?>" alt="">
                                <span><?php echo htmlspecialchars($hero_products[0]['product_title'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        <?php else: ?>
                            <span class="shop-hero-mark"><i class="fa fa-bolt" style="color: #c59b27;"></i></span>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
            <div class="carousel-item">
                <div class="shop-hero-slide shop-hero-catalog">
                    <div class="shop-hero-copy">
                        <span class="shop-hero-eyebrow">KOLEKSI PILIHAN</span>
                        <h2>Perlengkapan untuk setiap penyihir</h2>
                        <p>Dari ruang kelas hingga perjalanan ke Hogsmeade, temukan barang favoritmu.</p>
                        <a href="#kategori-produk" class="btn btn-hogwarts-hero font-weight-bold px-4">Jelajahi Kategori</a>
                    </div>
                    <div class="shop-hero-art" aria-hidden="true">
                        <?php if (!empty($hero_products[1])): ?>
                            <div class="shop-hero-art-frame">
                                <img src="product_images/<?php echo rawurlencode(basename($hero_products[1]['product_image'])); ?>" alt="">
                                <span><?php echo htmlspecialchars($hero_products[1]['product_title'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        <?php else: ?>
                            <span class="shop-hero-mark"><i class="fa fa-magic" style="color: #c59b27;"></i></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="carousel-item">
                <div class="shop-hero-slide shop-hero-delivery">
                    <div class="shop-hero-copy">
                        <span class="shop-hero-eyebrow">PESANANMU SIAP BERANGKAT</span>
                        <h2>Kiriman Burung Hantu menantimu</h2>
                        <p>Pilih kurir JNE, J&amp;T, SiCepat, atau Anteraja saat checkout.</p>
                        <a href="#katalog-produk" class="btn btn-hogwarts-hero font-weight-bold px-4">Mulai Belanja</a>
                    </div>
                    <div class="shop-hero-art" aria-hidden="true">
                        <?php if (!empty($hero_products[2])): ?>
                            <div class="shop-hero-art-frame">
                                <img src="product_images/<?php echo rawurlencode(basename($hero_products[2]['product_image'])); ?>" alt="">
                                <span><?php echo htmlspecialchars($hero_products[2]['product_title'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        <?php else: ?>
                            <span class="shop-hero-mark"><i class="fa fa-paper-plane" style="color: #c59b27;"></i></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <a class="carousel-control-prev" href="#hogwartsPromotions" role="button" data-slide="prev" aria-label="Promo sebelumnya">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        </a>
        <a class="carousel-control-next" href="#hogwartsPromotions" role="button" data-slide="next" aria-label="Promo berikutnya">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
        </a>
    </div>
    <?php endif; ?>

    <div class="container shop-home-content">
    <?php if (!$is_search_results): ?>
    <section class="shop-benefits mb-4" aria-label="Informasi belanja">
        <a class="shop-benefit" href="#katalog-produk">
            <span class="shop-benefit-icon"><i class="fa fa-magic" aria-hidden="true"></i></span>
            <span><strong>Temukan perlengkapan sihir</strong><small>Jelajahi koleksi Hogwarts Shop</small></span>
            <i class="fa fa-angle-right shop-benefit-arrow" aria-hidden="true"></i>
        </a>
        <div class="shop-benefit">
            <span class="shop-benefit-icon"><i class="fa fa-paper-plane" aria-hidden="true"></i></span>
            <span><strong>Pilihan pengiriman</strong><small>JNE, J&amp;T, SiCepat, dan Anteraja</small></span>
        </div>
        <div class="shop-benefit">
            <span class="shop-benefit-icon"><i class="fa fa-shopping-bag" aria-hidden="true"></i></span>
            <span><strong>Pesanan dalam satu tempat</strong><small>Lihat riwayat melalui akunmu</small></span>
        </div>
    </section>

    <!-- 2. Kategori -->
    <section class="kategori-box shop-category-section p-3 p-md-4 rounded mb-4 bg-white shadow-sm" id="kategori-produk">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <span class="home-panel-eyebrow mb-1">PILIH DUNIA FAVORITMU</span>
                <h2 class="font-weight-bold mb-0">Belanja berdasarkan kategori</h2>
            </div>
            <a href="#katalog-produk" class="shop-category-all">Lihat semua <i class="fa fa-arrow-right ml-1" aria-hidden="true"></i></a>
        </div>
        <div class="shop-category-grid">
            <?php
            $query_cat = mysqli_query($con, "SELECT * FROM categories");
            $icons = ['fa-shopping-bag', 'fa-home', 'fa-book', 'fa-paint-brush', 'fa-magic'];
            $i = 0;
            $category_cards = [];
            while($cat = mysqli_fetch_assoc($query_cat)):
                $category_cards[] = $cat;
                $icon = $icons[$i % count($icons)];
                $i++;
            ?>
                <a href="index.php?cat_id=<?php echo (int)$cat['cat_id']; ?>#katalog-produk" class="category-link text-decoration-none text-dark">
                        <div class="category-icon mb-2 d-flex align-items-center justify-content-center">
                            <i class="fa <?php echo $icon; ?> fa-lg text-maroon" aria-hidden="true"></i>
                        </div>
                        <span class="category-link-title"><?php echo htmlspecialchars($cat['cat_title'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <i class="fa fa-angle-right category-link-arrow" aria-hidden="true"></i>
                </a>
            <?php endwhile; ?>
        </div>
    </section>

    <?php
    $featured_categories = array_slice($category_cards, 0, 2);
    if (count($featured_categories) > 0):
    ?>
    <section class="shop-feature-collections" aria-label="Koleksi pilihan">
        <?php foreach ($featured_categories as $featured_category):
            $featured_category_id = (int)$featured_category['cat_id'];
            $featured_product_query = mysqli_query($con, "SELECT product_title, product_image FROM products WHERE product_cat = '$featured_category_id' ORDER BY product_id DESC LIMIT 1");
            $featured_product = $featured_product_query ? mysqli_fetch_assoc($featured_product_query) : null;
            if (!$featured_product_query) {
                error_log('Unable to load featured category product: ' . mysqli_error($con));
            }
        ?>
            <a class="shop-feature-card" href="index.php?cat_id=<?php echo $featured_category_id; ?>#katalog-produk">
                <span class="shop-feature-copy">
                    <small>KATEGORI PILIHAN</small>
                    <strong><?php echo htmlspecialchars($featured_category['cat_title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span>Jelajahi koleksi <i class="fa fa-arrow-right" aria-hidden="true"></i></span>
                </span>
                <?php if ($featured_product): ?>
                    <img src="product_images/<?php echo rawurlencode(basename($featured_product['product_image'])); ?>"
                         alt="<?php echo htmlspecialchars($featured_product['product_title'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php else: ?>
                    <span class="shop-feature-icon" aria-hidden="true"><i class="fa fa-magic" style="color: #c59b27;"></i></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <?php endif; ?>

    <!-- 3. Header Katalog -->
    <div class="catalog-heading d-flex justify-content-between align-items-end mb-3" id="katalog-produk">
        <div>
        <span class="home-panel-eyebrow mb-1">KOLEKSI HOGWARTS SHOP</span>
        <h2 class="font-weight-bold mb-1">
            <?php 
            $catalog_title = "Rekomendasi Untukmu";
            if(isset($_GET['cat_id'])) {
                $c_id = (int)$_GET['cat_id'];
                $get_c_name = mysqli_query($con, "SELECT cat_title FROM categories WHERE cat_id=$c_id");
                $c_name = $get_c_name ? mysqli_fetch_assoc($get_c_name) : null;
                if ($c_name) {
                    $catalog_title = "Kategori: " . $c_name['cat_title'];
                }
            }
            if ($search_term !== '') {
                $catalog_title = "Hasil pencarian: " . $search_term;
            }
            echo htmlspecialchars($catalog_title, ENT_QUOTES, 'UTF-8');
            ?>
        </h2>
        <p class="catalog-subtitle mb-0">Pilih perlengkapan favoritmu dan siapkan petualangan berikutnya.</p>
        </div>

        <?php if(isset($_GET['cat_id']) || isset($_GET['search'])): ?>
            <a href="index.php#katalog-produk" class="btn btn-sm font-weight-bold px-3 py-1 shadow-sm" style="background-color: #5a0000; color: #f5f0e8; border: 1px solid #c59b27; border-radius: 20px;">
                <i class="fa fa-undo mr-1" style="color: #c59b27;"></i> Tampilkan Semua Produk
            </a>
        <?php endif; ?>
    </div>

    <!-- 4. Grid Produk -->
    <div class="row">
        <?php
        $filters = [];
        if(isset($_GET['cat_id'])) {
            $cat_id = (int)$_GET['cat_id'];
            $filters[] = "p.product_cat = $cat_id";
        }

        if ($search_term !== '') {
            $search_sql = mysqli_real_escape_string($con, $search_term);
            $search_pattern = "'%" . $search_sql . "%'";
            $filters[] = "(p.product_title LIKE $search_pattern
                OR p.product_desc LIKE $search_pattern
                OR p.product_keywords LIKE $search_pattern
                OR b.brand_title LIKE $search_pattern)";
        }

        $where_clause = $filters ? " WHERE " . implode(" AND ", $filters) : "";
        $query_prod = "SELECT p.*, b.brand_title FROM products p LEFT JOIN brands b ON p.product_brand = b.brand_id $where_clause";
        $run_prod = mysqli_query($con, $query_prod);
        $is_logged_in = isset($user_id) && (int)$user_id > 0;

        if($run_prod && mysqli_num_rows($run_prod) > 0):
            while($row = mysqli_fetch_assoc($run_prod)):
                $stok = isset($row['product_qty']) ? $row['product_qty'] : 10;
                $product_id = (int)$row['product_id'];
                $product_name = htmlspecialchars($row['product_title'], ENT_QUOTES, 'UTF-8');
        ?>
            <div class="col-6 col-md-4 col-xl-3 mb-4">
                <article class="card h-100 border-0 shadow-sm product-card">
                    <?php if ($is_logged_in): ?>
                    <a href="product.php?id=<?php echo $product_id; ?>" class="product-image-link" aria-label="Lihat detail <?php echo $product_name; ?>">
                    <?php else: ?>
                    <a class="product-image-link" href="login.php" aria-label="Masuk untuk melihat detail <?php echo $product_name; ?>">
                    <?php endif; ?>
                    <img src="product_images/<?php echo rawurlencode(basename($row['product_image'])); ?>" 
                         class="card-img-top" 
                         alt="<?php echo $product_name; ?>"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                    >
                    <span class="product-card-image-placeholder" aria-hidden="true"><i class="fa fa-magic" style="color: #c59b27;"></i></span>
                    <?php if ($is_logged_in): ?>
                    </a>
                    <?php else: ?>
                    </a>
                    <?php endif; ?>
                    
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div>
                            <h6 class="card-title text-truncate mb-1" style="font-size: 0.95rem;" title="<?php echo htmlspecialchars($row['product_title'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if ($is_logged_in): ?>
                                <a href="product.php?id=<?php echo (int)$row['product_id']; ?>" class="product-title-link">
                                    <?php echo $product_name; ?>
                                </a>
                                <?php else: ?>
                                <a class="text-dark font-weight-bold" href="login.php">
                                    <?php echo $product_name; ?>
                                </a>
                                <?php endif; ?>
                            </h6>
                            <h5 class="price mb-1 font-weight-bold text-maroon" style="font-size: 1.05rem;">
                                Rp <?php echo number_format($row['product_price'], 0, ',', '.'); ?>
                            </h5>
                            <?php if (!empty($row['product_desc'])): ?>
                                <p class="product-card-description mb-2"><?php echo htmlspecialchars($row['product_desc'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                            <!-- Informasi Stok dan Toko -->
                            <div class="d-flex justify-content-between align-items-center mb-2" style="font-size: 0.75rem;">
                                <span class="text-muted"><i class="fa fa-map-marker mr-1" style="color: #c59b27;"></i> <?php echo htmlspecialchars($row['brand_title'] ? $row['brand_title'] : 'Diagon Alley', ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="badge border" style="background: #faf7f2; border-color: #ebdccb !important; color: #5a0000;"><i class="fa fa-archive mr-1" style="color: #c59b27;"></i> Stok: <?php echo $stok; ?></span>
                            </div>
                        </div>
                        
                        <?php if ($is_logged_in): ?>
                            <div>
                                <a href="product.php?id=<?php echo $product_id; ?>" class="btn btn-sm btn-block btn-outline-hogwarts font-weight-bold mt-2">
                                    <i class="fa fa-eye"></i> Lihat Detail
                                </a>
                                <a href="index.php?action=add_to_cart&amp;pid=<?php echo $product_id; ?>" class="btn btn-sm btn-block btn-hogwarts-primary font-weight-bold mt-2">
                                    <i class="fa fa-shopping-cart"></i> + Keranjang
                                </a>
                            </div>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-sm btn-block btn-guest-lock font-weight-bold mt-2" role="button">
                                <i class="fa fa-lock mr-1"></i> Masuk untuk Belanja
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
            </div>
        <?php 
            endwhile;
        else:
        ?>
            <div class="col-12 text-center py-5">
                <p class="text-muted">
                    <?php echo $search_term !== '' ? 'Tidak ada produk yang cocok dengan pencarianmu.' : 'Belum ada produk untuk kategori ini.'; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <section class="shop-reasons" aria-labelledby="shopReasonsHeading">
        <div class="shop-reasons-heading">
            <span class="home-panel-eyebrow">PENGALAMAN BELANJA</span>
            <h2 id="shopReasonsHeading">Semua kebutuhan sihirmu, lebih mudah</h2>
        </div>
        <div class="shop-reasons-grid">
            <article class="shop-reason-card">
                <span class="shop-reason-icon"><i class="fa fa-magic" aria-hidden="true"></i></span>
                <h3>Koleksi pilihan</h3>
                <p>Jelajahi perlengkapan sihir berdasarkan kategori yang tersedia.</p>
            </article>
            <article class="shop-reason-card">
                <span class="shop-reason-icon"><i class="fa fa-paper-plane" aria-hidden="true"></i></span>
                <h3>Pilihan pengiriman</h3>
                <p>Pilih JNE, J&amp;T, SiCepat, atau Anteraja saat checkout.</p>
            </article>
            <article class="shop-reason-card">
                <span class="shop-reason-icon"><i class="fa fa-shopping-bag" aria-hidden="true"></i></span>
                <h3>Pesanan tercatat</h3>
                <p>Lihat kembali pesananmu melalui halaman akun.</p>
            </article>
            <article class="shop-reason-card">
                <span class="shop-reason-icon"><i class="fa fa-comments" aria-hidden="true"></i></span>
                <h3>Bantuan Lumos</h3>
                <p>Tanyakan panduan belanja dan informasi layanan toko kepada Lumos.</p>
            </article>
        </div>
    </section>
</div>
