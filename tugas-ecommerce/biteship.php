<?php

function biteship_is_configured() {
    return defined('BITESHIP_API_KEY') && BITESHIP_API_KEY !== '' &&
        strpos(BITESHIP_API_KEY, 'REPLACE_WITH_') === false;
}

function biteship_api_request($method, $path, $payload = null) {
    if (!biteship_is_configured() || !function_exists('curl_init')) {
        error_log('Biteship is not configured or PHP cURL is unavailable.');
        return null;
    }

    $url = 'https://api.biteship.com' . $path;
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: ' . BITESHIP_API_KEY,
        ],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ];

    if ($payload !== null) {
        $encoded_payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded_payload === false) {
            error_log('Failed to encode Biteship request payload.');
            curl_close($ch);
            return null;
        }
        $options[CURLOPT_POSTFIELDS] = $encoded_payload;
    }

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        error_log('Biteship request failed: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);

    $result = json_decode($response, true);
    if ($http_code < 200 || $http_code >= 300 || !is_array($result)) {
        $provider_message = is_array($result) && isset($result['message']) && is_string($result['message'])
            ? $result['message']
            : 'invalid response';
        error_log('Biteship returned HTTP ' . $http_code . ': ' . $provider_message);
        return null;
    }

    return $result;
}

function biteship_search_areas($query) {
    $path = '/v1/maps/areas?countries=ID&input=' . rawurlencode($query) . '&type=single';
    $response = biteship_api_request('GET', $path);
    if (!$response || empty($response['areas']) || !is_array($response['areas'])) {
        return [];
    }

    $areas = [];
    foreach ($response['areas'] as $area) {
        if (!is_array($area) || empty($area['id']) || empty($area['name'])) {
            continue;
        }
        $areas[] = [
            'id' => (string)$area['id'],
            'name' => (string)$area['name'],
            'postal_code' => isset($area['postal_code']) ? (string)$area['postal_code'] : '',
        ];
    }
    return $areas;
}

function biteship_resolve_origin_area() {
    if (BITESHIP_ORIGIN_AREA_ID !== '') {
        return BITESHIP_ORIGIN_AREA_ID;
    }

    $query = trim(BITESHIP_ORIGIN_CITY_DISTRICT . ' ' . BITESHIP_ORIGIN_POSTAL_CODE);
    $areas = biteship_search_areas($query);
    $matches = [];
    foreach ($areas as $area) {
        if ($area['postal_code'] === (string)BITESHIP_ORIGIN_POSTAL_CODE) {
            $matches[] = $area;
        }
    }

    if (count($matches) !== 1) {
        error_log('Biteship origin lookup did not return one exact postal-code match.');
        return '';
    }
    return $matches[0]['id'];
}

function biteship_cart_fingerprint($items) {
    $fingerprint_items = [];
    foreach ($items as $item) {
        $fingerprint_items[] = [
            'cart_id' => isset($item['cart_id']) ? (int)$item['cart_id'] : 0,
            'id' => (int)$item['p_id'],
            'qty' => (int)$item['qty'],
            'price' => (int)$item['product_price'],
            'title' => (string)$item['product_title'],
        ];
    }
    return hash('sha256', json_encode($fingerprint_items, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
}
