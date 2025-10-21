<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Desativado para produção, ative (1) se precisar depurar.

require_once 'config.php';

// Inicialização de variáveis
$agendamentos = [];
$clientes = [];
$faturacao_diaria = [];
$agendamentos_agrupados = [];
$datas_com_agendamentos = [];
$erro = null;

try {
    $pdo = conectarDB();

    // --- CORREÇÃO: Adicionado 's.moeda' na consulta ---
    $stmt = $pdo->query("
        SELECT 
            a.id, a.nome_cliente, a.whatsapp_cliente, s.nome as nome_servico, 
            s.preco, s.moeda, a.data_agendamento, a.hora_agendamento
        FROM agendamentos a
        LEFT JOIN servicos s ON a.servico_id = s.id
        ORDER BY a.data_agendamento DESC, a.hora_agendamento ASC
    ");
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Consulta de clientes para a outra aba
    $stmt_clientes = $pdo->query("SELECT id, nome, telefone, pontos FROM clientes ORDER BY pontos DESC, nome ASC");
    $clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);

    // Processar dados dos agendamentos
    if ($agendamentos) {
        foreach ($agendamentos as $agendamento) {
            $data = $agendamento['data_agendamento'];
            $preco = $agendamento['preco'] ?? 0;
            $agendamentos_agrupados[$data][] = $agendamento;
            $faturacao_diaria[$data] = ($faturacao_diaria[$data] ?? 0) + (float)$preco;
        }
        $datas_com_agendamentos = array_keys($agendamentos_agrupados);
    }

} catch (PDOException $e) {
    $erro = "Erro ao carregar dados: " . $e->getMessage();
}

// --- LÓGICA DO CALENDÁRIO ---
$mes_atual = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$ano_atual = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$hoje = date('Y-m-d'); // Data de hoje para comparação

$primeiro_dia_mes = mktime(0, 0, 0, $mes_atual, 1, $ano_atual);
$dias_no_mes = date('t', $primeiro_dia_mes);
$dia_semana_primeiro = date('w', $primeiro_dia_mes);

$formatter = new IntlDateFormatter('pt_PT', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMMM');
$nome_mes = $formatter->format($primeiro_dia_mes);

$mes_anterior = $mes_atual == 1 ? 12 : $mes_atual - 1;
$ano_anterior = $mes_atual == 1 ? $ano_atual - 1 : $ano_atual;
$mes_seguinte = $mes_atual == 12 ? 1 : $mes_atual + 1;
$ano_seguinte = $mes_atual == 12 ? $ano_atual + 1 : $ano_atual;

?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Administração</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f9f9f9; }
        .admin-header {
            background: linear-gradient(to right, #1e3a8a, #1e40af);
            color: white; padding: 1.5rem; margin-bottom: 2rem; border-bottom: 4px solid #1d4ed8;
        }
        .mobile-card {
            background-color: white; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-left: 4px solid #3b82f6;
        }
        @media (max-width: 768px) { .desktop-table-wrapper { display: none; } .mobile-cards { display: block; } }
        @media (min-width: 769px) { .desktop-table-wrapper { display: block; } .mobile-cards { display: none; } }
        
        .calendar-day {
            transition: all 0.2s ease; position: relative;
        }
        .calendar-day.has-appointments {
            background-color: #dbeafe; color: #1e40af; font-weight: bold; cursor: pointer;
        }
        .calendar-day.has-appointments:hover { background-color: #bfdbfe; }
        
        /* --- NOVO ESTILO PARA DATAS PASSADAS --- */
        .calendar-day.has-appointments.is-past {
            background-color: #fee2e2; /* bg-red-100 */
            color: #b91c1c; /* text-red-700 */
        }
        .calendar-day.has-appointments.is-past:hover {
             background-color: #fecaca; /* bg-red-200 */
        }
        .calendar-day.is-past .appointment-dot {
            background-color: #b91c1c; /* text-red-700 */
        }
        /* --- FIM DO NOVO ESTILO --- */

        .calendar-day.active {
            background-color: #2563eb; color: white; transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .appointment-dot {
            height: 6px; width: 6px; background-color: #2563eb;
            border-radius: 50%; position: absolute; bottom: 6px; left: 50%;
            transform: translateX(-50%);
        }
        .calendar-day.active .appointment-dot { background-color: white; }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container mx-auto px-4"><h1 class="text-2xl sm:text-3xl font-bold">Painel de Administração</h1></div>
    </div>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6 border-b border-gray-200">
            <nav class="flex -mb-px space-x-2 sm:space-x-4 overflow-x-auto">
                <button id="tab-agendamentos" class="tab-button border-blue-600 text-blue-600 whitespace-nowrap py-3 px-3 sm:px-4 border-b-2 font-medium text-sm sm:text-base">Agendamentos</button>
                <button id="tab-clientes" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-3 px-3 sm:px-4 border-b-2 font-medium text-sm sm:text-base">Clientes e Fidelidade</button>
            </nav>
        </div>

        <div id="secao-agendamentos" class="tab-content">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 gap-4">
                <h2 class="text-xl sm:text-2xl font-bold text-gray-800">Agendamentos</h2>
                <a href="gerenciar_servicos.php" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition-colors flex items-center justify-center text-sm sm:text-base">
                    <span class="mr-1">Gerir Serviços e Preços</span> &rarr;
                </a>
            </div>

            <?php if ($erro): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Erro!</strong> <span class="block sm:inline"><?= htmlspecialchars($erro) ?></span>
                </div>
            <?php endif; ?>

            <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md mb-6">
                <div class="flex justify-between items-center mb-4">
                    <a href="?month=<?= $mes_anterior ?>&year=<?= $ano_anterior ?>" class="text-blue-600 hover:text-blue-800 font-bold">&larr; Mês Anterior</a>
                    <h3 class="text-xl font-bold text-gray-800 capitalize"><?= $nome_mes ?> de <?= $ano_atual ?></h3>
                    <a href="?month=<?= $mes_seguinte ?>&year=<?= $ano_seguinte ?>" class="text-blue-600 hover:text-blue-800 font-bold">Mês Seguinte &rarr;</a>
                </div>
                <div class="grid grid-cols-7 gap-1 text-center">
                    <div class="font-semibold text-gray-600 text-sm">Dom</div><div class="font-semibold text-gray-600 text-sm">Seg</div><div class="font-semibold text-gray-600 text-sm">Ter</div><div class="font-semibold text-gray-600 text-sm">Qua</div><div class="font-semibold text-gray-600 text-sm">Qui</div><div class="font-semibold text-gray-600 text-sm">Sex</div><div class="font-semibold text-gray-600 text-sm">Sáb</div>
                    <?php for ($i = 0; $i < $dia_semana_primeiro; $i++): ?><div></div><?php endfor; ?>
                    <?php for ($dia = 1; $dia <= $dias_no_mes; $dia++):
                        $data_completa = sprintf('%04d-%02d-%02d', $ano_atual, $mes_atual, $dia);
                        $tem_agendamento = in_array($data_completa, $datas_com_agendamentos);
                        $is_past = $data_completa < $hoje;

                        $classes = 'calendar-day h-12 sm:h-16 flex items-center justify-center rounded-lg ';
                        if ($tem_agendamento) {
                            $classes .= 'has-appointments ';
                            if ($is_past) {
                                $classes .= 'is-past ';
                            }
                        } else {
                            $classes .= 'text-gray-400';
                        }
                    ?>
                        <div class="<?= trim($classes) ?>" data-date-target="<?= $data_completa ?>">
                            <?= $dia ?><?php if ($tem_agendamento): ?><span class="appointment-dot"></span><?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <?php if (empty($agendamentos_agrupados)): ?>
                <div id="no-appointments-message" class="bg-white rounded-lg shadow-md p-6 text-center text-gray-600">
                    Nenhuma marcação encontrada.
                </div>
            <?php else: ?>
                <?php foreach ($agendamentos_agrupados as $data => $agendamentos_do_dia): ?>
                    <div id="date-content-<?= $data ?>" class="date-tab-content hidden">
                        <div class="bg-gray-100 p-4 rounded-lg mb-4 text-center">
                            <p class="text-md text-gray-600">Faturação prevista para <?= date('d/m/Y', strtotime($data)) ?>:</p>
                            <?php
                                // --- CORREÇÃO: Determina a moeda para o total do dia ---
                                $moeda_total = !empty($agendamentos_do_dia[0]['moeda']) && $agendamentos_do_dia[0]['moeda'] === 'BRL' ? 'R$' : '€';
                            ?>
                            <p class="text-2xl font-bold text-gray-800"><?= $moeda_total ?> <?= number_format($faturacao_diaria[$data], 2, ',', '.') ?></p>
                        </div>

                        <div class="desktop-table-wrapper bg-white shadow-md rounded-lg overflow-hidden">
                            <table class="min-w-full leading-normal">
                                <thead>
                                    <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                                        <th class="py-3 px-6 text-left">Cliente</th><th class="py-3 px-6 text-left">Hora</th><th class="py-3 px-6 text-left">Serviço</th><th class="py-3 px-6 text-right">Preço</th><th class="py-3 px-6 text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-600 text-sm font-light">
                                    <?php foreach ($agendamentos_do_dia as $agendamento): ?>
                                        <?php
                                            // --- CORREÇÃO: Determina a moeda para cada agendamento ---
                                            $moeda_item = !empty($agendamento['moeda']) && $agendamento['moeda'] === 'BRL' ? 'R$' : '€';
                                        ?>
                                        <tr class="border-b border-gray-200 hover:bg-gray-100">
                                            <td class="py-3 px-6 text-left whitespace-nowrap"><div class="font-medium"><?= htmlspecialchars($agendamento['nome_cliente']) ?></div><div class="text-xs text-gray-500"><?= htmlspecialchars($agendamento['whatsapp_cliente']) ?></div></td>
                                            <td class="py-3 px-6 text-left font-medium"><?= date("H:i", strtotime($agendamento['hora_agendamento'])) ?></td>
                                            <td class="py-3 px-6 text-left"><?= htmlspecialchars($agendamento['nome_servico'] ?? 'N/D') ?></td>
                                            <td class="py-3 px-6 text-right font-medium"><?= $moeda_item ?> <?= number_format($agendamento['preco'] ?? 0, 2, ',', '.') ?></td>
                                            <td class="py-3 px-6 text-center"><div class="flex item-center justify-center space-x-2"><?php $link = "https://wa.me/" . preg_replace('/[^0-9]/', '', $agendamento['whatsapp_cliente']); ?><a href="<?= $link ?>" target="_blank" class="bg-green-500 text-white py-1 px-3 rounded-full text-xs hover:bg-green-600">WhatsApp</a><a href="apagar_agendamento.php?id=<?= $agendamento['id'] ?>" onclick="return confirm('Tem a certeza?');" class="bg-red-500 text-white py-1 px-3 rounded-full text-xs hover:bg-red-600">Apagar</a></div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mobile-cards space-y-3">
                            <?php foreach ($agendamentos_do_dia as $agendamento): ?>
                                <?php
                                    // --- CORREÇÃO: Determina a moeda para cada agendamento (móvel) ---
                                    $moeda_item = !empty($agendamento['moeda']) && $agendamento['moeda'] === 'BRL' ? 'R$' : '€';
                                ?>
                                <div class="mobile-card">
                                    <div class="flex justify-between items-start"><p class="font-bold text-lg text-gray-800"><?= htmlspecialchars($agendamento['nome_cliente']) ?></p><p class="font-bold text-lg text-blue-600"><?= date("H:i", strtotime($agendamento['hora_agendamento'])) ?></p></div>
                                    <div class="mt-3 pt-3 border-t"><p><span class="font-semibold">Serviço:</span> <?= htmlspecialchars($agendamento['nome_servico'] ?? 'N/D') ?></p><p><span class="font-semibold">Preço:</span> <?= $moeda_item ?> <?= number_format($agendamento['preco'] ?? 0, 2, ',', '.') ?></p></div>
                                    <div class="mt-4 flex justify-end space-x-2"><?php $link = "https://wa.me/" . preg_replace('/[^0-9]/', '', $agendamento['whatsapp_cliente']); ?><a href="<?= $link ?>" target="_blank" class="bg-green-500 text-white py-1 px-3 rounded-full text-xs hover:bg-green-600">WhatsApp</a><a href="apagar_agendamento.php?id=<?= $agendamento['id'] ?>" onclick="return confirm('Tem a certeza?');" class="bg-red-500 text-white py-1 px-3 rounded-full text-xs hover:bg-red-600">Apagar</a></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                 <div id="no-selection-message" class="bg-white rounded-lg shadow-md p-6 text-center text-gray-600">
                    Selecione um dia no calendário para ver os detalhes.
                </div>
            <?php endif; ?>
        </div>

        <div id="secao-clientes" class="tab-content hidden">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-800 mb-6">Clientes e Pontos de Fidelidade</h2>
            <?php if (empty($clientes)): ?>
                 <div class="bg-white rounded-lg shadow-md p-6 text-center text-gray-600">Nenhum cliente encontrado.</div>
            <?php else: ?>
                <div class="bg-white shadow-md rounded-lg overflow-hidden">
                    <table class="min-w-full leading-normal">
                         <thead><tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal"><th class="py-3 px-6 text-left">Nome</th><th class="py-3 px-6 text-left">Telefone</th><th class="py-3 px-6 text-center">Pontos</th></tr></thead>
                        <tbody class="text-gray-600 text-sm font-light">
                            <?php foreach ($clientes as $cliente): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-100">
                                    <td class="py-3 px-6 text-left font-medium"><?= htmlspecialchars($cliente['nome']) ?></td><td class="py-3 px-6 text-left"><?= htmlspecialchars($cliente['telefone']) ?></td><td class="py-3 px-6 text-center"><span class="bg-blue-200 text-blue-800 font-semibold py-1 px-3 rounded-full text-xs"><?= $cliente['pontos'] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-8 mb-8 text-center"><a href="index.php" class="text-blue-500 hover:underline text-sm">Ir para a página de agendamentos</a></div>
    </div>

    <script>
        const mainTabs = document.querySelectorAll('.tab-button');
        const mainContents = document.querySelectorAll('.tab-content');
        mainTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                mainTabs.forEach(t => { t.classList.remove('border-blue-600', 'text-blue-600'); t.classList.add('border-transparent', 'text-gray-500'); });
                mainContents.forEach(c => c.classList.add('hidden'));
                tab.classList.add('border-blue-600', 'text-blue-600');
                tab.classList.remove('border-transparent', 'text-gray-500');
                document.getElementById(tab.id.replace('tab-', 'secao-')).classList.remove('hidden');
            });
        });

        const calendarDays = document.querySelectorAll('.calendar-day.has-appointments');
        const noSelectionMessage = document.getElementById('no-selection-message');
        const noAppointmentsMessage = document.getElementById('no-appointments-message');

        calendarDays.forEach(day => {
            day.addEventListener('click', () => {
                calendarDays.forEach(d => d.classList.remove('active'));
                day.classList.add('active');
                if(noSelectionMessage) noSelectionMessage.classList.add('hidden');
                if(noAppointmentsMessage) noAppointmentsMessage.classList.add('hidden');
                document.querySelectorAll('.date-tab-content').forEach(c => c.classList.add('hidden'));
                const targetContent = document.getElementById(`date-content-${day.dataset.dateTarget}`);
                if (targetContent) {
                    targetContent.classList.remove('hidden');
                }
            });
        });

        const urlParams = new URLSearchParams(window.location.search);
        const todayForCalendar = new Date().toISOString().slice(0, 10);
        const dayToClick = urlParams.get('day') || todayForCalendar;
        const targetDay = document.querySelector(`.calendar-day[data-date-target="${dayToClick}"]`);

        if (targetDay && targetDay.classList.contains('has-appointments')) {
            targetDay.click();
        }
    </script>
</body>
</html>
