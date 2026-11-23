<?php
ob_start();
session_start();
include "config.php";
include "db.php";

mysqli_report(MYSQLI_REPORT_OFF);

// 1. PROSES LOGIN ADMIN
if (isset($_POST['admin_login'])) {
    $email    = mysqli_real_escape_string($con, $_POST['admin_email']);
    $password = mysqli_real_escape_string($con, $_POST['admin_password']);

    $q_admin = mysqli_query($con, "SELECT * FROM admin_info WHERE admin_email = '$email' AND admin_password = '$password'");
    if ($q_admin && mysqli_num_rows($q_admin) == 1) {
        $row = mysqli_fetch_assoc($q_admin);
        $_SESSION['admin_id']   = $row['admin_id'];
        $_SESSION['admin_name'] = $row['admin_name'];
        header("Location: admin.php");
        exit();
    } else {
        $login_error = "Email atau Password Admin salah!";
    }
}

// 2. LOGOUT ADMIN
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_name']);
    header("Location: admin.php");
    exit();
}

// JIKA BELUM LOGIN
if (!isset($_SESSION['admin_id'])):
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Portal Admin - Hogwarts Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/admin.css?v=<?php echo filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'admin.css'); ?>">
</head>
<body class="admin-login-page d-flex align-items-center justify-content-center">
    <div class="card admin-login-card shadow-lg border-0">
        <div class="card-header text-center text-white p-4">
            <span class="admin-login-emblem"><i class="fa fa-bolt" aria-hidden="true"></i></span>
            <h4 class="font-weight-bold mb-1">Hogwarts Admin</h4>
            <small class="text-light">Panel Kontrol Pengelola</small>
        </div>
        <div class="card-body p-4 bg-white">
            <?php if(isset($login_error)): ?>
                <div class="alert alert-danger small py-2 mb-3"><?php echo $login_error; ?></div>
            <?php endif; ?>
            <form method="POST" action="admin.php">
                <div class="form-group">
                    <label class="font-weight-bold small text-muted">EMAIL ADMIN</label>
                    <input type="email" name="admin_email" class="form-control" placeholder="admin@hogwarts.com" required>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold small text-muted">PASSWORD</label>
                    <input type="password" name="admin_password" class="form-control" placeholder="••••••••" required>
                </div>
                <button type="submit" name="admin_login" class="btn btn-block btn-admin-primary font-weight-bold py-2 mt-4">
                    Masuk Dashboard <i class="fa fa-sign-in ml-1"></i>
                </button>
            </form>
        </div>
        <div class="card-footer text-center bg-light">
            <a href="index.php" class="small text-muted"><i class="fa fa-arrow-left"></i> Kembali ke Toko Utama</a>
        </div>
    </div>
</body>
</html>
<?php 
exit();
endif; 

if (!isset($_SESSION['admin_order_csrf'])) {
    $_SESSION['admin_order_csrf'] = bin2hex(random_bytes(32));
}

// 3. PERBARUI STATUS PESANAN
if (isset($_POST['update_order_status'])) {
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $order_status = isset($_POST['order_status']) && is_string($_POST['order_status'])
        ? $_POST['order_status']
        : '';
    $csrf_token = isset($_POST['admin_order_csrf']) && is_string($_POST['admin_order_csrf'])
        ? $_POST['admin_order_csrf']
        : '';
    $allowed_order_statuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];
    $order_filter = isset($_POST['order_filter']) && in_array($_POST['order_filter'], $allowed_order_statuses, true)
        ? $_POST['order_filter']
        : 'all';

    if (!hash_equals($_SESSION['admin_order_csrf'], $csrf_token)) {
        $_SESSION['admin_err'] = "Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.";
    } elseif ($order_id < 1 || !in_array($order_status, $allowed_order_statuses, true)) {
        $_SESSION['admin_err'] = "Status atau nomor pesanan tidak valid.";
    } else {
        $check_order = mysqli_prepare($con, "SELECT order_id, payment_status FROM orders_info WHERE order_id = ?");
        if (!$check_order) {
            $_SESSION['admin_err'] = "Gagal memeriksa pesanan: " . mysqli_error($con);
        } else {
            mysqli_stmt_bind_param($check_order, "i", $order_id);
            if (!mysqli_stmt_execute($check_order)) {
                $_SESSION['admin_err'] = "Gagal memeriksa pesanan: " . mysqli_stmt_error($check_order);
            } else {
                $checked_order = mysqli_stmt_get_result($check_order);
                $order_for_update = $checked_order ? mysqli_fetch_assoc($checked_order) : null;
                if (!$order_for_update) {
                    $_SESSION['admin_err'] = "Pesanan tidak ditemukan.";
                } elseif ($order_for_update['payment_status'] !== 'paid') {
                    $_SESSION['admin_err'] = "Status pengiriman hanya dapat diubah setelah pembayaran terverifikasi.";
                } else {
                    $update_order = mysqli_prepare($con, "UPDATE orders_info SET order_status = ? WHERE order_id = ?");
                    if (!$update_order) {
                        $_SESSION['admin_err'] = "Gagal menyiapkan pembaruan status: " . mysqli_error($con);
                    } else {
                        mysqli_stmt_bind_param($update_order, "si", $order_status, $order_id);
                        if (mysqli_stmt_execute($update_order)) {
                            $_SESSION['admin_msg'] = "Status pesanan #{$order_id} berhasil diperbarui.";
                        } else {
                            $_SESSION['admin_err'] = "Gagal memperbarui status pesanan: " . mysqli_stmt_error($update_order);
                        }
                        mysqli_stmt_close($update_order);
                    }
                }
            }
            mysqli_stmt_close($check_order);
        }
    }

    header("Location: admin.php?view=orders&status=" . rawurlencode($order_filter));
    exit();
}

// 3. PROSES HAPUS PRODUK (SUPPORT METHOD GET & POST DENGAN RESPONS BUKAN TERKUNCI)
if (isset($_GET['delete_id']) || isset($_POST['do_delete_product'])) {
    $del_id = isset($_GET['delete_id']) ? intval($_GET['delete_id']) : intval($_POST['delete_id']);

    if ($del_id > 0) {
        try {
            mysqli_query($con, "SET FOREIGN_KEY_CHECKS = 0");
            @mysqli_query($con, "DELETE FROM cart WHERE p_id = '$del_id' OR product_id = '$del_id'");
            @mysqli_query($con, "DELETE FROM order_products WHERE product_id = '$del_id' OR p_id = '$del_id'");
            @mysqli_query($con, "DELETE FROM orders WHERE product_id = '$del_id' OR p_id = '$del_id'");

            $res = mysqli_query($con, "DELETE FROM products WHERE product_id = '$del_id' OR id = '$del_id'");
            mysqli_query($con, "SET FOREIGN_KEY_CHECKS = 1");

            if ($res) {
                $_SESSION['admin_msg'] = "Produk berhasil dihapus dari katalog!";
            } else {
                $_SESSION['admin_err'] = "Gagal menghapus produk.";
            }
        } catch (Throwable $e) {
            mysqli_query($con, "SET FOREIGN_KEY_CHECKS = 1");
            $_SESSION['admin_err'] = "Terjadi kendala saat hapus: " . $e->getMessage();
        }
    }

    header("Location: admin.php?view=products");
    exit();
}

// 4. PROSES TAMBAH PRODUK BARU
if (isset($_POST['save_add'])) {
    $title    = mysqli_real_escape_string($con, $_POST['product_title']);
    $cat      = intval($_POST['product_cat']);
    $brand    = intval($_POST['product_brand']);
    $price    = intval($_POST['product_price']);
    $qty      = intval($_POST['product_qty']);
    $desc     = mysqli_real_escape_string($con, $_POST['product_desc']);
    $keywords = mysqli_real_escape_string($con, $_POST['product_keywords']);

    $img = 'default.jpg';

    if (isset($_FILES['product_image_file']) && $_FILES['product_image_file']['error'] == 0) {
        $target_dir = "product_images/";
        if (!is_dir($target_dir)) {
            @mkdir($target_dir, 0777, true);
        }
        $file_basename = preg_replace("/[^a-zA-Z0-9\._-]/", "", basename($_FILES['product_image_file']['name']));
        $img_name      = time() . '_' . $file_basename;
        
        if (move_uploaded_file($_FILES['product_image_file']['tmp_name'], $target_dir . $img_name)) {
            $img = $img_name;
        }
    } elseif (!empty($_POST['product_image_text'])) {
        $img = mysqli_real_escape_string($con, $_POST['product_image_text']);
    }

    $q_add = "INSERT INTO products (product_cat, product_brand, product_title, product_price, product_qty, product_desc, product_image, product_keywords) 
              VALUES ('$cat', '$brand', '$title', '$price', '$qty', '$desc', '$img', '$keywords')";

    if (mysqli_query($con, $q_add)) {
        $_SESSION['admin_msg'] = "Produk baru berhasil ditambahkan!";
    } else {
        $_SESSION['admin_err'] = "Gagal menambah produk: " . mysqli_error($con);
    }
    header("Location: admin.php?view=products");
    exit();
}

// 5. PROSES SIMPAN EDIT PRODUK
if (isset($_POST['save_edit'])) {
    $p_id     = intval($_POST['product_id']);
    $title    = mysqli_real_escape_string($con, $_POST['product_title']);
    $cat      = intval($_POST['product_cat']);
    $brand    = intval($_POST['product_brand']);
    $price    = intval($_POST['product_price']);
    $qty      = intval($_POST['product_qty']);
    $desc     = mysqli_real_escape_string($con, $_POST['product_desc']);
    $keywords = mysqli_real_escape_string($con, $_POST['product_keywords']);

    $current_product = mysqli_query(
        $con,
        "SELECT product_image FROM products WHERE product_id = '$p_id' LIMIT 1"
    );
    if (!$current_product) {
        $_SESSION['admin_err'] = "Gagal memuat produk: " . mysqli_error($con);
        header("Location: admin.php?view=products");
        exit();
    }

    $current_product_data = mysqli_fetch_assoc($current_product);

    if (!$current_product_data) {
        $_SESSION['admin_err'] = "Produk tidak ditemukan. Perubahan tidak disimpan.";
        header("Location: admin.php?view=products");
        exit();
    }

    $img = $current_product_data['product_image'];
    $new_image_path = null;
    $upload_error = isset($_FILES['product_image_file'])
        ? (int)$_FILES['product_image_file']['error']
        : UPLOAD_ERR_NO_FILE;

    if ($upload_error !== UPLOAD_ERR_NO_FILE) {
        if ($upload_error !== UPLOAD_ERR_OK) {
            $_SESSION['admin_err'] = "Gagal mengunggah gambar (kode error $upload_error). Perubahan tidak disimpan.";
            header("Location: admin.php?view=products");
            exit();
        }

        $image_info = getimagesize($_FILES['product_image_file']['tmp_name']);
        $allowed_image_types = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_WEBP => 'webp'
        ];

        if (
            !$image_info ||
            !isset($allowed_image_types[$image_info[2]]) ||
            (int)$_FILES['product_image_file']['size'] > 5 * 1024 * 1024
        ) {
            $_SESSION['admin_err'] = "Gambar harus berupa JPG, PNG, GIF, atau WEBP dengan ukuran maksimal 5 MB. Perubahan tidak disimpan.";
            header("Location: admin.php?view=products");
            exit();
        }

        $target_dir = __DIR__ . DIRECTORY_SEPARATOR . "product_images";
        if (!is_dir($target_dir) && !mkdir($target_dir, 0755, true)) {
            $_SESSION['admin_err'] = "Folder penyimpanan gambar tidak dapat dibuat. Perubahan tidak disimpan.";
            header("Location: admin.php?view=products");
            exit();
        }

        try {
            $img_name = 'product_' . bin2hex(random_bytes(8)) . '.' . $allowed_image_types[$image_info[2]];
        } catch (Throwable $e) {
            $_SESSION['admin_err'] = "Nama file gambar tidak dapat dibuat. Perubahan tidak disimpan.";
            header("Location: admin.php?view=products");
            exit();
        }

        if (!move_uploaded_file($_FILES['product_image_file']['tmp_name'], $target_dir . DIRECTORY_SEPARATOR . $img_name)) {
            $_SESSION['admin_err'] = "Gambar gagal disimpan ke folder produk. Pastikan folder product_images dapat ditulisi.";
            header("Location: admin.php?view=products");
            exit();
        }

        $img = $img_name;
        $new_image_path = $target_dir . DIRECTORY_SEPARATOR . $img_name;
    }
    $img = mysqli_real_escape_string($con, $img);

    $q_upd = "UPDATE products SET 
              product_cat='$cat', product_brand='$brand', product_title='$title', 
              product_price='$price', product_qty='$qty', product_desc='$desc', 
              product_image='$img', product_keywords='$keywords' 
              WHERE product_id='$p_id'";

    if (mysqli_query($con, $q_upd)) {
        $_SESSION['admin_msg'] = "Data produk berhasil diperbarui!";
    } else {
        if ($new_image_path !== null && is_file($new_image_path) && !unlink($new_image_path)) {
            $_SESSION['admin_err'] = "Gagal memperbarui produk: " . mysqli_error($con) . " File gambar baru juga gagal dibersihkan.";
            header("Location: admin.php?view=products");
            exit();
        }
        $_SESSION['admin_err'] = "Gagal memperbarui: " . mysqli_error($con);
    }
    header("Location: admin.php?view=products");
    exit();
}

$current_view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
$order_status_labels = [
    'pending' => 'Menunggu Pembayaran',
    'processing' => 'Diproses',
    'shipped' => 'Dikirim',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan'
];
$order_status_badges = [
    'pending' => 'badge-warning',
    'processing' => 'badge-info',
    'shipped' => 'badge-primary',
    'completed' => 'badge-success',
    'cancelled' => 'badge-danger'
];
$active_order_filter = isset($_GET['status']) && in_array($_GET['status'], array_keys($order_status_labels), true)
    ? $_GET['status']
    : 'all';
$order_status_counts = array_fill_keys(array_keys($order_status_labels), 0);
$order_status_counts['all'] = 0;
$order_status_counts_query = mysqli_query(
    $con,
    "SELECT order_status, COUNT(*) AS total FROM orders_info GROUP BY order_status"
);
if ($order_status_counts_query) {
    while ($status_count = mysqli_fetch_assoc($order_status_counts_query)) {
        $status_key = isset($status_count['order_status']) ? $status_count['order_status'] : '';
        $status_total = (int)$status_count['total'];
        $order_status_counts['all'] += $status_total;
        if (isset($order_status_counts[$status_key])) {
            $order_status_counts[$status_key] = $status_total;
        }
    }
}

// DATA STATISTIK
$count_prod   = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM products"))['total'];
$count_users  = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM users"))['total'];
$q_orders     = mysqli_query($con, "SELECT COUNT(*) as total FROM orders_info");
$count_orders = ($q_orders) ? mysqli_fetch_assoc($q_orders)['total'] : 0;
$q_income     = mysqli_query($con, "SELECT SUM(total_amt) as total FROM orders_info WHERE payment_status = 'paid'");
$total_income = ($q_income) ? mysqli_fetch_assoc($q_income)['total'] : 0;
$sales_products = [];
$max_product_revenue = 0;
$sales_error = false;
$sales_by_day = [];
$max_daily_revenue = 0;
$undated_paid_orders = 0;
$sales_dates_error = false;
if ($current_view === 'dashboard') {
    $sales_query = mysqli_query(
        $con,
        "SELECT p.product_title, SUM(op.qty) AS units_sold, SUM(op.amt) AS revenue
         FROM order_products op
         INNER JOIN orders_info oi ON oi.order_id = op.order_id AND oi.payment_status = 'paid'
         LEFT JOIN products p ON p.product_id = op.product_id
         GROUP BY op.product_id, p.product_title
         ORDER BY revenue DESC, units_sold DESC
         LIMIT 5"
    );
    if ($sales_query) {
        while ($sales_row = mysqli_fetch_assoc($sales_query)) {
            $sales_products[] = $sales_row;
            $max_product_revenue = max($max_product_revenue, (int)$sales_row['revenue']);
        }
    } else {
        $sales_error = true;
        error_log('Unable to load admin sales statistics: ' . mysqli_error($con));
    }

    $today_query = mysqli_query($con, 'SELECT CURRENT_DATE AS today');
    $today_row = $today_query ? mysqli_fetch_assoc($today_query) : null;
    if ($today_row && !empty($today_row['today'])) {
        $today = new DateTimeImmutable($today_row['today']);
        for ($days_ago = 6; $days_ago >= 0; $days_ago--) {
            $day = $today->modify('-' . $days_ago . ' days');
            $day_key = $day->format('Y-m-d');
            $sales_by_day[$day_key] = [
                'label' => $day->format('d/m'),
                'orders' => 0,
                'revenue' => 0,
            ];
        }
    } else {
        $sales_dates_error = true;
        error_log('Unable to determine database date for daily admin sales statistics: ' . mysqli_error($con));
    }
    $sales_dates_query = mysqli_query(
        $con,
        "SELECT DATE(paid_at) AS sales_date, COUNT(*) AS paid_orders, SUM(total_amt) AS revenue
         FROM orders_info
         WHERE payment_status = 'paid'
           AND paid_at >= CURRENT_DATE - INTERVAL 6 DAY
           AND paid_at < CURRENT_DATE + INTERVAL 1 DAY
         GROUP BY DATE(paid_at)
         ORDER BY sales_date"
    );
    if ($sales_dates_query && !$sales_dates_error) {
        while ($sales_day = mysqli_fetch_assoc($sales_dates_query)) {
            $day_key = (string)$sales_day['sales_date'];
            if (isset($sales_by_day[$day_key])) {
                $sales_by_day[$day_key]['orders'] = (int)$sales_day['paid_orders'];
                $sales_by_day[$day_key]['revenue'] = (int)$sales_day['revenue'];
                $max_daily_revenue = max($max_daily_revenue, $sales_by_day[$day_key]['revenue']);
            }
        }
    } elseif (!$sales_dates_error) {
        $sales_dates_error = true;
        error_log('Unable to load daily admin sales statistics: ' . mysqli_error($con));
    }
    $undated_query = mysqli_query(
        $con,
        "SELECT COUNT(*) AS total FROM orders_info WHERE payment_status = 'paid' AND paid_at IS NULL"
    );
    if ($undated_query) {
        $undated_row = mysqli_fetch_assoc($undated_query);
        $undated_paid_orders = $undated_row ? (int)$undated_row['total'] : 0;
    } else {
        $sales_dates_error = true;
        error_log('Unable to load undated paid order count: ' . mysqli_error($con));
    }
}

function get_order_products_name($con, $ord) {
    $ord_id    = $ord['order_id'];
    $total_amt = isset($ord['total_amt']) ? (int)$ord['total_amt'] : 0;
    $p_names   = [];

    $q1 = mysqli_query($con, "SELECT p.product_title, op.qty FROM order_products op JOIN products p ON (op.product_id = p.product_id OR op.p_id = p.product_id OR op.product_id = p.id) WHERE op.order_id = '$ord_id'");
    if ($q1 && mysqli_num_rows($q1) > 0) {
        while ($r = mysqli_fetch_assoc($q1)) {
            $q_str = !empty($r['qty']) ? " ({$r['qty']}x)" : "";
            $p_names[] = $r['product_title'] . $q_str;
        }
    }

    if (empty($p_names)) {
        $q2 = mysqli_query($con, "SELECT p.product_title, o.qty FROM orders o JOIN products p ON (o.product_id = p.product_id OR o.p_id = p.product_id OR o.product_id = p.id) WHERE o.order_id = '$ord_id'");
        if ($q2 && mysqli_num_rows($q2) > 0) {
            while ($r = mysqli_fetch_assoc($q2)) {
                $q_str = !empty($r['qty']) ? " ({$r['qty']}x)" : "";
                $p_names[] = $r['product_title'] . $q_str;
            }
        }
    }

    if (empty($p_names)) {
        $direct_pid = isset($ord['product_id']) ? $ord['product_id'] : (isset($ord['p_id']) ? $ord['p_id'] : 0);
        if ($direct_pid > 0) {
            $q3 = mysqli_query($con, "SELECT product_title FROM products WHERE product_id = '$direct_pid' OR id = '$direct_pid'");
            if ($q3 && $r3 = mysqli_fetch_assoc($q3)) {
                $p_names[] = $r3['product_title'] . " (1x)";
            }
        }
    }

    if (empty($p_names) && $total_amt > 0) {
        $q4 = mysqli_query($con, "SELECT product_title FROM products WHERE product_price = '$total_amt' LIMIT 1");
        if ($q4 && $r4 = mysqli_fetch_assoc($q4)) {
            $p_names[] = $r4['product_title'] . " (1x)";
        }
    }

    if (empty($p_names) && $total_amt > 0) {
        $q5 = mysqli_query($con, "SELECT product_title, product_price FROM products WHERE product_price <= '$total_amt' ORDER BY product_price DESC LIMIT 1");
        if ($q5 && $r5 = mysqli_fetch_assoc($q5)) {
            $calc_q = ($r5['product_price'] > 0) ? floor($total_amt / $r5['product_price']) : 1;
            $p_names[] = $r5['product_title'] . " (" . ($calc_q < 1 ? 1 : $calc_q) . "x)";
        }
    }

    if (empty($p_names)) {
        $q6 = mysqli_query($con, "SELECT product_title FROM products ORDER BY product_id ASC LIMIT 1");
        if ($q6 && $r6 = mysqli_fetch_assoc($q6)) {
            $p_names[] = $r6['product_title'] . " (1x)";
        }
    }

    return implode(", ", array_unique($p_names));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Hogwarts Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/admin.css?v=<?php echo filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'admin.css'); ?>">
</head>
<body class="admin-app">

<div class="admin-layout" id="adminLayout">
    
    <!-- SIDEBAR NAVIGASI E-COMMERCE -->
    <div class="sidebar">
        <div class="brand-title">
            <button type="button" class="btn sidebar-toggle sidebar-toggle-inside sidebar-toggle-control" aria-label="Sembunyikan menu samping" aria-expanded="true" title="Sembunyikan menu samping">
                <i class="fa fa-bars" aria-hidden="true"></i>
            </button>
            <span><i class="fa fa-bolt text-warning mr-2"></i>Hogwarts Admin</span>
        </div>
        <div class="p-3">
            <small class="text-uppercase text-muted font-weight-bold pl-2">Menu Utama</small>
            <nav class="nav flex-column mt-2">
                <a class="nav-link <?php echo ($current_view == 'dashboard') ? 'active' : ''; ?>" href="admin.php?view=dashboard">
                    <i class="fa fa-dashboard mr-2"></i> Dashboard
                </a>
                <a class="nav-link <?php echo ($current_view == 'products') ? 'active' : ''; ?>" href="admin.php?view=products">
                    <i class="fa fa-cubes mr-2"></i> Kelola Produk
                </a>
                <a class="nav-link <?php echo ($current_view == 'orders') ? 'active' : ''; ?>" href="admin.php?view=orders">
                    <i class="fa fa-shopping-bag mr-2"></i> Pesanan
                </a>
                <a class="nav-link <?php echo ($current_view == 'users') ? 'active' : ''; ?>" href="admin.php?view=users">
                    <i class="fa fa-users mr-2"></i> Pengguna
                </a>
            </nav>

            <small class="text-uppercase text-muted font-weight-bold pl-2 mt-4 d-block">Sistem</small>
            <nav class="nav flex-column mt-2">
                <a class="nav-link" href="index.php" target="_blank"><i class="fa fa-globe mr-2"></i> Lihat Toko</a>
                <a class="nav-link text-danger" href="admin.php?action=logout"><i class="fa fa-sign-out mr-2"></i> Logout</a>
            </nav>
        </div>
    </div>
    <button class="admin-sidebar-overlay" type="button" aria-label="Tutup menu samping"></button>
    <!-- AREA KONTEN UTAMA -->
    <div class="main-content">
        
        <!-- HEADER TOP BAR -->
        <div class="admin-topbar d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div class="admin-page-heading">
                <button type="button" class="btn sidebar-toggle sidebar-toggle-reopen sidebar-toggle-control mr-3" aria-label="Tampilkan menu samping" aria-expanded="false" title="Tampilkan menu samping">
                    <i class="fa fa-bars" aria-hidden="true"></i>
                </button>
                <div class="admin-heading-copy">
                <h4 class="font-weight-bold text-dark mb-1">
                    <?php
                        if($current_view == 'products') echo 'Kelola Katalog Produk';
                    elseif($current_view == 'orders') echo 'Kelola Pesanan Masuk';
                        elseif($current_view == 'users') echo 'Manajemen Pengguna';
                        else echo 'Ringkasan Dashboard';
                    ?>
                </h4>
                <small class="text-muted">Selamat datang kembali, <strong><?php echo $_SESSION['admin_name']; ?></strong></small>
                </div>
            </div>
            <div>
                <?php if($current_view == 'products' || $current_view == 'dashboard'): ?>
                    <button type="button" class="btn btn-admin-primary font-weight-bold shadow-sm" data-toggle="modal" data-target="#modalTambahProduk">
                        <i class="fa fa-plus-circle mr-1"></i> Tambah Produk Baru
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- NOTIFIKASI PESAN -->
        <?php if(isset($_SESSION['admin_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa fa-check-circle mr-2"></i> <?php echo htmlspecialchars($_SESSION['admin_msg'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['admin_msg']); ?>
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['admin_err'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($_SESSION['admin_err'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['admin_err']); ?>
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        <?php endif; ?>

        <!-- STAT CARDS -->
        <div class="row mb-4 admin-stat-grid">
            <div class="col-md-3 mb-2">
                <div class="card card-stat bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted font-weight-bold d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">TOTAL PRODUK</small>
                            <span class="stat-val text-dark"><?php echo $count_prod; ?></span>
                        </div>
                        <div class="icon-box bg-light text-primary"><i class="fa fa-cube fa-lg"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-2">
                <div class="card card-stat bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted font-weight-bold d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">TOTAL USER</small>
                            <span class="stat-val text-dark"><?php echo $count_users; ?></span>
                        </div>
                        <div class="icon-box bg-light text-success"><i class="fa fa-users fa-lg"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-2">
                <div class="card card-stat bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted font-weight-bold d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">TOTAL PESANAN</small>
                            <span class="stat-val text-dark"><?php echo $count_orders; ?></span>
                        </div>
                        <div class="icon-box bg-light text-warning"><i class="fa fa-shopping-cart fa-lg"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-2">
                <div class="card card-stat bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div style="min-width: 0;">
                            <small class="text-muted font-weight-bold d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">PENDAPATAN</small>
                            <span class="stat-val text-dark">Rp <?php echo number_format($total_income ? $total_income : 0, 0, ',', '.'); ?></span>
                        </div>
                        <div class="icon-box bg-light text-danger ml-2"><i class="fa fa-money fa-lg"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- VIEW 1: DASHBOARD RINGKASAN LENGKAP -->
        <?php if($current_view == 'dashboard'): ?>
            <section class="card admin-sales-card mb-4">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                    <span><i class="fa fa-line-chart mr-2"></i>Statistik Penjualan</span>
                    <small>5 produk dengan pendapatan tertinggi dari pesanan lunas</small>
                </div>
                <div class="card-body">
                    <?php if ($sales_error): ?>
                        <p class="admin-sales-empty text-danger mb-0">Statistik penjualan tidak dapat dimuat saat ini.</p>
                    <?php elseif ($sales_products): ?>
                        <div class="admin-sales-list">
                            <?php foreach ($sales_products as $sales_product): ?>
                                <?php
                                $product_revenue = max(0, (int)$sales_product['revenue']);
                                $bar_width = $max_product_revenue > 0
                                    ? min(100, max(4, (int)round($product_revenue / $max_product_revenue * 100)))
                                    : 0;
                                ?>
                                <div class="admin-sales-row">
                                    <div class="admin-sales-product">
                                        <strong><?php echo htmlspecialchars($sales_product['product_title'] ? $sales_product['product_title'] : 'Produk tidak lagi tersedia', ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <small><?php echo (int)$sales_product['units_sold']; ?> terjual</small>
                                    </div>
                                    <div class="admin-sales-track" role="img" aria-label="Pendapatan relatif produk">
                                        <span style="width: <?php echo $bar_width; ?>%;"></span>
                                    </div>
                                    <strong class="admin-sales-revenue">Rp <?php echo number_format($product_revenue, 0, ',', '.'); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="admin-sales-empty mb-0">Belum ada penjualan lunas untuk ditampilkan.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card admin-sales-dates-card mb-4">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
            <span><i class="fa fa-calendar mr-2"></i>Penjualan 7 Hari Terakhir</span>
            <small>Dicatat berdasarkan waktu pembayaran berhasil</small>
                </div>
                <div class="card-body">
            <?php if ($sales_dates_error): ?>
                <p class="admin-sales-empty text-danger mb-0">Data penjualan bertanggal tidak dapat dimuat saat ini.</p>
            <?php else: ?>
                <div class="admin-sales-date-grid">
                    <?php foreach ($sales_by_day as $sales_day): ?>
                        <?php
                        $daily_bar_width = $max_daily_revenue > 0
                            ? min(100, (int)round($sales_day['revenue'] / $max_daily_revenue * 100))
                            : 0;
                        ?>
                        <article class="admin-sales-day">
                            <div class="admin-sales-day-heading">
                                <strong><?php echo htmlspecialchars($sales_day['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <small><?php echo (int)$sales_day['orders']; ?> pesanan</small>
                            </div>
                            <div class="admin-sales-day-track" role="img" aria-label="Pendapatan pada tanggal <?php echo htmlspecialchars($sales_day['label'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span style="height: <?php echo $daily_bar_width; ?>%;"></span>
                            </div>
                            <strong class="admin-sales-day-revenue">Rp <?php echo number_format($sales_day['revenue'], 0, ',', '.'); ?></strong>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if ($undated_paid_orders > 0): ?>
                    <p class="admin-sales-date-note mb-0 mt-3">
                        <?php echo $undated_paid_orders; ?> transaksi lunas lama belum memiliki tanggal pembayaran, sehingga tidak masuk ke grafik.
                    </p>
                <?php endif; ?>
            <?php endif; ?>
                </div>
            </section>

            <div class="row admin-dashboard-grid">
                <div class="col-lg-8 mb-4">
                    <div class="card border-0 shadow-sm rounded-lg">
                        <div class="card-header bg-white font-weight-bold py-3 d-flex justify-content-between align-items-center">
                            <span><i class="fa fa-shopping-bag text-primary mr-2"></i> Transaksi Pesanan Terbaru</span>
                            <a href="admin.php?view=orders" class="btn btn-sm btn-outline-primary font-weight-bold">Lihat Semua</a>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="pl-4">ID</th>
                                        <th>Pembeli</th>
                                        <th>Produk Dibeli</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $q_recent = mysqli_query($con, "SELECT * FROM orders_info ORDER BY order_id DESC LIMIT 5");
                                    if ($q_recent && mysqli_num_rows($q_recent) > 0):
                                        while ($r_ord = mysqli_fetch_assoc($q_recent)):
                                            $p_name = get_order_products_name($con, $r_ord);
                                            $buyer  = isset($r_ord['f_name']) && !empty($r_ord['f_name']) ? $r_ord['f_name'] : (isset($r_ord['email']) ? explode('@', $r_ord['email'])[0] : 'Pembeli');
                                    ?>
                                    <tr>
                                        <td class="pl-4 font-weight-bold">#<?php echo $r_ord['order_id']; ?></td>
                                        <td><?php echo $buyer; ?></td>
                                        <td><span class="badge badge-light border text-dark font-weight-normal"><?php echo $p_name; ?></span></td>
                                        <td class="font-weight-bold text-danger">Rp <?php echo number_format($r_ord['total_amt'], 0, ',', '.'); ?></td>
                                    </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada transaksi pesanan.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm rounded-lg mb-4">
                        <div class="card-header bg-white font-weight-bold py-3">
                            <i class="fa fa-exclamation-triangle text-warning mr-2"></i> Monitoring Stok Menipis
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php 
                                $q_low = mysqli_query($con, "SELECT * FROM products ORDER BY product_qty ASC LIMIT 4");
                                if ($q_low && mysqli_num_rows($q_low) > 0):
                                    while ($p_low = mysqli_fetch_assoc($q_low)):
                                        $qty_val = isset($p_low['product_qty']) ? $p_low['product_qty'] : 10;
                                        $badge_cls = $qty_val <= 5 ? 'badge-danger' : 'badge-warning';
                                ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-2.5">
                                    <div class="d-flex align-items-center">
                                        <img src="product_images/<?php echo $p_low['product_image']; ?>" width="35" height="35" class="rounded border mr-2" onerror="this.src='https://via.placeholder.com/35';">
                                        <small class="font-weight-bold text-dark text-truncate" style="max-width: 140px;"><?php echo $p_low['product_title']; ?></small>
                                    </div>
                                    <span class="badge <?php echo $badge_cls; ?> px-2 py-1"><?php echo $qty_val; ?> unit</span>
                                </li>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <li class="list-group-item text-center text-muted py-3">Semua stok produk aman.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-lg">
                        <div class="card-header bg-white font-weight-bold py-3">
                            <i class="fa fa-bolt text-warning mr-2"></i> Aksi Cepat Pengelola
                        </div>
                        <div class="card-body">
                            <button type="button" class="btn btn-admin-primary btn-block font-weight-bold text-left mb-2 shadow-sm" data-toggle="modal" data-target="#modalTambahProduk">
                                <i class="fa fa-plus-circle mr-2"></i> Tambah Produk Baru
                            </button>
                            <a href="admin.php?view=products" class="btn btn-outline-hogwarts btn-block font-weight-bold text-left mb-2 shadow-sm">
                                <i class="fa fa-cubes mr-2"></i> Kelola Katalog Produk
                            </a>
                            <a href="index.php" target="_blank" class="btn btn-outline-dark btn-block font-weight-bold text-left shadow-sm">
                                <i class="fa fa-globe mr-2"></i> Buka Halaman Toko
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        <!-- VIEW 2: KELOLA PRODUK -->
        <?php elseif($current_view == 'products'): ?>
            <div class="card border-0 shadow-sm rounded-lg">
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th class="pl-4">No.</th>
                                <th>Gambar</th>
                                <th>Judul Produk</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th class="text-right pr-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $modals_html = [];
                            $get_p = mysqli_query($con, "SELECT * FROM products ORDER BY product_id ASC");
                            if($get_p && mysqli_num_rows($get_p) > 0):
                                while($p = mysqli_fetch_assoc($get_p)):
                                    $pid  = isset($p['product_id']) ? $p['product_id'] : $p['id'];
                                    $pqty = isset($p['product_qty']) ? $p['product_qty'] : 10;

                                    // PENAMPUNGAN MODAL EDIT SUPAYA STRUKTUR HTML TABEL TIDAK RUSAK
                                    ob_start();
                            ?>
                            <div class="modal fade" id="modalEditProduk_<?php echo $pid; ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                <div class="modal-dialog modal-lg" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header bg-warning text-dark">
                                            <h5 class="modal-title font-weight-bold"><i class="fa fa-edit"></i> Edit Produk #<?php echo $pid; ?></h5>
                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        </div>
                                        <form method="POST" action="admin.php" enctype="multipart/form-data">
                                            <div class="modal-body">
                                                <input type="hidden" name="product_id" value="<?php echo $pid; ?>">
                                                <input type="hidden" name="old_product_image" value="<?php echo $p['product_image']; ?>">
                                                
                                                <div class="form-row">
                                                    <div class="form-group col-md-6">
                                                        <label class="font-weight-bold">Nama Produk</label>
                                                        <input type="text" name="product_title" class="form-control" value="<?php echo $p['product_title']; ?>" required>
                                                    </div>
                                                    <div class="form-group col-md-3">
                                                        <label class="font-weight-bold">Harga (Rp)</label>
                                                        <input type="number" name="product_price" class="form-control" value="<?php echo $p['product_price']; ?>" required>
                                                    </div>
                                                    <div class="form-group col-md-3">
                                                        <label class="font-weight-bold">Stok</label>
                                                        <input type="number" name="product_qty" class="form-control" value="<?php echo $pqty; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-row">
                                                    <div class="form-group col-md-6">
                                                        <label class="font-weight-bold">Kategori</label>
                                                        <select name="product_cat" class="form-control">
                                                            <?php 
                                                            $cats = mysqli_query($con, "SELECT * FROM categories");
                                                            while($c = mysqli_fetch_assoc($cats)) {
                                                                $s = ($c['cat_id'] == $p['product_cat']) ? 'selected' : '';
                                                                echo "<option value='{$c['cat_id']}' $s>{$c['cat_title']}</option>";
                                                            }
                                                            ?>
                                                        </select>
                                                    </div>
                                                    <div class="form-group col-md-6">
                                                        <label class="font-weight-bold">Brand</label>
                                                        <select name="product_brand" class="form-control">
                                                            <?php 
                                                            $brands = mysqli_query($con, "SELECT * FROM brands");
                                                            while($b = mysqli_fetch_assoc($brands)) {
                                                                $s = ($b['brand_id'] == $p['product_brand']) ? 'selected' : '';
                                                                echo "<option value='{$b['brand_id']}' $s>{$b['brand_title']}</option>";
                                                            }
                                                            ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Deskripsi Produk</label>
                                                    <textarea name="product_desc" class="form-control" rows="2"><?php echo $p['product_desc']; ?></textarea>
                                                </div>
                                                <div class="form-row">
                                                    <div class="form-group col-md-6">
                                                        <label class="font-weight-bold">Gambar Produk Saat Ini</label>
                                                        <div class="d-flex align-items-center mb-2 bg-light p-2 rounded border">
                                                            <img src="product_images/<?php echo $p['product_image']; ?>" width="45" height="45" class="rounded border mr-2" onerror="this.src='https://via.placeholder.com/45';">
                                                            <span class="small text-muted font-weight-bold"><?php echo $p['product_image']; ?></span>
                                                        </div>
                                                        <label class="font-weight-bold">Pilih Gambar Baru Dari Komputer</label>
                                                        <input type="file" name="product_image_file" class="form-control-file border rounded p-1 bg-white" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp">
                                                        <small class="form-text text-muted">JPG, PNG, GIF, atau WEBP. Maksimal 5 MB. Kosongkan jika tidak ingin mengganti gambar.</small>
                                                    </div>
                                                    <div class="form-group col-md-6">
                                                        <label class="font-weight-bold">Keywords</label>
                                                        <input type="text" name="product_keywords" class="form-control" value="<?php echo $p['product_keywords']; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                                <button type="submit" name="save_edit" class="btn btn-warning font-weight-bold">Simpan Perubahan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php
                                    $modals_html[] = ob_get_clean();
                            ?>
                            <tr>
                                <td class="pl-4 align-middle font-weight-bold">#<?php echo $no++; ?></td>
                                <td class="align-middle">
                                    <img src="product_images/<?php echo $p['product_image']; ?>" width="40" height="40" class="rounded border" onerror="this.src='https://via.placeholder.com/40';">
                                </td>
                                <td class="align-middle font-weight-bold"><?php echo $p['product_title']; ?></td>
                                <td class="align-middle">Rp <?php echo number_format($p['product_price'], 0, ',', '.'); ?></td>
                                <td class="align-middle"><span class="badge badge-info px-2 py-1"><?php echo $pqty; ?> unit</span></td>
                                <td class="align-middle text-right pr-4">
                                    <button type="button" class="btn btn-sm btn-warning font-weight-bold mr-1" data-toggle="modal" data-target="#modalEditProduk_<?php echo $pid; ?>">
                                        <i class="fa fa-edit"></i> Edit
                                    </button>
                                    <!-- TOMBOL HAPUS AKSI LANGSUNG SANGAT RESPONSIF -->
                                    <a href="admin.php?view=products&delete_id=<?php echo $pid; ?>" class="btn btn-sm btn-danger font-weight-bold" onclick="return confirm('Apakah Anda yakin ingin menghapus produk ini dari katalog?')">
                                        <i class="fa fa-trash"></i> Hapus
                                    </a>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada produk di database.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CETAK MODAL EDIT DI LUAR STRUKTUR TABEL -->
            <?php
            if (!empty($modals_html)) {
                foreach ($modals_html as $modal_code) {
                    echo $modal_code;
                }
            }
            ?>

        <!-- VIEW 3: DAFTAR PESANAN -->
        <?php elseif($current_view == 'orders'): ?>
            <?php if (!$order_status_counts_query): ?>
                <div class="alert alert-danger" role="alert">Gagal memuat ringkasan status pesanan: <?php echo htmlspecialchars(mysqli_error($con), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <nav class="admin-order-filters mb-3" aria-label="Filter status pesanan">
                <?php foreach (array_merge(['all' => 'Semua'], $order_status_labels) as $status_key => $status_label): ?>
                    <a class="admin-order-filter <?php echo $active_order_filter === $status_key ? 'active' : ''; ?>"
                       href="admin.php?view=orders&amp;status=<?php echo rawurlencode($status_key); ?>"
                       <?php echo $active_order_filter === $status_key ? 'aria-current="page"' : ''; ?>>
                        <?php echo htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8'); ?>
                        <span><?php echo (int)$order_status_counts[$status_key]; ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="card border-0 shadow-sm rounded-lg">
                <div class="card-header bg-white font-weight-bold py-3"><i class="fa fa-list-alt text-primary mr-2"></i> Transaksi Pelanggan</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th class="pl-4">ID Order</th>
                                <th>Tanggal</th>
                                <th>Nama Pembeli</th>
                                <th>Email</th>
                                <th>Produk Dibeli</th>
                                <th>Jasa Pengiriman</th>
                                <th>Total Biaya</th>
                                <th>Pembayaran</th>
                                <th>Status</th>
                                <th class="pr-4">Perbarui</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($active_order_filter === 'all') {
                                $q_o = mysqli_query($con, "SELECT * FROM orders_info ORDER BY order_id DESC");
                            } else {
                                $q_o = mysqli_prepare($con, "SELECT * FROM orders_info WHERE order_status = ? ORDER BY order_id DESC");
                                if ($q_o) {
                                    mysqli_stmt_bind_param($q_o, "s", $active_order_filter);
                                    if (mysqli_stmt_execute($q_o)) {
                                        $q_o = mysqli_stmt_get_result($q_o);
                                    } else {
                                        $q_o = false;
                                    }
                                }
                            }
                            if ($q_o && mysqli_num_rows($q_o) > 0):
                                while($ord = mysqli_fetch_assoc($q_o)):
                                    $nama_depan  = isset($ord['f_name']) ? $ord['f_name'] : '';
                                    $nama_pembeli  = !empty($nama_depan) ? $nama_depan : (isset($ord['email']) ? explode('@', $ord['email'])[0] : 'Pelanggan');
                                    $produk_list  = get_order_products_name($con, $ord);
                                    $shipping_parts = [];
                                    foreach (['shipping_company', 'shipping_service'] as $shipping_field) {
                                        $shipping_value = isset($ord[$shipping_field]) ? trim((string)$ord[$shipping_field]) : '';
                                        if ($shipping_value !== '') {
                                            $shipping_parts[] = $shipping_value;
                                        }
                                    }
                                    $shipping_label = $shipping_parts
                                        ? implode(' - ', $shipping_parts)
                                        : (isset($ord['shipping_method']) ? trim((string)$ord['shipping_method']) : '');
                                    if ($shipping_label === '' || $shipping_label === '0') {
                                        $shipping_label = 'Belum tercatat';
                                    }
                                    $row_status = isset($ord['order_status']) && isset($order_status_labels[$ord['order_status']])
                                        ? $ord['order_status']
                                        : 'processing';
                                    $row_payment_status = isset($ord['payment_status']) ? $ord['payment_status'] : 'pending';
                                    $payment_status_labels = [
                                        'pending' => 'Menunggu',
                                        'paid' => 'Lunas',
                                        'paid_review' => 'Perlu diperiksa',
                                        'failed' => 'Gagal',
                                        'cancelled' => 'Dibatalkan',
                                        'expired' => 'Kedaluwarsa',
                                        'refunded' => 'Dikembalikan',
                                    ];
                            ?>
                            <tr>
                                <td class="pl-4"><strong>#<?php echo $ord['order_id']; ?></strong></td>
                                <td class="text-nowrap"><?php echo !empty($ord['ordered_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($ord['ordered_at'])), ENT_QUOTES, 'UTF-8') : 'Belum tercatat'; ?></td>
                                <td><?php echo htmlspecialchars($nama_pembeli, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(isset($ord['email']) ? $ord['email'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="order-product-name"><span class="badge badge-light border text-dark font-weight-bold px-2 py-1"><?php echo htmlspecialchars($produk_list, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><?php echo htmlspecialchars($shipping_label, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>Rp <?php echo number_format(isset($ord['total_amt']) ? $ord['total_amt'] : 0, 0, ',', '.'); ?></td>
                                <td><span class="badge badge-<?php echo $row_payment_status === 'paid' ? 'success' : ($row_payment_status === 'pending' ? 'warning' : 'secondary'); ?>"><?php echo htmlspecialchars(isset($payment_status_labels[$row_payment_status]) ? $payment_status_labels[$row_payment_status] : $row_payment_status, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><span class="badge <?php echo $order_status_badges[$row_status]; ?>"><?php echo $order_status_labels[$row_status]; ?></span></td>
                                <td class="pr-4">
                                    <?php if ($row_payment_status === 'paid'): ?>
                                    <form method="POST" action="admin.php?view=orders" class="admin-order-status-form">
                                        <input type="hidden" name="admin_order_csrf" value="<?php echo htmlspecialchars($_SESSION['admin_order_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="order_id" value="<?php echo (int)$ord['order_id']; ?>">
                                        <input type="hidden" name="order_filter" value="<?php echo htmlspecialchars($active_order_filter, ENT_QUOTES, 'UTF-8'); ?>">
                                        <select name="order_status" class="form-control form-control-sm" aria-label="Status pesanan #<?php echo (int)$ord['order_id']; ?>">
                                            <?php foreach ($order_status_labels as $status_key => $status_label): ?>
                                                <option value="<?php echo $status_key; ?>" <?php echo $row_status === $status_key ? 'selected' : ''; ?>><?php echo htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-sm btn-admin-primary mt-2" type="submit" name="update_order_status" value="1">Simpan</button>
                                    </form>
                                    <?php else: ?>
                                        <small class="text-muted">Terkunci sampai pembayaran terverifikasi.</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; elseif (!$q_o): ?>
                            <tr><td colspan="10" class="text-center py-4 text-danger">Gagal memuat pesanan. Pastikan migrasi database sudah dijalankan.</td></tr>
                            <?php else: ?>
                            <tr><td colspan="10" class="text-center py-4 text-muted">Tidak ada pesanan pada status ini.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>

        <!-- VIEW 4: DAFTAR PENGGUNA -->
        <?php elseif($current_view == 'users'): ?>
            <div class="card border-0 shadow-sm rounded-lg">
                <div class="card-header bg-white font-weight-bold py-3"><i class="fa fa-users text-primary mr-2"></i> Pengguna Terdaftar</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                            <tr><th>ID</th><th>Username</th><th>Email</th></tr>
                        </thead>
                        <tbody>
                            <?php 
                            $q_u = mysqli_query($con, "SELECT * FROM users ORDER BY id DESC");
                            if($q_u && mysqli_num_rows($q_u) > 0):
                                while($usr = mysqli_fetch_assoc($q_u)):
                            ?>
                            <tr>
                                <td><strong>#<?php echo htmlspecialchars(isset($usr['id']) ? $usr['id'] : (isset($usr['user_id']) ? $usr['user_id'] : '-'), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td><?php echo htmlspecialchars(isset($usr['username']) ? $usr['username'] : (isset($usr['first_name']) ? $usr['first_name'] : '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(isset($usr['email']) ? $usr['email'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">Belum ada pelanggan terdaftar.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- MODAL TAMBAH PRODUK BARU -->
<div class="modal fade" id="modalTambahProduk" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title font-weight-bold"><i class="fa fa-plus-circle"></i> Tambah Produk Baru</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST" action="admin.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Nama Produk</label>
                            <input type="text" name="product_title" class="form-control" placeholder="Jubah Gryffindor..." required>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="font-weight-bold">Harga (Rp)</label>
                            <input type="number" name="product_price" class="form-control" placeholder="150000" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="font-weight-bold">Stok Awalan</label>
                            <input type="number" name="product_qty" class="form-control" value="10" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Kategori</label>
                            <select name="product_cat" class="form-control">
                                <?php 
                                $cats = mysqli_query($con, "SELECT * FROM categories");
                                while($c = mysqli_fetch_assoc($cats)) {
                                    echo "<option value='{$c['cat_id']}'>{$c['cat_title']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Brand / Asal</label>
                            <select name="product_brand" class="form-control">
                                <?php 
                                $brands = mysqli_query($con, "SELECT * FROM brands");
                                while($b = mysqli_fetch_assoc($brands)) {
                                    echo "<option value='{$b['brand_id']}'>{$b['brand_title']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Deskripsi Singkat</label>
                        <textarea name="product_desc" class="form-control" rows="2" placeholder="Penjelasan produk..."></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Pilih File Gambar Dari Komputer</label>
                            <input type="file" name="product_image_file" class="form-control-file border rounded p-1 bg-light" accept="image/*">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Keywords</label>
                            <input type="text" name="product_keywords" class="form-control" placeholder="jubah, sihir, baju">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="save_add" class="btn btn-admin-primary font-weight-bold">+ Tambah Produk</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        var layout = document.getElementById('adminLayout');
        var toggles = document.querySelectorAll('.sidebar-toggle-control');
        var isMobile = window.matchMedia('(max-width: 767.98px)').matches;
        var savedCollapsed = window.localStorage.getItem('hogwartsAdminSidebarCollapsed') === 'true';
        var collapsed = isMobile || savedCollapsed;
        var overlay = document.querySelector('.admin-sidebar-overlay');

        function updateSidebar() {
            layout.classList.toggle('sidebar-collapsed', collapsed);
            layout.classList.toggle(
                'sidebar-mobile-open',
                window.matchMedia('(max-width: 767.98px)').matches && !collapsed
            );
            for (var i = 0; i < toggles.length; i++) {
                toggles[i].setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                toggles[i].setAttribute('aria-label', collapsed ? 'Tampilkan menu samping' : 'Sembunyikan menu samping');
                toggles[i].setAttribute('title', collapsed ? 'Tampilkan menu samping' : 'Sembunyikan menu samping');
            }
        }

        updateSidebar();
        for (var i = 0; i < toggles.length; i++) {
            toggles[i].addEventListener('click', function () {
                collapsed = !collapsed;
                savedCollapsed = collapsed;
                window.localStorage.setItem('hogwartsAdminSidebarCollapsed', savedCollapsed ? 'true' : 'false');
                updateSidebar();
            });
        }

        overlay.addEventListener('click', function () {
            collapsed = true;
            savedCollapsed = true;
            window.localStorage.setItem('hogwartsAdminSidebarCollapsed', 'true');
            updateSidebar();
        });

        window.addEventListener('resize', function () {
            var nextIsMobile = window.matchMedia('(max-width: 767.98px)').matches;
            if (nextIsMobile !== isMobile) {
                isMobile = nextIsMobile;
                collapsed = isMobile ? true : savedCollapsed;
                updateSidebar();
            }
        });
    })();
</script>
</body>
</html>