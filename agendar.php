<?php
require_once 'config.php';

header('Content-Type: application/json');

// Validação básica dos dados recebidos
$nome = trim($_POST['nome'] ?? '');
$whatsapp = trim($_POST['whatsapp'] ?? '');
$servico_id = filter_var($_POST['servico'] ?? '', FILTER_VALIDATE_INT);
$data = trim($_POST['data'] ?? '');
$hora = trim($_POST['hora'] ?? '');

if (empty($nome) || empty($whatsapp) || empty($servico_id) || empty($data) || empty($hora)) {
    echo json_encode(['status' => 'error', 'message' => 'Todos os campos são obrigatórios.']);
    exit;
}

try {
    $pdo = conectarDB();
    $pdo->beginTransaction();

    // --- LÓGICA PARA CLIENTES E PONTOS ---
    $stmt_cliente = $pdo->prepare("SELECT id FROM clientes WHERE telefone = ?");
    $stmt_cliente->execute([$whatsapp]);
    $cliente_id = $stmt_cliente->fetchColumn();

    if ($cliente_id) {
        $stmt_update_cliente = $pdo->prepare("UPDATE clientes SET nome = ?, pontos = pontos + 10 WHERE id = ?");
        $stmt_update_cliente->execute([$nome, $cliente_id]);
    } else {
        $stmt_insert_cliente = $pdo->prepare("INSERT INTO clientes (nome, telefone, pontos) VALUES (?, ?, 10)");
        $stmt_insert_cliente->execute([$nome, $whatsapp]);
    }

    // --- BUSCAR NOME E DURAÇÃO DO SERVIÇO ---
    $stmt_servico = $pdo->prepare("SELECT nome, duracao_minutos FROM servicos WHERE id = ?");
    $stmt_servico->execute([$servico_id]);
    $servico_info = $stmt_servico->fetch(PDO::FETCH_ASSOC);

    if (!$servico_info) {
        throw new Exception("Serviço selecionado não foi encontrado.");
    }
    $nome_servico = $servico_info['nome'];
    $duracao_servico_novo = $servico_info['duracao_minutos'];

    // --- VERIFICAR DISPONIBILIDADE DO HORÁRIO ---
    $novo_inicio = new DateTime("$data $hora");
    $novo_fim = (clone $novo_inicio)->add(new DateInterval("PT{$duracao_servico_novo}M"));

    $stmt_conflitos = $pdo->prepare("
        SELECT a.hora_agendamento, s.duracao_minutos
        FROM agendamentos a
        JOIN servicos s ON a.servico_id = s.id
        WHERE a.data_agendamento = ?
    ");
    $stmt_conflitos->execute([$data]);
    $agendamentos_existentes = $stmt_conflitos->fetchAll(PDO::FETCH_ASSOC);

    foreach ($agendamentos_existentes as $existente) {
        $existente_inicio = new DateTime("$data {$existente['hora_agendamento']}");
        $existente_fim = (clone $existente_inicio)->add(new DateInterval("PT{$existente['duracao_minutos']}M"));

        if ($novo_inicio < $existente_fim && $novo_fim > $existente_inicio) {
            throw new Exception("Conflito de horário. O horário das " . $novo_inicio->format('H:i') . " não está mais disponível.");
        }
    }

    // --- INSERIR AGENDAMENTO ---
    $stmt_insert = $pdo->prepare(
        "INSERT INTO agendamentos (nome_cliente, whatsapp_cliente, servico_id, servico, data_agendamento, hora_agendamento, status) VALUES (?, ?, ?, ?, ?, ?, 'confirmado')"
    );
    $stmt_insert->execute([
        $nome,
        $whatsapp,
        $servico_id,
        $nome_servico,
        $data,
        $hora
    ]);

    $pdo->commit();
    
    // --- INÍCIO DA FUNCIONALIDADE RESTAURADA: ENVIAR WHATSAPP ---
    try {
        $telefone_normalizado = preg_replace('/\D+/', '', $whatsapp);
        $data_formatada = date('d/m/Y', strtotime($data));
        
        $mensagem_whatsapp = "Olá, *{$nome}*! ✅ Seu agendamento foi confirmado com sucesso!\n\n" .
                             "Serviço: *{$nome_servico}*\n" .
                             "Data: *{$data_formatada}*\n" .
                             "Hora: *{$hora}*\n\n" .
                             "Obrigado por agendar conosco!".
                             " *Babearia Vintage* ";
        
        // A função enviarMensagem() vem do seu arquivo config.php
        enviarMensagem($telefone_normalizado, $mensagem_whatsapp);

    } catch (Exception $e) {
        // Se o envio do WhatsApp falhar, não interrompe o processo.
        // Apenas registra o erro para depuração.
        error_log("Falha ao enviar WhatsApp de confirmação: " . $e->getMessage());
    }
    // --- FIM DA FUNCIONALIDADE RESTAURADA ---

    session_start();
    $_SESSION['agendamento_sucesso'] = true;
    $_SESSION['detalhes_agendamento'] = ['nome' => $nome, 'data' => $data, 'hora' => $hora, 'servico_nome' => $nome_servico];

    echo json_encode(['status' => 'success', 'message' => 'Seu agendamento foi confirmado com sucesso!']);

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
