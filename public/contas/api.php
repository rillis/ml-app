<?php
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
date_default_timezone_set('America/Sao_Paulo');

const CONTAS_VALOR_MAXIMO = 9999999.99;

$usuario = $_SESSION['user'];
$action = $_GET['action'] ?? '';
$pedido = [];

function responder_erro(string $mensagem, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['erro' => $mensagem]);
    exit;
}

// Converte "2026-11" em "2026-11-01". Encerra a requisição se o mês for inválido.
function ler_mes($valor): string
{
    if (!is_string($valor) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor)) {
        responder_erro('Mês inválido.');
    }
    return $valor . '-01';
}

function mes_salvo(PDO $pdo, string $usuario, string $mes): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM meses_salvos WHERE username = ? AND mes = ?');
    $stmt->execute([$usuario, $mes]);
    return (bool)$stmt->fetchColumn();
}

function lista_contas(PDO $pdo, string $usuario): array
{
    $stmt = $pdo->prepare("
        SELECT c.id, c.nome, c.categoria, c.dia_vencimento AS dia, c.ativa,
               (SELECT l.valor FROM lancamentos l WHERE l.conta_id = c.id ORDER BY l.mes DESC LIMIT 1) AS ultimo_valor,
               (SELECT DATE_FORMAT(l.mes, '%Y-%m') FROM lancamentos l WHERE l.conta_id = c.id ORDER BY l.mes DESC LIMIT 1) AS ultimo_mes
        FROM contas c
        WHERE c.username = ?
        ORDER BY c.ativa DESC, c.dia_vencimento IS NULL, c.dia_vencimento, c.nome
    ");
    $stmt->execute([$usuario]);
    return array_map(fn($c) => [
        'id' => (int)$c['id'],
        'nome' => $c['nome'],
        'categoria' => $c['categoria'],
        'dia' => $c['dia'] === null ? null : (int)$c['dia'],
        'ativa' => (bool)$c['ativa'],
        'ultimoValor' => $c['ultimo_valor'] === null ? null : (float)$c['ultimo_valor'],
        'ultimoMes' => $c['ultimo_mes'],
    ], $stmt->fetchAll());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Formulários de outros sites não conseguem enviar este tipo de conteúdo
    if (!str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        responder_erro('Requisição inválida.', 415);
    }
    $pedido = json_decode(file_get_contents('php://input'), true);
    if (!is_array($pedido)) {
        responder_erro('Requisição inválida.');
    }
}

$acoesGravacao = ['valor', 'salvar', 'reabrir', 'conta_salvar', 'conta_ativar'];
if (in_array($action, $acoesGravacao, true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_erro('Método não permitido.', 405);
}

try {
    $pdo = ml_db('contas_pessoais');

    switch ($action) {
        case 'mes':
            $mes = ler_mes($_GET['mes'] ?? date('Y-m'));
            $stmt = $pdo->prepare('SELECT salvo_em FROM meses_salvos WHERE username = ? AND mes = ?');
            $stmt->execute([$usuario, $mes]);
            $salvoEm = $stmt->fetchColumn();

            // Mês salvo mostra só o que foi lançado; mês aberto mostra também as contas ativas
            $stmt = $pdo->prepare("
                SELECT c.id, c.nome, c.categoria, c.dia_vencimento AS dia, l.valor,
                       (SELECT a.valor FROM lancamentos a WHERE a.conta_id = c.id AND a.mes < ? ORDER BY a.mes DESC LIMIT 1) AS anterior,
                       (SELECT DATE_FORMAT(a.mes, '%Y-%m') FROM lancamentos a WHERE a.conta_id = c.id AND a.mes < ? ORDER BY a.mes DESC LIMIT 1) AS anterior_mes
                FROM contas c
                LEFT JOIN lancamentos l ON l.conta_id = c.id AND l.mes = ?
                WHERE c.username = ?
                  AND (l.id IS NOT NULL OR (c.ativa = 1 AND ? = 0))
                ORDER BY c.dia_vencimento IS NULL, c.dia_vencimento, c.nome
            ");
            $stmt->execute([$mes, $mes, $mes, $usuario, $salvoEm ? 1 : 0]);

            echo json_encode([
                'mes' => substr($mes, 0, 7),
                'hoje' => date('Y-m-d'),
                'salvoEm' => $salvoEm ?: null,
                'contas' => array_map(fn($c) => [
                    'id' => (int)$c['id'],
                    'nome' => $c['nome'],
                    'categoria' => $c['categoria'],
                    'dia' => $c['dia'] === null ? null : (int)$c['dia'],
                    'valor' => $c['valor'] === null ? null : (float)$c['valor'],
                    'anterior' => $c['anterior'] === null ? null : (float)$c['anterior'],
                    'anteriorMes' => $c['anterior_mes'],
                ], $stmt->fetchAll()),
            ]);
            break;

        case 'valor':
            $mes = ler_mes($pedido['mes'] ?? null);
            $contaId = filter_var($pedido['conta_id'] ?? null, FILTER_VALIDATE_INT);
            $stmt = $pdo->prepare('SELECT id FROM contas WHERE id = ? AND username = ?');
            $stmt->execute([$contaId, $usuario]);
            if ($contaId === false || !$stmt->fetchColumn()) {
                responder_erro('Conta não encontrada.', 404);
            }
            if (mes_salvo($pdo, $usuario, $mes)) {
                responder_erro('Este mês já foi salvo. Reabra o mês para alterar.', 409);
            }

            $valor = $pedido['valor'] ?? null;
            if ($valor === null) {
                $pdo->prepare('DELETE FROM lancamentos WHERE conta_id = ? AND mes = ?')->execute([$contaId, $mes]);
            } else {
                if (!is_numeric($valor) || $valor < 0 || $valor > CONTAS_VALOR_MAXIMO) {
                    responder_erro('Valor inválido.');
                }
                $pdo->prepare('
                    INSERT INTO lancamentos (conta_id, mes, valor) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE valor = VALUES(valor)
                ')->execute([$contaId, $mes, round((float)$valor, 2)]);
            }
            echo json_encode(['sucesso' => true]);
            break;

        case 'salvar':
            $mes = ler_mes($pedido['mes'] ?? null);
            $stmt = $pdo->prepare('
                SELECT COUNT(*) FROM lancamentos l JOIN contas c ON c.id = l.conta_id
                WHERE c.username = ? AND l.mes = ?
            ');
            $stmt->execute([$usuario, $mes]);
            if ((int)$stmt->fetchColumn() === 0) {
                responder_erro('Preencha ao menos uma conta antes de salvar o mês.');
            }
            $pdo->prepare('INSERT IGNORE INTO meses_salvos (username, mes) VALUES (?, ?)')->execute([$usuario, $mes]);
            echo json_encode(['sucesso' => true]);
            break;

        case 'reabrir':
            $mes = ler_mes($pedido['mes'] ?? null);
            $pdo->prepare('DELETE FROM meses_salvos WHERE username = ? AND mes = ?')->execute([$usuario, $mes]);
            echo json_encode(['sucesso' => true]);
            break;

        case 'contas':
            echo json_encode(['contas' => lista_contas($pdo, $usuario)]);
            break;

        case 'conta_salvar':
            $nome = trim((string)($pedido['nome'] ?? ''));
            $categoria = trim((string)($pedido['categoria'] ?? ''));
            $dia = $pedido['dia'] ?? null;
            if ($nome === '' || mb_strlen($nome) > 100) {
                responder_erro('Informe um nome de até 100 caracteres.');
            }
            if (mb_strlen($categoria) > 60) {
                responder_erro('A categoria pode ter até 60 caracteres.');
            }
            if ($dia === '' || $dia === null) {
                $dia = null;
            } else {
                $dia = filter_var($dia, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 31]]);
                if ($dia === false) {
                    responder_erro('O dia de vencimento deve ser de 1 a 31.');
                }
            }

            try {
                if (!empty($pedido['id'])) {
                    $stmt = $pdo->prepare('SELECT id FROM contas WHERE id = ? AND username = ?');
                    $stmt->execute([$pedido['id'], $usuario]);
                    if (!$stmt->fetchColumn()) {
                        responder_erro('Conta não encontrada.', 404);
                    }
                    $pdo->prepare('UPDATE contas SET nome = ?, categoria = ?, dia_vencimento = ? WHERE id = ? AND username = ?')
                        ->execute([$nome, $categoria ?: null, $dia, $pedido['id'], $usuario]);
                } else {
                    $pdo->prepare('INSERT INTO contas (username, nome, categoria, dia_vencimento) VALUES (?, ?, ?, ?)')
                        ->execute([$usuario, $nome, $categoria ?: null, $dia]);
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    responder_erro('Já existe uma conta com este nome.', 409);
                }
                throw $e;
            }
            echo json_encode(['sucesso' => true]);
            break;

        case 'conta_ativar':
            $stmt = $pdo->prepare('UPDATE contas SET ativa = ? WHERE id = ? AND username = ?');
            $stmt->execute([empty($pedido['ativa']) ? 0 : 1, $pedido['id'] ?? 0, $usuario]);
            echo json_encode(['sucesso' => true]);
            break;

        case 'insights':
            $stmt = $pdo->prepare('SELECT DATE_FORMAT(mes, "%Y-%m") AS mes FROM meses_salvos WHERE username = ? ORDER BY mes');
            $stmt->execute([$usuario]);
            $meses = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $stmt = $pdo->prepare('
                SELECT DATE_FORMAT(l.mes, "%Y-%m") AS mes, c.id AS conta_id, c.nome, c.categoria, l.valor
                FROM lancamentos l
                JOIN contas c ON c.id = l.conta_id
                JOIN meses_salvos m ON m.username = c.username AND m.mes = l.mes
                WHERE c.username = ?
                ORDER BY l.mes, c.nome
            ');
            $stmt->execute([$usuario]);
            echo json_encode([
                'meses' => $meses,
                'lancamentos' => array_map(fn($l) => [
                    'mes' => $l['mes'],
                    'contaId' => (int)$l['conta_id'],
                    'nome' => $l['nome'],
                    'categoria' => $l['categoria'],
                    'valor' => (float)$l['valor'],
                ], $stmt->fetchAll()),
            ]);
            break;

        default:
            responder_erro('Ação inválida.');
    }
} catch (PDOException $e) {
    error_log('contas pessoais: ' . $e->getMessage());
    responder_erro('Não foi possível concluir a operação. Tente novamente.', 500);
}
