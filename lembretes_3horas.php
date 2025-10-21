<?php
/**
 * Script para envio de lembretes SMS 3 horas antes dos agendamentos
 * Deve ser executado a cada hora via cron job
 */

require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';

use Twilio\Rest\Client;
use Twilio\Exceptions\RestException;

echo "Iniciando envio de lembretes 3 horas antes...\n";
$log_file = __DIR__ . '/logs/lembretes_3h_' . date('Y-m-d') . '.log';

// Função para registrar logs
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    
    // Garantir que o diretório de logs existe
    if (!is_dir(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    echo $log_entry;
}

try {
    $pdo = conectarDB();
    
    // Calcular o intervalo de tempo para buscar agendamentos
    // (entre 3 horas e 3 horas e 59 minutos a partir de agora)
    $data_atual = date('Y-m-d');
    $hora_atual = date('H:i:s');
    
    $inicio_intervalo = date('H:i:s', strtotime('+3 hours'));
    $fim_intervalo = date('H:i:s', strtotime('+3 hours 59 minutes'));
    
    log_message("Buscando agendamentos para hoje ({$data_atual}) entre {$inicio_intervalo} e {$fim_intervalo}");
    
    // Consulta para buscar agendamentos que acontecerão em aproximadamente 3 horas
    $stmt = $pdo->prepare("
        SELECT a.*, s.nome as nome_servico, s.preco, s.moeda 
        FROM agendamentos a 
        LEFT JOIN servicos s ON a.servico_id = s.id 
        WHERE a.data_agendamento = CURDATE() 
        AND a.hora_agendamento BETWEEN ? AND ? 
        AND a.status = 'Confirmado'
        AND (a.lembrete_3h_enviado IS NULL OR a.lembrete_3h_enviado = 0)
    ");
    
    $stmt->execute([$inicio_intervalo, $fim_intervalo]);
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total = count($agendamentos);
    log_message("Encontrados {$total} agendamentos para enviar lembretes");
    
    if ($total === 0) {
        log_message("Nenhum lembrete para enviar neste momento. Encerrando.");
        exit;
    }
    
    // Inicializar cliente Twilio
    $twilio = new Twilio\Rest\Client(TWILIO_SID, TWILIO_AUTH_TOKEN);
    $enviados = 0;
    $falhas = 0;
    
    foreach ($agendamentos as $ag) {
        $nome_cliente = $ag['nome_cliente'];
        $telefone_cliente = $ag['whatsapp_cliente'];
        $id_agendamento = $ag['id'];
        
        // Usar o nome do serviço da tabela de serviços se disponível, senão usar o campo serviço antigo
        $servico = $ag['nome_servico'] ?: $ag['servico'];
        
        // Formatar data e hora
        $data_formatada = date("d/m/Y", strtotime($ag['data_agendamento']));
        $hora_formatada = date("H:i", strtotime($ag['hora_agendamento']));
        
        // Informações de preço
        $preco_info = '';
        if (!empty($ag['preco'])) {
            $moeda_simbolo = $ag['moeda'] === 'BRL' ? 'R$' : '€';
            $preco_formatado = number_format($ag['preco'], 2, ',', '.');
            $preco_info = " Valor: {$moeda_simbolo} {$preco_formatado}.";
        }
        
        // Compor a mensagem do lembrete
        $mensagem = "LEMBRETE: Olá {$nome_cliente}, você tem um agendamento para '{$servico}' HOJE às {$hora_formatada}.{$preco_info} Agradecemos a preferência!";
        
        try {
            // Enviar SMS
            $message = $twilio->messages->create(
                $telefone_cliente, // Número direto, sem prefixo "whatsapp:"
                [
                    'from' => TWILIO_PHONE_NUMBER,
                    'body' => $mensagem
                ]
            );
            
            // Registrar o envio no banco de dados
            $update_stmt = $pdo->prepare("
                UPDATE agendamentos 
                SET lembrete_3h_enviado = 1, 
                    lembrete_3h_sid = ?,
                    lembrete_3h_data = NOW()
                WHERE id = ?
            ");
            $update_stmt->execute([$message->sid, $id_agendamento]);
            
            log_message("SMS enviado com sucesso para {$nome_cliente} ({$telefone_cliente}). SID: {$message->sid}");
            $enviados++;
            
        } catch (Exception $e) {
            log_message("ERRO: Falha ao enviar SMS para {$nome_cliente} ({$telefone_cliente}). Erro: " . $e->getMessage());
            $falhas++;
        }
    }
    
    log_message("Processo concluído. Enviados: {$enviados}, Falhas: {$falhas}");

} catch (Exception $e) {
    log_message("ERRO GERAL NO SCRIPT: " . $e->getMessage());
}
