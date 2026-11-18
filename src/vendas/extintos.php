<?php
// Anúncios que já venderam e não existem mais no ML (encerrados ou apagados), com os anúncios de hoje do mesmo SKU.
// A busca de anúncios do vendedor só devolve os que existem: o que vendeu e não está nela foi encerrado.
// Roda uma vez por dia, junto com a cópia das vendas (sync.php); --extintos força.
// Sozinho pela linha de comando (botão da seção na aba Vendas): docker exec ml-app php /var/www/html/src/vendas/extintos.php

require_once __DIR__ . '/full.php';

function vendas_sincronizar_extintos(PDO $pdo, string $vendedor, bool $forcar = false): array
{
    $ultima = $pdo->query('SELECT MAX(verificado) FROM anuncios_extintos')->fetchColumn();
    if (!$forcar && $ultima && substr($ultima, 0, 10) === date('Y-m-d')) {
        return ['pulado' => 'já verificado hoje'];
    }
    $inicio = microtime(true);

    // Todos os anúncios que existem hoje (scan: sem o limite de 1000 do offset)
    $existem = [];
    $parametros = ['search_type' => 'scan', 'limit' => 100];
    do {
        $r = vendas_get("/users/$vendedor/items/search", $parametros);
        foreach ($r['results'] ?? [] as $id) {
            $existem[$id] = true;
        }
        $parametros['scroll_id'] = $r['scroll_id'] ?? null;
    } while (!empty($r['results']) && $parametros['scroll_id']);
    if (!$existem) {
        throw new RuntimeException('A busca de anúncios do ML voltou vazia');
    }

    $vendidos = $pdo->query('SELECT DISTINCT anuncio, sku FROM itens')->fetchAll();
    $extintos = array_values(array_filter($vendidos, fn($v) => !isset($existem[$v['anuncio']])));

    // Anúncios de hoje com o mesmo SKU (o seller_sku acha também o SKU das variações)
    $skus = array_values(array_unique(array_filter(array_column($extintos, 'sku'), 'strlen')));
    $buscas = vendas_get_varios(array_combine($skus, array_map(
        fn($sku) => "/users/$vendedor/items/search?" . http_build_query(['seller_sku' => $sku]), $skus)));
    $ids = array_values(array_unique(array_merge(...array_map(fn($b) => $b['results'] ?? [], array_values($buscas ?: [[]])))));
    $itens = $ids ? vendas_get_varios(array_combine($ids, array_map(fn($id) => "/items/$id", $ids))) : [];

    // Como estava antes, para contar o que mudou
    $antes = [];
    foreach ($pdo->query('SELECT anuncio, sku, equivalentes FROM anuncios_extintos') as $a) {
        $antes[$a['anuncio'] . '|' . $a['sku']] = json_decode($a['equivalentes'], true);
    }
    $novos = $alterados = 0;

    $grava = $pdo->prepare('INSERT INTO anuncios_extintos (anuncio, sku, equivalentes) VALUES (?, ?, ?)');
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM anuncios_extintos');
    foreach ($extintos as $e) {
        $equivalentes = [];
        foreach ($e['sku'] !== '' ? ($buscas[$e['sku']]['results'] ?? []) : [] as $id) {
            $it = $itens[$id] ?? null;
            if (!$it) {
                continue;
            }
            $equivalentes[] = [
                'id' => $id,
                'tipo' => $it['listing_type_id'] ?? null,
                'full' => ($it['shipping']['logistic_type'] ?? '') === 'fulfillment',
                'status' => $it['status'] ?? null,
                'titulo' => mb_substr((string) ($it['title'] ?? ''), 0, 255),
            ];
        }
        $chave = $e['anuncio'] . '|' . $e['sku'];
        if (!array_key_exists($chave, $antes)) {
            $novos++;
        } elseif (vendas_extintos_resumo($antes[$chave]) !== vendas_extintos_resumo($equivalentes)) {
            $alterados++;
        }
        unset($antes[$chave]);
        $grava->execute([$e['anuncio'], $e['sku'], json_encode($equivalentes, JSON_UNESCAPED_UNICODE)]);
    }
    $pdo->commit();

    return ['extintos' => count($extintos), 'novos' => $novos, 'alterados' => $alterados, 'voltaram' => count($antes), 'existem' => count($existem), 'segundos' => round(microtime(true) - $inicio, 1)];
}

// O que importa na comparação: quais anúncios, tipo, Full e status (o título pode mudar sem importar)
function vendas_extintos_resumo(array $equivalentes): array
{
    $r = array_map(fn($e) => [$e['id'], $e['tipo'], (bool) $e['full'], $e['status']], $equivalentes);
    sort($r);
    return $r;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['argv'][0] ?? '') === __FILE__) {
    try {
        $pdo = ml_db('ml_vendas');
        echo json_encode(vendas_sincronizar_extintos($pdo, (string) vendas_get('/users/me')['id'], true), JSON_UNESCAPED_UNICODE), "\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
        exit(1);
    }
}
