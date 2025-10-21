<?php
// --- CONFIGURAÇÃO DA BASE DE DADOS ---
define('DB_HOST', 'localhost');
define('DB_USER', 'josepazjunior2admin_reservas_on');
define('DB_PASS', '6S$9Ar=a%6zR@f}5');
define('DB_NAME', 'josepazjunior2admin_reservas_on');

// --- CONEXÃO COM BANCO DE DADOS ---
function conectarDB() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Erro ao conectar ao banco de dados: " . $e->getMessage());
    }
}

// --- CONFIGURAÇÃO Z-API ---
define('ZAPI_BASE_URL', 'https://api.z-api.io/instances/');
define('ZAPI_INSTANCE', '3E8E3844019D923DC897D62DFBFE6798');
define('ZAPI_TOKEN', 'D7D6001D0ED5FCA4529C71AF');
define('ZAPI_CLIENT_TOKEN', 'F587c920d7e20443993cb69d009f6fe11S');

// --- FUNÇÃO PARA ENVIAR MENSAGENS ---
function enviarMensagem($phone, $message) {
    $url = ZAPI_BASE_URL . ZAPI_INSTANCE . "/token/" . ZAPI_TOKEN . "/send-text";
    $headers = [
        'Content-Type: application/json',
        'Client-Token: ' . ZAPI_CLIENT_TOKEN
    ];

    $data = json_encode([
        'phone' => $phone,
        'message' => $message
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}
?>

