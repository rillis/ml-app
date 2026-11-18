<?php
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? '';

function responder(array $dados, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ===== ATUALIZAR: copia do ML o que mudou desde a última vez ===== */
if ($action === 'atualizar') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['error' => 'Use POST'], 405);
    }
    // O tokens.json do ml-jrf só é legível pelo dono; o www-data roda a cópia como root no próprio container.
    exec('docker exec ml-app php /var/www/html/src/vendas/sync.php 2>&1', $saida, $codigo);
    $texto = trim(implode("\n", $saida));
    $resultado = json_decode($texto, true);
    if ($codigo !== 0 || !is_array($resultado)) {
        responder(['error' => preg_replace('/^Erro: /', '', $texto) ?: 'Falha ao atualizar'], 500);
    }
    responder($resultado);
}

/* ===== DADOS: agregados por dia para as telas (canceladas ficam de fora) ===== */
if ($action === 'dados') {
    $pdo = ml_db('ml_vendas');

    $dias = $pdo->query("SELECT DATE(data_venda) AS dia, logistica,
            COUNT(DISTINCT COALESCE(pack_id, id)) AS vendas, SUM(total) AS faturamento
        FROM pedidos WHERE NOT cancelada GROUP BY dia, logistica ORDER BY dia")->fetchAll();

    $skus = $pdo->query("SELECT DATE(p.data_venda) AS dia, i.sku, p.logistica, i.tipo_anuncio,
            SUM(i.qtd) AS unidades, SUM(i.qtd * i.preco) AS faturamento
        FROM itens i JOIN pedidos p ON p.id = i.pedido_id
        WHERE NOT p.cancelada GROUP BY dia, i.sku, p.logistica, i.tipo_anuncio ORDER BY dia")->fetchAll();

    // Título do anúncio mais recente de cada SKU
    $titulos = $pdo->query("SELECT i.sku, i.titulo FROM itens i JOIN pedidos p ON p.id = i.pedido_id
        JOIN (SELECT i2.sku, MAX(p2.data_venda) AS ultima FROM itens i2 JOIN pedidos p2 ON p2.id = i2.pedido_id GROUP BY i2.sku) u
          ON u.sku = i.sku AND u.ultima = p.data_venda")->fetchAll(PDO::FETCH_KEY_PAIR);

    $sinc = $pdo->query("SELECT fim, modo, pedidos, erro FROM sincronizacoes WHERE fim IS NOT NULL ORDER BY id DESC LIMIT 1")->fetch() ?: null;
    $ultimaOk = $pdo->query("SELECT MAX(fim) FROM sincronizacoes WHERE erro IS NULL")->fetchColumn() ?: null;

    responder([
        'dias' => array_map(fn($d) => [$d['dia'], $d['logistica'] === 'full' ? 1 : 0, (int) $d['vendas'], (float) $d['faturamento']], $dias),
        'skus' => array_map(fn($s) => [$s['dia'], $s['sku'], $s['logistica'] === 'full' ? 1 : 0, $s['tipo_anuncio'] === 'gold_pro' ? 1 : 0, (int) $s['unidades'], (float) $s['faturamento']], $skus),
        'titulos' => $titulos,
        'sincronizacao' => ['ultima_ok' => $ultimaOk, 'ultima' => $sinc],
    ]);
}

responder(['error' => 'Ação inválida'], 400);
