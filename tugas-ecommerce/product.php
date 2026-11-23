<?php
session_start();
include "config.php";
include "db.php";
include_once "auth.php";

$user_id = current_user_id($con);
if (!$user_id) {
    $_SESSION['error'] = "Silakan masuk atau daftar terlebih dahulu untuk melihat detail dan membeli produk.";
    header("Location: login.php?login_required=1");
    exit();
}

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product_query = mysqli_query(
    $con,
    "SELECT p.*, b.brand_title, c.cat_title
     FROM products p
     LEFT JOIN brands b ON p.product_brand = b.brand_id
     LEFT JOIN categories c ON p.product_cat = c.cat_id
     WHERE p.product_id = '$product_id'
     LIMIT 1"
);

if (!$product_query) {
    die("Gagal memuat detail produk: " . htmlspecialchars(mysqli_error($con), ENT_QUOTES, 'UTF-8'));
}

$product = mysqli_fetch_assoc($product_query);
if (!$product) {
    http_response_code(404);
}

$ip_add = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$cart_count_query = mysqli_query($con, "SELECT SUM(qty) AS total FROM cart WHERE user_id = '$user_id'");
$cart_count_result = $cart_count_query ? mysqli_fetch_assoc($cart_count_query) : null;
$cart_count = ($cart_count_result && $cart_count_result['total']) ? (int)$cart_count_result['total'] : 0;

$page_title = $product
    ? htmlspecialchars($product['product_title'], ENT_QUOTES, 'UTF-8') . ' - Hogwarts Shop'
    : 'Produk Tidak Ditemukan - Hogwarts Shop';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include "header.php"; ?>

    <main class="container my-4 my-md-5">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa fa-check-circle mr-1"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa fa-exclamation-circle mr-1"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        <?php endif; ?>

        <div class="product-back-link">
            <a href="index.php#katalog-produk" aria-label="Kembali ke katalog produk">
                <i class="fa fa-arrow-left" aria-hidden="true"></i>
                <span>Kembali ke katalog</span>
            </a>
        </div>

        <?php if ($product): ?>
            <?php
            $product_title = htmlspecialchars($product['product_title'], ENT_QUOTES, 'UTF-8');
            $product_image = rawurlencode(basename($product['product_image']));
            $product_stock = isset($product['product_qty']) ? (int)$product['product_qty'] : 0;
            $product_brand = !empty($product['brand_title']) ? $product['brand_title'] : 'Diagon Alley';
            $product_category = !empty($product['cat_title']) ? $product['cat_title'] : 'Koleksi Hogwarts';
            ?>
            <section class="product-detail-card">
                <div class="row no-gutters">
                    <div class="col-lg-6">
                        <div class="product-detail-image-wrap">
                            <span class="product-gallery-label"><i class="fa fa-bolt mr-1" style="color: #c59b27;" aria-hidden="true"></i>HOGWARTS SHOP</span>
                            <img src="product_images/<?php echo $product_image; ?>"
                                 class="product-detail-image"
                                 alt="<?php echo $product_title; ?>"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="product-detail-placeholder" aria-hidden="true">
                                <i class="fa fa-magic" style="color: #c59b27;"></i>
                                <span>Gambar produk belum tersedia</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="product-detail-info">
                            <div class="product-detail-topline">
                                <span class="product-category-pill"><?php echo htmlspecialchars($product_category, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="product-detail-code">PRODUK #<?php echo (int)$product['product_id']; ?></span>
                            </div>
                            <h1 class="product-detail-title"><?php echo $product_title; ?></h1>
                            <p class="product-seller">
                                <i class="fa fa-map-marker mr-1" style="color: #c59b27;" aria-hidden="true"></i>
                                Dijual oleh <strong><?php echo htmlspecialchars($product_brand, ENT_QUOTES, 'UTF-8'); ?></strong>
                            </p>

                            <div class="product-price-panel">
                                <span class="product-price-caption">Harga terbaik</span>
                                <strong class="product-detail-price">Rp <?php echo number_format((float)$product['product_price'], 0, ',', '.'); ?></strong>
                            </div>

                            <div class="product-detail-meta <?php echo $product_stock > 0 ? 'is-in-stock' : 'is-out-of-stock'; ?>">
                                <span class="product-stock-icon"><i class="fa <?php echo $product_stock > 0 ? 'fa-check' : 'fa-times'; ?>" aria-hidden="true"></i></span>
                                <span>
                                    <strong><?php echo $product_stock > 0 ? 'Stok tersedia' : 'Stok habis'; ?></strong>
                                    <?php if ($product_stock > 0): ?>
                                        <small><?php echo $product_stock; ?> item siap dikirim</small>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="product-description-block">
                                <h2><i class="fa fa-align-left mr-2" aria-hidden="true"></i>Deskripsi produk</h2>
                                <div class="product-detail-description">
                                    <?php if (!empty($product['product_desc'])): ?>
                                        <?php echo nl2br(htmlspecialchars($product['product_desc'], ENT_QUOTES, 'UTF-8')); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Deskripsi produk belum tersedia.</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="product-detail-benefits">
                                <div><i class="fa fa-paper-plane" style="color: #c59b27;" aria-hidden="true"></i><span>Pengiriman<br><strong>Burung Hantu</strong></span></div>
                                <div><i class="fa fa-shield" style="color: #c59b27;" aria-hidden="true"></i><span>Pembayaran<br><strong>Aman & praktis</strong></span></div>
                            </div>

                            <?php if ($product_stock > 0): ?>
                                <form action="index.php" method="GET" class="product-purchase-form">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="pid" value="<?php echo (int)$product['product_id']; ?>">
                                    <input type="hidden" name="return_to" value="product">
                                    <label for="productQuantity" class="product-quantity-label">Jumlah</label>
                                    <div class="product-purchase-controls">
                                        <input type="number" id="productQuantity" name="qty" class="form-control product-quantity-input"
                                               value="1" min="1" max="<?php echo $product_stock; ?>" required>
                                        <button type="submit" class="btn btn-hogwarts-primary btn-lg font-weight-bold">
                                            <i class="fa fa-shopping-cart mr-2" aria-hidden="true"></i>Tambahkan ke Keranjang
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary btn-lg btn-block" disabled>
                                    <i class="fa fa-times-circle mr-2" aria-hidden="true"></i>Stok Habis
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <section class="text-center bg-white rounded shadow-sm py-5 px-3">
                <i class="fa fa-search fa-3x text-muted mb-3" aria-hidden="true"></i>
                <h1 class="h4 font-weight-bold">Produk tidak ditemukan</h1>
                <p class="text-muted">Produk mungkin sudah dihapus atau tautannya tidak valid.</p>
                <a href="index.php" class="btn btn-hogwarts-primary font-weight-bold">Kembali ke toko</a>
            </section>
        <?php endif; ?>
    </main>

    <?php
    include "footer.php";
    ?>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
