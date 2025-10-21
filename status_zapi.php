<?php
require_once 'config.php';

$url = ZAPI_BASE_URL . ZAPI_INSTANCE . '/token/' . ZAPI_TOKEN . '/status';
$headers = [
    'Content-Type: application/json',
    'Client-Token: ' . ZAPI_CLIENT_TOKEN
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$resposta = curl_exec($ch);
curl_close($ch);

$data = json_decode($resposta, true);

if (isset($data['connected']) && $data['connected'] === true) {
    echo '<span style="color:green;font-weight:bold;">🟢 Conectado ao WhatsApp</span>';
} else {
    echo '<span style="color:red;font-weight:bold;">🔴 Desconectado</span>';
}
?>
