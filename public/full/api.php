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

$estoques = $pdo->query('SELECT inventory_id, disponivel, indisponivel, anuncios FROM full_anuncios')->fetchAll();

// Uma linha por anúncio (e variação). Anúncios que dividem o mesmo estoque levam a lista dos outros.
$anuncios = [];
foreach ($estoques as $e) {
    $lista = json_decode($e['anuncios'], true) ?: [];
    foreach ($lista as $a) {
        $anuncios[] = [
            'inventory' => $e['inventory_id'],
            'anuncio' => $a['id'],
            'variacao' => $a['variacao'],
            'sku' => $a['sku'] ?? '',
            'titulo' => $a['titulo'] ?? '',
            'ativo' => ($a['status'] ?? '') === 'active',
            'subStatus' => $a['sub_status'] ?? null,
            'disponivel' => (int) $e['disponivel'],
            'indisponivel' => (int) $e['indisponivel'],
            'compartilha' => array_values(array_filter(array_column($lista, 'id'), fn($id) => $id !== $a['id'])),
        ];
    }
}

// Unidades vendidas pelo Full por anúncio, variação e dia, nos últimos 90 dias (canceladas fora)
$vendas = $pdo->query("SELECT i.anuncio, i.variacao, DATE(p.data_venda) AS dia, SUM(i.qtd) AS unidades
    FROM itens i JOIN pedidos p ON p.id = i.pedido_id
    WHERE NOT p.cancelada AND p.logistica = 'full' AND p.data_venda >= CURDATE() - INTERVAL 90 DAY
    GROUP BY i.anuncio, i.variacao, dia ORDER BY dia")->fetchAll();

// Estoque local (módulo Estoque), para saber se dá para mandar o que for sugerido
$local = $pdo->query('SELECT sku, qtd FROM ml_estoque.estoque')->fetchAll(PDO::FETCH_KEY_PAIR);

$ultimaOk = $pdo->query('SELECT MAX(fim) FROM sincronizacoes WHERE erro IS NULL')->fetchColumn() ?: null;
$estoqueEm = $pdo->query('SELECT MAX(atualizado_em) FROM full_anuncios')->fetchColumn() ?: null;

echo json_encode([
    'anuncios' => $anuncios,
    'vendas' => array_map(fn($v) => [$v['anuncio'], $v['variacao'] === null ? null : (int) $v['variacao'], $v['dia'], (int) $v['unidades']], $vendas),
    'local' => array_map('intval', array_change_key_case($local, CASE_UPPER)),
    'sincronizacao' => ['ultima_ok' => $ultimaOk, 'estoque_em' => $estoqueEm],
], JSON_UNESCAPED_UNICODE);
