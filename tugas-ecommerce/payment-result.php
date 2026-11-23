<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/midtrans.php';

function payment_result_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$payment_order_id = isset($_GET['order_id']) && is_string($_GET['order_id'])
    ? $_GET['order_id']
    : '';
if (!preg_match('/^HS-[A-F0-9]{24}$/', $payment_order_id)) {
    http_response_code(404);
    exit('Pesanan tidak ditemukan.');
}

$order_query = mysqli_prepare(
    $con,
    'SELECT order_id, payment_status, payment_type, payment_url, subtotal_amt, shipping_cost,
            shipping_method, shipping_company, shipping_service, total_amt
     FROM orders_info WHERE payment_order_id = ?'
);
if (!$order_query || !mysqli_stmt_bind_param($order_query, 's', $payment_order_id) ||
    !mysqli_stmt_execute($order_query)) {
    error_log('Unable to load payment result: ' . mysqli_error($con));
    http_response_code(500);
    exit('Status pembayaran belum dapat dimuat.');
}
$order_result = mysqli_stmt_get_result($order_query);
$order = $order_result ? mysqli_fetch_assoc($order_result) : null;
mysqli_stmt_close($order_query);
if (!$order) {
    http_response_code(404);
    exit('Pesanan tidak ditemukan.');
}

// The random payment reference lets Midtrans return without an active login session.
$order_id = (int)$order['order_id'];
$items_query = mysqli_prepare(
    $con,
    'SELECT op.qty, op.amt, p.product_title
     FROM order_products op
     LEFT JOIN products p ON p.product_id = op.product_id
     WHERE op.order_id = ?
     ORDER BY op.order_pro_id ASC'
);
if (!$items_query || !mysqli_stmt_bind_param($items_query, 'i', $order_id) ||
    !mysqli_stmt_execute($items_query)) {
    error_log('Unable to load payment result items: ' . mysqli_error($con));
    if ($items_query) {
        mysqli_stmt_close($items_query);
    }
    http_response_code(500);
    exit('Detail barang pesanan belum dapat dimuat.');
}
$items_result = mysqli_stmt_get_result($items_query);
if (!$items_result) {
    error_log('Unable to read payment result items: ' . mysqli_stmt_error($items_query));
    mysqli_stmt_close($items_query);
    http_response_code(500);
    exit('Detail barang pesanan belum dapat dimuat.');
}
$items = [];
while ($item = mysqli_fetch_assoc($items_result)) {
    $items[] = $item;
}
mysqli_stmt_close($items_query);

$shipping_parts = [];
foreach (['shipping_company', 'shipping_service'] as $shipping_field) {
    $shipping_value = isset($order[$shipping_field]) ? trim((string)$order[$shipping_field]) : '';
    if ($shipping_value !== '') {
        $shipping_parts[] = $shipping_value;
    }
}
$shipping_label = $shipping_parts
    ? implode(' - ', $shipping_parts)
    : (isset($order['shipping_method']) ? trim((string)$order['shipping_method']) : '');
if ($shipping_label === '' || $shipping_label === '0') {
    $shipping_label = 'Belum tercatat';
}

$provider_error = false;
if (midtrans_is_configured()) {
    $provider_status = midtrans_api_request(
        'GET',
        '/v2/' . rawurlencode($payment_order_id) . '/status'
    );
    if ($provider_status) {
        if (!midtrans_apply_status($con, $payment_order_id, $provider_status)) {
            $provider_error = true;
        }
    } else {
        $provider_error = true;
    }

    $refresh_query = mysqli_prepare(
        $con,
        'SELECT payment_status, payment_type, payment_url, total_amt, order_status
         FROM orders_info WHERE payment_order_id = ?'
    );
    if ($refresh_query && mysqli_stmt_bind_param($refresh_query, 's', $payment_order_id) &&
        mysqli_stmt_execute($refresh_query)) {
        $refresh_result = mysqli_stmt_get_result($refresh_query);
        $refreshed_order = $refresh_result ? mysqli_fetch_assoc($refresh_result) : null;
        if ($refreshed_order) {
            $order = array_merge($order, $refreshed_order);
        }
    } else {
        error_log('Unable to refresh payment result: ' . mysqli_error($con));
        $provider_error = true;
    }
    if ($refresh_query) {
        mysqli_stmt_close($refresh_query);
    }
}

$payment_status_labels = [
    'pending' => 'Menunggu pembayaran',
    'paid' => 'Pembayaran berhasil',
    'paid_review' => 'Pembayaran diterima — perlu pemeriksaan toko',
    'failed' => 'Pembayaran gagal',
    'cancelled' => 'Pembayaran dibatalkan',
    'expired' => 'Waktu pembayaran berakhir',
    'refunded' => 'Pembayaran dikembalikan',
];
$payment_status = isset($payment_status_labels[$order['payment_status']])
    ? $order['payment_status']
    : 'pending';
$payment_alert_class = in_array($payment_status, ['paid', 'paid_review'], true)
    ? 'success'
    : ($payment_status === 'pending' ? 'warning' : 'danger');
$payment_icon = in_array($payment_status, ['paid', 'paid_review'], true)
    ? 'fa-check'
    : ($payment_status === 'pending' ? 'fa-clock-o' : 'fa-exclamation');
$payment_url = (string)$order['payment_url'];
$expected_payment_host = MIDTRANS_IS_PRODUCTION ? 'app.midtrans.com' : 'app.sandbox.midtrans.com';
$can_continue_payment = $payment_status === 'pending' &&
    strtolower((string)parse_url($payment_url, PHP_URL_SCHEME)) === 'https' &&
    strtolower((string)parse_url($payment_url, PHP_URL_HOST)) === $expected_payment_host;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Pesanan - Hogwarts Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'style.css'); ?>">
</head>
<body class="payment-result-page">
    <?php include "header.php"; ?>

    <main class="container payment-result-main">
        <nav class="payment-result-breadcrumb" aria-label="Breadcrumb">
            <a href="index.php">Beranda</a>
            <i class="fa fa-angle-right" aria-hidden="true"></i>
            <span>Detail pesanan</span>
        </nav>

        <section class="payment-result-card">
            <header class="payment-result-hero">
                <span class="payment-result-icon payment-result-icon-<?php echo payment_result_h($payment_alert_class); ?>">
                    <i class="fa <?php echo payment_result_h($payment_icon); ?>" aria-hidden="true"></i>
                </span>
                <div class="payment-result-heading">
                    <span class="payment-result-eyebrow">RINGKASAN TRANSAKSI</span>
                    <h1>Detail pesanan</h1>
                    <p>Pesanan #<?php echo (int)$order['order_id']; ?></p>
                </div>
                <span class="payment-result-status payment-result-status-<?php echo payment_result_h($payment_alert_class); ?>">
                    <?php echo payment_result_h($payment_status_labels[$payment_status]); ?>
                </span>
            </header>

            <div class="payment-result-body">
                <?php if ($provider_error): ?>
                    <div class="payment-result-notice" role="status">
                        <i class="fa fa-info-circle" aria-hidden="true"></i>
                        Status terbaru belum dapat diambil dari Midtrans. Silakan muat ulang halaman beberapa saat lagi.
                    </div>
                <?php endif; ?>

                <div class="payment-result-summary">
                    <div class="payment-result-summary-item payment-result-summary-total">
                        <span>Total pembayaran</span>
                        <strong>Rp <?php echo number_format((int)$order['total_amt'], 0, ',', '.'); ?></strong>
                    </div>
                    <div class="payment-result-summary-item">
                        <span>Jasa pengiriman</span>
                        <strong><?php echo payment_result_h($shipping_label); ?></strong>
                    </div>
                    <div class="payment-result-summary-item">
                        <span>Metode pembayaran</span>
                        <strong><?php echo payment_result_h(!empty($order['payment_type']) ? $order['payment_type'] : 'Belum tercatat'); ?></strong>
                    </div>
                </div>

                <section class="payment-result-items" aria-labelledby="paymentResultItemsTitle">
                    <div class="payment-result-section-heading">
                        <span class="payment-result-section-icon"><i class="fa fa-cubes" aria-hidden="true"></i></span>
                        <div>
                            <h2 id="paymentResultItemsTitle">Barang yang dibeli</h2>
                            <p>Rincian produk dalam pesanan ini</p>
                        </div>
                    </div>

                    <?php if ($items): ?>
                        <div class="table-responsive">
                            <table class="table payment-result-table">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th class="text-center">Jumlah</th>
                                        <th class="text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?php echo payment_result_h($item['product_title'] ?: 'Produk tidak lagi tersedia'); ?></td>
                                            <td class="text-center"><?php echo (int)$item['qty']; ?></td>
                                            <td class="text-right">Rp <?php echo number_format((int)$item['amt'], 0, ',', '.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2">Subtotal barang</td>
                                        <td class="text-right">Rp <?php echo number_format((int)$order['subtotal_amt'], 0, ',', '.'); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">Ongkos kirim</td>
                                        <td class="text-right">Rp <?php echo number_format((int)$order['shipping_cost'], 0, ',', '.'); ?></td>
                                    </tr>
                                    <tr class="payment-result-grand-total">
                                        <th colspan="2">Total pesanan</th>
                                        <th class="text-right">Rp <?php echo number_format((int)$order['total_amt'], 0, ',', '.'); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="payment-result-empty">Detail barang untuk pesanan ini tidak tersedia.</p>
                    <?php endif; ?>
                </section>

                <div class="payment-result-actions">
                    <?php if ($can_continue_payment): ?>
                        <a class="btn payment-result-button payment-result-button-primary" href="<?php echo payment_result_h($payment_url); ?>" rel="noopener">
                            <i class="fa fa-credit-card mr-2" aria-hidden="true"></i>Lanjutkan pembayaran
                        </a>
                    <?php endif; ?>
                    <a class="btn payment-result-button payment-result-button-secondary" href="user.php">Lihat riwayat pesanan</a>
                    <a class="payment-result-back-link" href="index.php">Kembali ke toko</a>
                </div>
            </div>
        </section>
    </main>

    <?php include "footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
