<?php
// API da Dash. Rotas (?r=):
//   banco       GET   indicadores, eventos, boletos, saúde e resumo da fila (a tela pede a cada 30 s)
//   ml          GET   perguntas, reclamações e etiquetas (a cada 2 min, com cache; &forcar=1 ignora o cache)
//   responder   POST  responde uma pergunta no ML {pergunta, texto}
//   fila        GET   fila do webhook completa (modal)
//   reprocessar POST  {id} ou {todas: true}
//   descartar   POST  {id}
//   logs        GET   docker logs do ml-jrf (modal)
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/dash/banco.php';
require __DIR__ . '/../../src/dash/fila.php';
session_write_close(); // as consultas ao ML demoram; não segura as outras abas

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const DASH_ESCRITA = ['responder', 'reprocessar', 'descartar'];

// Blocos do ML rodam pela linha de comando como root (o Apache não lê o tokens.json do ml-jrf)
function dash_ml_cli(array $args): array
{
    $cmd = 'docker exec ml-app php /var/www/html/src/dash/ml.php '
        . implode(' ', array_map('escapeshellarg', array_filter($args, 'strlen')));
    exec($cmd . ' 2>/dev/null', $saida, $codigo);
    $js = json_decode((string) end($saida), true);
    if (!is_array($js)) {
        throw new RuntimeException('Falha ao consultar o ML (código ' . $codigo . ')');
    }
    return $js;
}

$rota = $_GET['r'] ?? '';
$usuario = $_SESSION['user'] ?? 'desconhecido';

try {
    ob_start(); // Nada pode vazar antes do JSON
    $entrada = [];
    if (in_array($rota, DASH_ESCRITA, true)) {
        // Ações gravam: só POST com JSON (o cookie de sessão é SameSite=Lax, então outro site não consegue)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            http_response_code(405);
            throw new InvalidArgumentException('Use POST com JSON.');
        }
        $entrada = json_decode(file_get_contents('php://input'), true) ?: [];
    }

    switch ($rota) {
        case 'banco':
            $pdo = ml_db('ml_jrf');
            $resposta = [
                'kpis'     => dash_kpis($pdo),
                'eventos'  => dash_eventos($pdo),
                'boletos'  => dash_boletos($pdo),
                'saude'    => fila_saude(),
                'fila'     => fila_resumo($pdo),
                'sinal'    => dash_sinal($pdo),
            ];
            break;

        case 'ml':
            $resposta = dash_ml_cli(['dados', ($_GET['forcar'] ?? '') === '1' ? '--forcar' : '']);
            break;

        case 'responder':
            $pergunta = (int) ($entrada['pergunta'] ?? 0);
            $texto = trim((string) ($entrada['texto'] ?? ''));
            if (!$pergunta || $texto === '' || mb_strlen($texto) > 2000) {
                throw new InvalidArgumentException('Escreva uma resposta de até 2000 caracteres.');
            }
            $r = dash_ml_cli(['responder', (string) $pergunta, $texto]);
            if (isset($r['erro'])) {
                throw new RuntimeException($r['erro']);
            }
            error_log("ml-app dash: $usuario respondeu a pergunta $pergunta");
            $resposta = ['ok' => true];
            break;

        case 'fila':
            $resposta = fila_dados(ml_db('ml_jrf'));
            break;

        case 'reprocessar':
            $todas = !empty($entrada['todas']);
            $id = isset($entrada['id']) ? (int) $entrada['id'] : null;
            if (!$todas && !$id) {
                throw new InvalidArgumentException('Informe o id.');
            }
            $n = fila_reprocessar(ml_db('ml_jrf'), $todas ? null : $id);
            error_log("ml-app fila: $usuario reprocessou " . ($todas ? 'todas as falhas' : "#$id") . " ($n)");
            $resposta = ['ok' => true, 'alterados' => $n];
            break;

        case 'descartar':
            $id = (int) ($entrada['id'] ?? 0);
            if (!$id) {
                throw new InvalidArgumentException('Informe o id.');
            }
            $n = fila_descartar(ml_db('ml_jrf'), $id, $usuario);
            error_log("ml-app fila: $usuario descartou #$id ($n)");
            $resposta = ['ok' => true, 'alterados' => $n];
            break;

        case 'logs':
            require __DIR__ . '/../../src/dash/logs.php';
            $resposta = ['logs' => dash_logs()];
            break;

        default:
            http_response_code(404);
            throw new InvalidArgumentException('Rota desconhecida.');
    }
    ob_end_clean();
    // Logs podem ter bytes inválidos em UTF-8, o que faria json_encode falhar
    echo json_encode($resposta, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
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
    error_log("ml-app dash ($rota): " . $e->getMessage());
    http_response_code($rota === 'responder' ? 502 : 500);
    echo json_encode(['erro' => $rota === 'responder' ? $e->getMessage() : 'Não foi possível atualizar o painel.']);
}
