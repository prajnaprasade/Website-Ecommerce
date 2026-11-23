<?php
session_start();
include "config.php"; 
include "db.php";     
include_once "auth.php";
require_once __DIR__ . '/midtrans.php';
require_once __DIR__ . '/biteship.php';

$ip_add = $_SERVER['REMOTE_ADDR'];
$user_id = current_user_id($con);
$error = isset($_SESSION['checkout_error']) ? $_SESSION['checkout_error'] : '';
unset($_SESSION['checkout_error']);
$checkout_csrf_token = isset($_SESSION['checkout_csrf_token'])
    ? $_SESSION['checkout_csrf_token']
    : bin2hex(random_bytes(32));
$_SESSION['checkout_csrf_token'] = $checkout_csrf_token;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_checkout'])) {
    $selection_token = isset($_POST['cart_checkout_csrf']) && is_string($_POST['cart_checkout_csrf'])
        ? $_POST['cart_checkout_csrf']
        : '';
    $expected_selection_token = isset($_SESSION['cart_checkout_token'])
        ? $_SESSION['cart_checkout_token']
        : '';
    $requested_ids = isset($_POST['selected_cart_ids']) && is_array($_POST['selected_cart_ids'])
        ? $_POST['selected_cart_ids']
        : [];
    $selected_cart_ids = [];
    foreach ($requested_ids as $requested_id) {
        if (is_scalar($requested_id) && ctype_digit((string)$requested_id) && (int)$requested_id > 0) {
            $selected_cart_ids[] = (int)$requested_id;
        }
    }
    $selected_cart_ids = array_values(array_unique($selected_cart_ids));
    if ($expected_selection_token === '' || !hash_equals($expected_selection_token, $selection_token)) {
        $_SESSION['error'] = 'Sesi keranjang kedaluwarsa. Muat ulang keranjang dan coba lagi.';
        header('Location: cart.php');
        exit();
    }
    if (!$selected_cart_ids) {
        $_SESSION['error'] = 'Pilih setidaknya satu barang sebelum melanjutkan ke checkout.';
        header('Location: cart.php');
        exit();
    }

    $selected_ids_sql = implode(',', $selected_cart_ids);
    if ($user_id) {
        $selection_query = mysqli_query(
            $con,
            "SELECT id FROM cart WHERE user_id = '" . (int)$user_id . "' AND id IN ($selected_ids_sql)"
        );
    } else {
        $selection_ip = mysqli_real_escape_string($con, $ip_add);
        $selection_query = mysqli_query(
            $con,
            "SELECT id FROM cart WHERE ip_add = '$selection_ip' AND (user_id IS NULL OR user_id = 0) AND id IN ($selected_ids_sql)"
        );
    }
    if (!$selection_query) {
        error_log('Gagal memvalidasi barang pilihan untuk checkout: ' . mysqli_error($con));
        $_SESSION['error'] = 'Barang pilihan belum dapat diperiksa. Coba lagi.';
        header('Location: cart.php');
        exit();
    }
    if (mysqli_num_rows($selection_query) !== count($selected_cart_ids)) {
        $_SESSION['error'] = 'Sebagian barang pilihan sudah berubah. Periksa kembali keranjangmu.';
        header('Location: cart.php');
        exit();
    }
    $_SESSION['checkout_selected_cart_ids'] = $selected_cart_ids;
    unset($_SESSION['biteship_shipping_quotes']);
    header('Location: checkout.php', true, 303);
    exit();
}

$selected_cart_ids = isset($_SESSION['checkout_selected_cart_ids']) && is_array($_SESSION['checkout_selected_cart_ids'])
    ? array_values(array_unique(array_filter(array_map('intval', $_SESSION['checkout_selected_cart_ids']), function ($id) {
        return $id > 0;
    })))
    : [];
if (!$selected_cart_ids) {
    $_SESSION['error'] = 'Pilih barang yang ingin dibeli dari keranjang terlebih dahulu.';
    header('Location: cart.php');
    exit();
}

$saved_addresses = [];
$default_address = null;
if ($user_id) {
    $addresses_query = mysqli_prepare(
        $con,
        'SELECT id, recipient_name, phone, address, city, state, postal_code, destination_area_id, is_default
         FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC'
    );
    if ($addresses_query && mysqli_stmt_bind_param($addresses_query, 'i', $user_id) &&
        mysqli_stmt_execute($addresses_query)) {
        $addresses_result = mysqli_stmt_get_result($addresses_query);
        while ($addresses_result && ($saved_address = mysqli_fetch_assoc($addresses_result))) {
            $saved_addresses[] = $saved_address;
            if ($default_address === null) {
                $default_address = $saved_address;
            }
        }
    } else {
        error_log('Gagal memuat alamat checkout pengguna: ' . mysqli_error($con));
    }
    if ($addresses_query) {
        mysqli_stmt_close($addresses_query);
    }
}

function checkout_abort_midtrans_order($con, $payment_order_id, $success_message) {
    if (midtrans_cancel_reserved_order($con, $payment_order_id)) {
        return $success_message;
    }

    error_log('Checkout cleanup could not confirm cancellation of Midtrans order ' . $payment_order_id . '.');
    return "Status pembayaran belum dapat dipastikan. Jangan mencoba membayar ulang; periksa riwayat pesanan atau hubungi admin toko.";
}

$selected_ids_sql = implode(',', $selected_cart_ids);
// Ambil item yang dipilih dari keranjang
if ($user_id) {
    $c_query = "SELECT c.id AS cart_id, c.p_id, c.qty, p.product_title, p.product_price, p.product_image, p.product_qty
                FROM cart c JOIN products p ON c.p_id = p.product_id WHERE c.user_id = '$user_id' AND c.id IN ($selected_ids_sql) ORDER BY c.id";
} else {
    $c_query = "SELECT c.id AS cart_id, c.p_id, c.qty, p.product_title, p.product_price, p.product_image, p.product_qty
                FROM cart c JOIN products p ON c.p_id = p.product_id WHERE c.ip_add = '$ip_add' AND (c.user_id IS NULL OR c.user_id = 0) AND c.id IN ($selected_ids_sql) ORDER BY c.id";
}

$c_res = mysqli_query($con, $c_query);

// Jika keranjang kosong dan tidak sedang submit checkout, kembalikan ke index.php
if ((!$c_res || mysqli_num_rows($c_res) == 0) && !isset($_POST['process_checkout'])) {
    $_SESSION['error'] = "Keranjang belanjamu kosong. Silakan pilih produk terlebih dahulu.";
    header("Location: index.php");
    exit();
}

// Hitung total item untuk badge di navbar
$cart_count = 0;
if ($user_id) {
    $cart_count_query = mysqli_query($con, "SELECT COALESCE(SUM(qty), 0) AS total FROM cart WHERE user_id = '" . (int)$user_id . "'");
} else {
    $cart_count_query = mysqli_query($con, "SELECT COALESCE(SUM(qty), 0) AS total FROM cart WHERE ip_add = '" . mysqli_real_escape_string($con, $ip_add) . "' AND (user_id IS NULL OR user_id = 0)");
}
if ($cart_count_query) {
    $cart_count_row = mysqli_fetch_assoc($cart_count_query);
    $cart_count = (int)$cart_count_row['total'];
}

// --- VALIDASI PESANAN DAN MULAI PEMBAYARAN MIDTRANS ---
if (isset($_POST['process_checkout'])) {
    $submitted_token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : '';
    if (!hash_equals($checkout_csrf_token, $submitted_token)) {
        $_SESSION['checkout_error'] = "Sesi checkout kedaluwarsa. Muat ulang halaman, lalu coba lagi.";
        header("Location: checkout.php");
        exit();
    }

    if (!midtrans_is_configured()) {
        $_SESSION['checkout_error'] = midtrans_configuration_error();
        header("Location: checkout.php");
        exit();
    }
    if (!biteship_is_configured()) {
        $_SESSION['checkout_error'] = "Ongkir belum dapat dihitung. Admin perlu mengisi BITESHIP_API_KEY di .env.";
        header("Location: checkout.php");
        exit();
    }

    $city = isset($_POST['city']) && is_string($_POST['city']) ? trim($_POST['city']) : '';
    $state = isset($_POST['state']) && is_string($_POST['state']) ? trim($_POST['state']) : '';
    $zip = isset($_POST['zip']) && is_string($_POST['zip']) ? trim($_POST['zip']) : '';
    if ($city === '' || strlen($city) > 100 || $state === '' || strlen($state) > 100 ||
        !preg_match('/^[0-9]{5}$/', $zip)) {
        $_SESSION['checkout_error'] = "Isi kota, provinsi, dan kode pos Indonesia yang valid.";
        header("Location: checkout.php");
        exit();
    }

    $f_name = isset($_POST['f_name']) && is_string($_POST['f_name']) ? trim($_POST['f_name']) : '';
    $email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) && is_string($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) && is_string($_POST['address']) ? trim($_POST['address']) : '';
    if ($f_name === '' || strlen($f_name) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 255 || !preg_match('/^[0-9+(). -]{8,30}$/', $phone) ||
        $address === '' || strlen($address) > 255) {
        $_SESSION['checkout_error'] = "Nama, email, nomor telepon, dan alamat harus diisi dengan benar.";
        header("Location: checkout.php");
        exit();
    }

    $rate_id = isset($_POST['shipping_rate_id']) && is_string($_POST['shipping_rate_id'])
        ? $_POST['shipping_rate_id']
        : '';
    $destination_area_id = isset($_POST['destination_area_id']) && is_string($_POST['destination_area_id'])
        ? $_POST['destination_area_id']
        : '';
    $saved_address_id = isset($_POST['saved_address_id']) && is_string($_POST['saved_address_id'])
        ? max(0, (int)$_POST['saved_address_id'])
        : 0;
    if ($saved_address_id > 0) {
        $saved_address_query = mysqli_prepare(
            $con,
            'SELECT recipient_name, phone, address, city, state, postal_code, destination_area_id
             FROM user_addresses WHERE id = ? AND user_id = ? LIMIT 1'
        );
        if (!$saved_address_query ||
            !mysqli_stmt_bind_param($saved_address_query, 'ii', $saved_address_id, $user_id) ||
            !mysqli_stmt_execute($saved_address_query)) {
            error_log('Gagal memvalidasi alamat tersimpan saat checkout: ' . mysqli_error($con));
            if ($saved_address_query) {
                mysqli_stmt_close($saved_address_query);
            }
            $_SESSION['checkout_error'] = 'Alamat tersimpan belum dapat diperiksa. Coba lagi.';
            header('Location: checkout.php');
            exit();
        }
        $saved_address_result = mysqli_stmt_get_result($saved_address_query);
        $saved_address = $saved_address_result ? mysqli_fetch_assoc($saved_address_result) : null;
        mysqli_stmt_close($saved_address_query);
        if (!$saved_address ||
            $saved_address['recipient_name'] !== $f_name ||
            $saved_address['phone'] !== $phone ||
            $saved_address['address'] !== $address ||
            $saved_address['city'] !== $city ||
            $saved_address['state'] !== $state ||
            (string)$saved_address['postal_code'] !== (string)$zip ||
            $saved_address['destination_area_id'] !== $destination_area_id) {
            $_SESSION['checkout_error'] = 'Alamat tersimpan berubah. Pilih ulang alamat atau hitung ulang ongkir.';
            header('Location: checkout.php');
            exit();
        }
    }
    $shipping_quotes = isset($_SESSION['biteship_shipping_quotes']) && is_array($_SESSION['biteship_shipping_quotes'])
        ? $_SESSION['biteship_shipping_quotes']
        : [];
    $shipping_quote = isset($shipping_quotes[$rate_id]) ? $shipping_quotes[$rate_id] : null;
    if (!$shipping_quote || time() - (int)$shipping_quote['created_at'] > 900 ||
        $shipping_quote['destination_area_id'] !== $destination_area_id ||
        $shipping_quote['destination_city'] !== $city ||
        $shipping_quote['destination_state'] !== $state ||
        $shipping_quote['destination_zip'] !== (string)$zip ||
        $shipping_quote['destination_address'] !== $address ||
        !isset($shipping_quote['destination_address_id']) ||
        (int)$shipping_quote['destination_address_id'] !== $saved_address_id) {
        $_SESSION['checkout_error'] = "Tarif pengiriman kedaluwarsa atau alamat berubah. Hitung ulang ongkir, lalu pilih layanan kurir.";
        header("Location: checkout.php");
        exit();
    }

    if (!mysqli_begin_transaction($con)) {
        $_SESSION['checkout_error'] = "Pesanan belum dapat diproses. Silakan coba lagi.";
        header("Location: checkout.php");
        exit();
    }

    $c_res_proc = mysqli_query($con, $c_query . ' FOR UPDATE');
    if (!$c_res_proc) {
        error_log('Gagal mengunci keranjang saat checkout: ' . mysqli_error($con));
        mysqli_rollback($con);
        $_SESSION['checkout_error'] = "Keranjang gagal diperiksa. Silakan coba lagi.";
        header("Location: checkout.php");
        exit();
    }

    $prod_count = 0;
    $subtotal_amt = 0;
    $cart_items = [];
    while ($item = mysqli_fetch_assoc($c_res_proc)) {
        $quantity = (int)$item['qty'];
        $stock = (int)$item['product_qty'];
        if ($quantity < 1 || $stock < $quantity) {
            mysqli_rollback($con);
            $_SESSION['checkout_error'] = "Stok salah satu produk tidak mencukupi. Perbarui keranjang, lalu coba lagi.";
            header("Location: checkout.php");
            exit();
        }
        $prod_count += $quantity;
        $subtotal_amt += ((int)$item['product_price'] * $quantity);
        $cart_items[] = $item;
    }

    if (!$cart_items || $prod_count < 1 || $subtotal_amt < 1) {
        mysqli_rollback($con);
        $_SESSION['checkout_error'] = "Keranjang belanjamu kosong.";
        header("Location: index.php");
        exit();
    }

    if (!hash_equals($shipping_quote['cart_fingerprint'], biteship_cart_fingerprint($cart_items))) {
        mysqli_rollback($con);
        $_SESSION['checkout_error'] = "Isi keranjang berubah setelah tarif dihitung. Hitung ulang ongkir sebelum melanjutkan.";
        header("Location: checkout.php");
        exit();
    }

    $shipping_cost = (int)$shipping_quote['price'];
    $total_amt = $subtotal_amt + $shipping_cost;
    $shipping_company = (string)$shipping_quote['company_name'];
    $shipping_service = (string)$shipping_quote['service_name'];
    $shipping_duration = (string)$shipping_quote['duration'];
    $shipping_method = mb_substr($shipping_company . ' - ' . $shipping_service, 0, 50, 'UTF-8');

    $payment_order_id = 'HS-' . strtoupper(bin2hex(random_bytes(12)));
    $order_user_id = $user_id ? (int)$user_id : 0;
    $insert_order = mysqli_prepare(
        $con,
        "INSERT INTO orders_info
         (ordered_at, payment_order_id, user_id, f_name, email, shipping_phone, address, city, state, zip, prod_count, total_amt,
          subtotal_amt, shipping_cost, shipping_method, shipping_company, shipping_service, shipping_duration, shipping_destination_area_id, order_status, payment_status)
         VALUES (CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')"
    );
    if (!$insert_order ||
        !mysqli_stmt_bind_param($insert_order, 'sisssssssiiiisssss', $payment_order_id, $order_user_id, $f_name, $email, $phone, $address, $city, $state, $zip, $prod_count, $total_amt, $subtotal_amt, $shipping_cost, $shipping_method, $shipping_company, $shipping_service, $shipping_duration, $destination_area_id) ||
        !mysqli_stmt_execute($insert_order)) {
        error_log('Gagal membuat pesanan Midtrans: ' . mysqli_error($con));
        if ($insert_order) {
            mysqli_stmt_close($insert_order);
        }
        mysqli_rollback($con);
        $_SESSION['checkout_error'] = "Pesanan gagal disimpan. Silakan coba lagi.";
        header("Location: checkout.php");
        exit();
    }
    $order_id = (int)mysqli_insert_id($con);
    mysqli_stmt_close($insert_order);

    $reserve_stock = mysqli_prepare(
        $con,
        'UPDATE products SET product_qty = product_qty - ? WHERE product_id = ? AND product_qty >= ?'
    );
    $insert_order_item = mysqli_prepare(
        $con,
        'INSERT INTO order_products (order_id, product_id, qty, amt) VALUES (?, ?, ?, ?)'
    );
    $insert_order_history = mysqli_prepare(
        $con,
        "INSERT INTO orders (user_id, product_id, qty, trx_id, p_status) VALUES (?, ?, ?, ?, 'Pending')"
    );
    if (!$reserve_stock || !$insert_order_item || !$insert_order_history) {
        error_log('Gagal menyiapkan detail pesanan Midtrans: ' . mysqli_error($con));
        if ($reserve_stock) {
            mysqli_stmt_close($reserve_stock);
        }
        if ($insert_order_item) {
            mysqli_stmt_close($insert_order_item);
        }
        if ($insert_order_history) {
            mysqli_stmt_close($insert_order_history);
        }
        mysqli_rollback($con);
        $_SESSION['checkout_error'] = "Detail pesanan gagal disiapkan. Silakan coba lagi.";
        header("Location: checkout.php");
        exit();
    }

    foreach ($cart_items as $item) {
        $product_id = (int)$item['p_id'];
        $quantity = (int)$item['qty'];
        $line_total = (int)$item['product_price'] * $quantity;
        if (!mysqli_stmt_bind_param($reserve_stock, 'iii', $quantity, $product_id, $quantity) ||
            !mysqli_stmt_execute($reserve_stock) ||
            mysqli_stmt_affected_rows($reserve_stock) !== 1 ||
            !mysqli_stmt_bind_param($insert_order_item, 'iiii', $order_id, $product_id, $quantity, $line_total) ||
            !mysqli_stmt_execute($insert_order_item) ||
            !mysqli_stmt_bind_param($insert_order_history, 'iiis', $order_user_id, $product_id, $quantity, $payment_order_id) ||
            !mysqli_stmt_execute($insert_order_history)) {
            error_log('Gagal menyimpan detail atau reservasi stok pesanan Midtrans: ' . mysqli_error($con));
            mysqli_stmt_close($reserve_stock);
            mysqli_stmt_close($insert_order_item);
            mysqli_stmt_close($insert_order_history);
            mysqli_rollback($con);
            $_SESSION['checkout_error'] = "Stok berubah atau pesanan gagal disimpan. Silakan periksa keranjang.";
            header("Location: checkout.php");
            exit();
        }
    }
    mysqli_stmt_close($reserve_stock);
    mysqli_stmt_close($insert_order_item);
    mysqli_stmt_close($insert_order_history);

    if (!mysqli_commit($con)) {
        error_log('Gagal menyimpan transaksi checkout.');
        mysqli_rollback($con);
        $_SESSION['checkout_error'] = "Pesanan gagal disimpan. Silakan coba lagi.";
        header("Location: checkout.php");
        exit();
    }

    $midtrans_items = [];
    foreach ($cart_items as $item) {
        $midtrans_items[] = [
            'id' => (string)(int)$item['p_id'],
            'price' => (int)$item['product_price'],
            'quantity' => (int)$item['qty'],
            'name' => mb_substr($item['product_title'], 0, 50, 'UTF-8'),
        ];
    }
    $midtrans_payload = [
        'transaction_details' => [
            'order_id' => $payment_order_id,
            'gross_amount' => $total_amt,
        ],
        'callbacks' => [
            'finish' => BASE_URL . 'payment-result.php?order_id=' . rawurlencode($payment_order_id),
        ],
        'item_details' => $midtrans_items,
        'customer_details' => [
            'first_name' => mb_substr($f_name, 0, 50, 'UTF-8'),
            'email' => $email,
            'phone' => $phone,
            'shipping_address' => [
                'first_name' => mb_substr($f_name, 0, 50, 'UTF-8'),
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
                'postal_code' => (string)$zip,
                'country_code' => 'IDN',
            ],
        ],
    ];
    $midtrans_payload['item_details'][] = [
        'id' => 'shipping',
        'price' => $shipping_cost,
        'quantity' => 1,
        'name' => mb_substr('Ongkir ' . $shipping_company . ' ' . $shipping_service, 0, 50, 'UTF-8'),
    ];
    $midtrans_response = midtrans_api_request('POST', '/snap/v1/transactions', $midtrans_payload);
    if (!$midtrans_response || empty($midtrans_response['redirect_url']) ||
        !filter_var($midtrans_response['redirect_url'], FILTER_VALIDATE_URL)) {
        $_SESSION['checkout_error'] = checkout_abort_midtrans_order(
            $con,
            $payment_order_id,
            "Midtrans belum dapat membuat halaman pembayaran. Pesanan lokal dibatalkan dan stok dikembalikan. Jika pembayaran sempat dilakukan, jangan bayar ulang; cek riwayat pesanan."
        );
        header("Location: checkout.php");
        exit();
    }

    $payment_url = (string)$midtrans_response['redirect_url'];
    $allowed_payment_host = MIDTRANS_IS_PRODUCTION ? 'app.midtrans.com' : 'app.sandbox.midtrans.com';
    if (strtolower((string)parse_url($payment_url, PHP_URL_HOST)) !== $allowed_payment_host ||
        strtolower((string)parse_url($payment_url, PHP_URL_SCHEME)) !== 'https') {
        error_log('Midtrans returned an unexpected payment redirect host.');
        $_SESSION['checkout_error'] = checkout_abort_midtrans_order(
            $con,
            $payment_order_id,
            "Midtrans mengembalikan alamat pembayaran yang tidak valid. Pesanan lokal dibatalkan dan stok dikembalikan."
        );
        header("Location: checkout.php");
        exit();
    }

    if (!mysqli_begin_transaction($con)) {
        $_SESSION['checkout_error'] = checkout_abort_midtrans_order(
            $con,
            $payment_order_id,
            "Checkout gagal diselesaikan. Pesanan lokal dibatalkan dan stok dikembalikan."
        );
        header("Location: checkout.php");
        exit();
    }
    $save_payment_url = mysqli_prepare(
        $con,
        'UPDATE orders_info SET payment_url = ? WHERE order_id = ? AND payment_status = ?'
    );
    $pending_status = 'pending';
    $delete_cart = $user_id
        ? mysqli_prepare($con, "DELETE FROM cart WHERE user_id = ? AND id IN ($selected_ids_sql)")
        : mysqli_prepare($con, "DELETE FROM cart WHERE ip_add = ? AND (user_id IS NULL OR user_id = 0) AND id IN ($selected_ids_sql)");
    $cart_owner = $user_id ? (int)$user_id : $ip_add;
    $owner_type = $user_id ? 'i' : 's';
    $saved = $save_payment_url && $delete_cart &&
        mysqli_stmt_bind_param($save_payment_url, 'sis', $payment_url, $order_id, $pending_status) &&
        mysqli_stmt_execute($save_payment_url) &&
        mysqli_stmt_bind_param($delete_cart, $owner_type, $cart_owner) &&
        mysqli_stmt_execute($delete_cart);
    if ($save_payment_url) {
        mysqli_stmt_close($save_payment_url);
    }
    if ($delete_cart) {
        mysqli_stmt_close($delete_cart);
    }
    if (!$saved || !mysqli_commit($con)) {
        error_log('Failed to save Midtrans URL or clear cart after order creation.');
        mysqli_rollback($con);
        $_SESSION['checkout_error'] = checkout_abort_midtrans_order(
            $con,
            $payment_order_id,
            "Checkout gagal diselesaikan. Pesanan lokal dibatalkan dan stok dikembalikan."
        );
        header("Location: checkout.php");
        exit();
    }

    unset($_SESSION['checkout_selected_cart_ids']);
    unset($_SESSION['biteship_shipping_quotes']);
    header('Location: ' . $payment_url, true, 303);
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout - Hogwarts Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
</head>
<body style="background-color: #f8f9fa;">

    <?php include "header.php"; ?>

    <main class="container my-4 my-md-5">
        <div class="product-back-link mb-3">
            <a href="index.php#katalog-produk" aria-label="Kembali ke belanja">
                <i class="fa fa-arrow-left" aria-hidden="true"></i>
                <span>Kembali ke katalog belanja</span>
            </a>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <span class="home-panel-eyebrow mb-1 text-maroon font-weight-bold" style="font-size: 0.78rem; letter-spacing: 1px;">SELESAIKAN PEMBELIAN</span>
                <h2 class="font-weight-bold text-dark mb-0"><i class="fa fa-credit-card mr-2 text-maroon"></i> Checkout</h2>
            </div>
        </div>

        <?php if($error !== ''): ?>
            <div class="alert alert-danger shadow-sm border-0"><i class="fa fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (!MIDTRANS_IS_PRODUCTION): ?>
            <div class="alert alert-warning shadow-sm border-0"><i class="fa fa-info-circle mr-2"></i>Midtrans masih dalam mode Sandbox; transaksi belum menarik pembayaran nyata. Untuk Production, gunakan Live Server Key dan atur MIDTRANS_IS_PRODUCTION=true di .env.</div>
        <?php endif; ?>

        <form method="POST" action="checkout.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($checkout_csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="row">
                <!-- Kolom Kiri: Alamat, pengiriman, dan pembayaran -->
                <div class="col-lg-7 mb-4">
                    <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 14px;">
                        <?php if (!empty($saved_addresses)): ?>
                            <div class="form-group">
                                <label for="checkoutSavedAddress" class="font-weight-bold small text-muted">PILIH ALAMAT TERSIMPAN</label>
                                <select class="form-control" id="checkoutSavedAddress">
                                    <option value="0" <?php echo $default_address ? '' : 'selected'; ?>>Isi alamat baru</option>
                                    <?php foreach ($saved_addresses as $saved_address): ?>
                                        <option value="<?php echo (int)$saved_address['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($saved_address['recipient_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-phone="<?php echo htmlspecialchars($saved_address['phone'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-address="<?php echo htmlspecialchars($saved_address['address'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-city="<?php echo htmlspecialchars($saved_address['city'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-state="<?php echo htmlspecialchars($saved_address['state'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-zip="<?php echo htmlspecialchars($saved_address['postal_code'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-area-id="<?php echo htmlspecialchars($saved_address['destination_area_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php echo $default_address && (int)$default_address['id'] === (int)$saved_address['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($saved_address['recipient_name'] . ' · ' . $saved_address['city'] . ($saved_address['is_default'] ? ' · Alamat utama' : ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <h5 class="font-weight-bold mb-3 text-maroon border-bottom pb-2">
                            <i class="fa fa-map-marker mr-2"></i> Alamat Pengiriman
                        </h5>
                        
                        <div class="form-group">
                            <label class="font-weight-bold small text-muted">NAMA LENGKAP</label>
                            <input type="text" name="f_name" id="checkoutName" class="form-control" placeholder="Nama penerima" required value="<?php echo htmlspecialchars($default_address ? $default_address['recipient_name'] : (isset($_SESSION['Name']) ? $_SESSION['Name'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold small text-muted">EMAIL</label>
                            <input type="email" name="email" class="form-control" placeholder="nama@email.com" required value="<?php echo htmlspecialchars(isset($_SESSION['email']) ? $_SESSION['email'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold small text-muted">NOMOR TELEPON PENERIMA</label>
                            <input type="tel" name="phone" id="checkoutPhone" class="form-control" placeholder="08xxxxxxxxxx" minlength="8" maxlength="30" required value="<?php echo htmlspecialchars($default_address ? $default_address['phone'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold small text-muted">ALAMAT LENGKAP</label>
                            <input type="text" name="address" id="checkoutAddress" class="form-control" maxlength="255" placeholder="Nama jalan, nomor rumah, RT/RW, patokan" required value="<?php echo htmlspecialchars($default_address ? $default_address['address'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-5">
                                <label class="font-weight-bold small text-muted">KECAMATAN / KOTA</label>
                                <input type="text" name="city" id="checkoutCity" class="form-control" maxlength="100" placeholder="Kecamatan / kota" autocomplete="off" required value="<?php echo htmlspecialchars($default_address ? $default_address['city'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="font-weight-bold small text-muted">KABUPATEN / PROVINSI</label>
                                <input type="text" name="state" id="checkoutState" class="form-control" maxlength="100" placeholder="Kabupaten / provinsi" required value="<?php echo htmlspecialchars($default_address ? $default_address['state'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="form-group col-md-3">
                                <label class="font-weight-bold small text-muted text-nowrap">KODE POS</label>
                                <input type="text" name="zip" id="checkoutZip" class="form-control" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" placeholder="Kode pos" required value="<?php echo htmlspecialchars($default_address ? $default_address['postal_code'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <input type="hidden" name="saved_address_id" id="savedAddressId" value="<?php echo $default_address ? (int)$default_address['id'] : 0; ?>">
                        <input type="hidden" name="destination_area_id" id="destinationAreaId" value="<?php echo htmlspecialchars($default_address ? $default_address['destination_area_id'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="shipping_rate_id" id="shippingRateId" value="">
                        <button type="button" class="btn btn-outline-secondary" id="searchShippingAreas">
                            <i class="fa fa-search mr-1"></i>Cari lokasi tujuan
                        </button>
                        <small class="form-text text-muted">Pilih salah satu hasil lokasi untuk menampilkan kurir dan ongkir.</small>
                        <div id="shippingAreaResults" class="mt-2" role="listbox" aria-label="Hasil lokasi pengiriman"></div>
                        <div id="selectedShippingArea" class="small text-success mt-2" role="status"></div>

                        <div class="border-top mt-3 pt-3">
                            <div id="shippingRateMessage" class="small mt-2" role="status"></div>
                            <div id="shippingRateOptions" class="mt-2"></div>
                            <small class="form-text text-muted mt-2">Tarif dihitung Biteship untuk JNE, J&amp;T, SiCepat, dan Anteraja. Sementara berat tiap produk diasumsikan 1 kg; ongkir dapat berubah bila berat produk sebenarnya berbeda.</small>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Ringkasan Pesanan -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm p-4 sticky-top" style="border-radius: 14px; top: 85px;">
                        <h5 class="font-weight-bold mb-3 border-bottom pb-2">
                            <i class="fa fa-shopping-bag mr-2 text-maroon"></i> Ringkasan Pesanan
                        </h5>
                        <ul class="list-group list-group-flush mb-3">
                            <?php 
                            $grand_total = 0;
                            mysqli_data_seek($c_res, 0); // Reset pointer loop
                            while($row = mysqli_fetch_assoc($c_res)): 
                                $subtotal = $row['product_price'] * $row['qty'];
                                $grand_total += $subtotal;
                            ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                                    <div class="d-flex align-items-center pr-2">
                                        <img src="product_images/<?php echo rawurlencode(basename($row['product_image'])); ?>" 
                                             width="44" height="44" 
                                             style="object-fit: contain; border-radius: 6px; background: #fff; border: 1px solid #e2dad0; padding: 2px;"
                                             class="mr-2"
                                             alt="<?php echo htmlspecialchars($row['product_title'], ENT_QUOTES, 'UTF-8'); ?>"
                                             onerror="this.src='https://via.placeholder.com/44?text=Item';">
                                        <div>
                                            <h6 class="my-0 font-weight-bold text-dark" style="font-size: 0.88rem;"><?php echo htmlspecialchars($row['product_title'], ENT_QUOTES, 'UTF-8'); ?></h6>
                                            <small class="text-muted"><?php echo (int)$row['qty']; ?> x Rp <?php echo number_format($row['product_price'], 0, ',', '.'); ?></small>
                                        </div>
                                    </div>
                                    <span class="font-weight-bold text-danger text-nowrap">Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                                </li>
                            <?php endwhile; ?>
                        </ul>

                        <div class="border-top pt-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted small">Subtotal Produk</span>
                                <span class="font-weight-bold" id="subtotalAmount" data-amount="<?php echo (int)$grand_total; ?>">Rp <?php echo number_format($grand_total, 0, ',', '.'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted small">Ongkos Kirim</span>
                                <span class="font-weight-bold" id="shippingCostDisplay">Pilih layanan kurir</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-4 pt-2 border-top">
                                <h5 class="font-weight-bold mb-0 text-dark">Total Tagihan</h5>
                                <h4 class="font-weight-bold text-danger mb-0" id="grandTotalDisplay">Rp <?php echo number_format($grand_total, 0, ',', '.'); ?></h4>
                            </div>

                            <button type="submit" name="process_checkout" id="processCheckout" class="btn btn-block font-weight-bold py-3 shadow-sm" style="background-color: #5a0000; color: #dbd3c7; border-radius: 8px; font-size: 1.05rem; transition: 0.3s;" data-checkout-ready="<?php echo midtrans_is_configured() && biteship_is_configured() ? '1' : '0'; ?>" disabled>
                                Pilih Kurir untuk Melanjutkan <i class="fa fa-arrow-right ml-1"></i>
                            </button>
                            <?php if (!midtrans_is_configured()): ?>
                                <small class="form-text text-danger mt-2"><?php echo htmlspecialchars(midtrans_configuration_error(), ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                            <?php if (!biteship_is_configured()): ?>
                                <small class="form-text text-danger mt-2">Biteship belum dikonfigurasi. Admin perlu mengisi BITESHIP_API_KEY di .env.</small>
                            <?php endif; ?>
                            
                            <a href="index.php" class="btn btn-link text-muted btn-block mt-2 small">
                                <i class="fa fa-arrow-left mr-1"></i> Batal dan Kembali ke Beranda
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <?php 
    include "footer.php"; 
    ?>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/checkout.js?v=<?php echo filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'checkout.js'); ?>"></script>
</body>
</html>
