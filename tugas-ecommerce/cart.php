<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$user_id = current_user_id($con);
if (!$user_id) {
    $_SESSION['error'] = 'Masuk terlebih dahulu untuk melihat keranjang belanja.';
    header('Location: login.php?login_required=1');
    exit();
}

$cart_checkout_token = isset($_SESSION['cart_checkout_token'])
    ? $_SESSION['cart_checkout_token']
    : bin2hex(random_bytes(32));
$_SESSION['cart_checkout_token'] = $cart_checkout_token;

$cart_query = mysqli_query(
    $con,
    "SELECT c.id AS cart_id, c.p_id, c.qty, p.product_title, p.product_price, p.product_image
     FROM cart c JOIN products p ON c.p_id = p.product_id
     WHERE c.user_id = '" . (int)$user_id . "' ORDER BY c.id"
);
$cart_items = [];
$cart_count = 0;
if ($cart_query) {
    while ($cart_item = mysqli_fetch_assoc($cart_query)) {
        $cart_items[] = $cart_item;
        $cart_count += (int)$cart_item['qty'];
    }
} else {
    error_log('Gagal memuat keranjang pengguna: ' . mysqli_error($con));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Keranjang Belanja - Hogwarts Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'style.css'); ?>">
</head>
<body class="cart-page">
    <?php include __DIR__ . '/header.php'; ?>

    <main class="container cart-page-main">
        <a class="product-back-link mb-3 d-inline-flex" href="index.php#katalog-produk">
            <i class="fa fa-arrow-left" aria-hidden="true"></i><span>Kembali belanja</span>
        </a>
        <div class="cart-page-heading">
            <div>
                <span class="home-panel-eyebrow">HOGWARTS SHOP</span>
                <h1>Keranjang Belanja</h1>
                <p>Pilih barang yang ingin kamu pesan.</p>
            </div>
            <span class="cart-page-count"><i class="fa fa-shopping-bag mr-2"></i><?php echo $cart_count; ?> item</span>
        </div>

        <?php if (isset($_SESSION['success']) || isset($_SESSION['error'])): ?>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($cart_query && $cart_items): ?>
            <form id="cartCheckoutForm" method="POST" action="checkout.php">
                <input type="hidden" name="start_checkout" value="1">
                <input type="hidden" name="cart_checkout_csrf" value="<?php echo htmlspecialchars($cart_checkout_token, ENT_QUOTES, 'UTF-8'); ?>">
            </form>
            <div class="cart-page-card">
                <div class="table-responsive">
                    <table class="table cart-page-table mb-0">
                        <thead>
                            <tr>
                                <th class="text-center cart-select-column">
                                    <input type="checkbox" id="cartSelectAll" aria-label="Pilih semua barang">
                                </th>
                                <th colspan="2">Produk</th>
                                <th>Harga</th>
                                <th class="text-center">Jumlah</th>
                                <th>Subtotal</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $item):
                                $cart_id = (int)$item['cart_id'];
                                $product_id = (int)$item['p_id'];
                                $subtotal = (int)$item['product_price'] * (int)$item['qty'];
                            ?>
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" class="cart-item-checkbox" form="cartCheckoutForm" name="selected_cart_ids[]" value="<?php echo $cart_id; ?>" data-subtotal="<?php echo $subtotal; ?>" aria-label="Pilih <?php echo htmlspecialchars($item['product_title'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </td>
                                    <td class="cart-product-image">
                                        <img src="product_images/<?php echo rawurlencode(basename($item['product_image'])); ?>" alt="<?php echo htmlspecialchars($item['product_title'], ENT_QUOTES, 'UTF-8'); ?>" onerror="this.style.display='none';">
                                    </td>
                                    <td><a class="cart-product-title" href="product.php?id=<?php echo $product_id; ?>"><?php echo htmlspecialchars($item['product_title'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                                    <td class="text-nowrap">Rp <?php echo number_format((int)$item['product_price'], 0, ',', '.'); ?></td>
                                    <td class="text-center"><?php echo (int)$item['qty']; ?></td>
                                    <td class="text-nowrap font-weight-bold text-maroon">Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></td>
                                    <td class="text-center">
                                        <form method="POST" action="index.php">
                                            <input type="hidden" name="cart_item_id" value="<?php echo $cart_id; ?>">
                                            <input type="hidden" name="cart_product_id" value="<?php echo $product_id; ?>">
                                            <input type="hidden" name="return_to" value="cart">
                                            <button type="submit" name="delete_from_cart" class="btn btn-sm btn-outline-danger" aria-label="Hapus <?php echo htmlspecialchars($item['product_title'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fa fa-trash" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="cart-page-summary">
                    <div>
                        <span class="text-muted small">Subtotal barang dipilih (<span id="cartSelectedCount">0</span>)</span>
                        <strong id="cartSelectedTotal" data-total="0">Rp 0</strong>
                    </div>
                    <button type="submit" form="cartCheckoutForm" id="cartCheckoutButton" class="btn btn-hogwarts-primary font-weight-bold" disabled>
                        Lanjut ke Checkout <i class="fa fa-arrow-right ml-2" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        <?php elseif (!$cart_query): ?>
            <div class="cart-page-empty">
                <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                <h2>Keranjang belum dapat dimuat</h2>
                <p>Silakan muat ulang halaman atau coba kembali beberapa saat lagi.</p>
            </div>
        <?php else: ?>
            <div class="cart-page-empty">
                <i class="fa fa-shopping-basket" aria-hidden="true"></i>
                <h2>Keranjangmu masih kosong</h2>
                <p>Temukan perlengkapan sihir untuk petualangan berikutnya.</p>
                <a class="btn btn-hogwarts-primary font-weight-bold" href="index.php#katalog-produk">Mulai Belanja</a>
            </div>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
