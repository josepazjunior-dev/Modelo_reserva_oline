<?php
require_once 'config.php';

// Obter o ID do agendamento da URL (opcional)
$agendamento_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Função para detectar se o dispositivo é iOS
function is_ios() {
    $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
    return (strpos($ua, 'iphone') !== false || strpos($ua, 'ipod') !== false || strpos($ua, 'ipad') !== false);
}

// Função para detectar se o dispositivo é Android
function is_android() {
    $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
    return (strpos($ua, 'android') !== false);
}

// Determinar qual é o dispositivo
$device_type = is_ios() ? 'ios' : (is_android() ? 'android' : 'unknown');

// Definir URLs para download
$download_url = '';
switch ($device_type) {
    case 'ios':
        $download_url = APP_STORE_URL;
        $app_store = 'App Store';
        $device_name = 'iPhone/iPad';
        $button_color = 'bg-blue-500 hover:bg-blue-600';
        break;
        
    case 'android':
        $download_url = PLAY_STORE_URL;
        $app_store = 'Google Play';
        $device_name = 'Android';
        $button_color = 'bg-green-500 hover:bg-green-600';
        break;
        
    default:
        // Para desktop ou dispositivos desconhecidos, mostrar ambas as opções
        $download_url = '#';
        $app_store = 'loja de aplicativos';
        $device_name = 'dispositivo';
        $button_color = 'bg-gray-500 hover:bg-gray-600';
}

// Se for um agendamento específico, podemos obter informações adicionais
$agendamento = null;
if ($agendamento_id > 0) {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            SELECT a.*, s.nome as nome_servico
            FROM agendamentos a
            LEFT JOIN servicos s ON a.servico_id = s.id
            WHERE a.id = ?
        ");
        $stmt->execute([$agendamento_id]);
        $agendamento = $stmt->fetch();
    } catch (Exception $e) {
        // Falha silenciosa - o agendamento não é essencial para a página funcionar
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Baixe nosso aplicativo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #fce4ec, #e0f2f7);
            min-height: 100vh;
        }
        h1, h2 {
            font-family: 'Playfair Display', serif;
            color: #883e5a;
        }
        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            min-height: 100vh;
        }
        .card {
            background-color: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 2rem;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            color: white;
            transition: all 0.3s ease;
            margin: 0.5rem;
        }
        .app-icon {
            width: 120px;
            height: 120px;
            margin: 1rem auto;
            border-radius: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            background-color: #f8f8f8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
        }
        @keyframes pulse {
            0% {transform: scale(1);}
            50% {transform: scale(1.05);}
            100% {transform: scale(1);}
        }
        .animate-pulse {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="app-icon animate-pulse">✂️</div>
            <h1 class="text-3xl font-bold mb-4">Baixe nosso aplicativo</h1>
            
            <?php if ($agendamento): ?>
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <h3 class="font-semibold">Detalhes do seu agendamento:</h3>
                    <p>Serviço: <?= htmlspecialchars($agendamento['nome_servico'] ?? $agendamento['servico']) ?></p>
                    <p>Data: <?= date("d/m/Y", strtotime($agendamento['data_agendamento'])) ?></p>
                    <p>Horário: <?= date("H:i", strtotime($agendamento['hora_agendamento'])) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($device_type !== 'unknown'): ?>
                <p class="mb-6">Detectamos que você está usando um dispositivo <?= $device_name ?>. Para uma experiência melhor, baixe nosso aplicativo:</p>
                <a href="<?= $download_url ?>" class="btn <?= $button_color ?>">
                    Baixar no <?= $app_store ?>
                </a>
            <?php else: ?>
                <p class="mb-6">Escolha a loja de aplicativos para o seu dispositivo:</p>
                <div class="flex justify-center flex-wrap">
                    <a href="<?= APP_STORE_URL ?>" class="btn bg-blue-500 hover:bg-blue-600 m-2">
                        Baixar na App Store
                    </a>
                    <a href="<?= PLAY_STORE_URL ?>" class="btn bg-green-500 hover:bg-green-600 m-2">
                        Baixar no Google Play
                    </a>
                </div>
            <?php endif; ?>
            
            <p class="mt-8 text-sm text-gray-500">Com nosso aplicativo, você pode agendar horários, receber lembretes e muito mais!</p>
        </div>
    </div>
</body>
</html>