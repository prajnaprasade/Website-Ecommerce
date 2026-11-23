<?php
$host = "127.0.0.1";
$user = "root";     
$pass = "root";          // Password Laragon bawaannya KOSONG
$db   = "hogwarts_db";

// Menggunakan $con agar cocok dengan index.php
$con = mysqli_connect($host, $user, $pass, $db);

if (!$con) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>