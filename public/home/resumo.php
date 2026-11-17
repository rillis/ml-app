<?php
// Resumo para os cards da home (home.rillis.com.br): boletos por nível e se o mês atual
// das contas pessoais já foi salvo. Só responde na rede local, sem passar pela Cloudflare.

function rede_local(string $ip): bool
{
    foreach (['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.0/8'] as $faixa) {
        [$rede, $bits] = explode('/', $faixa);
        $mascara = -1 << (32 - (int)$bits);
        if ((ip2long($ip) & $mascara) === (ip2long($rede) & $mascara)) {
            return true;
        }
    }
    return false;
}

$viaCloudflare = isset($_SERVER['HTTP_CF_CONNECTING_IP']) || isset($_SERVER['HTTP_CF_RAY']);
if ($viaCloudflare || !rede_local($_SERVER['REMOTE_ADDR'] ?? '')) {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/boletos/vencimento.php';
require_once __DIR__ . '/../../src/boletos/nivel.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pdo = ml_db('ml_boletos');

// Boletos em aberto por nível (mesma regra da tabela de /boletos/ e do widget do Dash)
$feriados = $pdo->query('SELECT data FROM ml_boletos.feriados')->fetchAll(PDO::FETCH_COLUMN);
$hoje = boletoHoje();
$niveis = ['critico' => 0, 'urgente' => 0, 'atencao' => 0];
$abertos = $pdo->query('SELECT data_vencimento, agendado FROM ml_boletos.boletos WHERE pago = 0 AND deleted_at IS NULL');
foreach ($abertos as $bol) {
    $dias = boletoDias(calcularVencimentoReal($bol['data_vencimento'], $feriados), $hoje);
    $nivel = boletoNivel(false, (bool)$bol['agendado'], $dias);
    if (isset($niveis[$nivel])) {
        $niveis[$nivel]++;
    }
}

// Mês atual das contas pessoais: salvo pelo botão "Salvar mês"?
$agora = new DateTime('now', new DateTimeZone(BOLETO_FUSO));
$mes = $agora->format('Y-m-01');
$meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$stmt = $pdo->prepare('SELECT 1 FROM contas_pessoais.meses_salvos WHERE username = ? AND mes = ?');
$stmt->execute(['rillis', $mes]);

echo json_encode([
    'boletos' => $niveis,
    'contas'  => [
        'mes'    => $mes,
        'rotulo' => $meses[(int)$agora->format('n') - 1] . '/' . $agora->format('y'),
        'salvo'  => (bool)$stmt->fetchColumn(),
    ],
]);
