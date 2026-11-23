<?php
session_start();
include "config.php"; 
include "db.php";     
include_once "auth.php";

$ip_add  = $_SERVER['REMOTE_ADDR'];
$user_id = current_user_id($con);

// --- 1. PROSES TAMBAH KE KERANJANG ---
if (isset($_GET['action']) && $_GET['action'] == 'add_to_cart' && isset($_GET['pid'])) {
    $p_id = (int)$_GET['pid'];
    $requested_qty = isset($_GET['qty']) ? max(1, (int)$_GET['qty']) : 1;

    if (!$user_id) {
        $_SESSION['error'] = "Silakan masuk atau daftar terlebih dahulu untuk melihat detail dan menambahkan produk ke keranjang.";
        header("Location: login.php?login_required=1");
        exit();
    }
    
    $check_stock = mysqli_query($con, "SELECT product_qty FROM products WHERE product_id = '$p_id'");
    $stock_data  = mysqli_fetch_assoc($check_stock);

    if ($stock_data && $stock_data['product_qty'] > 0 && $requested_qty <= (int)$stock_data['product_qty']) {
        $check_cart = mysqli_query($con, "SELECT * FROM cart WHERE p_id = '$p_id' AND user_id = '$user_id'");

        if (mysqli_num_rows($check_cart) > 0) {
            $row_c   = mysqli_fetch_assoc($check_cart);
            $new_qty = (int)$row_c['qty'] + $requested_qty;
            if ($new_qty > (int)$stock_data['product_qty']) {
                $_SESSION['error'] = "Jumlah produk di keranjang melebihi stok yang tersedia.";
            } else {
                mysqli_query($con, "UPDATE cart SET qty = '$new_qty' WHERE id = '{$row_c['id']}'");
                $_SESSION['success'] = "Produk berhasil ditambahkan ke keranjang sihirmu!";
            }
        } else {
            mysqli_query($con, "INSERT INTO cart (p_id, ip_add, user_id, qty) VALUES ('$p_id', '$ip_add', '$user_id', '$requested_qty')");
            $_SESSION['success'] = "Produk berhasil ditambahkan ke keranjang sihirmu!";
        }
    } else {
        $_SESSION['error'] = "Maaf, jumlah yang diminta melebihi stok produk yang tersedia.";
    }
    
    if (isset($_GET['return_to']) && $_GET['return_to'] === 'product' && $p_id > 0) {
        header("Location: product.php?id=" . $p_id);
    } else {
        header("Location: index.php#katalog-produk");
    }
    exit();
}

// --- 2. PROSES HAPUS KERANJANG (VIA FORM POST DIRECT) ---
if (isset($_POST['delete_from_cart'])) {
    $c_id = (int)$_POST['cart_item_id'];
    $p_id = (int)$_POST['cart_product_id'];

    mysqli_query($con, "SET FOREIGN_KEY_CHECKS = 0");

    if ($user_id) {
        mysqli_query($con, "DELETE FROM cart WHERE (id = '$c_id' OR p_id = '$p_id') AND user_id = '$user_id'");
    } else {
        mysqli_query($con, "DELETE FROM cart WHERE (id = '$c_id' OR p_id = '$p_id') AND ip_add = '$ip_add'");
    }
    
    // Pembersihan menyeluruh jika id item cocok
    mysqli_query($con, "DELETE FROM cart WHERE id = '$c_id'");
    mysqli_query($con, "SET FOREIGN_KEY_CHECKS = 1");

    $_SESSION['success'] = "Barang berhasil dihapus dari keranjang!";
    header("Location: " . (isset($_POST['return_to']) && $_POST['return_to'] === 'cart' ? 'cart.php' : 'index.php'));
    exit();
}

// --- 3. PROSES HAPUS KERANJANG (VIA GET LINK FALLBACK) ---
if (isset($_GET['action']) && $_GET['action'] == 'remove_cart') {
    $c_id = isset($_GET['cid']) ? (int)$_GET['cid'] : 0;
    $p_id = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;

    mysqli_query($con, "SET FOREIGN_KEY_CHECKS = 0");
    mysqli_query($con, "DELETE FROM cart WHERE id = '$c_id' OR p_id = '$p_id'");
    mysqli_query($con, "SET FOREIGN_KEY_CHECKS = 1");

    $_SESSION['success'] = "Produk berhasil dihapus dari keranjang!";
    header("Location: index.php");
    exit();
}

// --- 4. HITUNG TOTAL ITEM DI KERANJANG ---
if ($user_id) {
    $cart_q = mysqli_query($con, "SELECT SUM(qty) AS total FROM cart WHERE user_id = '$user_id'");
    $cart_res   = mysqli_fetch_assoc($cart_q);
    $cart_count = ($cart_res && $cart_res['total']) ? (int)$cart_res['total'] : 0;
} else {
    $cart_count = 0;
}

// --- 5. PROSES REGISTRASI USER BARU ---
if (isset($_POST['reg_user'])) {
    $username   = mysqli_real_escape_string($con, $_POST['username']);
    $email      = mysqli_real_escape_string($con, $_POST['email']);
    $password_1 = $_POST['password_1'];
    $password_2 = $_POST['password_2'];

    if ($password_1 != $password_2) {
        $_SESSION['error'] = "Konfirmasi password tidak cocok!";
    } else {
        $check_email = mysqli_query($con, "SELECT email FROM users WHERE email = '$email'");
        if (mysqli_num_rows($check_email) > 0) {
            $_SESSION['error'] = "Email sudah terdaftar!";
        } else {
            $hashed_password = password_hash($password_1, PASSWORD_DEFAULT); 
            $query           = "INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$hashed_password')";
            
            if (mysqli_query($con, $query)) {
                $new_id              = mysqli_insert_id($con);
                if ($new_id < 1) {
                    $_SESSION['error'] = "Akun berhasil dibuat, tetapi sesi login gagal disiapkan. Silakan masuk kembali.";
                    header("Location: login.php");
                    exit();
                }
                $_SESSION['user_id'] = $new_id;
                $_SESSION['Name']    = $username; 
                $_SESSION['success'] = "Pendaftaran berhasil! Selamat datang, $username.";
                header("Location: index.php");
                exit();
            } else {
                $_SESSION['error'] = "Gagal mendaftar: " . mysqli_error($con);
            }
        }
    }
    header("Location: register.php");
    exit();
}

// --- 6. PROSES LOGIN USER ---
if (isset($_POST['login_user'])) {
    $email    = mysqli_real_escape_string($con, $_POST['email']);
    $password = $_POST['password'];

    $result = mysqli_query($con, "SELECT * FROM users WHERE email = '$email'");
    if (mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        if (password_verify($password, $row['password'])) {
            $logged_id           = (int)$row['id'];
            $_SESSION['user_id'] = $logged_id;
            $_SESSION['Name']    = $row['username']; 
            $_SESSION['success'] = "Selamat datang kembali, " . $row['username'] . "!";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "Password salah!";
        }
    } else {
        $_SESSION['error'] = "Email tidak ditemukan!";
    }
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hogwarts Shop - Fashion & Aksesoris Sihir</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
</head>
<body class="storefront-home">

    <!-- Header Navigation -->
    <?php include "header.php"; ?>

    <!-- Notifikasi Sistem -->
    <?php if(isset($_SESSION['success']) || isset($_SESSION['error'])): ?>
    <div class="container mt-3">
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa fa-check-circle mr-1"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa fa-exclamation-circle mr-1"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Katalog Produk Utama -->
    <main>
        <?php include "body.php"; ?>
    </main>

    <!-- Modals & Footer -->
    <?php 
        include "newsletter.php";
        include "footer.php"; 
    ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
