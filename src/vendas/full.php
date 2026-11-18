<?php
// Anúncios no Full e o estoque de cada um no ML. Roda junto com a cópia das vendas (sync.php).
// O que já foi enviado e ainda não foi recebido pelo ML não aparece na API: só conta depois de recebido.

require_once __DIR__ . '/sync.php';

// Vários GETs em paralelo (8 por vez). Devolve [chave => resposta]; o que falhar é refeito sozinho por vendas_get.
function vendas_get_varios(array $caminhos): array
{
    $respostas = [];
    foreach (array_chunk($caminhos, 8, true) as $lote) {
        $multi = curl_multi_init();
        $handles = [];
        foreach ($lote as $chave => $caminho) {
            $ch = curl_init(VENDAS_API . $caminho);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . vendas_token()],
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[$chave] = $ch;
        }
        do {
            curl_multi_exec($multi, $ativos);
            curl_multi_select($multi);
        } while ($ativos);
        foreach ($handles as $chave => $ch) {
            $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $corpo = curl_multi_getcontent($ch);
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
            $respostas[$chave] = $codigo === 200 ? json_decode($corpo, true) : vendas_get($lote[$chave]);
        }
        curl_multi_close($multi);
    }
    return $respostas;
}

function vendas_full_sku(array $atributos, ?string $campoLivre): string
{
    foreach ($atributos as $a) {
        if (($a['id'] ?? '') === 'SELLER_SKU' && !empty($a['value_name'])) {
            return $a['value_name'];
        }
    }
    return (string) ($campoLivre ?? '');
}

// Anúncios diferentes (ex.: Clássico e Premium) podem dividir o mesmo estoque: junta pelo inventory_id.
// Fica ativo se algum anúncio estiver ativo; o título e o SKU vêm do primeiro ativo.
function vendas_full_juntar(array &$linhas, string $inv, array $linha): void
{
    $linha['anuncios'] = [['id' => $linha['anuncio'], 'variacao' => $linha['variacao']]];
    if (!isset($linhas[$inv])) {
        $linhas[$inv] = $linha;
        return;
    }
    $atual = &$linhas[$inv];
    $atual['anuncios'][] = $linha['anuncios'][0];
    if ($atual['status'] !== 'active' && $linha['status'] === 'active') {
        $anuncios = $atual['anuncios'];
        $atual = $linha;
        $atual['anuncios'] = $anuncios;
    }
}

function vendas_sincronizar_full(PDO $pdo, string $vendedor): array
{
    $inicio = microtime(true);

    // Todos os anúncios Full (ativos e pausados)
    $ids = [];
    $offset = 0;
    do {
        $r = vendas_get("/users/$vendedor/items/search", ['logistic_type' => 'fulfillment', 'limit' => 50, 'offset' => $offset]);
        $ids = array_merge($ids, $r['results'] ?? []);
        $offset += 50;
    } while ($offset < ($r['paging']['total'] ?? 0));

    // Detalhes de cada anúncio (o /items?ids= em lote foi descontinuado pelo ML); cada variação tem o próprio estoque
    $linhas = [];
    $itens = vendas_get_varios(array_combine($ids, array_map(fn($id) => "/items/$id", $ids)));
    foreach ($itens as $it) {
        if (empty($it['id'])) {
            continue;
        }
        $base = [
            'anuncio' => $it['id'], 'titulo' => mb_substr((string) $it['title'], 0, 255),
            'status' => $it['status'], 'sub_status' => implode(',', $it['sub_status'] ?? []) ?: null,
        ];
        if (!empty($it['variations'])) {
            foreach ($it['variations'] as $v) {
                if (empty($v['inventory_id'])) {
                    continue;
                }
                $nomes = implode(' / ', array_map(fn($c) => $c['value_name'] ?? '', $v['attribute_combinations'] ?? []));
                vendas_full_juntar($linhas, $v['inventory_id'], $base + [
                    'variacao' => $v['id'],
                    'sku' => vendas_full_sku($v['attributes'] ?? [], $v['seller_custom_field'] ?? null) ?: vendas_full_sku($it['attributes'] ?? [], $it['seller_custom_field'] ?? null),
                    'titulo' => mb_substr($it['title'] . ($nomes ? " ($nomes)" : ''), 0, 255),
                ]);
            }
        } elseif (!empty($it['inventory_id'])) {
            vendas_full_juntar($linhas, $it['inventory_id'], $base + ['variacao' => null, 'sku' => vendas_full_sku($it['attributes'] ?? [], $it['seller_custom_field'] ?? null)]);
        }
    }

    $estoques = vendas_get_varios(array_combine(array_keys($linhas), array_map(fn($inv) => "/inventories/$inv/stock/fulfillment", array_keys($linhas))));

    $grava = $pdo->prepare('INSERT INTO full_anuncios (inventory_id, anuncio, variacao, anuncios, sku, titulo, status, sub_status, disponivel, indisponivel, indisponivel_detalhe)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE anuncio = VALUES(anuncio), variacao = VALUES(variacao), anuncios = VALUES(anuncios), sku = VALUES(sku), titulo = VALUES(titulo),
            status = VALUES(status), sub_status = VALUES(sub_status), disponivel = VALUES(disponivel),
            indisponivel = VALUES(indisponivel), indisponivel_detalhe = VALUES(indisponivel_detalhe)');
    $foto = $pdo->prepare('INSERT INTO full_estoque_dia (dia, inventory_id, disponivel, indisponivel) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE disponivel = VALUES(disponivel), indisponivel = VALUES(indisponivel)');
    $hoje = (new DateTime('now', new DateTimeZone(VENDAS_FUSO)))->format('Y-m-d');

    $pdo->beginTransaction();
    foreach ($linhas as $inv => $l) {
        $e = $estoques[$inv] ?? [];
        $disponivel = (int) ($e['available_quantity'] ?? 0);
        $indisponivel = (int) ($e['not_available_quantity'] ?? 0);
        $grava->execute([
            $inv, $l['anuncio'], $l['variacao'], json_encode($l['anuncios']), $l['sku'], $l['titulo'], $l['status'], $l['sub_status'],
            $disponivel, $indisponivel, json_encode($e['not_available_detail'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);
        $foto->execute([$hoje, $inv, $disponivel, $indisponivel]);
    }
    // Anúncio que saiu do Full deixa de aparecer na aba (a foto diária fica)
    if ($linhas) {
        $marcas = implode(',', array_fill(0, count($linhas), '?'));
        $pdo->prepare("DELETE FROM full_anuncios WHERE inventory_id NOT IN ($marcas)")->execute(array_keys($linhas));
    }
    $pdo->commit();

    return ['anuncios' => count($linhas), 'segundos' => round(microtime(true) - $inicio, 1)];
}
