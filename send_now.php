<?php
require_once 'config.php';

// Função para normalizar o telefone, garantindo que ele está no formato que a Z-API espera
function normalize_phone_for_zapi($raw) {
    $raw = (string)$raw;
    // Remove tudo que não for dígito
    $digits = preg_replace('/\D+/', '', $raw);
    
    // Se o número já tem o DDI do Brasil ou de Portugal, retorna como está
    if ((strpos($digits, '55') === 0) || (strpos($digits, '351') === 0)) {
        return $digits;
    }
    
    // Se for um número de 9 dígitos (provavelmente Portugal)
    if (strlen($digits) === 9) {
        return '351' . $digits;
    }
    
    // Se for um número de 10 ou 11 dígitos (provavelmente Brasil)
    if (strlen($digits) === 10 || strlen($digits) === 11) {
        return '55' . $digits;
    }
    
    // Fallback: retorna o que tiver, pode ser que o usuário digitou o DDI de outro país
    return $digits;
}

$phone_raw = $_POST['phone'] ?? '';
$message = $_POST['message'] ?? '';

if (!$phone_raw || !$message) {
    die("⚠️ Número ou mensagem ausente.");
}

// Normaliza o número antes de enviar
$phone_normalized = normalize_phone_for_zapi($phone_raw);

?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <title>Resultado do Envio Z-API</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-4">📤 Resultado do Envio via Z-API</h1>
        
        <div class="mb-4">
            <p><strong>Número Original:</strong> <?= htmlspecialchars($phone_raw) ?></p>
            <p><strong>Número Normalizado (enviado para a API):</strong> <?= htmlspecialchars($phone_normalized) ?></p>
            <p><strong>Mensagem:</strong> <?= htmlspecialchars($message) ?></p>
        </div>

        <?php
        $result_raw = enviarMensagem($phone_normalized, $message);
        $result_data = json_decode($result_raw, true);
        ?>

        <div class="mb-6">
            <h2 class="text-xl font-semibold mb-2">Resposta da API:</h2>
            <pre class="bg-gray-800 text-white p-4 rounded-md overflow-x-auto"><?php print_r($result_data ?: $result_raw); ?></pre>
        </div>

        <div class="p-4 rounded-lg 
            <?php 
            if (isset($result_data['messageId'])) {
                echo 'bg-green-100 text-green-800';
            } elseif (isset($result_data['error'])) {
                 echo 'bg-red-100 text-red-800';
            } else {
                 echo 'bg-yellow-100 text-yellow-800';
            }
            ?>">
            <h3 class="font-bold text-lg mb-2">Diagnóstico:</h3>
            <?php
            if (isset($result_data['messageId'])) {
                echo "<p>✅ A API aceitou a mensagem e retornou um 'messageId'. Isso significa que a comunicação do seu servidor com a Z-API está funcionando.</p>";
                echo "<p class='mt-2'><strong>Possível Causa:</strong> Se a mensagem não chegou, o problema pode estar na sua instância da Z-API (verifique no painel deles se o QR Code foi lido e se a instância está conectada) ou o número de destino pode não ter WhatsApp.</p>";
            } elseif (isset($result_data['error'])) {
                echo "<p>❌ A API retornou um erro explícito: <strong>" . htmlspecialchars($result_data['error']) . "</strong></p>";
                echo "<p class='mt-2'><strong>Ação:</strong> Verifique a mensagem de erro. Pode ser um problema com o token, a instância ou o formato do número.</p>";
            } else {
                echo "<p>⚠️ A resposta da API não foi o esperado. Não contém um 'messageId' nem um 'error'.</p>";
                echo "<p class='mt-2'><strong>Ação:</strong> Verifique as credenciais (Instância, Token, Client-Token) no seu ficheiro <code>config.php</code> e o status da sua instância no painel da Z-API.</p>";
            }
            ?>
        </div>

        <div class="mt-6">
            <a href="admin.php" class="text-blue-600 hover:underline">&larr; Voltar ao Painel de Administração</a>
        </div>
    </div>
</body>
</html>
