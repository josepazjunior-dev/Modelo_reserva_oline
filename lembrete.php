<?php
// Este script deve ser executado via Cron Job (ex: a cada hora)
// para enviar lembretes dos agendamentos que acontecerão em breve.

require_once 'config.php';

echo "Iniciando script de envio de lembretes via Z-API...\n";
log_message("Iniciando script de envio de lembretes via Z-API...");

try {
    $pdo = conectarDB();
    
    // Procura por agendamentos confirmados para as próximas 3 horas
    $data_atual = date('Y-m-d');
    $inicio_intervalo = date('H:i:s', strtotime('+3 hours'));
    $fim_intervalo = date('H:i:s', strtotime('+3 hours 59 minutes'));
    
    log_message("Buscando agendamentos para hoje ({$data_atual}) entre {$inicio_intervalo} e {$fim_intervalo}");

    $stmt = $pdo->prepare("
        SELECT a.*, s.nome as nome_servico, s.preco, s.moeda
        FROM agendamentos a 
        LEFT JOIN servicos s ON a.servico_id = s.id 
        WHERE a.data_agendamento = CURDATE() 
        AND a.hora_agendamento BETWEEN ? AND ? 
        AND a.status = 'Confirmado'
        AND (a.lembrete_enviado IS NULL OR a.lembrete_enviado = 0)
    ");
    $stmt->execute([$inicio_intervalo, $fim_intervalo]);
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($agendamentos) === 0) {
        log_message("Nenhum agendamento para enviar lembrete agora. Encerrando.");
        echo "Nenhum agendamento para enviar lembrete agora. Encerrando.\n";
        exit;
    }

    log_message("Encontrados " . count($agendamentos) . " agendamentos para enviar lembrete.");
    echo "Encontrados " . count($agendamentos) . " agendamentos para enviar lembrete.\n";

    foreach ($agendamentos as $ag) {
        $nome_cliente = $ag['nome_cliente'];
        $telefone_cliente = preg_replace('/\D+/', '', $ag['whatsapp_cliente']); // Limpa o telefone
        
        $servico = $ag['nome_servico'] ?: $ag['servico'];
        $hora_formatada = date("H:i", strtotime($ag['hora_agendamento']));
        
        // Mensagem do lembrete
        $mensagem = "LEMBRETE: Olá, *{$nome_cliente}*! Não se esqueça do seu agendamento para '{$servico}' HOJE às *{$hora_formatada}*. Estamos à sua espera!";

        try {
            $response_raw = enviarMensagem($telefone_cliente, $mensagem);
            $response = json_decode($response_raw, true);

            if (isset($response['messageId'])) {
                // Marcar como enviado no banco de dados
                $update_stmt = $pdo->prepare("UPDATE agendamentos SET lembrete_enviado = 1 WHERE id = ?");
                $update_stmt->execute([$ag['id']]);
                log_message("Lembrete enviado para {$nome_cliente} ({$telefone_cliente}). Message ID: " . $response['messageId']);
                echo "Lembrete enviado para {$nome_cliente} ({$telefone_cliente}).\n";
            } else {
                throw new Exception("Falha no envio do lembrete. Resposta: " . $response_raw);
            }
            
        } catch (Exception $e) {
            log_message("ERRO ao enviar lembrete para {$nome_cliente}: " . $e->getMessage());
            echo "ERRO ao enviar lembrete para {$nome_cliente}: " . $e->getMessage() . "\n";
        }
    }

    log_message("Script de lembretes concluído.");
    echo "Script de lembretes concluído.\n";

} catch (Exception $e) {
    log_message("ERRO GERAL NO SCRIPT de lembretes: " . $e->getMessage());
    echo "ERRO GERAL NO SCRIPT de lembretes: " . $e->getMessage() . "\n";
}

// Função para registrar logs
function log_message($message) {
    $log_file = __DIR__ . '/logs/lembretes_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    
    if (!is_dir(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}
?>