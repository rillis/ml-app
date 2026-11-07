<?php
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    ob_start(); // Nada dos includes pode vazar antes do JSON
    $pdo = ml_db('ml_jrf');
    include __DIR__ . '/../../src/dash/posts.php';
    include __DIR__ . '/../../src/dash/boletos.php';
    include __DIR__ . '/../../src/dash/logs.php';
    require __DIR__ . '/../../src/dash/parciais.php';
    $resposta = [
        'ultimoEvento' => $lastEventText,
        'contagens' => $contagemEventos,
        'eventos' => dash_html_eventos($posts),
        'boletos' => dash_html_boletos($boletos),
        'logs' => getLogsContent(),
    ];
    ob_end_clean();
    // Logs podem ter bytes inválidos em UTF-8, o que faria json_encode falhar
    echo json_encode($resposta, JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log('ml-app dash: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível atualizar o painel.']);
}
