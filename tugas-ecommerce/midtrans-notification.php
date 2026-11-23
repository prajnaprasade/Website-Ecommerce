<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/midtrans.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'rejected']);
    exit();
}
if (!midtrans_is_configured()) {
    http_response_code(503);
    echo json_encode(['status' => 'temporarily_unavailable']);
    exit();
}

$raw_body = file_get_contents('php://input');
$notification = json_decode($raw_body, true);
if (!is_array($notification) ||
    !isset($notification['order_id'], $notification['status_code'], $notification['gross_amount'], $notification['signature_key']) ||
    !is_string($notification['order_id']) ||
    !is_scalar($notification['status_code']) ||
    !is_scalar($notification['gross_amount']) ||
    !is_string($notification['signature_key']) ||
    !preg_match('/^HS-[A-F0-9]{24}$/', $notification['order_id']) ||
    !preg_match('/^[0-9]+$/', (string)$notification['status_code']) ||
    !is_numeric($notification['gross_amount']) ||
    !preg_match('/^[a-fA-F0-9]{128}$/', $notification['signature_key'])) {
    http_response_code(400);
    echo json_encode(['status' => 'invalid_notification']);
    exit();
}

$order_id = (string)$notification['order_id'];
$expected_signature = hash(
    'sha512',
    $order_id . $notification['status_code'] . $notification['gross_amount'] . MIDTRANS_SERVER_KEY
);
if (!hash_equals($expected_signature, (string)$notification['signature_key'])) {
    http_response_code(403);
    echo json_encode(['status' => 'invalid_signature']);
    exit();
}

$provider_status = midtrans_api_request('GET', '/v2/' . rawurlencode($order_id) . '/status');
if (!$provider_status || !midtrans_apply_status($con, $order_id, $provider_status)) {
    http_response_code(500);
    echo json_encode(['status' => 'status_update_failed']);
    exit();
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
