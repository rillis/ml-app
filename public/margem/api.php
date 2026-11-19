<?php
// Repassa as chamadas da aba Margem para o ml-tools-web, que busca os anúncios no ML,
// calcula a margem, compara os SKUs e soma os PDFs de envio.
require __DIR__ . '/../../src/auth.php';
session_write_close(); // a busca no ML roda no ml-tools-web; aqui só repassa

const MARGEM_PADRAO = 'http://192.168.15.11:51016';
$base = rtrim(getenv('MARGEM_URL') ?: MARGEM_PADRAO, '/');

$modulos = ['margem', 'skus'];
$tabelas = ['precos', 'apelidos'];
$modulo = $_GET['modulo'] ?? '';
$nome = $_GET['nome'] ?? '';

/* ===== DOWNLOADS: planilhas geradas e tabelas ===== */
$r = $_GET['r'] ?? '';
if ($r === 'arquivo' || $r === 'tabela') {
    if ($r === 'arquivo') {
        if (!in_array($modulo, $modulos, true) || !preg_match('/^[\w-]+\.xlsx$/', $nome)) {
            http_response_code(404);
            exit;
        }
        $url = "$base/arquivos/$modulo/" . rawurlencode($nome);
        $arquivo = $nome;
    } else {
        if (!in_array($nome, $tabelas, true)) {
            http_response_code(404);
            exit;
        }
        $url = "$base/tabelas/$nome";
        $arquivo = "$nome.xlsx";
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 3]);
    $dados = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($dados === false || $codigo !== 200) {
        http_response_code($dados === false ? 502 : $codigo);
        exit;
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $arquivo . '"');
    header('Content-Length: ' . strlen($dados));
    echo $dados;
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$rotas = [
    'estado'          => ['GET', '/api/estado'],
    'job'             => ['GET', '/api/job'],
    'iniciar'         => ['POST', '/api/job'],
    'parar'           => ['POST', '/api/job/parar'],
    'visto'           => ['POST', '/api/job/visto'],
    'margem'          => ['GET', '/api/margem'],
    'margem_teste'    => ['GET', '/api/margem/teste'],
    'skus'            => ['GET', '/api/skus'],
    'planilhas'       => ['GET', '/api/planilhas/'],
    'config'          => ['POST', '/api/config'],
    'simular'         => ['POST', '/api/simular'],
    'frete_divergente' => ['GET', '/api/frete-divergente'],
    'clonar'          => ['GET', '/api/clonar'],
    'clonar_andamento' => ['GET', '/api/clonar/andamento'],
    'clonar_gtin'     => ['GET', '/api/clonar/gtin'],
    'tabelas'         => ['GET', '/api/tabelas'],
    'trocar_tabela'   => ['POST', '/api/tabelas/', 'arquivo'],
    'envio'           => ['POST', '/api/envio', 'arquivo'],
    'acesso_url'      => ['POST', '/api/acesso/url'],
    'acesso_concluir' => ['POST', '/api/acesso/concluir'],
];
$rota = $rotas[$r] ?? null;
if (!$rota) {
    http_response_code(404);
    echo json_encode(['erro' => 'Rota desconhecida']);
    exit;
}
[$metodo, $caminho] = $rota;
$upload = $rota[2] ?? null;
if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
    http_response_code(405);
    echo json_encode(['erro' => "Use $metodo"]);
    exit;
}
if ($r === 'planilhas') {
    if (!in_array($modulo, $modulos, true)) {
        http_response_code(404);
        echo json_encode(['erro' => 'Módulo desconhecido']);
        exit;
    }
    $caminho .= $modulo;
}
if ($r === 'clonar_gtin') {
    // só um código de anúncio MLB passa adiante
    $item = strtoupper(trim((string) ($_GET['item'] ?? '')));
    if (!preg_match('/^MLB\d+$/', $item)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Anúncio inválido']);
        exit;
    }
    $caminho .= '?item=' . $item;
}
if ($r === 'trocar_tabela') {
    if (!in_array($nome, $tabelas, true)) {
        http_response_code(404);
        echo json_encode(['erro' => 'Tabela desconhecida']);
        exit;
    }
    $caminho .= $nome;
}

$ch = curl_init($base . $caminho);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 3]);
if ($metodo === 'POST') {
    if ($upload) {
        $f = $_FILES[$upload] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            $grande = $f && in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            echo json_encode(['erro' => $grande ? 'Arquivo grande demais' : 'Nenhum arquivo recebido']);
            exit;
        }
        $corpo = [$upload => new CURLFile($f['tmp_name'], $f['type'] ?: 'application/octet-stream', $f['name'])];
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $corpo]);
    } else {
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => file_get_contents('php://input') ?: '{}',
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
    }
}
$resposta = curl_exec($ch);
if ($resposta === false) {
    http_response_code(502);
    echo json_encode(['erro' => 'Serviço da margem (ml-tools-web) indisponível']);
    exit;
}
http_response_code(curl_getinfo($ch, CURLINFO_HTTP_CODE));
echo $resposta;
