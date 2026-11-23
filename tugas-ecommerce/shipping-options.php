<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/biteship.php';

header('Content-Type: application/json; charset=utf-8');

function shipping_options_reply($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    shipping_options_reply(405, ['error' => 'Metode permintaan tidak didukung.']);
}
if (!biteship_is_configured()) {
    shipping_options_reply(503, ['error' => 'Biteship belum dikonfigurasi. Admin perlu mengisi BITESHIP_API_KEY di .env.']);
}

$csrf_token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
    ? $_POST['csrf_token']
    : '';
$session_token = isset($_SESSION['checkout_csrf_token']) ? $_SESSION['checkout_csrf_token'] : '';
if ($session_token === '' || !hash_equals($session_token, $csrf_token)) {
    shipping_options_reply(403, ['error' => 'Sesi checkout kedaluwarsa. Muat ulang halaman.']);
}

$now = time();
if (!isset($_SESSION['biteship_requests']) || !is_array($_SESSION['biteship_requests']) ||
    !isset($_SESSION['biteship_requests']['started_at']) ||
    $now - (int)$_SESSION['biteship_requests']['started_at'] >= 60) {
    $_SESSION['biteship_requests'] = ['started_at' => $now, 'count' => 0];
}
if ((int)$_SESSION['biteship_requests']['count'] >= 20) {
    header('Retry-After: 60');
    shipping_options_reply(429, ['error' => 'Terlalu banyak permintaan ongkir. Coba lagi satu menit lagi.']);
}
$_SESSION['biteship_requests']['count']++;

$action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
if ($action === 'search_areas') {
    $query = isset($_POST['query']) && is_string($_POST['query']) ? trim($_POST['query']) : '';
    if (strlen($query) < 3 || strlen($query) > 120) {
        shipping_options_reply(400, ['error' => 'Masukkan minimal 3 karakter lokasi.']);
    }

    $areas = biteship_search_areas($query);
    if (!$areas) {
        shipping_options_reply(502, ['error' => 'Lokasi tidak ditemukan atau layanan Biteship sedang tidak tersedia.']);
    }
    $_SESSION['biteship_area_options'] = $areas;
    shipping_options_reply(200, ['areas' => $areas]);
}

if ($action !== 'get_rates') {
    shipping_options_reply(400, ['error' => 'Permintaan ongkir tidak valid.']);
}

$destination_area_id = isset($_POST['destination_area_id']) && is_string($_POST['destination_area_id'])
    ? $_POST['destination_area_id']
    : '';
$destination_city = isset($_POST['city']) && is_string($_POST['city']) ? trim($_POST['city']) : '';
$destination_state = isset($_POST['state']) && is_string($_POST['state']) ? trim($_POST['state']) : '';
$destination_zip = isset($_POST['zip']) && is_string($_POST['zip']) ? trim($_POST['zip']) : '';
$destination_address = isset($_POST['address']) && is_string($_POST['address']) ? trim($_POST['address']) : '';
$saved_address_id = isset($_POST['saved_address_id']) && is_string($_POST['saved_address_id'])
    ? max(0, (int)$_POST['saved_address_id'])
    : 0;
if ($destination_area_id === '' || $destination_city === '' || $destination_state === '' ||
    !preg_match('/^[0-9]{5}$/', $destination_zip) || $destination_address === '') {
    shipping_options_reply(400, ['error' => 'Lengkapi alamat dan pilih lokasi dari hasil pencarian sebelum menghitung ongkir.']);
}

$user_id = current_user_id($con);
$selected_area = null;
if ($saved_address_id < 1 && !empty($_SESSION['biteship_area_options']) && is_array($_SESSION['biteship_area_options'])) {
    foreach ($_SESSION['biteship_area_options'] as $area) {
        if (isset($area['id']) && hash_equals((string)$area['id'], $destination_area_id)) {
            $selected_area = $area;
            break;
        }
    }
}
if (!$selected_area && $saved_address_id > 0 && $user_id > 0) {
    $saved_address_query = mysqli_prepare(
        $con,
        'SELECT address, city, state, postal_code, destination_area_id
         FROM user_addresses WHERE id = ? AND user_id = ? LIMIT 1'
    );
    if (!$saved_address_query ||
        !mysqli_stmt_bind_param($saved_address_query, 'ii', $saved_address_id, $user_id) ||
        !mysqli_stmt_execute($saved_address_query)) {
        error_log('Unable to verify saved address for shipping quote: ' . mysqli_error($con));
        if ($saved_address_query) {
            mysqli_stmt_close($saved_address_query);
        }
        shipping_options_reply(500, ['error' => 'Alamat tersimpan belum dapat diperiksa.']);
    }
    $saved_address_result = mysqli_stmt_get_result($saved_address_query);
    $saved_address = $saved_address_result ? mysqli_fetch_assoc($saved_address_result) : null;
    mysqli_stmt_close($saved_address_query);
    if ($saved_address &&
        $saved_address['address'] === $destination_address &&
        $saved_address['city'] === $destination_city &&
        $saved_address['state'] === $destination_state &&
        (string)$saved_address['postal_code'] === $destination_zip &&
        $saved_address['destination_area_id'] === $destination_area_id) {
        $selected_area = [
            'id' => $destination_area_id,
            'postal_code' => $destination_zip,
        ];
    }
}
if (!$selected_area ||
    ($selected_area['postal_code'] !== '' && $selected_area['postal_code'] !== $destination_zip)) {
    shipping_options_reply(400, ['error' => 'Lokasi tujuan berubah. Cari dan pilih ulang lokasi pengiriman.']);
}

$selected_cart_ids = isset($_SESSION['checkout_selected_cart_ids']) && is_array($_SESSION['checkout_selected_cart_ids'])
    ? array_values(array_unique(array_filter(array_map('intval', $_SESSION['checkout_selected_cart_ids']), function ($id) {
        return $id > 0;
    })))
    : [];
if (!$selected_cart_ids) {
    shipping_options_reply(400, ['error' => 'Pilih barang yang ingin dibeli dari keranjang terlebih dahulu.']);
}
$selected_ids_sql = implode(',', $selected_cart_ids);
$ip_add = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
if ($user_id) {
    $cart_query = mysqli_prepare(
        $con,
        "SELECT c.id AS cart_id, c.p_id, c.qty, p.product_title, p.product_price
         FROM cart c JOIN products p ON c.p_id = p.product_id
         WHERE c.user_id = ? AND c.id IN ($selected_ids_sql) ORDER BY c.id"
    );
    if ($cart_query) {
        mysqli_stmt_bind_param($cart_query, 'i', $user_id);
    }
} else {
    $cart_query = mysqli_prepare(
        $con,
        "SELECT c.id AS cart_id, c.p_id, c.qty, p.product_title, p.product_price
         FROM cart c JOIN products p ON c.p_id = p.product_id
         WHERE c.ip_add = ? AND (c.user_id IS NULL OR c.user_id = 0) AND c.id IN ($selected_ids_sql) ORDER BY c.id"
    );
    if ($cart_query) {
        mysqli_stmt_bind_param($cart_query, 's', $ip_add);
    }
}
if (!$cart_query || !mysqli_stmt_execute($cart_query)) {
    error_log('Unable to load cart for Biteship quote: ' . mysqli_error($con));
    if ($cart_query) {
        mysqli_stmt_close($cart_query);
    }
    shipping_options_reply(500, ['error' => 'Keranjang belum dapat diperiksa.']);
}
$cart_result = mysqli_stmt_get_result($cart_query);
$items = [];
$rate_items = [];
$subtotal = 0;
while ($cart_result && ($item = mysqli_fetch_assoc($cart_result))) {
    $quantity = (int)$item['qty'];
    $unit_price = (int)$item['product_price'];
    if ($quantity < 1 || $unit_price < 0) {
        continue;
    }
    $items[] = $item;
    $subtotal += $quantity * $unit_price;
    $rate_items[] = [
        'name' => mb_substr((string)$item['product_title'], 0, 100, 'UTF-8'),
        'value' => $unit_price,
        'weight' => 1000,
        'quantity' => $quantity,
    ];
}
mysqli_stmt_close($cart_query);
if (!$items || $subtotal < 1) {
    shipping_options_reply(400, ['error' => 'Keranjang kosong. Tambahkan produk sebelum menghitung ongkir.']);
}

$origin_area_id = biteship_resolve_origin_area();
if ($origin_area_id === '') {
    shipping_options_reply(503, ['error' => 'Lokasi asal toko belum cocok dengan area Biteship. Admin perlu memeriksa BITESHIP_ORIGIN_AREA_ID di .env.']);
}

$rate_response = biteship_api_request('POST', '/v1/rates/couriers', [
    'origin_area_id' => $origin_area_id,
    'destination_area_id' => $destination_area_id,
    'couriers' => 'jne,jnt,sicepat,anteraja',
    'items' => $rate_items,
]);
if (!$rate_response || !isset($rate_response['pricing']) || !is_array($rate_response['pricing'])) {
    shipping_options_reply(502, ['error' => 'Tarif kurir belum dapat diperoleh dari Biteship. Periksa area tujuan dan saldo/akses akun.']);
}

$options = [];
$session_quotes = [];
foreach ($rate_response['pricing'] as $rate) {
    if (!is_array($rate)) {
        continue;
    }
    $courier = isset($rate['courier']) && is_array($rate['courier']) ? $rate['courier'] : [];
    $company_code = strtolower((string)(isset($rate['courier_code']) ? $rate['courier_code'] : (isset($courier['company']) ? $courier['company'] : '')));
    $normalized_company_code = preg_replace('/[^a-z0-9]/', '', $company_code);
    if (in_array($normalized_company_code, ['jt', 'jnt', 'jtexpress'], true)) {
        $company_code = 'jnt';
    } elseif (strpos($normalized_company_code, 'anteraja') === 0) {
        $company_code = 'anteraja';
    }
    $company_name = (string)(isset($rate['courier_name']) ? $rate['courier_name'] : (isset($courier['name']) ? $courier['name'] : strtoupper($company_code)));
    $service_code = (string)(isset($rate['courier_service_code']) ? $rate['courier_service_code'] : (isset($courier['type']) ? $courier['type'] : ''));
    $service_name = (string)(isset($rate['courier_service_name']) ? $rate['courier_service_name'] : (isset($courier['rate_type']) ? $courier['rate_type'] : $service_code));
    $price = isset($rate['price']) ? (int)$rate['price'] : (isset($courier['amount']) ? (int)$courier['amount'] : 0);
    if (!in_array($company_code, ['jne', 'jnt', 'sicepat', 'anteraja'], true) || $price < 0 || ($service_name === '' && $service_code === '')) {
        continue;
    }
    if ($service_name === '') {
        $service_name = $service_code;
    }

    $rate_id = bin2hex(random_bytes(16));
    $duration = isset($rate['duration']) && is_string($rate['duration'])
        ? $rate['duration']
        : (isset($courier['estimated_days'])
            ? (string)$courier['estimated_days'] . ' hari'
            : (isset($rate['shipment_duration_range']) ? (string)$rate['shipment_duration_range'] . ' ' . (isset($rate['shipment_duration_unit']) ? (string)$rate['shipment_duration_unit'] : 'hari') : ''));
    $session_quotes[$rate_id] = [
        'company_code' => $company_code,
        'company_name' => $company_name,
        'service_code' => $service_code,
        'service_name' => $service_name,
        'price' => $price,
        'duration' => $duration,
        'destination_area_id' => $destination_area_id,
        'destination_city' => $destination_city,
        'destination_state' => $destination_state,
        'destination_zip' => $destination_zip,
        'destination_address' => $destination_address,
        'destination_address_id' => $saved_address_id,
        'cart_fingerprint' => biteship_cart_fingerprint($items),
        'created_at' => time(),
    ];
    $options[] = [
        'rate_id' => $rate_id,
        'company' => $company_name,
        'service' => $service_name,
        'price' => $price,
        'duration' => $duration,
    ];
}

if (!$options) {
    shipping_options_reply(404, ['error' => 'Biteship tidak mengembalikan layanan JNE, J&T, SiCepat, atau Anteraja untuk rute ini. Coba alamat lain atau periksa cakupan dan akun Biteship.']);
}
$_SESSION['biteship_shipping_quotes'] = $session_quotes;
shipping_options_reply(200, ['options' => $options, 'subtotal' => $subtotal]);
