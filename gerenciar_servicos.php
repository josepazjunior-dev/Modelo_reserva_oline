<?php
require_once 'config.php';
$pdo = conectarDB();
$servico_para_edicao = null;
$erro = '';
$sucesso = '';

// Dias da semana para exibição
$dias_semana = [
    1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira',
    4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo'
];

// Carregar horários e exceções
$horarios_atuais = [];
$excecoes = [];
try {
    $stmt_horarios = $pdo->query("SELECT * FROM horarios_funcionamento ORDER BY dia_semana");
    $horarios_atuais = $stmt_horarios->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_excecoes = $pdo->query("SELECT * FROM horarios_excecoes ORDER BY data DESC");
    $excecoes = $stmt_excecoes->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignorar se as tabelas não existirem ainda
}

// --- LÓGICA DE PROCESSAMENTO DO FORMULÁRIO (CRIAR/ATUALIZAR SERVIÇOS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'servico') {
    $id = $_POST['id'] ?? null;
    $nome = trim($_POST['nome'] ?? '');
    $duracao = filter_var($_POST['duracao'] ?? 0, FILTER_VALIDATE_INT);
    $preco = filter_var($_POST['preco'] ?? 0, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $moeda = $_POST['moeda'] ?? 'EUR';
    
    if ($moeda !== 'EUR' && $moeda !== 'BRL') $moeda = 'EUR';

    if ($nome && $duracao > 0) {
        try {
            if ($id) { // Atualizar
                $stmt = $pdo->prepare("UPDATE servicos SET nome = ?, duracao_minutos = ?, preco = ?, moeda = ? WHERE id = ?");
                $stmt->execute([$nome, $duracao, $preco, $moeda, $id]);
                $sucesso = "Serviço atualizado com sucesso!";
            } else { // Inserir
                $stmt = $pdo->prepare("INSERT INTO servicos (nome, duracao_minutos, preco, moeda) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nome, $duracao, $preco, $moeda]);
                $sucesso = "Serviço adicionado com sucesso!";
            }
        } catch (PDOException $e) {
            $erro = "Erro ao salvar o serviço: " . $e->getMessage();
        }
    } else {
        $erro = "Nome e duração (maior que zero) são obrigatórios.";
    }
}

// --- LÓGICA PARA HORÁRIOS DE FUNCIONAMENTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'horarios') {
    foreach ($dias_semana as $dia_num => $dia_nome) {
        $fechado = isset($_POST["fechado_$dia_num"]) ? 1 : 0;
        $hora_abertura = $fechado ? null : ($_POST["abertura_$dia_num"] ?? null);
        $hora_fechamento = $fechado ? null : ($_POST["fechamento_$dia_num"] ?? null);
        
        try {
            $stmt_horario = $pdo->prepare("
                INSERT INTO horarios_funcionamento (dia_semana, hora_abertura, hora_fechamento, fechado) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE hora_abertura = VALUES(hora_abertura), hora_fechamento = VALUES(hora_fechamento), fechado = VALUES(fechado)
            ");
            $stmt_horario->execute([$dia_num, $hora_abertura, $hora_fechamento, $fechado]);
        } catch (PDOException $e) { $erro = "Erro ao salvar horários: " . $e->getMessage(); break; }
    }
    if (!$erro) {
        $sucesso = "Horários de funcionamento atualizados com sucesso!";
        // Recarregar horários
        $stmt_horarios = $pdo->query("SELECT * FROM horarios_funcionamento ORDER BY dia_semana");
        $horarios_atuais = $stmt_horarios->fetchAll(PDO::FETCH_ASSOC);
    }
}

// --- LÓGICA PARA EXCEÇÕES DE HORÁRIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excecao') {
    $data_excecao = $_POST['data_excecao'] ?? null;
    $tipo_excecao = $_POST['tipo_excecao'] ?? 'fechado';
    $abertura_excecao = $_POST['abertura_excecao'] ?? null;
    $fechamento_excecao = $_POST['fechamento_excecao'] ?? null;
    $descricao = trim($_POST['descricao_excecao'] ?? '');

    if ($data_excecao) {
        $fechado = ($tipo_excecao === 'fechado') ? 1 : 0;
        $hora_abertura = !$fechado ? $abertura_excecao : null;
        $hora_fechamento = !$fechado ? $fechamento_excecao : null;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO horarios_excecoes (data, fechado, hora_abertura, hora_fechamento, descricao) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE fechado = VALUES(fechado), hora_abertura = VALUES(hora_abertura), hora_fechamento = VALUES(hora_fechamento), descricao = VALUES(descricao)
            ");
            $stmt->execute([$data_excecao, $fechado, $hora_abertura, $hora_fechamento, $descricao]);
            $sucesso = "Exceção de horário salva com sucesso!";
            // Recarregar exceções
            $excecoes = $pdo->query("SELECT * FROM horarios_excecoes ORDER BY data DESC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $erro = "Erro ao salvar a exceção: " . $e->getMessage();
        }
    } else {
        $erro = "A data é obrigatória para adicionar uma exceção.";
    }
}

// --- LÓGICA PARA APAGAR ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Apagar serviço
    if (isset($_GET['apagar_servico'])) {
        $id = filter_var($_GET['apagar_servico'], FILTER_VALIDATE_INT);
        if ($id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM servicos WHERE id = ?");
                $stmt->execute([$id]);
                $sucesso = "Serviço apagado com sucesso!";
            } catch (PDOException $e) { $erro = "Erro ao apagar serviço: " . $e->getMessage(); }
        }
    }
    // Apagar exceção
    if (isset($_GET['apagar_excecao'])) {
        $id = filter_var($_GET['apagar_excecao'], FILTER_VALIDATE_INT);
        if ($id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM horarios_excecoes WHERE id = ?");
                $stmt->execute([$id]);
                $sucesso = "Exceção apagada com sucesso!";
                // Recarregar exceções
                $excecoes = $pdo->query("SELECT * FROM horarios_excecoes ORDER BY data DESC")->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) { $erro = "Erro ao apagar exceção: " . $e->getMessage(); }
        }
    }
    // Carregar serviço para edição
    if (isset($_GET['editar'])) {
        $id_para_editar = filter_var($_GET['editar'], FILTER_VALIDATE_INT);
        if ($id_para_editar) {
            $stmt = $pdo->prepare("SELECT * FROM servicos WHERE id = ?");
            $stmt->execute([$id_para_editar]);
            $servico_para_edicao = $stmt->fetch();
        }
    }
}

// Carregar todos os serviços
$servicos = $pdo->query("SELECT * FROM servicos ORDER BY nome ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerir Serviços e Horários</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> 
        body { font-family: 'Inter', sans-serif; background-color: #f9f9f9; } 
        .moeda-seletor { display: flex; border: 1px solid #e2e8f0; border-radius: 0.25rem; overflow: hidden; }
        .moeda-btn { padding: 0.5rem 0.75rem; background-color: #f7fafc; border: none; cursor: pointer; flex: 1; text-align: center; font-size: 0.875rem; }
        .moeda-btn.active { background-color: #4299e1; color: white; }
        .dia-fechado, .excecao-horarios-container.hidden { display: none; }
        .dia-fechado-checkbox:checked ~ .grid { opacity: 0.5; pointer-events: none; }
        
        @media (max-width: 768px) { .desktop-table { display: none; } .mobile-cards { display: block; } }
        @media (min-width: 769px) { .desktop-table { display: table; } .mobile-cards { display: none; } }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <h1 class="text-2xl sm:text-3xl font-bold mb-4 sm:mb-6">Gerir Configurações</h1>
        <a href="admin.php" class="text-blue-500 hover:underline mb-4 sm:mb-6 block text-sm sm:text-base">&larr; Voltar ao Painel</a>

        <?php if ($erro): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm sm:text-base"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
        <?php if ($sucesso): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4 text-sm sm:text-base"><?= htmlspecialchars($sucesso) ?></div><?php endif; ?>

        <!-- Abas para navegação -->
        <div class="mb-6 overflow-x-auto">
            <nav class="flex space-x-2 sm:space-x-4 border-b">
                <button id="tab-servicos" class="tab-button bg-blue-600 text-white px-3 sm:px-4 py-2 rounded-t-lg text-sm sm:text-base whitespace-nowrap">Serviços</button>
                <button id="tab-horarios" class="tab-button bg-gray-200 text-gray-600 px-3 sm:px-4 py-2 rounded-t-lg text-sm sm:text-base whitespace-nowrap">Horários Fixos</button>
                <button id="tab-excecoes" class="tab-button bg-gray-200 text-gray-600 px-3 sm:px-4 py-2 rounded-t-lg text-sm sm:text-base whitespace-nowrap">Folgas e Exceções</button>
            </nav>
        </div>

        <!-- Seção Serviços -->
        <div id="secao-servicos" class="tab-content">
            <!-- Formulário para Adicionar/Editar Serviços -->
            <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md mb-6 sm:mb-8">
                <h2 class="text-lg sm:text-xl font-bold mb-4"><?= $servico_para_edicao ? 'Editar Serviço' : 'Adicionar Novo Serviço' ?></h2>
                <form action="gerenciar_servicos.php" method="POST">
                    <input type="hidden" name="acao" value="servico">
                    <input type="hidden" name="id" value="<?= $servico_para_edicao['id'] ?? '' ?>">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="nome" class="block text-sm font-medium mb-1">Nome do Serviço</label>
                            <input type="text" name="nome" id="nome" value="<?= htmlspecialchars($servico_para_edicao['nome'] ?? '') ?>" required class="w-full border border-gray-300 px-3 py-2 rounded-md text-sm sm:text-base">
                        </div>
                        <div>
                            <label for="duracao" class="block text-sm font-medium mb-1">Duração (minutos)</label>
                            <input type="number" name="duracao" id="duracao" value="<?= $servico_para_edicao['duracao_minutos'] ?? '' ?>" required min="1" class="w-full border border-gray-300 px-3 py-2 rounded-md text-sm sm:text-base">
                        </div>
                        <div>
                            <label for="preco" class="block text-sm font-medium mb-1">Preço</label>
                            <input type="text" name="preco" id="preco" value="<?= htmlspecialchars($servico_para_edicao['preco'] ?? '') ?>" placeholder="Ex: 25.50" class="w-full border border-gray-300 px-3 py-2 rounded-md text-sm sm:text-base mb-2">
                            <div class="moeda-seletor">
                                <button type="button" class="moeda-btn <?= (!isset($servico_para_edicao['moeda']) || $servico_para_edicao['moeda'] == 'EUR') ? 'active' : '' ?>" data-moeda="EUR">EUR (€)</button>
                                <button type="button" class="moeda-btn <?= (isset($servico_para_edicao['moeda']) && $servico_para_edicao['moeda'] == 'BRL') ? 'active' : '' ?>" data-moeda="BRL">BRL (R$)</button>
                            </div>
                            <input type="hidden" name="moeda" id="moeda-input" value="<?= $servico_para_edicao['moeda'] ?? 'EUR' ?>">
                        </div>
                    </div>
                    <div class="mt-4 flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3">
                        <?php if ($servico_para_edicao): ?>
                            <a href="gerenciar_servicos.php" class="bg-gray-300 text-gray-800 py-2 px-4 rounded-md hover:bg-gray-400 text-center text-sm sm:text-base">Cancelar Edição</a>
                        <?php endif; ?>
                        <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 text-sm sm:text-base"><?= $servico_para_edicao ? 'Salvar Alterações' : 'Adicionar Serviço' ?></button>
                    </div>
                </form>
            </div>
            <!-- Lista de Serviços -->
            <div class="bg-white shadow-md rounded-lg overflow-hidden desktop-table">
                <table class="min-w-full leading-normal">
                    <thead class="bg-gray-200 text-gray-600 uppercase text-sm"><tr><th class="py-3 px-6 text-left">Nome</th><th class="py-3 px-6 text-left">Duração</th><th class="py-3 px-6 text-left">Preço</th><th class="py-3 px-6 text-center">Ações</th></tr></thead>
                    <tbody class="text-gray-600 text-sm">
                        <?php foreach ($servicos as $serv): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-100">
                            <td class="py-3 px-6"><?= htmlspecialchars($serv['nome']) ?></td><td class="py-3 px-6"><?= $serv['duracao_minutos'] ?> min</td>
                            <td class="py-3 px-6"><?= $serv['moeda'] == 'BRL' ? 'R$ ' : '€ ' ?><?= number_format($serv['preco'], 2, ',', '.') ?></td>
                            <td class="py-3 px-6 text-center">
                                <a href="?editar=<?= $serv['id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">Editar</a>
                                <a href="?apagar_servico=<?= $serv['id'] ?>" onclick="return confirm('Tem a certeza?')" class="text-red-600 hover:text-red-900">Apagar</a>
                            </td>
                        </tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Seção Horários de Funcionamento -->
        <div id="secao-horarios" class="tab-content hidden">
            <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md">
                <h2 class="text-lg sm:text-xl font-bold mb-4">Horários de Funcionamento Fixo</h2>
                <form action="gerenciar_servicos.php" method="POST">
                    <input type="hidden" name="acao" value="horarios">
                    <div class="space-y-4">
                        <?php foreach ($dias_semana as $dia_num => $dia_nome): 
                            $horario_atual = current(array_filter($horarios_atuais, fn($h) => $h['dia_semana'] == $dia_num)) ?: null;
                            $fechado = $horario_atual ? $horario_atual['fechado'] : true; ?>
                            <div class="border border-gray-200 p-3 sm:p-4 rounded-md">
                                <div class="flex items-center mb-3">
                                    <input type="checkbox" id="fechado_<?= $dia_num ?>" name="fechado_<?= $dia_num ?>" value="1" <?= $fechado ? 'checked' : '' ?> class="w-4 h-4 dia-fechado-checkbox">
                                    <label for="fechado_<?= $dia_num ?>" class="ml-2 font-medium text-sm sm:text-base"><?= $dia_nome ?> - Fechado</label>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                                    <div><label for="abertura_<?= $dia_num ?>" class="block text-xs sm:text-sm font-medium mb-1">Abertura</label><input type="time" id="abertura_<?= $dia_num ?>" name="abertura_<?= $dia_num ?>" value="<?= $horario_atual['hora_abertura'] ?? '' ?>" class="w-full border-gray-300 rounded-md"></div>
                                    <div><label for="fechamento_<?= $dia_num ?>" class="block text-xs sm:text-sm font-medium mb-1">Fechamento</label><input type="time" id="fechamento_<?= $dia_num ?>" name="fechamento_<?= $dia_num ?>" value="<?= $horario_atual['hora_fechamento'] ?? '' ?>" class="w-full border-gray-300 rounded-md"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-6 flex justify-end"><button type="submit" class="w-full sm:w-auto bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700">Salvar Horários Fixos</button></div>
                </form>
            </div>
        </div>
        
        <!-- Seção Folgas e Exceções -->
        <div id="secao-excecoes" class="tab-content hidden">
            <!-- Formulário para Adicionar Exceção -->
            <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md mb-6 sm:mb-8">
                <h2 class="text-lg sm:text-xl font-bold mb-4">Adicionar Folga ou Exceção</h2>
                <form action="gerenciar_servicos.php" method="POST">
                    <input type="hidden" name="acao" value="excecao">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                        <div>
                            <label for="data_excecao" class="block text-sm font-medium mb-1">Data</label>
                            <input type="date" name="data_excecao" id="data_excecao" required class="w-full border border-gray-300 px-3 py-2 rounded-md">
                        </div>
                        <div>
                            <label for="tipo_excecao" class="block text-sm font-medium mb-1">Tipo</label>
                            <select name="tipo_excecao" id="tipo_excecao" class="w-full border border-gray-300 px-3 py-2 rounded-md">
                                <option value="fechado">Dia de Folga (Fechado)</option>
                                <option value="horario_especial">Horário Especial</option>
                            </select>
                        </div>
                    </div>
                    <div class="excecao-horarios-container grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label for="abertura_excecao" class="block text-sm font-medium mb-1">Abertura</label>
                            <input type="time" name="abertura_excecao" id="abertura_excecao" class="w-full border border-gray-300 px-3 py-2 rounded-md">
                        </div>
                        <div>
                            <label for="fechamento_excecao" class="block text-sm font-medium mb-1">Fechamento</label>
                            <input type="time" name="fechamento_excecao" id="fechamento_excecao" class="w-full border border-gray-300 px-3 py-2 rounded-md">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label for="descricao_excecao" class="block text-sm font-medium mb-1">Descrição (Opcional)</label>
                        <input type="text" name="descricao_excecao" id="descricao_excecao" placeholder="Ex: Feriado, Consulta médica" class="w-full border border-gray-300 px-3 py-2 rounded-md">
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700">Adicionar Exceção</button>
                    </div>
                </form>
            </div>
            <!-- Lista de Exceções -->
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <h2 class="text-lg sm:text-xl font-bold p-4 sm:p-6 border-b">Exceções Cadastradas</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full leading-normal">
                        <thead class="bg-gray-200 text-gray-600 uppercase text-sm"><tr><th class="py-3 px-6 text-left">Data</th><th class="py-3 px-6 text-left">Tipo / Horário</th><th class="py-3 px-6 text-left">Descrição</th><th class="py-3 px-6 text-center">Ação</th></tr></thead>
                        <tbody class="text-gray-600 text-sm">
                            <?php if (empty($excecoes)): ?>
                                <tr><td colspan="4" class="py-4 px-6 text-center">Nenhuma exceção cadastrada.</td></tr>
                            <?php else: foreach ($excecoes as $exc): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-100">
                                    <td class="py-3 px-6 font-medium"><?= date("d/m/Y", strtotime($exc['data'])) ?></td>
                                    <td class="py-3 px-6">
                                        <?php if ($exc['fechado']): ?>
                                            <span class="bg-red-200 text-red-800 py-1 px-3 rounded-full text-xs">Fechado</span>
                                        <?php else: ?>
                                            <span class="bg-green-200 text-green-800 py-1 px-3 rounded-full text-xs"><?= substr($exc['hora_abertura'], 0, 5) ?> - <?= substr($exc['hora_fechamento'], 0, 5) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-6"><?= htmlspecialchars($exc['descricao']) ?></td>
                                    <td class="py-3 px-6 text-center">
                                        <a href="?apagar_excecao=<?= $exc['id'] ?>" onclick="return confirm('Tem a certeza que quer apagar esta exceção?')" class="text-red-600 hover:text-red-900">Apagar</a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab-button');
            const contents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // Desativar todas as abas e conteúdos
                    tabs.forEach(t => {
                        t.classList.remove('bg-blue-600', 'text-white');
                        t.classList.add('bg-gray-200', 'text-gray-600');
                    });
                    contents.forEach(c => c.classList.add('hidden'));

                    // Ativar aba clicada
                    tab.classList.add('bg-blue-600', 'text-white');
                    tab.classList.remove('bg-gray-200', 'text-gray-600');
                    document.getElementById('secao-' + tab.id.split('-')[1]).classList.remove('hidden');
                });
            });

            // Lógica do seletor de moeda
            const moedaBotoes = document.querySelectorAll('.moeda-btn');
            const moedaInput = document.getElementById('moeda-input');
            moedaBotoes.forEach(btn => {
                btn.addEventListener('click', () => {
                    moedaInput.value = btn.dataset.moeda;
                    moedaBotoes.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                });
            });

            // Lógica para mostrar/esconder horários da exceção
            const tipoExcecaoSelect = document.getElementById('tipo_excecao');
            const horariosContainer = document.querySelector('.excecao-horarios-container');
            tipoExcecaoSelect.addEventListener('change', () => {
                if (tipoExcecaoSelect.value === 'horario_especial') {
                    horariosContainer.style.display = 'grid';
                } else {
                    horariosContainer.style.display = 'none';
                }
            });
            // Inicializa o estado correto
            if (tipoExcecaoSelect.value !== 'horario_especial') {
                horariosContainer.style.display = 'none';
            }

            // Lógica para desabilitar inputs de horário fixo
            document.querySelectorAll('.dia-fechado-checkbox').forEach(checkbox => {
                const container = checkbox.closest('.border');
                const inputs = container.querySelectorAll('input[type="time"]');
                
                const toggleInputs = () => {
                    inputs.forEach(input => input.disabled = checkbox.checked);
                };

                checkbox.addEventListener('change', toggleInputs);
                toggleInputs(); // Seta o estado inicial
            });
        });
    </script>
</body>
</html>
