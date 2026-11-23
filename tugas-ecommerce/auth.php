<?php
function current_user_id($con) {
    if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
        return (int)$_SESSION['user_id'];
    }

    if (empty($_SESSION['Name']) || !is_string($_SESSION['Name'])) {
        return null;
    }

    $username = mysqli_real_escape_string($con, $_SESSION['Name']);
    $result = mysqli_query($con, "SELECT id FROM users WHERE username = '$username' LIMIT 1");
    if (!$result) {
        error_log("Gagal memulihkan sesi pengguna: " . mysqli_error($con));
        return null;
    }

    $user = mysqli_fetch_assoc($result);
    if (!$user || (int)$user['id'] < 1) {
        return null;
    }

    $_SESSION['user_id'] = (int)$user['id'];
    return (int)$user['id'];
}
