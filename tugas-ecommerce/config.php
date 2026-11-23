<?php
// Pengaturan nama toko
define('SITE_NAME', 'Hogwarts Shop');
$siteBaseUrl = getenv('SITE_BASE_URL');

$localEnvValues = [];
$envFile = __DIR__ . DIRECTORY_SEPARATOR . '.env';
if (is_file($envFile)) {
    $parsedEnvValues = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if ($parsedEnvValues === false) {
        error_log('Unable to parse the local .env configuration file.');
    } else {
        $localEnvValues = $parsedEnvValues;
    }
}
if (($siteBaseUrl === false || $siteBaseUrl === '') && isset($localEnvValues['SITE_BASE_URL'])) {
    $siteBaseUrl = trim($localEnvValues['SITE_BASE_URL']);
}
define('BASE_URL', rtrim($siteBaseUrl === false || $siteBaseUrl === '' ? 'http://localhost/tugas-ecommerce/' : $siteBaseUrl, '/') . '/');

$geminiApiKey = getenv('GEMINI_API_KEY');
if (($geminiApiKey === false || $geminiApiKey === '') && isset($localEnvValues['GEMINI_API_KEY'])) {
    $geminiApiKey = trim($localEnvValues['GEMINI_API_KEY']);
}
if ($geminiApiKey === false || $geminiApiKey === 'PASTE_NEW_ROTATED_KEY_HERE') {
    $geminiApiKey = '';
}
define('GEMINI_API_KEY', $geminiApiKey === false ? '' : $geminiApiKey);

$midtransServerKey = getenv('MIDTRANS_SERVER_KEY');
if (($midtransServerKey === false || $midtransServerKey === '') && isset($localEnvValues['MIDTRANS_SERVER_KEY'])) {
    $midtransServerKey = trim($localEnvValues['MIDTRANS_SERVER_KEY']);
}
define('MIDTRANS_SERVER_KEY', $midtransServerKey === false ? '' : $midtransServerKey);

$midtransProduction = getenv('MIDTRANS_IS_PRODUCTION');
if (($midtransProduction === false || $midtransProduction === '') && isset($localEnvValues['MIDTRANS_IS_PRODUCTION'])) {
    $midtransProduction = trim($localEnvValues['MIDTRANS_IS_PRODUCTION']);
}
define('MIDTRANS_IS_PRODUCTION', filter_var($midtransProduction, FILTER_VALIDATE_BOOLEAN));

$biteshipApiKey = getenv('BITESHIP_API_KEY');
if (($biteshipApiKey === false || $biteshipApiKey === '') && isset($localEnvValues['BITESHIP_API_KEY'])) {
    $biteshipApiKey = trim($localEnvValues['BITESHIP_API_KEY']);
}
define('BITESHIP_API_KEY', $biteshipApiKey === false ? '' : $biteshipApiKey);

$biteshipOriginAreaId = getenv('BITESHIP_ORIGIN_AREA_ID');
if (($biteshipOriginAreaId === false || $biteshipOriginAreaId === '') && isset($localEnvValues['BITESHIP_ORIGIN_AREA_ID'])) {
    $biteshipOriginAreaId = trim($localEnvValues['BITESHIP_ORIGIN_AREA_ID']);
}
define('BITESHIP_ORIGIN_AREA_ID', $biteshipOriginAreaId === false ? '' : $biteshipOriginAreaId);

$biteshipOriginCity = getenv('BITESHIP_ORIGIN_CITY_DISTRICT');
if (($biteshipOriginCity === false || $biteshipOriginCity === '') && isset($localEnvValues['BITESHIP_ORIGIN_CITY_DISTRICT'])) {
    $biteshipOriginCity = trim($localEnvValues['BITESHIP_ORIGIN_CITY_DISTRICT']);
}
define('BITESHIP_ORIGIN_CITY_DISTRICT', $biteshipOriginCity === false || $biteshipOriginCity === '' ? 'Parakan, Temanggung' : $biteshipOriginCity);

$biteshipOriginPostalCode = getenv('BITESHIP_ORIGIN_POSTAL_CODE');
if (($biteshipOriginPostalCode === false || $biteshipOriginPostalCode === '') && isset($localEnvValues['BITESHIP_ORIGIN_POSTAL_CODE'])) {
    $biteshipOriginPostalCode = trim($localEnvValues['BITESHIP_ORIGIN_POSTAL_CODE']);
}
define('BITESHIP_ORIGIN_POSTAL_CODE', $biteshipOriginPostalCode === false || $biteshipOriginPostalCode === '' ? '56254' : $biteshipOriginPostalCode);
?>
