<?php
session_start();
include "config.php";
include "db.php";

// Matikan error report ketat agar proses tidak terhenti
mysqli_report(MYSQLI_REPORT_OFF);

if (isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    
    if ($del_id > 0) {
        // Matikan proteksi Foreign Key sementara
        mysqli_query($con, "SET FOREIGN_KEY_CHECKS = 0");

        // Hapus data relasi produk di keranjang & pesanan
        @mysqli_query($con, "DELETE FROM cart WHERE p_id = '$del_id' OR product_id = '$del_id'");
        @mysqli_query($con, "DELETE FROM order_products WHERE product_id = '$del_id' OR p_id = '$del_id'");
        @mysqli_query($con, "DELETE FROM orders WHERE product_id = '$del_id' OR p_id = '$del_id'");

        // Hapus produk dari tabel utama
        $del1 = mysqli_query($con, "DELETE FROM products WHERE product_id = '$del_id'");
        $del2 = mysqli_query($con, "DELETE FROM products WHERE id = '$del_id'");

        mysqli_query($con, "SET FOREIGN_KEY_CHECKS = 1");

        if ($del1 || $del2) {
            $_SESSION['admin_msg'] = "Produk #$del_id berhasil dihapus dari katalog!";
        } else {
            $_SESSION['admin_err'] = "Gagal menghapus produk dari database.";
        }
    }
}

// Kembalikan ke halaman kelola produk
header("Location: admin.php?view=products");
exit();
?>