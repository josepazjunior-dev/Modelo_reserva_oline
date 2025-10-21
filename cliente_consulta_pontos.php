<?php
require_once 'config.php';
$mensagem = '';
$pontos = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telefone = normalize_phone($_POST['telefone'] ?? '');
    if ($telefone) {
        try {
            $pdo = conectarDB();
            $stmt = $pdo->prepare("SELECT nome, pontos FROM clientes WHERE telefone = ?");
            $stmt->execute([$telefone]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cliente) {
                $pontos = $cliente['pontos'];
                $mensagem = "Olá {$cliente['nome']}, você tem {$pontos} pontos de fidelidade!";
            } else {
                $mensagem = "Cliente não encontrado. Verifique o telefone.";
            }
        } catch (Exception $e) {
            $mensagem = "Erro ao consultar pontos.";
        }
    }
}

function normalize_phone($raw) {
    // Mesma função do agendar.php
    $raw = (string)$raw;
    if (strpos($raw, '+') === 0) return $raw;
    $digits = preg_replace('/\D+/', '', $raw);
    if (strpos($digits, '55') === 0 || strpos($digits, '351') === 0) {
        return '+' . $digits;
    }
    if (strlen($digits) >= 10 && strlen($digits) <= 11) {
        return '+55' . $digits;
    }
    return '+' . $digits;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Consultar Pontos de Fidelidade</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-md mx-auto bg-white p-6 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-4">Consultar Pontos de Fidelidade</h1>
        <form method="POST">
            <label for="telefone" class="block mb-2">Seu Telefone (com código do país):</label>
            <input type="tel" id="telefone" name="telefone" required class="w-full border px-3 py-2 rounded">
            <button type="submit" class="w-full bg-blue-600 text-white py-2 mt-4 rounded">Consultar</button>
        </form>
        <?php if ($mensagem): ?>
            <p class="mt-4 text-green-600"><?= $mensagem ?></p>
        <?php endif; ?>
    </div>
</body>
</html>