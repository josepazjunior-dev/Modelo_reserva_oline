<?php
header('Content-Type: application/json');
require_once 'config.php';

// --- DEFINIR VALORES PADRÃO ---
define('HORA_INICIO_PADRAO', '09:00:00');
define('HORA_FIM_PADRAO', '18:00:00');
define('INTERVALO_PADRAO_MINUTOS', 15);

// Validações de entrada
if (!isset($_GET['data']) || !isset($_GET['servico_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data e serviço são obrigatórios.']);
    exit;
}

$data = $_GET['data'];
$servico_id = filter_var($_GET['servico_id'], FILTER_VALIDATE_INT);

$d = DateTime::createFromFormat('Y-m-d', $data);
if (!$d || $d->format('Y-m-d') !== $data || !$servico_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Formato de dados inválido.']);
    exit;
}

try {
    $pdo = conectarDB();

    $hora_inicio_dia = null;
    $hora_fim_dia = null;
    $fechado = false;

    // ==================================================================
    // NOVA LÓGICA: VERIFICAR PRIMEIRO AS EXCEÇÕES
    // ==================================================================
    $stmt_excecao = $pdo->prepare("SELECT * FROM horarios_excecoes WHERE data = ?");
    $stmt_excecao->execute([$data]);
    $excecao = $stmt_excecao->fetch(PDO::FETCH_ASSOC);

    if ($excecao) {
        // Uma exceção foi encontrada para esta data
        if (!empty($excecao['fechado'])) {
            $fechado = true;
        } else {
            // A exceção define um horário especial
            $hora_inicio_dia = $excecao['hora_abertura'];
            $hora_fim_dia = $excecao['hora_fechamento'];
        }
    } else {
        // Nenhuma exceção encontrada, usar a lógica de horário fixo semanal
        $dia_semana = (int)$d->format('N');
        $stmt_horarios = $pdo->prepare("SELECT * FROM horarios_funcionamento WHERE dia_semana = ?");
        $stmt_horarios->execute([$dia_semana]);
        $horario_dia = $stmt_horarios->fetch(PDO::FETCH_ASSOC);

        if (!$horario_dia || !empty($horario_dia['fechado'])) {
            $fechado = true;
        } else {
            $hora_inicio_dia = $horario_dia['hora_abertura'] ?? HORA_INICIO_PADRAO;
            $hora_fim_dia = $horario_dia['hora_fechamento'] ?? HORA_FIM_PADRAO;
        }
    }

    // Se, após todas as verificações, o dia for considerado fechado, encerra aqui.
    if ($fechado) {
        echo json_encode([
            'status' => 'success',
            'horarios' => [],
            'info' => ['fechado' => true, 'message' => 'Dia fechado para agendamentos.']
        ]);
        exit;
    }
    // ==================================================================
    // FIM DA NOVA LÓGICA
    // ==================================================================

    $intervalo_pausa = INTERVALO_PADRAO_MINUTOS;

    $stmt_servico = $pdo->prepare("SELECT duracao_minutos, nome FROM servicos WHERE id = ?");
    $stmt_servico->execute([$servico_id]);
    $servico = $stmt_servico->fetch();
    
    if (!$servico) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Serviço não encontrado.']);
        exit;
    }
    
    $duracao_servico_atual = (int)$servico['duracao_minutos'];
    $nome_servico = $servico['nome'];

    $stmt_agendados = $pdo->prepare("
        SELECT a.hora_agendamento, s.duracao_minutos
        FROM agendamentos a
        JOIN servicos s ON a.servico_id = s.id
        WHERE a.data_agendamento = ? AND a.status != 'cancelado'
    ");
    $stmt_agendados->execute([$data]);
    $agendamentos_do_dia = $stmt_agendados->fetchAll();

    $slots_ocupados = [];
    foreach ($agendamentos_do_dia as $ag) {
        $inicio_ocupado = new DateTime($data . ' ' . $ag['hora_agendamento']);
        $fim_ocupado = clone $inicio_ocupado;
        // A duração total do bloqueio deve considerar o serviço agendado + o intervalo
        $duracao_total_ocupada = (int)$ag['duracao_minutos'] + $intervalo_pausa;
        $fim_ocupado->add(new DateInterval("PT{$duracao_total_ocupada}M"));
        
        $slots_ocupados[] = ['inicio' => $inicio_ocupado, 'fim' => $fim_ocupado];
    }

    $horarios_disponiveis = [];
    $inicio_dia = new DateTime($data . ' ' . $hora_inicio_dia);
    $fim_dia = new DateTime($data . ' ' . $hora_fim_dia);

    $horario_teste = clone $inicio_dia;

    while ($horario_teste < $fim_dia) {
        $fim_horario_teste = clone $horario_teste;
        // O slot a ser testado precisa ter a duração do serviço ATUAL
        $fim_horario_teste->add(new DateInterval("PT{$duracao_servico_atual}M"));
        
        if ($fim_horario_teste > $fim_dia) {
            break; // O serviço terminaria depois do expediente
        }

        $is_disponivel = true;
        foreach ($slots_ocupados as $ocupado) {
            // Verifica se o slot que queremos agendar colide com algum slot já ocupado
            if ($horario_teste < $ocupado['fim'] && $fim_horario_teste > $ocupado['inicio']) {
                $is_disponivel = false;
                // Pula o horário de teste para o final do slot ocupado para otimizar a busca
                $horario_teste = clone $ocupado['fim']; 
                break;
            }
        }

        if ($is_disponivel) {
            $horarios_disponiveis[] = $horario_teste->format('H:i');
            // Avança para o próximo slot possível, considerando um intervalo mínimo
            $horario_teste->add(new DateInterval("PT" . INTERVALO_PADRAO_MINUTOS . "M"));
        }
    }

    echo json_encode([
        'status' => 'success', 
        'horarios' => $horarios_disponiveis,
        'info' => [
            'servico' => $nome_servico,
            'duracao' => $duracao_servico_atual,
            'intervalo' => $intervalo_pausa,
            'total_por_agendamento' => $duracao_servico_atual + $intervalo_pausa,
            'fechado' => false,
            'horario_abertura' => $hora_inicio_dia,
            'horario_fechamento' => $hora_fim_dia
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Erro em horarios.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Ocorreu um erro inesperado no servidor. Detalhe: ' . $e->getMessage()]);
}
?>
