<?php
// Script para mudar o status de um agendamento para "Confirmado" e notificar o cliente via Twilio

require_once 'config.php';

// Validação do ID recebido via GET
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    die("Erro: ID de agendamento inválido.");
}
$id = $_GET['id'];

$pdo = null;
try {
    $pdo = conectarDB();

    // --- PASSO 1: Buscar os dados do agendamento ---
    $stmt_select = $pdo->prepare("SELECT nome_cliente, whatsapp_cliente, servico, data_agendamento, hora_agendamento FROM agendamentos WHERE id = ?");
    $stmt_select->execute([$id]);
    $agendamento = $stmt_select->fetch(PDO::FETCH_ASSOC);

    if (!$agendamento) {
        die("Erro: Agendamento não encontrado.");
    }

    // --- PASSO 2: Buscar pontos do cliente ---
    $pontos_cliente = 0;
    $stmt_pontos = $pdo->prepare("SELECT pontos FROM clientes WHERE telefone = ?");
    $stmt_pontos->execute([$agendamento['whatsapp_cliente']]);
    $cliente = $stmt_pontos->fetch(PDO::FETCH_ASSOC);
    if ($cliente) {
        $pontos_cliente = $cliente['pontos'];
    }

    // --- PASSO 3: Atualizar o status para "Confirmado" ---
    $stmt_update = $pdo->prepare("UPDATE agendamentos SET status = 'Confirmado' WHERE id = ?");
    $stmt_update->execute([$id]);

    // --- PASSO 4: Enviar notificação de confirmação via Twilio ---
    if (defined('TWILIO_SID') && TWILIO_SID !== 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' && !empty(TWILIO_AUTH_TOKEN)) {
        try {
            require_once __DIR__ . '/vendor/autoload.php';
            
            $twilio = new Twilio\Rest\Client(TWILIO_SID, TWILIO_AUTH_TOKEN);

            $nome_cliente = $agendamento['nome_cliente'];
            $servico = $agendamento['servico'];
            $data = date("d/m/Y", strtotime($agendamento['data_agendamento']));
            $hora = date("H:i", strtotime($agendamento['hora_agendamento']));
            
            // Incluir pontos na mensagem
            $mensagem = "Olá, {$nome_cliente}! O seu agendamento para '{$servico}' no dia {$data} às {$hora} foi CONFIRMADO. Você tem {$pontos_cliente} pontos de fidelidade acumulados. Até breve!";

            $twilio->messages->create(
                "whatsapp:{$agendamento['whatsapp_cliente']}",
                [
                    "from" => "whatsapp:" . TWILIO_WHATSAPP_NUMBER,
                    "body" => $mensagem
                ]
            );

        } catch (Exception $e) {
            // Se o envio falhar, registar o erro mas continuar
            error_log("Erro Twilio em confirmar.php: " . $e->getMessage());
        }
    }

    // --- PASSO 5: Redirecionar de volta para o painel de administração ---
    header("Location: admin.php");
    exit;

} catch (PDOException $e) {
    die("Erro na base de dados: " . $e->getMessage());
} catch (Exception $e) {
    die("Ocorreu um erro: " . $e->getMessage());
}
