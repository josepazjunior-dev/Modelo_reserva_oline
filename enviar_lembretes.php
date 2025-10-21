<?php
// Este script deve ser executado uma vez por dia (via Cron Job)
// para enviar lembretes dos agendamentos do dia seguinte.

require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';

echo "Iniciando script de envio de lembretes...\n";

try {
    $pdo = conectarDB();
    
    // Procura por agendamentos confirmados para o dia de amanhã
    $stmt = $pdo->prepare("
        SELECT a.*, s.nome as nome_servico, s.preco, s.moeda, c.pontos as pontos_cliente
        FROM agendamentos a 
        LEFT JOIN servicos s ON a.servico_id = s.id 
        LEFT JOIN clientes c ON a.whatsapp_cliente = c.telefone
        WHERE a.data_agendamento = CURDATE() + INTERVAL 1 DAY 
        AND a.status = 'Confirmado'
        AND (a.lembrete_3h_enviado IS NULL OR a.lembrete_3h_enviado = 0)
    ");
    $stmt->execute();
    $agendamentos = $stmt->fetchAll();

    if (count($agendamentos) === 0) {
        echo "Nenhum agendamento para amanhã. Encerrando.\n";
        exit;
    }

    echo "Encontrados " . count($agendamentos) . " agendamentos para amanhã.\n";

    $twilio = new Twilio\Rest\Client(TWILIO_SID, TWILIO_AUTH_TOKEN);

    foreach ($agendamentos as $ag) {
        $nome_cliente = $ag['nome_cliente'];
        $telefone_cliente = $ag['whatsapp_cliente'];
        
        // Usar o nome do serviço da tabela de serviços se disponível
        $servico = $ag['nome_servico'] ?: $ag['servico'];
        
        $dataHoraFormatada = date("d/m/Y", strtotime($ag['data_agendamento'])) . " às " . date("H:i", strtotime($ag['hora_agendamento']));
        
        // Informações de preço
        $preco_info = '';
        if (!empty($ag['preco'])) {
            $moeda_simbolo = $ag['moeda'] === 'BRL' ? 'R$' : '€';
            $preco_formatado = number_format($ag['preco'], 2, ',', '.');
            $preco_info = " Valor: {$moeda_simbolo} {$preco_formatado}.";
        }
        
        // Incluir pontos na mensagem
        $pontos_info = isset($ag['pontos_cliente']) ? " Você tem {$ag['pontos_cliente']} pontos de fidelidade." : "";
        
        // Link para download do aplicativo
        $download_link = LANDING_PAGE_URL . '?id=' . $ag['id'];
        
        // Mensagem do lembrete
        $mensagem = "Lembrete: Olá {$nome_cliente}, não esqueça seu agendamento para '{$servico}' amanhã, dia {$dataHoraFormatada}.{$preco_info}{$pontos_info} Veja mais detalhes no nosso aplicativo: {$download_link}";

        try {
            // Enviar SMS
            $twilio->messages->create(
                $telefone_cliente, // Número direto, sem prefixo "whatsapp:"
                [
                    'from' => TWILIO_PHONE_NUMBER,
                    'body' => $mensagem
                ]
            );
            
            echo "Lembrete via SMS enviado para {$nome_cliente}.\n";
            
        } catch (Exception $e) {
            echo "ERRO: Falha ao enviar SMS para {$nome_cliente}. Erro: " . $e->getMessage() . "\n";
        }
    }

    echo "Script concluído.\n";

} catch (Exception $e) {
    echo "ERRO GERAL NO SCRIPT: " . $e->getMessage() . "\n";
}
