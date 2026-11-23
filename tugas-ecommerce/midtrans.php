<?php

function midtrans_is_configured() {
    return midtrans_configuration_error() === '';
}

function midtrans_configuration_error() {
    if (!defined('MIDTRANS_SERVER_KEY') || MIDTRANS_SERVER_KEY === '' ||
        strpos(MIDTRANS_SERVER_KEY, 'REPLACE_WITH_') !== false) {
        return 'MIDTRANS_SERVER_KEY belum diisi.';
    }

    $is_sandbox_key = strpos(MIDTRANS_SERVER_KEY, 'SB-Mid-server-') === 0;
    if (MIDTRANS_IS_PRODUCTION && $is_sandbox_key) {
        return 'Mode Production memerlukan Live Server Key Midtrans.';
    }
    if (MIDTRANS_IS_PRODUCTION) {
        $base_url_scheme = strtolower((string)parse_url(BASE_URL, PHP_URL_SCHEME));
        $base_url_host = strtolower((string)parse_url(BASE_URL, PHP_URL_HOST));
        if ($base_url_scheme !== 'https' || in_array($base_url_host, ['localhost', '127.0.0.1', '::1'], true)) {
            return 'Mode Production memerlukan SITE_BASE_URL berupa domain publik dengan HTTPS.';
        }
    }
    if (!MIDTRANS_IS_PRODUCTION && !$is_sandbox_key) {
        return 'Mode Sandbox memerlukan Sandbox Server Key Midtrans.';
    }

    return '';
}

function midtrans_api_request($method, $path, $payload = null) {
    if (!midtrans_is_configured() || !function_exists('curl_init')) {
        error_log('Midtrans is not configured or PHP cURL is unavailable.');
        return null;
    }

    $base_url = MIDTRANS_IS_PRODUCTION
        ? 'https://api.midtrans.com'
        : 'https://api.sandbox.midtrans.com';
    $url = $base_url . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
        CURLOPT_USERPWD => MIDTRANS_SERVER_KEY . ':',
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ];

    if ($payload !== null) {
        $encoded_payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded_payload === false) {
            error_log('Failed to encode Midtrans request payload.');
            curl_close($ch);
            return null;
        }
        $options[CURLOPT_POSTFIELDS] = $encoded_payload;
    }

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        error_log('Midtrans request failed: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);

    $result = json_decode($response, true);
    if ($http_code < 200 || $http_code >= 300 || !is_array($result)) {
        $provider_message = is_array($result) && isset($result['status_message'])
            ? $result['status_message']
            : 'invalid response';
        error_log('Midtrans returned HTTP ' . $http_code . ': ' . $provider_message);
        return null;
    }

    return $result;
}

function midtrans_cancel_reserved_order($con, $payment_order_id) {
    if (!mysqli_begin_transaction($con)) {
        error_log('Failed to start transaction while releasing a Midtrans order.');
        return false;
    }

    $order_query = mysqli_prepare(
        $con,
        'SELECT order_id, payment_status FROM orders_info WHERE payment_order_id = ? FOR UPDATE'
    );
    if (!$order_query || !mysqli_stmt_bind_param($order_query, 's', $payment_order_id) ||
        !mysqli_stmt_execute($order_query)) {
        error_log('Failed to load order while releasing a Midtrans order: ' . mysqli_error($con));
        mysqli_rollback($con);
        return false;
    }
    $order_result = mysqli_stmt_get_result($order_query);
    $order = $order_result ? mysqli_fetch_assoc($order_result) : null;
    mysqli_stmt_close($order_query);
    if (!$order || $order['payment_status'] !== 'pending') {
        mysqli_rollback($con);
        return false;
    }

    $order_id = (int)$order['order_id'];
    $items_query = mysqli_prepare($con, 'SELECT product_id, qty FROM order_products WHERE order_id = ?');
    if (!$items_query || !mysqli_stmt_bind_param($items_query, 'i', $order_id) ||
        !mysqli_stmt_execute($items_query)) {
        error_log('Failed to load reserved Midtrans order items: ' . mysqli_error($con));
        mysqli_rollback($con);
        return false;
    }
    $items_result = mysqli_stmt_get_result($items_query);
    $stock_update = mysqli_prepare($con, 'UPDATE products SET product_qty = product_qty + ? WHERE product_id = ?');
    if (!$items_result || !$stock_update) {
        error_log('Failed to prepare stock restoration for a Midtrans order: ' . mysqli_error($con));
        mysqli_stmt_close($items_query);
        if ($stock_update) {
            mysqli_stmt_close($stock_update);
        }
        mysqli_rollback($con);
        return false;
    }
    while ($item = mysqli_fetch_assoc($items_result)) {
        $quantity = (int)$item['qty'];
        $product_id = (int)$item['product_id'];
        if (!mysqli_stmt_bind_param($stock_update, 'ii', $quantity, $product_id) ||
            !mysqli_stmt_execute($stock_update)) {
            error_log('Failed to restore stock for a Midtrans order: ' . mysqli_stmt_error($stock_update));
            mysqli_stmt_close($stock_update);
            mysqli_stmt_close($items_query);
            mysqli_rollback($con);
            return false;
        }
    }
    mysqli_stmt_close($stock_update);
    mysqli_stmt_close($items_query);

    $update_order = mysqli_prepare(
        $con,
        "UPDATE orders_info SET payment_status = 'failed', order_status = 'cancelled'
         WHERE order_id = ? AND payment_status = 'pending'"
    );
    $update_items = mysqli_prepare(
        $con,
        "UPDATE orders SET p_status = 'Cancelled' WHERE trx_id = ?"
    );
    if (!$update_order || !$update_items ||
        !mysqli_stmt_bind_param($update_order, 'i', $order_id) ||
        !mysqli_stmt_execute($update_order) ||
        !mysqli_stmt_bind_param($update_items, 's', $payment_order_id) ||
        !mysqli_stmt_execute($update_items)) {
        error_log('Failed to mark a Midtrans order as failed: ' . mysqli_error($con));
        if ($update_order) {
            mysqli_stmt_close($update_order);
        }
        if ($update_items) {
            mysqli_stmt_close($update_items);
        }
        mysqli_rollback($con);
        return false;
    }
    mysqli_stmt_close($update_order);
    mysqli_stmt_close($update_items);
    if (!mysqli_commit($con)) {
        error_log('Failed to commit Midtrans stock restoration.');
        mysqli_rollback($con);
        return false;
    }
    return true;
}

function midtrans_apply_status($con, $payment_order_id, $provider_data) {
    if (!isset($provider_data['order_id'], $provider_data['gross_amount'], $provider_data['transaction_status'])) {
        return false;
    }
    if (!hash_equals($payment_order_id, (string)$provider_data['order_id'])) {
        return false;
    }

    if (!mysqli_begin_transaction($con)) {
        error_log('Failed to start transaction while updating Midtrans status.');
        return false;
    }
    $order_query = mysqli_prepare(
        $con,
        'SELECT order_id, total_amt, payment_status, order_status
         FROM orders_info WHERE payment_order_id = ? FOR UPDATE'
    );
    if (!$order_query || !mysqli_stmt_bind_param($order_query, 's', $payment_order_id) ||
        !mysqli_stmt_execute($order_query)) {
        error_log('Failed to load order for Midtrans status: ' . mysqli_error($con));
        mysqli_rollback($con);
        return false;
    }
    $order_result = mysqli_stmt_get_result($order_query);
    $order = $order_result ? mysqli_fetch_assoc($order_result) : null;
    mysqli_stmt_close($order_query);
    if (!$order || number_format((float)$provider_data['gross_amount'], 2, '.', '') !==
        number_format((float)$order['total_amt'], 2, '.', '')) {
        error_log('Midtrans order reference or amount did not match local order.');
        mysqli_rollback($con);
        return false;
    }

    $provider_status = (string)$provider_data['transaction_status'];
    $fraud_status = isset($provider_data['fraud_status']) ? (string)$provider_data['fraud_status'] : '';
    if ($provider_status === 'capture' && $fraud_status === 'challenge') {
        $payment_status = 'pending';
    } elseif ($provider_status === 'settlement' || ($provider_status === 'capture' && $fraud_status === 'accept')) {
        $payment_status = 'paid';
    } elseif (in_array($provider_status, ['deny', 'cancel', 'expire'], true)) {
        $payment_status = $provider_status === 'deny' ? 'failed' : $provider_status . 'led';
        if ($provider_status === 'expire') {
            $payment_status = 'expired';
        }
    } elseif ($provider_status === 'pending') {
        $payment_status = 'pending';
    } elseif (in_array($provider_status, ['refund', 'partial_refund'], true)) {
        $payment_status = 'refunded';
    } else {
        error_log('Unhandled Midtrans transaction status: ' . $provider_status);
        mysqli_rollback($con);
        return false;
    }

    $order_id = (int)$order['order_id'];
    $current_payment_status = (string)$order['payment_status'];
    $payment_type = isset($provider_data['payment_type']) ? (string)$provider_data['payment_type'] : null;
    $transaction_id = isset($provider_data['transaction_id']) ? (string)$provider_data['transaction_id'] : null;

    if ($payment_status === 'paid' && $current_payment_status !== 'pending') {
        if (in_array($current_payment_status, ['failed', 'cancelled', 'expired'], true)) {
            $payment_status = 'paid_review';
            error_log('Late Midtrans payment requires manual stock review for order ' . $order_id . '.');
        } elseif (in_array($current_payment_status, ['paid', 'paid_review'], true)) {
            mysqli_commit($con);
            return true;
        }
    }

    if (in_array($payment_status, ['failed', 'cancelled', 'expired'], true) &&
        $current_payment_status === 'pending') {
        $items_query = mysqli_prepare($con, 'SELECT product_id, qty FROM order_products WHERE order_id = ?');
        $stock_update = mysqli_prepare($con, 'UPDATE products SET product_qty = product_qty + ? WHERE product_id = ?');
        if (!$items_query || !$stock_update ||
            !mysqli_stmt_bind_param($items_query, 'i', $order_id) ||
            !mysqli_stmt_execute($items_query)) {
            error_log('Failed to prepare Midtrans order stock release: ' . mysqli_error($con));
            mysqli_rollback($con);
            return false;
        }
        $items_result = mysqli_stmt_get_result($items_query);
        while ($items_result && ($item = mysqli_fetch_assoc($items_result))) {
            $quantity = (int)$item['qty'];
            $product_id = (int)$item['product_id'];
            if (!mysqli_stmt_bind_param($stock_update, 'ii', $quantity, $product_id) ||
                !mysqli_stmt_execute($stock_update)) {
                error_log('Failed to release stock after Midtrans payment failure: ' . mysqli_stmt_error($stock_update));
                mysqli_stmt_close($stock_update);
                mysqli_stmt_close($items_query);
                mysqli_rollback($con);
                return false;
            }
        }
        mysqli_stmt_close($stock_update);
        mysqli_stmt_close($items_query);
    }

    $new_order_status = null;
    if ($payment_status === 'paid') {
        $new_order_status = 'processing';
    } elseif (in_array($payment_status, ['failed', 'cancelled', 'expired'], true) &&
        $current_payment_status === 'pending') {
        $new_order_status = 'cancelled';
    }
    $update_order = mysqli_prepare(
        $con,
        'UPDATE orders_info
         SET payment_status = ?, payment_type = ?, payment_transaction_id = ?,
             order_status = COALESCE(?, order_status),
             paid_at = CASE WHEN ? IN (?, ?) THEN COALESCE(paid_at, CURRENT_TIMESTAMP) ELSE paid_at END
         WHERE order_id = ?'
    );
    $paid_status = 'paid';
    $review_status = 'paid_review';
    if (!$update_order ||
        !mysqli_stmt_bind_param(
            $update_order,
            'sssssssi',
            $payment_status,
            $payment_type,
            $transaction_id,
            $new_order_status,
            $payment_status,
            $paid_status,
            $review_status,
            $order_id
        ) ||
        !mysqli_stmt_execute($update_order)) {
        error_log('Failed to update order with Midtrans payment status: ' . mysqli_error($con));
        if ($update_order) {
            mysqli_stmt_close($update_order);
        }
        mysqli_rollback($con);
        return false;
    }
    mysqli_stmt_close($update_order);

    if ($new_order_status === 'processing' || $new_order_status === 'cancelled') {
        $item_status = $new_order_status === 'processing' ? 'Processing' : 'Cancelled';
        $update_items = mysqli_prepare($con, 'UPDATE orders SET p_status = ? WHERE trx_id = ?');
        if (!$update_items ||
            !mysqli_stmt_bind_param($update_items, 'ss', $item_status, $payment_order_id) ||
            !mysqli_stmt_execute($update_items)) {
            error_log('Failed to update order item payment status: ' . mysqli_error($con));
            if ($update_items) {
                mysqli_stmt_close($update_items);
            }
            mysqli_rollback($con);
            return false;
        }
        mysqli_stmt_close($update_items);
    }

    if (!mysqli_commit($con)) {
        error_log('Failed to commit Midtrans payment status update.');
        mysqli_rollback($con);
        return false;
    }
    return true;
}
