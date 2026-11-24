<?php
// Repassa as chamadas da aba Etiquetas para o ml-etiquetas-web, que emite as NF-e,
// gera os PDFs e mantém a fila de processamento.
require __DIR__ . '/../../src/auth.php';
session_write_close(); // a listagem pode levar alguns segundos; não segura as outras abas

const ETIQUETAS_PADRAO = 'http://192.168.15.11:51015';
$base = rtrim(getenv('ETIQUETAS_URL') ?: ETIQUETAS_PADRAO, '/');

$rotas = [
    'vendas'    => ['GET', '/api/vendas'],
    'processar' => ['POST', '/api/processar'],
    'job'       => ['GET', '/api/job'],
    'visto'     => ['POST', '/api/job/visto'],
    'historico' => ['GET', '/api/historico'],
    'baixa'     => ['POST', '/api/baixa'],
];

/* ===== ARQUIVO: PDFs gerados (DANFEs e etiquetas) ===== */
if (($_GET['r'] ?? '') === 'arquivo') {
    $pasta = $_GET['pasta'] ?? '';
    $nome = $_GET['nome'] ?? '';
    if (!preg_match('/^\d{8}-\d{4,6}$/', $pasta) || !preg_match('/^[\w-][\w.-]*\.pdf$/', $nome)) {
        http_response_code(404);
        exit;
    }
    $ch = curl_init("$base/arquivos/$pasta/" . rawurlencode($nome));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 3]);
    $pdf = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($pdf === false || $codigo !== 200) {
        http_response_code($pdf === false ? 502 : $codigo);
        exit;
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nome . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$rota = $rotas[$_GET['r'] ?? ''] ?? null;
if (!$rota) {
    http_response_code(404);
    echo json_encode(['erro' => 'Rota desconhecida']);
    exit;
}
[$metodo, $caminho] = $rota;
if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
    http_response_code(405);
    echo json_encode(['erro' => "Use $metodo"]);
    exit;
}

$url = $base . $caminho;
if ($rota === $rotas['vendas'] && ($_GET['atualizar'] ?? '') === '1') {
    $url .= '?atualizar=1';
}
$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 3]);
if ($metodo === 'POST') {
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => file_get_contents('php://input') ?: '{}',
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
}
$corpo = curl_exec($ch);
if ($corpo === false) {
    http_response_code(502);
    echo json_encode(['erro' => 'Serviço de etiquetas indisponível']);
    exit;
}
http_response_code(curl_getinfo($ch, CURLINFO_HTTP_CODE));
echo $corpo;
