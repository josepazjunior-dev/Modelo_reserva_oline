<?php
require_once 'config.php';

// 1. Validar se o ID foi recebido e é um número inteiro
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    // Se o ID for inválido, redireciona de volta com uma mensagem de erro (opcional)
    // Para simplificar, apenas interrompemos a execução.
    die("Erro: ID de agendamento inválido ou não fornecido.");
}

$id_agendamento = (int)$_GET['id'];

try {
    // 2. Conectar à base de dados
    $pdo = conectarDB();

    // 3. Preparar e executar a query de exclusão
    // A utilização de prepared statements previne injeção de SQL.
    $stmt = $pdo->prepare("DELETE FROM agendamentos WHERE id = ?");
    $stmt->execute([$id_agendamento]);

    // 4. Redirecionar de volta para a página de administração
    // O redirecionamento ocorre após a exclusão bem-sucedida.
    header("Location: admin.php");
    exit;

} catch (PDOException $e) {
    // Em caso de erro na base de dados, exibe uma mensagem.
    // Em um ambiente de produção, o ideal seria logar este erro em vez de exibi-lo.
    die("Erro ao apagar o agendamento: " . $e->getMessage());
}
?>