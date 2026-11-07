<?php
require __DIR__ . '/../../../src/auth.php';
require __DIR__ . '/../../../src/db.php';
require __DIR__ . '/../../../src/dash/fila.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$action = $_GET['action'] ?? 'listar';
$usuario = $_SESSION['user'] ?? 'desconhecido';

try {
    ob_start(); // Nada pode vazar antes do JSON
    $pdo = ml_db('ml_jrf');

    if ($action === 'listar') {
        $resposta = fila_dados($pdo);
    } else {
        // Ações gravam: só POST com JSON (o cookie de sessão é SameSite=Lax, então outro site não consegue)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            http_response_code(405);
            throw new InvalidArgumentException('Use POST com JSON.');
        }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $id = isset($input['id']) ? (int)$input['id'] : null;

        if ($action === 'reprocessar') {
            $todas = !empty($input['todas']);
            if (!$todas && !$id) {
                throw new InvalidArgumentException('Informe o id.');
            }
            $n = fila_reprocessar($pdo, $todas ? null : $id);
            error_log("ml-app fila: $usuario reprocessou " . ($todas ? 'todas as falhas' : "#$id") . " ($n)");
            $resposta = ['ok' => true, 'alterados' => $n];
        } elseif ($action === 'descartar') {
            if (!$id) {
                throw new InvalidArgumentException('Informe o id.');
            }
            $n = fila_descartar($pdo, $id, $usuario);
            error_log("ml-app fila: $usuario descartou #$id ($n)");
            $resposta = ['ok' => true, 'alterados' => $n];
        } else {
            http_response_code(400);
            throw new InvalidArgumentException('Ação desconhecida.');
        }
    }
    ob_end_clean();
    echo json_encode($resposta, JSON_INVALID_UTF8_SUBSTITUTE);
} catch (InvalidArgumentException $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    echo json_encode(['erro' => $e->getMessage()]);
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log('ml-app fila: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível consultar a fila.']);
}
