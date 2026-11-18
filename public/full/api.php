<?php
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

header('Content-Type: application/json');

// O botão Atualizar desta aba usa o mesmo de Vendas (/vendas/api.php?action=atualizar): pedidos e estoque Full juntos.
if (($_GET['action'] ?? '') !== 'dados') {
    http_response_code(400);
    echo json_encode(['error' => 'Ação inválida']);
    exit;
}

$pdo = ml_db('ml_vendas');

$anuncios = $pdo->query('SELECT inventory_id, anuncio, sku, titulo, status, sub_status, disponivel, indisponivel, anuncios
    FROM full_anuncios ORDER BY sku')->fetchAll();

// Unidades vendidas pelo Full por estoque e dia, nos últimos 90 dias (canceladas fora).
// Um estoque pode atender mais de um anúncio: soma as vendas de todos.
$vendas = $pdo->query("SELECT f.inventory_id, DATE(p.data_venda) AS dia, SUM(i.qtd) AS unidades
    FROM full_anuncios f
    JOIN JSON_TABLE(f.anuncios, '$[*]' COLUMNS (id VARCHAR(30) COLLATE utf8mb4_unicode_ci PATH '$.id', variacao BIGINT PATH '$.variacao')) a
    JOIN itens i ON i.anuncio = a.id AND (a.variacao IS NULL OR i.variacao = a.variacao)
    JOIN pedidos p ON p.id = i.pedido_id
    WHERE NOT p.cancelada AND p.logistica = 'full' AND p.data_venda >= CURDATE() - INTERVAL 90 DAY
    GROUP BY f.inventory_id, dia ORDER BY dia")->fetchAll();

// Estoque local (módulo Estoque), para saber se dá para mandar o que for sugerido
$local = $pdo->query('SELECT sku, qtd FROM ml_estoque.estoque')->fetchAll(PDO::FETCH_KEY_PAIR);

$ultimaOk = $pdo->query('SELECT MAX(fim) FROM sincronizacoes WHERE erro IS NULL')->fetchColumn() ?: null;
$estoqueEm = $pdo->query('SELECT MAX(atualizado_em) FROM full_anuncios')->fetchColumn() ?: null;

echo json_encode([
    'anuncios' => array_map(fn($a) => [
        'inventory' => $a['inventory_id'],
        'anuncio' => $a['anuncio'],
        'anuncios' => array_column(json_decode($a['anuncios'], true) ?: [], 'id'),
        'sku' => $a['sku'],
        'titulo' => $a['titulo'],
        'ativo' => $a['status'] === 'active',
        'subStatus' => $a['sub_status'],
        'disponivel' => (int) $a['disponivel'],
        'indisponivel' => (int) $a['indisponivel'],
    ], $anuncios),
    'vendas' => array_map(fn($v) => [$v['inventory_id'], $v['dia'], (int) $v['unidades']], $vendas),
    'local' => array_map('intval', array_change_key_case($local, CASE_UPPER)),
    'sincronizacao' => ['ultima_ok' => $ultimaOk, 'estoque_em' => $estoqueEm],
], JSON_UNESCAPED_UNICODE);
