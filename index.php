<?php
require_once 'config.php';

// Conecta à base de dados para buscar os serviços ativos
try {
    $pdo = conectarDB();
    $servicos = $pdo->query("SELECT id, nome, duracao_minutos, preco, moeda FROM servicos WHERE ativo = TRUE ORDER BY nome ASC")->fetchAll();
} catch (Exception $e) {
    $servicos = [];
}

// Verificar se há mensagem de sucesso na sessão (para exibir modal após redirecionamento)
session_start();
$mostrar_modal = false;
$detalhes_agendamento = null;

if (isset($_SESSION['agendamento_sucesso']) && $_SESSION['agendamento_sucesso'] === true) {
    $mostrar_modal = true;
    $detalhes_agendamento = isset($_SESSION['detalhes_agendamento']) ? $_SESSION['detalhes_agendamento'] : null;
    // Limpar a sessão
    unset($_SESSION['agendamento_sucesso']);
    unset($_SESSION['detalhes_agendamento']);
}

// Lidar com consulta de pontos via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'consultar_pontos') {
    header('Content-Type: application/json');
    $telefone = $_POST['telefone'] ?? '';
    $response = ['status' => 'error', 'message' => 'Cliente não encontrado.'];

    if ($telefone) {
        try {
            $pdo = conectarDB();
            $stmt = $pdo->prepare("SELECT nome, pontos FROM clientes WHERE telefone = ?");
            $stmt->execute([$telefone]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cliente) {
                $response = [
                    'status' => 'success',
                    'message' => "Olá {$cliente['nome']}, você tem {$cliente['pontos']} pontos de fidelidade!",
                    'pontos' => $cliente['pontos']
                ];
            }
        } catch (Exception $e) {
            $response = ['status' => 'error', 'message' => 'Erro ao consultar pontos.'];
        }
    }
    echo json_encode($response);
    exit;
}

function normalize_phone($raw) {
    if (strpos($raw, '+') === 0) return $raw;
    $digits = preg_replace('/\D+/', '', $raw);
    if (strlen($digits) >= 10) {
        return '+55' . $digits;
    }
    return '+' . $digits;
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e293b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Barbearia Vintage">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="icon-192x192.png">
    <link rel="shortcut icon" href="favicon.ico">
    <title>Agendamento - Barbearia Vintage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- CSS da Biblioteca de Telefone Internacional -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/css/intlTelInput.css">
    <style>
        /* ESTILOS COMPLETOS RESTAURADOS */
        :root {
            --color-primary: #1e293b;
            --color-secondary: #94a3b8;
            --color-accent: #c2410c;
            --color-bg: #f8f9fa;
            --color-text: #334155;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--color-bg);
            background-image: url('https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            position: relative;
            min-height: 100vh;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: -1;
        }
        
        h1, h2, h3, h4 {
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .form-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            border-top: 5px solid var(--color-accent);
            position: relative;
            overflow: hidden;
        }
        
        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 180px;
            background-image: url('https://images.unsplash.com/photo-1599351431202-1e0f0137899a?ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            opacity: 0.1;
            z-index: 0;
        }
        
        .form-field-label {
            color: var(--color-primary);
            font-weight: 600;
        }
        
        .form-input, .form-select, .form-textarea {
            border-color: #cbd5e1;
            background-color: #f8fafc;
            transition: all 0.2s ease-in-out;
            border-radius: 4px;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px rgba(194, 65, 12, 0.1);
        }
        
        .btn-submit {
            background-color: var(--color-accent);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            background-color: #9a3412;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(194, 65, 12, 0.25);
        }
        
        .horarios-grid button {
            background-color: #f1f5f9;
            color: var(--color-primary);
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }
        
        .horarios-grid button.selecionado {
            background-color: var(--color-accent);
            color: white;
            border-color: var(--color-accent);
            font-weight: 600;
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(194, 65, 12, 0.25);
        }
        
        .horarios-grid button:hover:not(.selecionado) {
            background-color: #e2e8f0;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }
        
        .feedback-success {
            background-color: #ecfdf5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .feedback-error {
            background-color: #fef2f2;
            color: #b91c1c;
            border-left: 4px solid #ef4444;
        }
        
        .info-text {
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .preco {
            color: var(--color-accent);
            font-weight: 600;
        }
        
        /* Modal styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            padding: 15px;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-container {
            background-color: white;
            border-radius: 8px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
            padding: 0;
            transform: translateY(20px) scale(0.95);
            transition: transform 0.3s ease;
            overflow: hidden;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            border-top: 5px solid var(--color-accent);
        }
        
        .modal-overlay.active .modal-container {
            transform: translateY(0) scale(1);
        }
        
        .modal-header {
            background-color: var(--color-primary);
            color: white;
            padding: 1.5rem;
            position: relative;
            text-align: center;
        }
        
        .modal-header h2 {
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex-grow: 1;
        }
        
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.1);
            transition: all 0.2s ease;
        }
        
        .modal-close:hover {
            background-color: rgba(255,255,255,0.2);
            transform: rotate(90deg);
        }
        
        .confirmation-details {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 1.25rem;
            border-left: 4px solid var(--color-accent);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }
        
        .divider {
            margin: 1.5rem 0;
            border-top: 1px solid #e2e8f0;
        }
        
        /* Buttons */
        .install-btn {
            background-color: #059669;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 10px 20px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(5, 150, 105, 0.3);
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            margin: 0 5px;
            min-width: 110px;
            text-decoration: none;
        }
        
        .install-btn:hover {
            background-color: #047857;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(5, 150, 105, 0.4);
        }
        
        .close-btn {
            background-color: #475569;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 10px 20px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(71, 85, 105, 0.3);
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            margin: 0 5px;
            min-width: 110px;
        }
        
        .close-btn:hover {
            background-color: #334155;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(71, 85, 105, 0.4);
        }
        
        /* Logo and header */
        .brand-logo {
            margin-bottom: 1rem;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        
        .logo-img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            border-radius: 50%;
            background-color: white;
            padding: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 0.75rem;
        }
        
        .brand-name {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            margin: 0;
            letter-spacing: 2px;
        }
        
        .brand-tagline {
            color: #e2e8f0;
            font-size: 1rem;
            margin-top: 0.25rem;
        }
        
        /* Decorative elements */
        .decorator {
            position: absolute;
            opacity: 0.05;
            z-index: 0;
        }
        
        .decorator-scissors {
            bottom: 2rem;
            right: 2rem;
            width: 120px;
            height: 120px;
        }
        
        .decorator-razor {
            top: 2rem;
            left: 2rem;
            width: 80px;
            height: 80px;
            transform: rotate(-15deg);
        }
        
        /* Media queries */
        @media (max-width: 640px) {
            .brand-name {
                font-size: 1.5rem;
            }
            
            .brand-tagline {
                font-size: 0.9rem;
            }
            
            .logo-img {
                width: 80px;
                height: 80px;
            }
            
            .form-container {
                border-radius: 0;
                box-shadow: none;
            }
        }
        
        /* Install instructions */
        .install-instructions {
            margin-top: 1rem;
            font-size: 0.85rem;
            color: #64748b;
            display: none;
            background-color: #f1f5f9;
            border-radius: 8px;
            padding: 0.75rem;
        }
        
        /* --- NOVOS ESTILOS PARA OS CARTÕES DE SERVIÇO --- */
        .service-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.75rem;
        }

        .service-card {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            background-color: #fdfdfe;
        }

        .service-card:hover {
            border-color: var(--color-secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        .service-card.selected {
            border-color: var(--color-accent);
            background-color: #fff7ed;
            box-shadow: 0 0 0 3px rgba(194, 65, 12, 0.15);
            transform: translateY(-2px);
        }
        
        .service-card-name {
            font-weight: 600;
            color: var(--color-primary);
            font-size: 1rem;
        }

        .service-card-details {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.25rem;
        }
        /* --- FIM DOS NOVOS ESTILOS --- */


        /* Time selection effect */
        @keyframes pulse {
            0% {transform: scale(1);}
            50% {transform: scale(1.05);}
            100% {transform: scale(1);}
        }
        .animate-pulse {
            animation: pulse 2s infinite;
        }
        
        /* Button row */
        .button-row {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        /* Botão Consultar Pontos - CENTRALIZADO */
        .btn-consultar-pontos {
            background-color: #059669;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 10px 20px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .btn-consultar-pontos:hover {
            background-color: #047857;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(5, 150, 105, 0.3);
        }

        /* Estilos para a biblioteca de telefone */
        .iti {
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="brand-logo pt-8 relative">
        <img src="barber-logo.png" alt="Barbearia Vintage" class="logo-img mx-auto">
        <h1 class="brand-name">BARBEARIA VINTAGE</h1>
        <p class="brand-tagline">Estilo & tradição desde 2010</p>
    </div>

    <div class="w-full max-w-4xl mx-auto form-container p-8 md:p-10 my-8 relative">
        <div class="text-center mb-4 relative z-1 button-row">
            <button id="btnConsultarPontos" class="btn-consultar-pontos">Consultar Pontos</button>
            <button id="installAppButton" class="install-btn" style="display: none;">Instalar App</button>
        </div>
        
        <h2 class="text-2xl md:text-3xl font-bold mb-2 text-center relative z-1">RESERVE SEU HORÁRIO</h2>
        <p class="text-gray-600 mb-8 text-center relative z-1">Escolha o serviço, a data e o horário.</p>

        <form id="form-agendamento" class="relative z-1">
            <div class="grid md:grid-cols-2 gap-8">
                <!-- COLUNA 1: DADOS DO CLIENTE -->
                <div>
                    <div class="mb-6">
                        <label for="nome" class="block text-sm font-medium form-field-label mb-2">Nome Completo</label>
                        <input type="text" id="nome" name="nome" required class="w-full px-4 py-3 form-input border rounded-lg" placeholder="Seu nome completo">
                    </div>
                    <div class="mb-6">
                        <label for="whatsapp" class="block text-sm font-medium form-field-label mb-2">Nº de Telefone (WhatsApp)</label>
                        <input type="tel" id="whatsapp" name="whatsapp_raw" required class="w-full form-input border rounded-lg">
                        <input type="hidden" id="whatsapp_full" name="whatsapp">
                    </div>
                     <div class="mb-6">
                        <label for="data" class="block text-sm font-medium form-field-label mb-2">Escolha a Data</label>
                        <input type="date" id="data" name="data" required class="w-full px-4 py-3 form-input border rounded-lg">
                    </div>
                </div>
                <!-- COLUNA 2: SERVIÇO E HORÁRIO -->
                <div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium form-field-label mb-2">Escolha o Serviço</label>
                        <!-- NOVO: Cartões de Serviço -->
                        <input type="hidden" id="servico_id" name="servico" required>
                        <div id="service-card-container" class="service-card-grid">
                            <?php foreach ($servicos as $servico): 
                                $simbolo_moeda = $servico['moeda'] == 'BRL' ? 'R$' : '€';
                                $preco_formatado = number_format($servico['preco'], 2, ',', '.');
                            ?>
                                <div class="service-card" data-id="<?= $servico['id'] ?>" data-duracao="<?= $servico['duracao_minutos'] ?>">
                                    <div class="service-card-name"><?= htmlspecialchars($servico['nome']) ?></div>
                                    <div class="service-card-details">
                                        <span><?= $servico['duracao_minutos'] ?> min</span> | <span class="preco"><?= $simbolo_moeda ?> <?= $preco_formatado ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium form-field-label mb-2">Horários Disponíveis</label>
                        <div id="horarios-disponiveis" class="p-4 border form-input rounded-lg bg-gray-50 min-h-[140px] flex items-center justify-center">
                            <p class="text-gray-500 text-center">Selecione um serviço e uma data para ver os horários.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-8">
                <div id="mensagem" class="hidden p-4 mb-4 text-sm rounded-lg" role="alert"></div>
                <button type="submit" class="w-full text-white font-bold py-4 px-4 rounded-lg btn-submit text-lg flex items-center justify-center">
                    <span class="mr-2">CONFIRMAR AGENDAMENTO</span>
                </button>
            </div>
        </form>
    </div>

    <!-- INÍCIO: MODAIS DE CONFIRMAÇÃO E SUCESSO -->
    <div id="confirmation-modal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2 class="text-2xl">CONFIRMAR AGENDAMENTO</h2>
                <span id="confirmation-modal-close" class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <p class="mb-4 text-gray-700">Por favor, confirme os detalhes da sua reserva:</p>
                <div id="confirmation-details-content" class="confirmation-details space-y-2">
                    <!-- Conteúdo dinâmico aqui -->
                </div>
                <div class="button-row mt-6">
                    <button id="btn-cancel" class="close-btn">Cancelar</button>
                    <button id="btn-confirm-booking" class="install-btn bg-green-600 hover:bg-green-700">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="success-modal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header" style="background-color: #059669;">
                <h2 class="text-2xl">AGENDAMENTO CONFIRMADO!</h2>
            </div>
            <div class="modal-body text-center">
                <p id="success-message" class="text-lg text-gray-800 mb-4"></p>
                <div id="success-details-content" class="confirmation-details text-left space-y-2">
                    <!-- Conteúdo dinâmico aqui -->
                </div>
                <button id="btn-close-success" class="install-btn mt-4">Fechar</button>
            </div>
        </div>
    </div>
    <!-- FIM: MODAIS -->

    <!-- JS da Biblioteca de Telefone Internacional -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/intlTelInput.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- Lógica do Input de Telefone ---
            const phoneInputField = document.querySelector("#whatsapp");
            const whatsappFullField = document.querySelector("#whatsapp_full");
            const phoneInputInstance = window.intlTelInput(phoneInputField, {
                initialCountry: "auto",
                geoIpLookup: callback => {
                    fetch("https://ipapi.co/json").then(res => res.json()).then(data => callback(data.country_code)).catch(() => callback("pt"));
                },
                utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js",
            });

            // --- Declaração de todas as variáveis do DOM ---
            const form = document.getElementById('form-agendamento');
            const nomeInput = document.getElementById('nome');
            const dataInput = document.getElementById('data');
            const servicoIdInput = document.getElementById('servico_id'); 
            const serviceCardContainer = document.getElementById('service-card-container');
            const horariosDiv = document.getElementById('horarios-disponiveis');
            const mensagemDiv = document.getElementById('mensagem');
            const hoje = new Date().toISOString().split('T')[0];
            dataInput.setAttribute('min', hoje);

            // --- Variáveis dos Modais ---
            const confirmationModal = document.getElementById('confirmation-modal');
            const successModal = document.getElementById('success-modal');
            const btnConfirmBooking = document.getElementById('btn-confirm-booking');
            const btnCancel = document.getElementById('btn-cancel');
            const btnCloseSuccess = document.getElementById('btn-close-success');
            const confirmationModalClose = document.getElementById('confirmation-modal-close');
            
            // --- Variáveis do Botão Consultar Pontos ---
            const btnConsultarPontos = document.getElementById('btnConsultarPontos');

            // --- Funções essenciais ---
            function carregarHorarios() {
                const dataSelecionada = dataInput.value;
                const servicoId = servicoIdInput.value; 
                
                if (!dataSelecionada || !servicoId) {
                    horariosDiv.innerHTML = '<p class="text-gray-500 text-center">Selecione um serviço e uma data para ver os horários.</p>';
                    return;
                }

                horariosDiv.innerHTML = '<div class="flex items-center justify-center"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-orange-700"></div><p class="ml-3 text-gray-500">Carregando...</p></div>';

                fetch(`horarios.php?data=${dataSelecionada}&servico_id=${servicoId}`)
                    .then(response => response.json())
                    .then(data => {
                        horariosDiv.innerHTML = '';
                        if (data.status === 'success' && data.horarios.length > 0) {
                             const grid = document.createElement('div');
                             grid.className = 'grid grid-cols-3 sm:grid-cols-4 gap-2 horarios-grid';
                             data.horarios.forEach(horario => {
                                const btn = document.createElement('button');
                                btn.type = 'button';
                                btn.textContent = horario;
                                btn.className = 'p-2 border rounded-lg text-center transition duration-150';
                                grid.appendChild(btn);
                            });
                            horariosDiv.appendChild(grid);
                        } else {
                            horariosDiv.innerHTML = '<p class="text-gray-500 text-center">Não há horários disponíveis para esta data.</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao carregar horários:', error);
                        horariosDiv.innerHTML = '<p class="text-red-500 text-center">Erro ao carregar horários. Tente novamente.</p>';
                    });
            }

            function showMessage(type, text) {
                mensagemDiv.textContent = text;
                mensagemDiv.className = `p-4 mb-4 text-sm rounded-lg ${type === 'error' ? 'feedback-error' : 'feedback-success'}`;
                mensagemDiv.style.display = 'block';
            }

            // --- Event Listeners ---
            dataInput.addEventListener('change', carregarHorarios);

            serviceCardContainer.addEventListener('click', (e) => {
                const card = e.target.closest('.service-card');
                if (!card) return;
                const currentSelected = serviceCardContainer.querySelector('.selected');
                if (currentSelected) {
                    currentSelected.classList.remove('selected');
                }
                card.classList.add('selected');
                servicoIdInput.value = card.dataset.id;
                carregarHorarios();
            });

            horariosDiv.addEventListener('click', function(e) {
                if (e.target.tagName === 'BUTTON') {
                    const selecionadoAtual = horariosDiv.querySelector('.selecionado');
                    if (selecionadoAtual) {
                        selecionadoAtual.classList.remove('selecionado');
                    }
                    e.target.classList.add('selecionado');
                }
            });

            // --- Lógica de Submissão e Modais ---
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                mensagemDiv.style.display = 'none';

                // Validações
                if (!phoneInputInstance.isValidNumber()) {
                    showMessage('error', 'Por favor, insira um número de telefone válido.');
                    return;
                }
                if (!servicoIdInput.value) {
                    showMessage('error', 'Por favor, selecione um serviço.');
                    return;
                }
                const horarioSelecionadoEl = document.querySelector('#horarios-disponiveis .selecionado');
                if (!horarioSelecionadoEl) {
                    showMessage('error', 'Por favor, selecione um horário disponível.');
                    return;
                }

                // Preencher e mostrar modal de confirmação
                const selectedServiceCard = serviceCardContainer.querySelector('.service-card.selected');
                const serviceName = selectedServiceCard.querySelector('.service-card-name').textContent;
                const dateFormatted = new Date(dataInput.value + 'T00:00:00').toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit', year: 'numeric' });

                const detailsContent = `
                    <p><strong>Nome:</strong> ${nomeInput.value}</p>
                    <p><strong>Serviço:</strong> ${serviceName}</p>
                    <p><strong>Data:</strong> ${dateFormatted}</p>
                    <p><strong>Hora:</strong> ${horarioSelecionadoEl.textContent}</p>
                `;
                document.getElementById('confirmation-details-content').innerHTML = detailsContent;
                confirmationModal.classList.add('active');
            });

            function closeConfirmationModal() {
                confirmationModal.classList.remove('active');
            }

            btnCancel.addEventListener('click', closeConfirmationModal);
            confirmationModalClose.addEventListener('click', closeConfirmationModal);

            btnConfirmBooking.addEventListener('click', () => {
                const originalButtonText = btnConfirmBooking.innerHTML;
                btnConfirmBooking.disabled = true;
                btnConfirmBooking.innerHTML = '<span>PROCESSANDO...</span>';

                whatsappFullField.value = phoneInputInstance.getNumber();
                const formData = new FormData(form);
                const horarioSelecionadoEl = document.querySelector('#horarios-disponiveis .selecionado');
                formData.append('hora', horarioSelecionadoEl.textContent);

                fetch('agendar.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                     if (data.status === 'success') {
                        closeConfirmationModal();
                        document.getElementById('success-message').textContent = data.message;
                        document.getElementById('success-details-content').innerHTML = document.getElementById('confirmation-details-content').innerHTML;
                        successModal.classList.add('active');
                    } else {
                        closeConfirmationModal();
                        showMessage('error', data.message || 'Ocorreu um erro desconhecido.');
                    }
                })
                .catch(error => {
                    closeConfirmationModal();
                    showMessage('error', 'Ocorreu um erro de comunicação com o servidor.');
                })
                .finally(() => {
                    btnConfirmBooking.disabled = false;
                    btnConfirmBooking.innerHTML = originalButtonText;
                });
            });

            btnCloseSuccess.addEventListener('click', () => {
                successModal.classList.remove('active');
                window.location.reload();
            });

            // --- INÍCIO: LÓGICA DO BOTÃO CONSULTAR PONTOS ---
            btnConsultarPontos.addEventListener('click', () => {
                const telefone = prompt("Para consultar seus pontos, por favor, insira seu número de telefone (incluindo o código do país, ex: +351912345678):");

                if (!telefone) {
                    return; // User cancelled the prompt
                }

                const formData = new FormData();
                formData.append('acao', 'consultar_pontos');
                formData.append('telefone', telefone);

                fetch(window.location.href, { // POST to the same page
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message); // Show the result in an alert
                })
                .catch(error => {
                    console.error('Erro ao consultar pontos:', error);
                    alert('Ocorreu um erro de comunicação ao tentar consultar os pontos.');
                });
            });
            // --- FIM: LÓGICA DO BOTÃO CONSULTAR PONTOS ---

        });
    </script>
</body>
</html>
