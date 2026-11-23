<?php
session_start();
$is_register = isset($auth_mode) && $auth_mode === 'register';
$page_title = $is_register ? 'Daftar Akun' : 'Masuk';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?> - Hogwarts Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'style.css'); ?>">
</head>
<body class="auth-page">
    <main class="auth-layout<?php echo $is_register ? ' auth-layout-register' : ' auth-layout-login'; ?>">
        <section class="auth-intro">
            <a class="auth-brand" href="index.php"><i class="fa fa-bolt" aria-hidden="true"></i> Hogwarts Shop</a>
            <div class="auth-intro-copy">
                <span class="auth-eyebrow">PERLENGKAPAN SIHIR PILIHAN</span>
                <h1><?php echo $is_register ? 'Mulai petualangan sihirmu.' : 'Senang melihatmu kembali.'; ?></h1>
                <p><?php echo $is_register
                    ? 'Buat akun untuk menemukan koleksi jubah, tongkat, dan aksesori favoritmu.'
                    : 'Masuk untuk melanjutkan belanja dan melihat pesanan sihirmu.'; ?></p>
            </div>
            <div class="auth-seal" aria-hidden="true"><i class="fa fa-bolt"></i></div>
            <a class="auth-back-link" href="index.php"><i class="fa fa-arrow-left mr-2"></i>Kembali ke toko</a>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <span class="auth-eyebrow"><?php echo $is_register ? 'AKUN BARU' : 'AKUN HOGWARTS SHOP'; ?></span>
                <h2><?php echo $is_register ? 'Daftar' : 'Masuk'; ?></h2>
                <p class="auth-form-lead"><?php echo $is_register ? 'Lengkapi data di bawah untuk membuat akun.' : 'Masukkan email dan password akunmu.'; ?></p>

                <?php if ($error_message !== ''): ?>
                    <div class="alert alert-danger auth-alert" role="alert">
                        <i class="fa fa-exclamation-circle mr-1"></i>
                        <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php elseif (isset($_GET['login_required']) && $_GET['login_required'] === '1'): ?>
                    <div class="alert alert-info auth-alert" role="status">Masuk terlebih dahulu untuk melanjutkan.</div>
                <?php endif; ?>

                <form method="POST" action="index.php">
                    <?php if ($is_register): ?>
                        <div class="form-group">
                            <label for="authUsername">Nama pengguna</label>
                            <input id="authUsername" type="text" class="form-control" name="username" autocomplete="name" placeholder="Nama lengkapmu" required>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="authEmail">Email</label>
                        <input id="authEmail" type="email" class="form-control" name="email" autocomplete="email" placeholder="nama@email.com" required>
                    </div>
                    <div class="form-group">
                        <label for="authPassword">Password</label>
                        <input id="authPassword" type="password" class="form-control" name="<?php echo $is_register ? 'password_1' : 'password'; ?>" autocomplete="<?php echo $is_register ? 'new-password' : 'current-password'; ?>" placeholder="Masukkan password" required>
                    </div>
                    <?php if ($is_register): ?>
                        <div class="form-group">
                            <label for="authPasswordConfirm">Konfirmasi password</label>
                            <input id="authPasswordConfirm" type="password" class="form-control" name="password_2" autocomplete="new-password" placeholder="Ulangi password" required>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-hogwarts-primary btn-block auth-submit" name="<?php echo $is_register ? 'reg_user' : 'login_user'; ?>">
                        <?php echo $is_register ? 'Buat Akun' : 'Masuk ke Akun'; ?>
                        <i class="fa fa-arrow-right ml-2" aria-hidden="true"></i>
                    </button>
                </form>

                <p class="auth-switch">
                    <?php if ($is_register): ?>
                        Sudah punya akun? <a href="login.php">Masuk di sini</a>
                    <?php else: ?>
                        Belum punya akun? <a href="register.php">Daftar di sini</a>
                    <?php endif; ?>
                </p>
            </div>
        </section>
    </main>
</body>
</html>
