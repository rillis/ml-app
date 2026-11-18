<?php
// Copia os pedidos do Mercado Livre para o banco ml_vendas.
// Banco vazio: carga a partir de VENDAS_INICIO. Depois: só o que mudou no ML desde a última cópia
// (order.date_last_updated), o que traz as vendas novas e as antigas que foram canceladas ou mudaram.
// Pela linha de comando: docker exec ml-app php /var/www/html/src/vendas/sync.php [--carga]

require_once __DIR__ . '/../db.php';

const VENDAS_API = 'https://api.mercadolibre.com';
const VENDAS_PING = 'http://192.168.15.11:52003/token/ping';
const VENDAS_POR_PAGINA = 51; // máximo aceito pelo /orders/search
const VENDAS_FOLGA_MINUTOS = 60; // reler um pouco antes da última atualização guardada não custa nada (upsert)
const VENDAS_FUSO = 'America/Sao_Paulo';

function vendas_token(): string
{
    $arquivo = getenv('ML_TOKENS_PATH') ?: '/ml-jrf/tokens.json';
    $dados = json_decode((string) @file_get_contents($arquivo), true);
    if (empty($dados['token'])) {
        throw new RuntimeException('Não foi possível ler o token do ML em ' . $arquivo);
    }
    return $dados['token'];
}

function vendas_http(string $url, array $cabecalhos = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $cabecalhos,
    ]);
    $corpo = curl_exec($ch);
    $codigo = curl_errno($ch) ? 0 : curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);
    return [$codigo, $corpo === false ? $erro : $corpo];
}

// O ml-jrf é quem renova o token. Em 401 pede para ele conferir e relê o tokens.json.
function vendas_ping_token(): void
{
    [$codigo, $corpo] = vendas_http(VENDAS_PING);
    if ($codigo === 200) {
        return;
    }
    if ($codigo === 503) {
        throw new RuntimeException('Token do ML recusado. Reautorize com: docker exec ml-jrf python -m scripts.authorize');
    }
    throw new RuntimeException('Sem resposta do ML para validar o token. Tente de novo mais tarde.');
}

function vendas_get(string $caminho, array $parametros = []): array
{
    $url = VENDAS_API . $caminho . ($parametros ? '?' . http_build_query($parametros) : '');
    $pingFeito = false;
    for ($tentativa = 1; ; $tentativa++) {
        [$codigo, $corpo] = vendas_http($url, ['Authorization: Bearer ' . vendas_token()]);
        if ($codigo === 200) {
            return json_decode($corpo, true);
        }
        if ($codigo === 401 && !$pingFeito) {
            vendas_ping_token();
            $pingFeito = true;
            continue;
        }
        $temporario = $codigo === 0 || $codigo === 429 || $codigo >= 500;
        if ($temporario && $tentativa < 4) {
            sleep($tentativa * 2);
            continue;
        }
        $mensagem = json_decode((string) $corpo, true)['message'] ?? substr((string) $corpo, 0, 200);
        throw new RuntimeException("ML respondeu $codigo em $caminho: $mensagem");
    }
}

// Pedidos antigos (antes de 2026-07) vêm sem stock.node_id: aí o Full sai do envio.
// Busca vários envios em paralelo e devolve [envio_id => logistic.type].
function vendas_logisticas(array $envios): array
{
    $tipos = [];
    foreach (array_chunk(array_values(array_unique($envios)), 8) as $lote) {
        $multi = curl_multi_init();
        $handles = [];
        foreach ($lote as $id) {
            $ch = curl_init(VENDAS_API . '/shipments/' . $id);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . vendas_token(), 'x-format-new: true'],
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[$id] = $ch;
        }
        do {
            curl_multi_exec($multi, $ativos);
            curl_multi_select($multi);
        } while ($ativos);
        foreach ($handles as $id => $ch) {
            $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $corpo = curl_multi_getcontent($ch);
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
            // Falhou em paralelo (401, 429...): tenta sozinho, com ping e novas tentativas
            $envio = $codigo === 200 ? json_decode($corpo, true) : vendas_get('/shipments/' . $id);
            $tipos[$id] = $envio['logistic']['type'] ?? $envio['logistic_type'] ?? null;
        }
        curl_multi_close($multi);
    }
    return $tipos;
}

function vendas_data_local(string $iso): string
{
    return (new DateTime($iso))->setTimezone(new DateTimeZone(VENDAS_FUSO))->format('Y-m-d H:i:s');
}

function vendas_data_utc(string $iso): string
{
    return (new DateTime($iso))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function vendas_gravar_pedido(PDO $pdo, array $p, string $vendedor, ?string $logisticaEnvio): void
{
    $itens = [];
    foreach ($p['order_items'] ?? [] as $i => $it) {
        $node = $it['stock']['node_id'] ?? null;
        $itens[] = [
            $p['id'], $i + 1,
            (string) ($it['item']['seller_sku'] ?? ''),
            $it['item']['id'],
            $it['item']['variation_id'] ?? null,
            mb_substr((string) ($it['item']['title'] ?? ''), 0, 255),
            (int) $it['quantity'],
            $it['unit_price'],
            $it['sale_fee'] ?? null,
            $it['listing_type_id'] ?? null,
            $node,
            $node !== null && !str_contains($node, $vendedor) ? 1 : 0,
        ];
    }
    $semNode = !array_filter(array_column($itens, 10));
    $full = $semNode ? $logisticaEnvio === 'fulfillment' : in_array(1, array_column($itens, 11), true);

    $pdo->prepare('INSERT INTO pedidos (id, pack_id, data_venda, status, total, comprador_id, logistica, ml_atualizado, bruto)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE pack_id = VALUES(pack_id), data_venda = VALUES(data_venda), status = VALUES(status),
            total = VALUES(total), comprador_id = VALUES(comprador_id), logistica = VALUES(logistica),
            ml_atualizado = VALUES(ml_atualizado), bruto = VALUES(bruto)')
        ->execute([
            $p['id'],
            $p['pack_id'] ?? null,
            vendas_data_local($p['date_created']),
            $p['status'],
            $p['total_amount'] ?? 0,
            $p['buyer']['id'] ?? null,
            $full ? 'full' : 'normal',
            vendas_data_utc($p['date_last_updated'] ?? $p['last_updated'] ?? $p['date_created']),
            json_encode($p, JSON_UNESCAPED_UNICODE),
        ]);

    $pdo->prepare('DELETE FROM itens WHERE pedido_id = ?')->execute([$p['id']]);
    $insere = $pdo->prepare('INSERT INTO itens (pedido_id, seq, sku, anuncio, variacao, titulo, qtd, preco, comissao, tipo_anuncio, node_id, do_full)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($itens as $item) {
        $insere->execute($item);
    }
}

// Logística dos pedidos da página que não têm stock.node_id. Quem já está no banco
// reaproveita o que foi gravado (Full guardado como 'fulfillment'), sem consultar o envio de novo.
function vendas_envios_sem_node(PDO $pdo, array $pedidos, bool $reaproveitar): array
{
    $alvo = [];
    foreach ($pedidos as $p) {
        $temNode = array_filter(array_map(fn($it) => $it['stock']['node_id'] ?? null, $p['order_items'] ?? []));
        if (!$temNode && !empty($p['shipping']['id'])) {
            $alvo[$p['id']] = $p['shipping']['id'];
        }
    }
    if (!$alvo || !$reaproveitar) {
        return vendas_logisticas($alvo);
    }
    $marcas = implode(',', array_fill(0, count($alvo), '?'));
    $stmt = $pdo->prepare("SELECT id, logistica FROM pedidos WHERE id IN ($marcas)");
    $stmt->execute(array_keys($alvo));
    $tipos = [];
    foreach ($stmt->fetchAll() as $linha) {
        $tipos[$alvo[$linha['id']]] = $linha['logistica'] === 'full' ? 'fulfillment' : 'normal';
        unset($alvo[$linha['id']]);
    }
    return $tipos + vendas_logisticas($alvo);
}

// $carga refaz tudo desde VENDAS_INICIO mesmo com o banco cheio (por cima, sem apagar nada).
function vendas_sincronizar(PDO $pdo, bool $carga = false): array
{
    @set_time_limit(600);
    if (!(int) $pdo->query("SELECT GET_LOCK('ml_vendas_sync', 0)")->fetchColumn()) {
        throw new RuntimeException('Já existe uma atualização em andamento.');
    }

    $inicio = microtime(true);
    $ultima = $pdo->query('SELECT MAX(ml_atualizado) FROM pedidos')->fetchColumn();
    if ($ultima && !$carga) {
        $modo = 'atualizacao';
        $desde = (new DateTime($ultima . ' UTC'))->modify('-' . VENDAS_FOLGA_MINUTOS . ' minutes')->format('Y-m-d\TH:i:s.000-00:00');
        $filtro = ['order.date_last_updated.from' => $desde];
    } else {
        $modo = 'carga';
        $desde = getenv('VENDAS_INICIO') ?: '2025-12-01T00:00:00.000-03:00';
        $filtro = ['order.date_created.from' => $desde];
    }
    $pdo->prepare('INSERT INTO sincronizacoes (modo, desde) VALUES (?, ?)')->execute([$modo, $desde]);
    $sincId = (int) $pdo->lastInsertId();

    $gravados = 0;
    try {
        $vendedor = (string) vendas_get('/users/me')['id'];
        $offset = 0;
        do {
            // Ordem crescente: vendas que chegam durante a cópia entram no fim e não deslocam as páginas
            $r = vendas_get('/orders/search', $filtro + [
                'seller' => $vendedor, 'sort' => 'date_asc', 'limit' => VENDAS_POR_PAGINA, 'offset' => $offset,
            ]);
            $envios = vendas_envios_sem_node($pdo, $r['results'], $modo === 'atualizacao');
            $pdo->beginTransaction();
            foreach ($r['results'] as $pedido) {
                $envio = $pedido['shipping']['id'] ?? null;
                vendas_gravar_pedido($pdo, $pedido, $vendedor, $envio ? ($envios[$envio] ?? null) : null);
                $gravados++;
            }
            $pdo->commit();
            $offset += VENDAS_POR_PAGINA;
        } while ($offset < ($r['paging']['total'] ?? 0));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->prepare('UPDATE sincronizacoes SET fim = NOW(), pedidos = ?, erro = ? WHERE id = ?')
            ->execute([$gravados, mb_substr($e->getMessage(), 0, 500), $sincId]);
        $pdo->query("DO RELEASE_LOCK('ml_vendas_sync')");
        throw $e;
    }

    $pdo->prepare('UPDATE sincronizacoes SET fim = NOW(), pedidos = ? WHERE id = ?')->execute([$gravados, $sincId]);
    $pdo->query("DO RELEASE_LOCK('ml_vendas_sync')");
    return ['modo' => $modo, 'desde' => $desde, 'pedidos' => $gravados, 'segundos' => round(microtime(true) - $inicio, 1)];
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['argv'][0] ?? '') === __FILE__) {
    try {
        echo json_encode(vendas_sincronizar(ml_db('ml_vendas'), in_array('--carga', $_SERVER['argv'], true)), JSON_UNESCAPED_UNICODE), "\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
        exit(1);
    }
}
