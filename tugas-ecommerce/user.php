<?php
session_start();
include "config.php";
include "db.php";

if (!isset($_SESSION['Name']) && !isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu untuk mengakses profil.";
    header("Location: login.php?login_required=1");
    exit();
}

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$user_info = null;
$profile_csrf_token = isset($_SESSION['profile_csrf_token'])
    ? $_SESSION['profile_csrf_token']
    : bin2hex(random_bytes(32));
$_SESSION['profile_csrf_token'] = $profile_csrf_token;
$checkout_csrf_token = isset($_SESSION['checkout_csrf_token'])
    ? $_SESSION['checkout_csrf_token']
    : bin2hex(random_bytes(32));
$_SESSION['checkout_csrf_token'] = $checkout_csrf_token;

if ($user_id > 0) {
    $user_query = mysqli_query($con, "SELECT id, username, email, phone, gender, birth_date FROM users WHERE id = '$user_id' LIMIT 1");
    if ($user_query && mysqli_num_rows($user_query) > 0) {
        $user_info = mysqli_fetch_assoc($user_query);
    }
}

if (!$user_info && isset($_SESSION['Name'])) {
    $session_name = mysqli_real_escape_string($con, $_SESSION['Name']);
    $user_query = mysqli_query($con, "SELECT id, username, email, phone, gender, birth_date FROM users WHERE username = '$session_name' LIMIT 1");
    if ($user_query && mysqli_num_rows($user_query) > 0) {
        $user_info = mysqli_fetch_assoc($user_query);
        $user_id = (int)$user_info['id'];
        $_SESSION['user_id'] = $user_id;
    }
}

$profile_feedback = isset($_SESSION['profile_feedback']) ? $_SESSION['profile_feedback'] : null;
unset($_SESSION['profile_feedback']);
$address_feedback = isset($_SESSION['address_feedback']) ? $_SESSION['address_feedback'] : null;
unset($_SESSION['address_feedback']);
$address_feedback_error = false;
$address_form_error = false;
$address_form_values = [];
$profile_feedback_error = false;
$profile_name_value = $user_info ? $user_info['username'] : (isset($_SESSION['Name']) ? $_SESSION['Name'] : 'Penyihir');
$profile_phone_value = $user_info && isset($user_info['phone']) ? $user_info['phone'] : '';
$profile_gender_value = $user_info && isset($user_info['gender']) ? $user_info['gender'] : '';
$profile_birth_date_value = $user_info && isset($user_info['birth_date']) ? $user_info['birth_date'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_address') {
    $address_form_error = true;
    $submitted_token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $address_id = isset($_POST['address_id']) && is_string($_POST['address_id']) ? max(0, (int)$_POST['address_id']) : 0;
    $recipient_name = isset($_POST['recipient_name']) && is_string($_POST['recipient_name']) ? trim($_POST['recipient_name']) : '';
    $phone = isset($_POST['address_phone']) && is_string($_POST['address_phone']) ? trim($_POST['address_phone']) : '';
    $address_detail = isset($_POST['address_detail']) && is_string($_POST['address_detail']) ? trim($_POST['address_detail']) : '';
    $city = isset($_POST['address_city']) && is_string($_POST['address_city']) ? trim($_POST['address_city']) : '';
    $state = isset($_POST['address_state']) && is_string($_POST['address_state']) ? trim($_POST['address_state']) : '';
    $postal_code = isset($_POST['address_postal']) && is_string($_POST['address_postal']) ? trim($_POST['address_postal']) : '';
    $area_id = isset($_POST['destination_area_id']) && is_string($_POST['destination_area_id']) ? trim($_POST['destination_area_id']) : '';
    $make_default = isset($_POST['is_default']) && $_POST['is_default'] === '1';
    $address_form_values = [
        'address_id' => $address_id,
        'recipient_name' => $recipient_name,
        'phone' => $phone,
        'address' => $address_detail,
        'city' => $city,
        'state' => $state,
        'postal_code' => $postal_code,
        'area_id' => $area_id,
        'is_default' => $make_default,
    ];
    $existing_address = null;

    if (!hash_equals($profile_csrf_token, $submitted_token)) {
        $address_feedback = 'Sesi formulir tidak valid. Muat ulang halaman, lalu coba lagi.';
        $address_feedback_error = true;
    } elseif ($user_id < 1 || !$user_info) {
        $address_feedback = 'Akun tidak ditemukan. Silakan masuk kembali.';
        $address_feedback_error = true;
    } elseif ($recipient_name === '' || strlen($recipient_name) > 120 ||
        !preg_match('/^[0-9+(). -]{8,30}$/', $phone) ||
        $address_detail === '' || strlen($address_detail) > 255 ||
        $city === '' || strlen($city) > 100 ||
        $state === '' || strlen($state) > 100 ||
        !preg_match('/^[0-9]{5}$/', $postal_code) ||
        $area_id === '' || strlen($area_id) > 100) {
        $address_feedback = 'Lengkapi nama, telepon, alamat, kota, provinsi, kode pos, dan lokasi kurir yang valid.';
        $address_feedback_error = true;
    } else {
        if ($address_id > 0) {
            $existing_query = mysqli_prepare(
                $con,
                'SELECT id, city, state, postal_code, destination_area_id, is_default
                 FROM user_addresses WHERE id = ? AND user_id = ? LIMIT 1'
            );
            if ($existing_query &&
                mysqli_stmt_bind_param($existing_query, 'ii', $address_id, $user_id) &&
                mysqli_stmt_execute($existing_query)) {
                $existing_result = mysqli_stmt_get_result($existing_query);
                $existing_address = $existing_result ? mysqli_fetch_assoc($existing_result) : null;
            } else {
                error_log('Gagal memeriksa alamat pengguna: ' . mysqli_error($con));
            }
            if ($existing_query) {
                mysqli_stmt_close($existing_query);
            }
            if (!$existing_address) {
                $address_feedback = 'Alamat tidak ditemukan. Muat ulang buku alamat, lalu coba lagi.';
                $address_feedback_error = true;
            }
        }

        $area_was_selected = false;
        if (!$address_feedback_error && !empty($_SESSION['biteship_area_options']) &&
            is_array($_SESSION['biteship_area_options'])) {
            foreach ($_SESSION['biteship_area_options'] as $area) {
                if (isset($area['id']) && hash_equals((string)$area['id'], $area_id) &&
                    (empty($area['postal_code']) || (string)$area['postal_code'] === $postal_code)) {
                    $area_was_selected = true;
                    break;
                }
            }
        }
        $area_matches_saved_address = $existing_address &&
            $existing_address['destination_area_id'] === $area_id &&
            $existing_address['city'] === $city &&
            $existing_address['state'] === $state &&
            (string)$existing_address['postal_code'] === $postal_code;
        if (!$address_feedback_error && !$area_was_selected && !$area_matches_saved_address) {
            $address_feedback = 'Cari dan pilih lokasi dari hasil pencarian sebelum menyimpan alamat.';
            $address_feedback_error = true;
        }

        if (!$address_feedback_error) {
            if (!mysqli_begin_transaction($con)) {
                error_log('Gagal memulai transaksi penyimpanan alamat: ' . mysqli_error($con));
                $address_feedback = 'Alamat belum dapat disimpan. Coba lagi.';
                $address_feedback_error = true;
            } else {
                $defaults_query = mysqli_prepare(
                    $con,
                    'SELECT COUNT(*) AS total FROM user_addresses WHERE user_id = ? AND is_default = 1 AND id <> ?'
                );
                $defaults_loaded = $defaults_query &&
                    mysqli_stmt_bind_param($defaults_query, 'ii', $user_id, $address_id) &&
                    mysqli_stmt_execute($defaults_query);
                $default_count = 0;
                if ($defaults_loaded) {
                    $defaults_result = mysqli_stmt_get_result($defaults_query);
                    $defaults_row = $defaults_result ? mysqli_fetch_assoc($defaults_result) : null;
                    $default_count = $defaults_row ? (int)$defaults_row['total'] : 0;
                }
                if ($defaults_query) {
                    mysqli_stmt_close($defaults_query);
                }
                if (!$defaults_loaded) {
                    error_log('Gagal memeriksa alamat utama: ' . mysqli_error($con));
                    mysqli_rollback($con);
                    $address_feedback = 'Alamat belum dapat disimpan. Coba lagi.';
                    $address_feedback_error = true;
                } else {
                    $is_default = $make_default || $default_count === 0;
                    $save_address = null;
                    if ($is_default) {
                        $clear_defaults = mysqli_prepare($con, 'UPDATE user_addresses SET is_default = 0 WHERE user_id = ?');
                        if (!$clear_defaults ||
                            !mysqli_stmt_bind_param($clear_defaults, 'i', $user_id) ||
                            !mysqli_stmt_execute($clear_defaults)) {
                            error_log('Gagal memperbarui alamat utama: ' . mysqli_error($con));
                            if ($clear_defaults) {
                                mysqli_stmt_close($clear_defaults);
                            }
                            mysqli_rollback($con);
                            $address_feedback = 'Alamat belum dapat disimpan. Coba lagi.';
                            $address_feedback_error = true;
                        } else {
                            mysqli_stmt_close($clear_defaults);
                        }
                    }

                    if (!$address_feedback_error) {
                        if ($address_id > 0) {
                            $save_address = mysqli_prepare(
                                $con,
                                'UPDATE user_addresses
                                 SET recipient_name = ?, phone = ?, address = ?, city = ?, state = ?,
                                     postal_code = ?, destination_area_id = ?, is_default = ?
                                 WHERE id = ? AND user_id = ?'
                            );
                            $save_succeeded = $save_address &&
                                mysqli_stmt_bind_param(
                                    $save_address,
                                    'sssssssiii',
                                    $recipient_name,
                                    $phone,
                                    $address_detail,
                                    $city,
                                    $state,
                                    $postal_code,
                                    $area_id,
                                    $is_default,
                                    $address_id,
                                    $user_id
                                ) &&
                                mysqli_stmt_execute($save_address);
                        } else {
                            $save_address = mysqli_prepare(
                                $con,
                                'INSERT INTO user_addresses
                                 (user_id, recipient_name, phone, address, city, state, postal_code, destination_area_id, is_default)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                            );
                            $save_succeeded = $save_address &&
                                mysqli_stmt_bind_param(
                                    $save_address,
                                    'isssssssi',
                                    $user_id,
                                    $recipient_name,
                                    $phone,
                                    $address_detail,
                                    $city,
                                    $state,
                                    $postal_code,
                                    $area_id,
                                    $is_default
                                ) &&
                                mysqli_stmt_execute($save_address);
                        }
                        if ($save_address) {
                            mysqli_stmt_close($save_address);
                        }
                        if (!$save_succeeded || !mysqli_commit($con)) {
                            error_log('Gagal menyimpan alamat pengguna: ' . mysqli_error($con));
                            mysqli_rollback($con);
                            $address_feedback = 'Alamat gagal disimpan. Silakan coba lagi.';
                            $address_feedback_error = true;
                        } else {
                            $_SESSION['address_feedback'] = 'Alamat berhasil disimpan.';
                            header('Location: user.php#akun-addresses');
                            exit();
                        }
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_address') {
    $submitted_token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $address_id = isset($_POST['address_id']) && is_string($_POST['address_id']) ? max(0, (int)$_POST['address_id']) : 0;
    if (!hash_equals($profile_csrf_token, $submitted_token) || $address_id < 1 || $user_id < 1) {
        $address_feedback = 'Permintaan hapus alamat tidak valid. Muat ulang halaman, lalu coba lagi.';
        $address_feedback_error = true;
    } elseif (!mysqli_begin_transaction($con)) {
        error_log('Gagal memulai transaksi penghapusan alamat: ' . mysqli_error($con));
        $address_feedback = 'Alamat belum dapat dihapus. Coba lagi.';
        $address_feedback_error = true;
    } else {
        $delete_address = mysqli_prepare($con, 'DELETE FROM user_addresses WHERE id = ? AND user_id = ?');
        $delete_succeeded = $delete_address &&
            mysqli_stmt_bind_param($delete_address, 'ii', $address_id, $user_id) &&
            mysqli_stmt_execute($delete_address);
        $deleted_rows = $delete_succeeded ? mysqli_stmt_affected_rows($delete_address) : 0;
        if ($delete_address) {
            mysqli_stmt_close($delete_address);
        }
        if (!$delete_succeeded || $deleted_rows !== 1) {
            error_log('Gagal menghapus alamat pengguna: ' . mysqli_error($con));
            mysqli_rollback($con);
            $address_feedback = 'Alamat tidak ditemukan atau gagal dihapus.';
            $address_feedback_error = true;
        } else {
            $default_exists = mysqli_query(
                $con,
                "SELECT id FROM user_addresses WHERE user_id = '" . (int)$user_id . "' AND is_default = 1 LIMIT 1"
            );
            if (!$default_exists) {
                error_log('Gagal memeriksa alamat utama setelah penghapusan: ' . mysqli_error($con));
                mysqli_rollback($con);
                $address_feedback = 'Alamat terhapus, tetapi status alamat utama belum dapat diperiksa.';
                $address_feedback_error = true;
            } elseif (mysqli_num_rows($default_exists) === 0) {
                $next_address = mysqli_query(
                    $con,
                    "SELECT id FROM user_addresses WHERE user_id = '" . (int)$user_id . "' ORDER BY id ASC LIMIT 1"
                );
                if (!$next_address) {
                    error_log('Gagal mencari alamat pengganti sebagai alamat utama: ' . mysqli_error($con));
                    mysqli_rollback($con);
                    $address_feedback = 'Alamat terhapus, tetapi alamat utama belum dapat diperbarui.';
                    $address_feedback_error = true;
                }
                $next_row = $next_address ? mysqli_fetch_assoc($next_address) : null;
                if ($next_row && !$address_feedback_error) {
                    $next_address_id = (int)$next_row['id'];
                    $set_default = mysqli_prepare($con, 'UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?');
                    if (!$set_default ||
                        !mysqli_stmt_bind_param($set_default, 'ii', $next_address_id, $user_id) ||
                        !mysqli_stmt_execute($set_default)) {
                        error_log('Gagal menetapkan alamat pengganti sebagai alamat utama: ' . mysqli_error($con));
                        if ($set_default) {
                            mysqli_stmt_close($set_default);
                        }
                        mysqli_rollback($con);
                        $address_feedback = 'Alamat terhapus, tetapi alamat utama belum dapat diperbarui.';
                        $address_feedback_error = true;
                    } else {
                        mysqli_stmt_close($set_default);
                    }
                }
            }
            if (!$address_feedback_error && !mysqli_commit($con)) {
                error_log('Gagal menyelesaikan penghapusan alamat: ' . mysqli_error($con));
                mysqli_rollback($con);
                $address_feedback = 'Alamat belum dapat dihapus. Coba lagi.';
                $address_feedback_error = true;
            } elseif (!$address_feedback_error) {
                $_SESSION['address_feedback'] = 'Alamat berhasil dihapus.';
                header('Location: user.php#akun-addresses');
                exit();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $profile_name_value = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
    $profile_phone_value = isset($_POST['phone']) && is_string($_POST['phone']) ? trim($_POST['phone']) : '';
    $profile_gender_value = isset($_POST['gender']) && is_string($_POST['gender']) ? $_POST['gender'] : '';
    $profile_birth_date_value = isset($_POST['birth_date']) && is_string($_POST['birth_date']) ? $_POST['birth_date'] : '';
    $submitted_token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!hash_equals($profile_csrf_token, $submitted_token)) {
        $profile_feedback = 'Sesi formulir tidak valid. Muat ulang halaman, lalu coba lagi.';
        $profile_feedback_error = true;
    } elseif ($user_id < 1 || !$user_info) {
        $profile_feedback = 'Akun tidak ditemukan. Silakan masuk kembali.';
        $profile_feedback_error = true;
    } elseif ($profile_name_value === '') {
        $profile_feedback = 'Nama lengkap wajib diisi.';
        $profile_feedback_error = true;
    } elseif (strlen($profile_phone_value) > 30) {
        $profile_feedback = 'Nomor telepon maksimal 30 karakter.';
        $profile_feedback_error = true;
    } elseif (!in_array($profile_gender_value, ['', 'Perempuan', 'Laki-laki', 'Tidak ingin menyebutkan'], true)) {
        $profile_feedback = 'Pilihan jenis kelamin tidak valid.';
        $profile_feedback_error = true;
    } else {
        $birth_date_parts = $profile_birth_date_value === '' ? [] : explode('-', $profile_birth_date_value);
        $valid_birth_date = $profile_birth_date_value === ''
            || (count($birth_date_parts) === 3
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $profile_birth_date_value)
                && checkdate((int)$birth_date_parts[1], (int)$birth_date_parts[2], (int)$birth_date_parts[0]));

        if (!$valid_birth_date) {
            $profile_feedback = 'Tanggal lahir tidak valid.';
            $profile_feedback_error = true;
        } else {
            $phone_value = $profile_phone_value === '' ? null : $profile_phone_value;
            $gender_value = $profile_gender_value === '' ? null : $profile_gender_value;
            $birth_date_value = $profile_birth_date_value === '' ? null : $profile_birth_date_value;
            $profile_update = mysqli_prepare(
                $con,
                'UPDATE users SET username = ?, phone = ?, gender = ?, birth_date = ? WHERE id = ?'
            );

            if ($profile_update) {
                $profile_bound = mysqli_stmt_bind_param(
                    $profile_update,
                    'ssssi',
                    $profile_name_value,
                    $phone_value,
                    $gender_value,
                    $birth_date_value,
                    $user_id
                );
                $profile_saved = $profile_bound && mysqli_stmt_execute($profile_update);
                if ($profile_saved) {
                    $_SESSION['Name'] = $profile_name_value;
                    $_SESSION['profile_feedback'] = 'Perubahan profil berhasil disimpan.';
                    mysqli_stmt_close($profile_update);
                    header('Location: user.php#akun-personal');
                    exit();
                }

                error_log('Gagal menyimpan profil pengguna: ' . mysqli_stmt_error($profile_update));
                mysqli_stmt_close($profile_update);
            } else {
                error_log('Gagal menyiapkan penyimpanan profil pengguna: ' . mysqli_error($con));
            }

            $profile_feedback = 'Profil gagal disimpan. Silakan coba lagi.';
            $profile_feedback_error = true;
        }
    }
}

$username_display = $user_info ? $user_info['username'] : (isset($_SESSION['Name']) ? $_SESSION['Name'] : 'Penyihir');
$email_display = $user_info
    ? $user_info['email']
    : (isset($_SESSION['email']) ? $_SESSION['email'] : '');
$email_sql = mysqli_real_escape_string($con, $email_display);
$addresses = [];
$addresses_query = mysqli_prepare(
    $con,
    'SELECT id, recipient_name, phone, address, city, state, postal_code, destination_area_id, is_default
     FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC'
);
if ($addresses_query && mysqli_stmt_bind_param($addresses_query, 'i', $user_id) &&
    mysqli_stmt_execute($addresses_query)) {
    $addresses_result = mysqli_stmt_get_result($addresses_query);
    while ($addresses_result && ($address_row = mysqli_fetch_assoc($addresses_result))) {
        $addresses[] = $address_row;
    }
} else {
    error_log('Gagal memuat buku alamat pengguna: ' . mysqli_error($con));
    if ($address_feedback === null) {
        $address_feedback = 'Buku alamat belum dapat dimuat. Pastikan migrasi database alamat sudah dijalankan.';
        $address_feedback_error = true;
    }
}
if ($addresses_query) {
    mysqli_stmt_close($addresses_query);
}

$orders_query = mysqli_query(
    $con,
    "SELECT * FROM orders_info
     WHERE user_id = '$user_id' OR email = '$email_sql'
     ORDER BY order_id DESC"
);

if (!$orders_query) {
    die("Gagal memuat riwayat pesanan: " . htmlspecialchars(mysqli_error($con), ENT_QUOTES, 'UTF-8'));
}

$orders = [];
$total_spent = 0;
$total_ordered_items = 0;
while ($order = mysqli_fetch_assoc($orders_query)) {
    $orders[] = $order;
    if (isset($order['payment_status']) && $order['payment_status'] === 'paid') {
        $total_spent += isset($order['total_amt']) ? (int)$order['total_amt'] : 0;
    }
    $total_ordered_items += isset($order['prod_count']) ? (int)$order['prod_count'] : 0;
}

$cart_count = 0;
if ($user_id > 0) {
    $cart_query = mysqli_query($con, "SELECT SUM(qty) AS total FROM cart WHERE user_id = '$user_id'");
    if ($cart_query) {
        $cart_row = mysqli_fetch_assoc($cart_query);
        $cart_count = ($cart_row && $cart_row['total']) ? (int)$cart_row['total'] : 0;
    }
}

function user_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profil & Pesanan - Hogwarts Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'style.css'); ?>">
</head>
<body>
    <?php include "header.php"; ?>

    <!-- Breadcrumb -->
    <div class="user-breadcrumb-bar">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb user-breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php"><i class="fa fa-home mr-1"></i>Beranda</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Profil &amp; Pesanan</li>
                </ol>
            </nav>
        </div>
    </div>

    <main class="container user-dashboard my-4 my-lg-5">
        <div class="user-dashboard-layout">
            <aside class="user-dashboard-sidebar" id="userDashboardSidebar">
                <div class="user-sidebar-profile">
                    <div class="user-profile-avatar"><?php echo user_h(strtoupper(substr($username_display, 0, 1))); ?></div>
                    <strong><?php echo user_h($username_display); ?></strong>
                    <span><?php echo user_h($email_display); ?></span>
                    <span class="user-membership"><i class="fa fa-star mr-1" aria-hidden="true"></i> Gold Member · demo</span>
                </div>
                <nav class="user-dashboard-nav" role="tablist" aria-label="Menu profil pengguna" aria-orientation="vertical">
                    <button class="user-tab-link active" id="user-tab-trigger-personal" type="button" role="tab" aria-controls="user-tab-personal" data-user-tab="personal" aria-selected="true"><i class="fa fa-user-o" aria-hidden="true"></i><span>Informasi Pribadi</span></button>
                    <button class="user-tab-link" id="user-tab-trigger-addresses" type="button" role="tab" aria-controls="user-tab-addresses" data-user-tab="addresses" aria-selected="false"><i class="fa fa-map-marker" aria-hidden="true"></i><span>Buku Alamat</span></button>
                    <button class="user-tab-link" id="user-tab-trigger-orders" type="button" role="tab" aria-controls="user-tab-orders" data-user-tab="orders" aria-selected="false"><i class="fa fa-shopping-bag" aria-hidden="true"></i><span>Pesanan Saya</span><span class="user-nav-count"><?php echo count($orders); ?></span></button>
                    <button class="user-tab-link" id="user-tab-trigger-security" type="button" role="tab" aria-controls="user-tab-security" data-user-tab="security" aria-selected="false"><i class="fa fa-shield" aria-hidden="true"></i><span>Keamanan & Pengaturan</span></button>
                </nav>
                <!-- Link Kembali ke Beranda -->
                <a class="user-sidebar-back" href="index.php"><i class="fa fa-arrow-left mr-2"></i> Kembali ke Beranda</a>
                <a class="user-sidebar-logout" href="logout.php"><i class="fa fa-sign-out mr-2"></i> Keluar akun</a>
            </aside>

            <section class="user-dashboard-main">
                <div class="user-dashboard-heading">
                    <div>
                        <span class="user-dashboard-eyebrow">AKUN HOGWARTS SHOP</span>
                        <h1>Halo, <?php echo user_h($username_display); ?>!</h1>
                        <p>Kelola profil, pesanan, dan preferensi belanjamu dari satu tempat.</p>
                    </div>
                    <button class="btn user-mobile-menu" type="button" id="userSidebarToggle" aria-expanded="false" aria-controls="userDashboardSidebar">
                        <i class="fa fa-bars mr-2" aria-hidden="true"></i> Menu akun
                    </button>
                </div>

                <div class="user-tab-panels">
                    <section class="user-tab-panel active" id="user-tab-personal" role="tabpanel" aria-labelledby="user-tab-trigger-personal" data-user-panel="personal">
                        <div class="user-panel-heading">
                            <div><span class="user-dashboard-eyebrow">PROFIL</span><h2>Informasi Pribadi</h2><p>Pastikan informasi akunmu selalu terbaru.</p></div>
                        </div>
                        <form class="user-surface-card user-profile-form" id="userProfileForm" method="post">
                            <input type="hidden" name="action" value="update_profile">
                            <input type="hidden" name="csrf_token" value="<?php echo user_h($profile_csrf_token); ?>">
                            <div class="user-form-grid">
                                <div class="form-group"><label for="profileName">Nama lengkap</label><input class="form-control" id="profileName" name="name" value="<?php echo user_h($profile_name_value); ?>" required></div>
                                <div class="form-group"><label for="profileEmail">Alamat email</label><div class="user-verified-input"><input class="form-control" id="profileEmail" type="email" value="<?php echo user_h($email_display); ?>" readonly><span><i class="fa fa-check-circle mr-1"></i>Terdaftar</span></div></div>
                                <div class="form-group"><label for="profilePhone">Nomor telepon</label><input class="form-control" id="profilePhone" name="phone" type="tel" value="<?php echo user_h($profile_phone_value); ?>" placeholder="Contoh: 0812 3456 7890"></div>
                                <div class="form-group"><label for="profileGender">Jenis kelamin</label><select class="form-control" id="profileGender" name="gender"><option value="">Pilih jenis kelamin</option><option value="Perempuan" <?php echo $profile_gender_value === 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option><option value="Laki-laki" <?php echo $profile_gender_value === 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option><option value="Tidak ingin menyebutkan" <?php echo $profile_gender_value === 'Tidak ingin menyebutkan' ? 'selected' : ''; ?>>Tidak ingin menyebutkan</option></select></div>
                                <div class="form-group"><label for="profileBirthDate">Tanggal lahir</label><input class="form-control" id="profileBirthDate" name="birth_date" type="date" value="<?php echo user_h($profile_birth_date_value); ?>"></div>
                            </div>
                            <div class="user-form-footer"><small>Perubahan informasi profil akan disimpan ke akunmu.</small><button class="btn user-primary-button" type="submit"><i class="fa fa-check mr-2"></i>Simpan Perubahan</button></div>
                            <div class="user-feedback<?php echo $profile_feedback_error ? ' is-error' : ''; ?>" id="profileFeedback" role="status" <?php echo $profile_feedback === null ? 'hidden' : ''; ?>><?php echo $profile_feedback !== null ? user_h($profile_feedback) : ''; ?></div>
                        </form>
                        <div class="user-inline-note"><i class="fa fa-info-circle mr-2"></i>Email akun saat ini hanya dapat dilihat. Hubungi CS jika perlu bantuan terkait email.</div>
                    </section>

                    <section class="user-tab-panel" id="user-tab-addresses" role="tabpanel" aria-labelledby="user-tab-trigger-addresses" data-user-panel="addresses" hidden>
                        <div class="user-panel-heading user-panel-heading-row"><div><span class="user-dashboard-eyebrow">PENGIRIMAN</span><h2>Buku Alamat</h2><p>Kelola alamat yang digunakan untuk pesanan.</p></div><button class="btn user-primary-button" type="button" data-toggle="modal" data-target="#userAddressModal"><i class="fa fa-plus mr-2"></i>Tambah Alamat Baru</button></div>
                        <div class="user-address-grid">
                            <?php foreach ($addresses as $address_row): ?>
                                <article class="user-surface-card user-address-card"
                                         data-address-id="<?php echo (int)$address_row['id']; ?>"
                                         data-address-name="<?php echo user_h($address_row['recipient_name']); ?>"
                                         data-address-phone="<?php echo user_h($address_row['phone']); ?>"
                                         data-address-street="<?php echo user_h($address_row['address']); ?>"
                                         data-address-city="<?php echo user_h($address_row['city']); ?>"
                                         data-address-state="<?php echo user_h($address_row['state']); ?>"
                                         data-address-postal="<?php echo user_h($address_row['postal_code']); ?>"
                                         data-address-area-id="<?php echo user_h($address_row['destination_area_id']); ?>">
                                    <div class="user-address-top">
                                        <span class="user-address-icon"><i class="fa fa-home" aria-hidden="true"></i></span>
                                        <?php if ((int)$address_row['is_default'] === 1): ?><span class="user-main-address">Alamat Utama</span><?php endif; ?>
                                    </div>
                                    <h3><?php echo user_h($address_row['recipient_name']); ?></h3>
                                    <p><?php echo user_h($address_row['address']); ?><br><?php echo user_h($address_row['city'] . ', ' . $address_row['state'] . ' ' . $address_row['postal_code']); ?></p>
                                    <span class="user-address-phone"><i class="fa fa-phone mr-2" aria-hidden="true"></i><?php echo user_h($address_row['phone']); ?></span>
                                    <div class="user-address-actions">
                                        <button type="button" class="btn user-secondary-button" data-address-edit><i class="fa fa-pencil mr-1" aria-hidden="true"></i>Edit</button>
                                        <form method="post" class="user-address-delete-form">
                                            <input type="hidden" name="action" value="delete_address">
                                            <input type="hidden" name="csrf_token" value="<?php echo user_h($profile_csrf_token); ?>">
                                            <input type="hidden" name="address_id" value="<?php echo (int)$address_row['id']; ?>">
                                            <button type="submit" class="btn user-text-button"><i class="fa fa-trash-o mr-1" aria-hidden="true"></i>Hapus</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                            <button class="user-add-address-card" type="button" data-toggle="modal" data-target="#userAddressModal"><span><i class="fa fa-plus"></i></span><strong>Tambah alamat lain</strong><small>Tambahkan alamat pengiriman baru</small></button>
                        </div>
                        <div class="user-feedback<?php echo $address_feedback_error ? ' is-error' : ''; ?>" id="addressFeedback" role="status" <?php echo $address_feedback === null ? 'hidden' : ''; ?>><?php echo $address_feedback !== null ? user_h($address_feedback) : ''; ?></div>
                    </section>

                    <section class="user-tab-panel" id="user-tab-orders" role="tabpanel" aria-labelledby="user-tab-trigger-orders" data-user-panel="orders" hidden>
                        <div class="user-panel-heading"><span class="user-dashboard-eyebrow">AKTIVITAS</span><h2>Pesanan Saya</h2><p>Rincian pesanan nyata ditampilkan dari riwayat akunmu.</p></div>
                        <div class="user-order-filters" role="group" aria-label="Filter status pesanan">
                            <button class="user-order-filter active" type="button" data-order-filter="all">Semua</button>
                            <button class="user-order-filter" type="button" data-order-filter="pending">Menunggu Pembayaran</button>
                            <button class="user-order-filter" type="button" data-order-filter="processing">Diproses</button>
                            <button class="user-order-filter" type="button" data-order-filter="shipped">Dikirim</button>
                            <button class="user-order-filter" type="button" data-order-filter="completed">Selesai</button>
                            <button class="user-order-filter" type="button" data-order-filter="cancelled">Dibatalkan</button>
                        </div>
                        <?php if (!empty($orders)): ?>
            <?php foreach ($orders as $order): ?>
                <?php
                $order_id = (int)$order['order_id'];
                $total_amt = isset($order['total_amt']) ? (int)$order['total_amt'] : 0;
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
                $items_query = mysqli_query(
                    $con,
                    "SELECT op.product_id, op.qty, op.amt,
                            p.product_title, p.product_price, p.product_image
                     FROM order_products op
                     LEFT JOIN products p ON op.product_id = p.product_id
                     WHERE op.order_id = '$order_id'
                     ORDER BY op.order_pro_id ASC"
                );

                if (!$items_query) {
                    die("Gagal memuat barang pesanan #" . $order_id . ": " . htmlspecialchars(mysqli_error($con), ENT_QUOTES, 'UTF-8'));
                }

                $items = [];
                while ($item = mysqli_fetch_assoc($items_query)) {
                    $items[] = $item;
                }

                $order_name = isset($order['f_name']) ? $order['f_name'] : $username_display;
                $address_parts = [];
                foreach (['address', 'city', 'state'] as $address_field) {
                    if (!empty($order[$address_field])) {
                        $address_parts[] = $order[$address_field];
                    }
                }
                $full_address = implode(', ', $address_parts);
                if (!empty($order['zip'])) {
                    $full_address .= ($full_address !== '' ? ', ' : '') . $order['zip'];
                }
                ?>
                <?php
                $customer_order_statuses = [
                    'pending' => ['label' => 'Menunggu Pembayaran', 'icon' => 'fa-clock-o'],
                    'processing' => ['label' => 'Diproses', 'icon' => 'fa-cog'],
                    'shipped' => ['label' => 'Dikirim', 'icon' => 'fa-paper-plane'],
                    'completed' => ['label' => 'Selesai', 'icon' => 'fa-check-circle'],
                    'cancelled' => ['label' => 'Dibatalkan', 'icon' => 'fa-times-circle']
                ];
                $customer_order_status = isset($order['order_status']) && isset($customer_order_statuses[$order['order_status']])
                    ? $order['order_status']
                    : 'processing';
                $payment_status_labels = [
                    'pending' => 'Menunggu pembayaran',
                    'paid' => 'Berhasil',
                    'paid_review' => 'Perlu pemeriksaan toko',
                    'failed' => 'Gagal',
                    'cancelled' => 'Dibatalkan',
                    'expired' => 'Kedaluwarsa',
                    'refunded' => 'Dikembalikan',
                ];
                $payment_status = isset($payment_status_labels[$order['payment_status']])
                    ? $order['payment_status']
                    : 'pending';
                $payment_url = isset($order['payment_url']) ? (string)$order['payment_url'] : '';
                $expected_payment_host = MIDTRANS_IS_PRODUCTION ? 'app.midtrans.com' : 'app.sandbox.midtrans.com';
                $can_continue_payment = $payment_status === 'pending' &&
                    strtolower((string)parse_url($payment_url, PHP_URL_SCHEME)) === 'https' &&
                    strtolower((string)parse_url($payment_url, PHP_URL_HOST)) === $expected_payment_host;
                ?>
                <article class="order-history-card user-order-card mb-4" data-order-status="<?php echo user_h($customer_order_status); ?>">
                    <header class="order-history-header">
                        <div>
                            <span class="order-reference">PESANAN #<?php echo $order_id; ?></span>
                            <span class="order-date"><?php echo !empty($order['ordered_at']) ? user_h('Dibuat ' . date('d/m/Y H:i', strtotime($order['ordered_at']))) : 'Tanggal belum tercatat'; ?></span>
                            <span class="order-status order-status-<?php echo user_h($customer_order_status); ?>"><i class="fa <?php echo user_h($customer_order_statuses[$customer_order_status]['icon']); ?> mr-1" aria-hidden="true"></i><?php echo user_h($customer_order_statuses[$customer_order_status]['label']); ?></span>
                        </div>
                        <span class="order-shipping"><i class="fa fa-paper-plane mr-1" aria-hidden="true"></i> <?php echo user_h($shipping_label); ?></span>
                    </header>

                    <div class="order-history-body">
                        <section class="order-items-section">
                            <h3><i class="fa fa-cubes mr-2" aria-hidden="true"></i>Barang pesanan</h3>
                            <?php if (!empty($items)): ?>
                                <ul class="order-items-list">
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        $item_title = !empty($item['product_title']) ? $item['product_title'] : 'Produk tidak lagi tersedia';
                                        $item_qty = isset($item['qty']) ? (int)$item['qty'] : 1;
                                        $unit_price = isset($item['product_price']) ? (int)$item['product_price'] : 0;
                                        $line_total = isset($item['amt']) && (int)$item['amt'] > 0
                                            ? (int)$item['amt']
                                            : $unit_price * $item_qty;
                                        $item_image = !empty($item['product_image']) ? basename($item['product_image']) : '';
                                        ?>
                                        <li class="order-item">
                                            <div class="order-item-image">
                                                <?php if ($item_image !== ''): ?>
                                                    <img src="product_images/<?php echo rawurlencode($item_image); ?>"
                                                         alt="<?php echo user_h($item_title); ?>"
                                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <?php endif; ?>
                                                <span class="order-item-placeholder" aria-hidden="true"><i class="fa fa-magic"></i></span>
                                            </div>
                                            <div class="order-item-description">
                                                <strong><?php echo user_h($item_title); ?></strong>
                                                <span><?php echo $item_qty; ?> × Rp <?php echo number_format($unit_price, 0, ',', '.'); ?></span>
                                            </div>
                                            <strong class="order-item-total">Rp <?php echo number_format($line_total, 0, ',', '.'); ?></strong>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="order-history-empty-items">
                                    <i class="fa fa-info-circle mr-1" aria-hidden="true"></i>
                                    Rincian barang untuk transaksi lama ini tidak tersedia.
                                </div>
                            <?php endif; ?>
                        </section>

                        <aside class="order-summary">
                            <div class="order-summary-block">
                                <h3><i class="fa fa-map-marker mr-2" aria-hidden="true"></i>Pengiriman</h3>
                                <dl>
                                    <dt>Penerima</dt>
                                    <dd><?php echo user_h($order_name); ?></dd>
                                    <dt>Email</dt>
                                    <dd><?php echo user_h(isset($order['email']) ? $order['email'] : $email_display); ?></dd>
                                    <dt>Alamat tujuan</dt>
                                    <dd><?php echo user_h($full_address !== '' ? $full_address : 'Alamat tidak tersedia'); ?></dd>
                                    <dt>Jasa pengiriman</dt>
                                    <dd><?php echo user_h($shipping_label); ?><?php if (!empty($order['shipping_duration'])): ?> · <?php echo user_h($order['shipping_duration']); ?><?php endif; ?></dd>
                                    <?php if (isset($order['shipping_phone']) && $order['shipping_phone'] !== ''): ?>
                                        <dt>Telepon penerima</dt>
                                        <dd><?php echo user_h($order['shipping_phone']); ?></dd>
                                    <?php endif; ?>
                                </dl>
                            </div>

                            <div class="order-summary-block">
                                <h3><i class="fa fa-credit-card mr-2" aria-hidden="true"></i>Pembayaran</h3>
                                <dl>
                                    <dt>Status</dt>
                                    <dd><span class="payment-recorded"><?php echo user_h($payment_status_labels[$payment_status]); ?></span></dd>
                                    <dt>Metode</dt>
                                    <dd><?php echo user_h(!empty($order['payment_type']) ? $order['payment_type'] : 'Midtrans'); ?></dd>
                                </dl>
                                <p class="payment-privacy-note"><i class="fa fa-lock mr-1" aria-hidden="true"></i>Detail pembayaran dikelola dengan aman oleh Midtrans.</p>
                            </div>

                            <div class="order-total">
                                <span>Subtotal produk</span>
                                <strong>Rp <?php echo number_format(isset($order['subtotal_amt']) ? (int)$order['subtotal_amt'] : $total_amt, 0, ',', '.'); ?></strong>
                            </div>
                            <div class="order-total">
                                <span>Ongkos kirim</span>
                                <strong>Rp <?php echo number_format(isset($order['shipping_cost']) ? (int)$order['shipping_cost'] : 0, 0, ',', '.'); ?></strong>
                            </div>
                            <div class="order-total">
                                <span><?php echo $payment_status === 'paid' ? 'Total dibayar' : 'Total pesanan'; ?></span>
                                <strong>Rp <?php echo number_format($total_amt, 0, ',', '.'); ?></strong>
                            </div>
                        </aside>
                    </div>
                    <div class="user-order-actions">
                        <a class="btn user-secondary-button" href="index.php#katalog-produk"><i class="fa fa-repeat mr-1"></i>Beli Lagi</a>
                        <?php if ($can_continue_payment): ?>
                            <a class="btn user-secondary-button" href="<?php echo user_h($payment_url); ?>" rel="noopener"><i class="fa fa-credit-card mr-1"></i>Lanjutkan Pembayaran</a>
                        <?php endif; ?>
                        <?php if (!empty($order['payment_order_id'])): ?>
                            <a class="btn user-text-button" href="payment-result.php?order_id=<?php echo rawurlencode($order['payment_order_id']); ?>"><i class="fa fa-refresh mr-1"></i>Perbarui Status</a>
                        <?php endif; ?>
                        <button type="button" class="btn user-text-button" data-order-track><i class="fa fa-map-signs mr-1"></i>Lacak Pesanan</button>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <section class="profile-empty-state">
                <span class="profile-empty-icon"><i class="fa fa-shopping-bag" aria-hidden="true"></i></span>
                <h3>Belum ada riwayat pesanan</h3>
                <p>Pesanan yang kamu buat akan tampil di sini, lengkap dengan rincian barang dan pembayaran.</p>
                <a href="index.php#katalog-produk" class="btn btn-hogwarts-primary font-weight-bold">
                    <i class="fa fa-magic mr-1" aria-hidden="true"></i> Jelajahi Katalog
                </a>
            </section>
        <?php endif; ?>
                        <div class="user-filter-empty" id="orderFilterEmpty" hidden>Belum ada pesanan dengan status ini. Status pengiriman belum tersedia pada sistem saat ini.</div>
                        <div class="user-inline-note"><i class="fa fa-info-circle mr-2"></i>Status pesanan diperbarui oleh pengelola toko.</div>
                    </section>

                    <section class="user-tab-panel" id="user-tab-security" role="tabpanel" aria-labelledby="user-tab-trigger-security" data-user-panel="security" hidden>
                        <div class="user-panel-heading"><span class="user-dashboard-eyebrow">PERLINDUNGAN AKUN</span><h2>Keamanan & Pengaturan</h2><p>Kelola keamanan login dan perangkat yang digunakan.</p></div>
                        <div class="user-surface-card user-security-card"><div class="user-subheading"><div><h3>Ubah kata sandi</h3><p>Gunakan kata sandi yang kuat dan unik.</p></div><i class="fa fa-lock"></i></div><form id="userPasswordForm" class="user-password-form"><div class="form-group"><label for="currentPassword">Kata sandi saat ini</label><input class="form-control" type="password" id="currentPassword" required autocomplete="current-password"></div><div class="user-form-grid"><div class="form-group"><label for="newPassword">Kata sandi baru</label><input class="form-control" type="password" id="newPassword" minlength="8" required autocomplete="new-password"></div><div class="form-group"><label for="confirmPassword">Konfirmasi kata sandi baru</label><input class="form-control" type="password" id="confirmPassword" minlength="8" required autocomplete="new-password"></div></div><button class="btn user-primary-button" type="submit">Perbarui kata sandi</button><div class="user-feedback" id="passwordFeedback" role="status" hidden></div></form><div class="user-inline-note"><i class="fa fa-info-circle mr-2"></i>Form keamanan hanya contoh visual dan tidak mengubah kata sandi akun.</div></div>
                        <div class="user-surface-card user-security-card"><div class="user-subheading"><div><h3>Autentikasi dua langkah</h3><p>Tambahkan lapisan perlindungan ekstra saat masuk.</p></div><label class="user-switch"><input type="checkbox" id="twoFactorToggle"><span></span></label></div><div class="user-inline-note" id="twoFactorFeedback" role="status">Autentikasi dua langkah belum diaktifkan.</div></div>
                        <div class="user-surface-card user-security-card"><div class="user-subheading"><div><h3>Sesi perangkat</h3><p>Perangkat yang pernah digunakan untuk mengakses akun.</p></div></div><div class="user-device-row"><span class="user-device-icon"><i class="fa fa-desktop"></i></span><div><strong>Windows · Browser saat ini</strong><small>Aktif sekarang · Sesi ini</small></div><span class="user-session-current">Perangkat ini</span></div><div class="user-device-row"><span class="user-device-icon"><i class="fa fa-mobile"></i></span><div><strong>Perangkat seluler</strong><small>Data sesi contoh</small></div><button class="btn user-text-button" type="button" data-demo-action="Sesi perangkat contoh tidak dapat diakhiri dari demo ini.">Akhiri sesi</button></div></div>
                    </section>
                </div>
            </section>
        </div>
    </main>

    <div class="modal fade" id="userAddressModal" tabindex="-1" role="dialog" aria-labelledby="userAddressModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content user-address-modal">
                <div class="modal-header">
                    <div><span class="user-dashboard-eyebrow">PENGIRIMAN</span><h2 class="modal-title" id="userAddressModalTitle">Tambah alamat baru</h2></div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <form id="userAddressForm" method="post" data-open-on-load="<?php echo $address_form_error ? '1' : '0'; ?>">
                    <input type="hidden" name="action" value="save_address">
                    <input type="hidden" name="csrf_token" value="<?php echo user_h($profile_csrf_token); ?>">
                    <input type="hidden" id="addressShippingCsrf" value="<?php echo user_h($checkout_csrf_token); ?>">
                    <input type="hidden" name="address_id" id="addressId" value="<?php echo isset($address_form_values['address_id']) ? (int)$address_form_values['address_id'] : 0; ?>">
                    <input type="hidden" name="destination_area_id" id="profileAddressAreaId" value="<?php echo isset($address_form_values['area_id']) ? user_h($address_form_values['area_id']) : ''; ?>">
                    <div class="modal-body">
                        <?php if ($address_form_error && $address_feedback !== null): ?>
                            <div class="alert alert-<?php echo $address_feedback_error ? 'danger' : 'success'; ?>" role="alert"><?php echo user_h($address_feedback); ?></div>
                        <?php endif; ?>
                        <div class="form-group"><label for="addressRecipient">Nama penerima</label><input class="form-control" id="addressRecipient" name="recipient_name" maxlength="120" value="<?php echo isset($address_form_values['recipient_name']) ? user_h($address_form_values['recipient_name']) : ''; ?>" required></div>
                        <div class="form-group"><label for="addressPhone">Nomor telepon</label><input class="form-control" id="addressPhone" name="address_phone" type="tel" maxlength="30" value="<?php echo isset($address_form_values['phone']) ? user_h($address_form_values['phone']) : ''; ?>" required></div>
                        <div class="form-group"><label for="addressDetail">Alamat lengkap</label><textarea class="form-control" id="addressDetail" name="address_detail" rows="3" maxlength="255" required><?php echo isset($address_form_values['address']) ? user_h($address_form_values['address']) : ''; ?></textarea></div>
                        <div class="form-row">
                            <div class="form-group col-md-5"><label for="addressCity">Kecamatan / kota</label><input class="form-control" id="addressCity" name="address_city" maxlength="100" value="<?php echo isset($address_form_values['city']) ? user_h($address_form_values['city']) : ''; ?>" required></div>
                            <div class="form-group col-md-4"><label for="addressState">Kabupaten / provinsi</label><input class="form-control" id="addressState" name="address_state" maxlength="100" value="<?php echo isset($address_form_values['state']) ? user_h($address_form_values['state']) : ''; ?>" required></div>
                            <div class="form-group col-md-3"><label for="addressPostal">Kode pos</label><input class="form-control" id="addressPostal" name="address_postal" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" value="<?php echo isset($address_form_values['postal_code']) ? user_h($address_form_values['postal_code']) : ''; ?>" required></div>
                        </div>
                        <button type="button" class="btn user-secondary-button" id="searchProfileShippingArea"><i class="fa fa-search mr-1" aria-hidden="true"></i>Cari lokasi kurir</button>
                        <div id="profileShippingAreaResults" class="mt-2" role="listbox" aria-label="Hasil lokasi pengiriman"></div>
                        <div id="profileSelectedShippingArea" class="small mt-2" role="status"></div>
                        <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="is_default" id="addressIsDefault" value="1" <?php echo !empty($address_form_values['is_default']) ? 'checked' : ''; ?>><label class="form-check-label" for="addressIsDefault">Jadikan alamat utama</label></div>
                        <small class="form-text text-muted">Pilih lokasi dari hasil pencarian agar ongkir dapat dihitung dengan benar.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn user-secondary-button" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn user-primary-button">Simpan Alamat</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

        <?php include "footer.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/user-dashboard.js?v=<?php echo filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'user-dashboard.js'); ?>" defer></script>
</body>
</html>
