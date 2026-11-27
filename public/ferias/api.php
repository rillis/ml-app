<?php
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? '';
const FUSO = 'America/Sao_Paulo';
const MAX_DIAS = 60;

function responder(array $dados, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function agora(): string
{
    return (new DateTime('now', new DateTimeZone(FUSO)))->format('Y-m-d H:i:s');
}

function hoje(): string
{
    return (new DateTime('now', new DateTimeZone(FUSO)))->format('Y-m-d');
}

// O tokens.json do ml-jrf só é legível pelo dono: a rodada roda como root no próprio container, em segundo plano
function disparar_rodada(): void
{
    exec('docker exec -d ml-app php /var/www/html/src/ferias/rodada.php --forcar 2>&1', $saida, $codigo);
    if ($codigo !== 0) {
        responder(['error' => 'Não consegui iniciar a rodada: ' . trim(implode("\n", $saida))], 500);
    }
}

function ferias_atual(PDO $pdo): ?array
{
    $f = $pdo->query("SELECT * FROM ferias WHERE status IN ('agendada', 'ativa', 'encerrando') ORDER BY id DESC LIMIT 1")->fetch();
    return $f ?: null;
}

function ler_dias(): int
{
    $dias = filter_var($_POST['dias'] ?? null, FILTER_VALIDATE_INT);
    if ($dias === false || $dias < 1 || $dias > MAX_DIAS) {
        responder(['error' => 'Informe de 1 a ' . MAX_DIAS . ' dias (máximo do ML).'], 400);
    }
    return $dias;
}

$pdo = ml_db('ml_vendas');

if ($action !== 'estado' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['error' => 'Use POST'], 405);
}

/* ===== ESTADO: férias em andamento, rodadas e produtos com erro ===== */
if ($action === 'estado') {
    $f = ferias_atual($pdo) ?: ($pdo->query('SELECT * FROM ferias ORDER BY id DESC LIMIT 1')->fetch() ?: null);
    $rodadas = $erros = [];
    $resumo = null;
    if ($f) {
        $stmt = $pdo->prepare('SELECT id, dia, prazo, inicio, fim, total, feitos, atualizados, iguais, pulados, falhas, erro
            FROM ferias_rodadas WHERE ferias_id = ? ORDER BY id DESC LIMIT 30');
        $stmt->execute([$f['id']]);
        $rodadas = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT user_product, anuncios, titulo, original, atual, erro FROM ferias_produtos
            WHERE ferias_id = ? AND erro IS NOT NULL ORDER BY titulo LIMIT 200');
        $stmt->execute([$f['id']]);
        $erros = array_map(fn($p) => ['anuncios' => json_decode($p['anuncios'], true)] + $p, $stmt->fetchAll());
        $stmt = $pdo->prepare('SELECT COUNT(*) AS produtos, SUM(atual IS NOT NULL) AS com_prazo, SUM(original IS NOT NULL) AS com_original
            FROM ferias_produtos WHERE ferias_id = ?');
        $stmt->execute([$f['id']]);
        $resumo = array_map('intval', $stmt->fetch());
    }
    responder([
        'hoje' => hoje(),
        'maxDias' => MAX_DIAS,
        'ferias' => $f,
        'resumo' => $resumo,
        'rodadas' => $rodadas,
        'erros' => $erros,
    ]);
}

/* ===== ATIVAR: agenda as férias (começa hoje ou num dia futuro) ===== */
if ($action === 'ativar') {
    if (ferias_atual($pdo)) {
        responder(['error' => 'Já existem férias em andamento. Encerre ou cancele antes.'], 409);
    }
    $inicio = (string) ($_POST['inicio'] ?? '');
    $data = DateTime::createFromFormat('!Y-m-d', $inicio);
    if (!$data || $data->format('Y-m-d') !== $inicio || $inicio < hoje()) {
        responder(['error' => 'Data de início inválida (hoje ou depois).'], 400);
    }
    $dias = ler_dias();
    $pdo->prepare("INSERT INTO ferias (inicio, dias, status, criada_em) VALUES (?, ?, 'agendada', ?)")->execute([$inicio, $dias, agora()]);
    if ($inicio === hoje()) {
        disparar_rodada();
    }
    responder(['ok' => true, 'rodando' => $inicio === hoje()]);
}

/* ===== ALTERAR: muda a quantidade de dias (a contagem continua do início) ===== */
if ($action === 'alterar') {
    $f = ferias_atual($pdo);
    if (!$f || $f['status'] === 'encerrando') {
        responder(['error' => 'Nenhuma férias para alterar.'], 409);
    }
    $dias = ler_dias();
    $pdo->prepare('UPDATE ferias SET dias = ? WHERE id = ?')->execute([$dias, $f['id']]);
    $rodando = $f['status'] === 'ativa';
    if ($rodando) {
        disparar_rodada();
    }
    responder(['ok' => true, 'rodando' => $rodando]);
}

/* ===== ENCERRAR: tira o prazo agora (ou cancela, se ainda não começou) ===== */
if ($action === 'encerrar') {
    $f = ferias_atual($pdo);
    if (!$f) {
        responder(['error' => 'Nenhuma férias em andamento.'], 409);
    }
    if ($f['status'] === 'agendada') {
        $pdo->prepare("UPDATE ferias SET status = 'cancelada', encerrada_em = ? WHERE id = ?")->execute([agora(), $f['id']]);
        responder(['ok' => true, 'rodando' => false]);
    }
    $pdo->prepare("UPDATE ferias SET status = 'encerrando' WHERE id = ?")->execute([$f['id']]);
    disparar_rodada();
    responder(['ok' => true, 'rodando' => true]);
}

/* ===== RODAR: refaz a rodada de hoje (ex.: depois de falhas) ===== */
if ($action === 'rodar') {
    $f = ferias_atual($pdo);
    if (!$f || $f['status'] === 'agendada') {
        responder(['error' => 'Nenhuma férias ativa.'], 409);
    }
    disparar_rodada();
    responder(['ok' => true, 'rodando' => true]);
}

responder(['error' => 'Ação inválida'], 400);
