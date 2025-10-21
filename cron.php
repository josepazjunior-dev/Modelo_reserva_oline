<?php
// Define o fuso horário para evitar erros de cálculo de tempo
date_default_timezone_set('Europe/Lisbon'); // Ajuste para o seu fuso horário se necessário (ex: 'America/Sao_Paulo')

require_once 'config.php';

echo "--- Iniciando verificação de lembretes (" . date('Y-m-d H:i:s') . ") ---\n";

try {
    $pdo = conectarDB();

    // Define a janela de tempo: agendamentos que ocorrerão entre 60 e 90 minutos a partir de agora.
    // Isso dá uma margem para o cron job rodar.
    $agora = new DateTime();
    $inicio_janela = (clone $agora)->add(new DateInterval('PT60M'))->format('Y-m-d H:i:s');
    $fim_janela = (clone $agora)->add(new DateInterval('PT90M'))->format('Y-m-d H:i:s');
    
    echo "Procurando agendamentos entre $inicio_janela e $fim_janela...\n";

    // Busca agendamentos na próxima janela de tempo que ainda não receberam lembrete
    $stmt = $pdo->prepare("
        SELECT 
            a.id, 
            a.nome_cliente, 
            a.whatsapp_cliente, 
            a.data_agendamento, 
            a.hora_agendamento,
            s.nome as nome_servico
        FROM agendamentos a
        LEFT JOIN servicos s ON a.servico_id = s.id
        WHERE 
            CONCAT(a.data_agendamento, ' ', a.hora_agendamento) >= :inicio_janela
        AND 
            CONCAT(a.data_agendamento, ' ', a.hora_agendamento) < :fim_janela
        AND 
            a.status = 'Confirmado'
        AND 
            a.lembrete_enviado = FALSE
    ");

    $stmt->execute([
        ':inicio_janela' => $inicio_janela,
        ':fim_janela' => $fim_janela
    ]);

    $agendamentos_para_lembrar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($agendamentos_para_lembrar)) {
        echo "Nenhum lembrete para enviar neste momento.\n";
        exit;
    }

    echo "Encontrados " . count($agendamentos_para_lembrar) . " agendamentos para notificar.\n";

    foreach ($agendamentos_para_lembrar as $ag) {
        $id_agendamento = $ag['id'];
        $nome_cliente = $ag['nome_cliente'];
        $hora_formatada = date("H:i", strtotime($ag['hora_agendamento']));
        $servico_nome = $ag['nome_servico'] ?? $ag['servico']; // Compatibilidade com serviços antigos
        
        // Remove caracteres não numéricos do telefone
        $telefone_limpo = preg_replace('/\D/', '', $ag['whatsapp_cliente']);

        // Monta a mensagem de lembrete
        $mensagem = "⏰ *Lembrete de Agendamento* ⏰\n\n";
        $mensagem .= "Olá, *{$nome_cliente}*! Passando para lembrar do seu horário para '{$servico_nome}' hoje às *{$hora_formatada}*.\n\n";
        $mensagem .= "Estamos à sua espera!\n*Barbearia Vintage*";

        echo "Enviando lembrete para: {$nome_cliente} ({$telefone_limpo})...\n";

        // Envia a mensagem usando a função do config.php
        $resultado_raw = enviarMensagem($telefone_limpo, $mensagem);
        $resultado = json_decode($resultado_raw, true);

        // Se o envio foi bem-sucedido (verificando pelo messageId), atualiza a base de dados
        if (isset($resultado['messageId'])) {
            $stmt_update = $pdo->prepare("UPDATE agendamentos SET lembrete_enviado = TRUE WHERE id = ?");
            $stmt_update->execute([$id_agendamento]);
            echo "✅ Lembrete enviado com sucesso! Agendamento #{$id_agendamento} atualizado.\n";
        } else {
            echo "❌ Falha ao enviar lembrete para o agendamento #{$id_agendamento}. Resposta da API: {$resultado_raw}\n";
        }
    }

} catch (PDOException $e) {
    echo "Erro de base de dados: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Erro geral: " . $e->getMessage() . "\n";
}

echo "--- Verificação de lembretes concluída (" . date('Y-m-d H:i:s') . ") ---\n";
?>