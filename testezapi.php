<?php
require_once 'config.php';

$numero = '351964949920'; // Substitui pelo teu número
$mensagem = "✅ Teste da integração Z-API concluído com sucesso!";

echo "<h2>🚀 Enviando mensagem...</h2>";

$resultado = enviarMensagem($numero, $mensagem);

echo "<h3>📩 Resultado:</h3>";
echo "<pre>";
var_dump($resultado);
echo "</pre>";
?>

