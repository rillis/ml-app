<?php
// Blocos da Dash que consultam o Mercado Livre: perguntas sem resposta, reclamações abertas
// e o resumo da aba Etiquetas. O resultado fica em cache no volume para várias abas abertas
// não multiplicarem as chamadas. Token: o do ml-jrf (vendas_get faz o ping em 401).
require_once __DIR__ . '/../vendas/sync.php';

date_default_timezone_set('America/Sao_Paulo');

const DASH_CACHE = '/var/lib/ml-app/dash-ml.json';
const DASH_CACHE_SEGUNDOS = 100; // a tela pede a cada 2 min
const DASH_ETIQUETAS_PADRAO = 'http://192.168.15.11:51015';

// Status das devoluções (/post-purchase/v2/claims/{id}/returns)
const DASH_DEVOLUCAO = [
    'pending'           => 'devolução pendente',
    'label_generated'   => 'etiqueta de devolução gerada',
    'ready_to_ship'     => 'devolução pronta para envio',
    'shipped'           => 'devolução a caminho',
    'delivered'         => 'devolução entregue',
    'not_delivered'     => 'devolução não entregue',
    'cancelled'         => 'devolução cancelada',
    'expired'           => 'devolução expirada',
    'failed'            => 'devolução falhou',
    'closed'            => 'devolução encerrada',
];

const DASH_ACOES = [
    'send_message_to_complainant' => 'Responder ao comprador',
    'send_message_to_mediator'    => 'Responder ao mediador',
    'refund'                      => 'Devolver o dinheiro',
    'allow_return'                => 'Autorizar devolução',
    'allow_partial_refund'        => 'Oferecer reembolso parcial',
    'open_dispute'                => 'Pedir mediação',
    'send_potential_shipping'     => 'Informar envio',
    'add_shipping_evidence'       => 'Enviar comprovante de envio',
    'send_tracking_number'        => 'Informar rastreio',
    'return_review_ok'            => 'Revisar devolução (ok)',
    'return_review_fail'          => 'Revisar devolução (problema)',
];

function dash_ml_post(string $caminho, array $corpo): array
{
    $pingFeito = false;
    while (true) {
        $ch = curl_init(VENDAS_API . $caminho);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($corpo),
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . vendas_token(), 'Content-Type: application/json'],
        ]);
        $resposta = curl_exec($ch);
        $codigo = curl_errno($ch) ? 0 : curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($codigo === 401 && !$pingFeito) {
            vendas_ping_token();
            $pingFeito = true;
            continue;
        }
        $js = json_decode((string) $resposta, true);
        if ($codigo >= 200 && $codigo < 300) {
            return is_array($js) ? $js : [];
        }
        throw new RuntimeException('ML respondeu ' . $codigo . ': ' . ($js['message'] ?? substr((string) $resposta, 0, 200)));
    }
}

function dash_vendedor(): string
{
    static $id = null;
    return $id ??= (string) vendas_get('/users/me')['id'];
}

// Anúncio resumido (título, SKU, preço, estoque). Guardado entre as rodadas do cache.
function dash_item(string $id, array &$memoria): array
{
    if (isset($memoria[$id]) && $memoria[$id]['quando'] > time() - 3600) {
        return $memoria[$id];
    }
    $it = vendas_get('/items/' . rawurlencode($id), ['include_attributes' => 'all']);
    $sku = $it['seller_custom_field'] ?? null;
    foreach ($it['attributes'] ?? [] as $a) {
        if (($a['id'] ?? '') === 'SELLER_SKU' && !empty($a['value_name'])) {
            $sku = $a['value_name'];
        }
    }
    return $memoria[$id] = [
        'quando'  => time(),
        'titulo'  => $it['title'] ?? $id,
        'sku'     => $sku,
        'preco'   => $it['price'] ?? null,
        'estoque' => $it['available_quantity'] ?? null,
        'status'  => $it['status'] ?? null,
        'full'    => (($it['shipping'] ?? [])['logistic_type'] ?? '') === 'fulfillment',
        'foto'    => $it['thumbnail'] ?? null,
        'link'    => $it['permalink'] ?? null,
    ];
}

function dash_perguntas(array &$itens): array
{
    $r = vendas_get('/questions/search', [
        'seller_id' => dash_vendedor(), 'status' => 'UNANSWERED', 'api_version' => 4,
        'sort_fields' => 'date_created', 'sort_types' => 'ASC', 'limit' => 50,
    ]);
    $lista = [];
    foreach ($r['questions'] ?? [] as $q) {
        $item = dash_item($q['item_id'], $itens);
        $lista[] = [
            'id'        => $q['id'],
            'texto'     => $q['text'] ?? '',
            'data'      => $q['date_created'],
            'item_id'   => $q['item_id'],
            'comprador' => $q['from']['id'] ?? null,
            'item'      => array_diff_key($item, ['quando' => 1]),
        ];
    }
    return ['total' => (int) ($r['total'] ?? count($lista)), 'lista' => $lista];
}

function dash_reclamacoes(array &$motivos, array &$pedidos): array
{
    $vendedor = dash_vendedor();
    $r = vendas_get('/post-purchase/v1/claims/search', ['status' => 'opened', 'limit' => 50]);
    $lista = [];
    foreach ($r['data'] ?? [] as $c) {
        $eu = null;
        foreach ($c['players'] ?? [] as $p) {
            if ((string) ($p['user_id'] ?? '') === $vendedor) {
                $eu = $p;
            }
        }
        $acoes = [];
        $prazo = null;
        $obrigatoria = false;
        foreach ($eu['available_actions'] ?? [] as $a) {
            $acoes[] = DASH_ACOES[$a['action']] ?? $a['action'];
            $obrigatoria = $obrigatoria || !empty($a['mandatory']);
            if (!empty($a['due_date']) && ($prazo === null || $a['due_date'] < $prazo)) {
                $prazo = $a['due_date'];
            }
        }

        $motivoId = $c['reason_id'] ?? '';
        if ($motivoId !== '' && !isset($motivos[$motivoId])) {
            try {
                $motivos[$motivoId] = vendas_get('/post-purchase/v1/claims/reasons/' . rawurlencode($motivoId))['detail'] ?? $motivoId;
            } catch (Throwable $e) {
                $motivos[$motivoId] = $motivoId;
            }
        }

        $devolucao = null;
        if (($c['type'] ?? '') === 'returns' || !empty($c['related_entities'])) {
            try {
                $dv = vendas_get('/post-purchase/v2/claims/' . $c['id'] . '/returns');
                $devolucao = DASH_DEVOLUCAO[$dv['status'] ?? ''] ?? ($dv['status'] ?? null);
            } catch (Throwable $e) {
                // claim sem devolução ainda: 404
            }
        }

        $mediacao = ($c['stage'] ?? '') === 'dispute';
        $pedido = ($c['resource'] ?? '') === 'order' ? (string) $c['resource_id'] : null;
        if ($pedido && !isset($pedidos[$pedido])) {
            try {
                $o = vendas_get('/orders/' . $pedido);
                $pedidos[$pedido] = [
                    'itens' => array_map(fn($it) => $it['quantity'] . 'x ' . ($it['item']['seller_sku'] ?? '' ?: $it['item']['title']), $o['order_items'] ?? []),
                    'comprador' => $o['buyer']['nickname'] ?? null,
                    'total' => $o['total_amount'] ?? null,
                ];
            } catch (Throwable $e) {
                $pedidos[$pedido] = ['itens' => [], 'comprador' => null, 'total' => null];
            }
        }
        $lista[] = [
            'id'         => $c['id'],
            'tipo'       => ($c['type'] ?? '') === 'returns' ? 'Devolução' : (($c['type'] ?? '') === 'mediations' ? 'Reclamação' : ucfirst((string) ($c['type'] ?? ''))),
            'mediacao'   => $mediacao,
            'motivo'     => $motivos[$motivoId] ?? $motivoId,
            'pedido'     => $pedido,
            'itens'      => $pedidos[$pedido]['itens'] ?? [],
            'comprador'  => $pedidos[$pedido]['comprador'] ?? null,
            'total'      => $pedidos[$pedido]['total'] ?? null,
            'data'       => $c['date_created'] ?? null,
            'atualizada' => $c['last_updated'] ?? null,
            'acoes'      => $acoes,
            'prazo'      => $prazo,
            'precisa'    => $obrigatoria || $prazo !== null || $mediacao,
            'devolucao'  => $devolucao,
            'link'       => $pedido
                ? 'https://www.mercadolivre.com.br/vendas/' . $pedido . '/detalhe'
                : 'https://www.mercadolivre.com.br/post-purchase/post-sales?main.filter=problems-to-manage',
        ];
    }
    // Quem precisa de ação primeiro (prazo mais curto antes), depois as mais recentes
    usort($lista, fn($a, $b) => [$b['precisa'], $a['prazo'] ?? '9', $b['data']] <=> [$a['precisa'], $b['prazo'] ?? '9', $a['data']]);
    return ['total' => count($lista), 'acao' => count(array_filter($lista, fn($c) => $c['precisa'])), 'lista' => $lista];
}

// Mesmo serviço da aba Etiquetas (ml-etiquetas-web), que tem cache próprio de 30 s
function dash_etiquetas(): array
{
    $base = rtrim(getenv('ETIQUETAS_URL') ?: DASH_ETIQUETAS_PADRAO, '/');
    $ch = curl_init($base . '/api/vendas');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 3]);
    $corpo = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $js = json_decode((string) $corpo, true);
    if ($codigo !== 200 || !is_array($js)) {
        throw new RuntimeException($js['erro'] ?? 'Serviço de etiquetas indisponível');
    }
    $nf = $prontas = $semFiscal = 0;
    $lista = [];
    foreach ($js['vendas'] ?? [] as $v) {
        if (!empty($v['sem_dados_fiscais'])) {
            $semFiscal++;
            $situacao = 'sem_fiscal';
        } elseif (($v['situacao'] ?? '') === 'nf_pendente') {
            $nf++;
            $situacao = 'nf';
        } else {
            $prontas++;
            $situacao = 'pronta';
        }
        $lista[] = [
            'venda'     => $v['venda'],
            'situacao'  => $situacao,
            'comprador' => $v['comprador'] ?? '',
            'total'     => $v['total'] ?? 0,
            'data'      => $v['data'] ?? null,
            'entrega'   => $v['entrega'] ?? null,
            'itens'     => array_map(fn($it) => $it['qtd'] . 'x ' . ($it['sku'] ?: $it['titulo']), $v['itens'] ?? []),
            'em_processamento' => !empty($v['em_processamento']),
        ];
    }
    return ['nf' => $nf, 'prontas' => $prontas, 'sem_fiscal' => $semFiscal, 'lista' => $lista];
}

// Cada bloco falha sozinho: um erro nas reclamações não apaga as perguntas da tela
function dash_ml_dados(bool $forcar = false): array
{
    $fp = fopen(DASH_CACHE, 'c+');
    flock($fp, LOCK_EX); // duas abas ao mesmo tempo: a segunda espera e usa o resultado da primeira
    try {
        $cache = json_decode((string) stream_get_contents($fp), true) ?: [];
        if (!$forcar && ($cache['quando'] ?? 0) > time() - DASH_CACHE_SEGUNDOS && isset($cache['dados'])) {
            return $cache['dados'] + ['cache' => true];
        }
        $itens = $cache['itens'] ?? [];
        $motivos = $cache['motivos'] ?? [];
        $pedidos = $cache['pedidos'] ?? [];
        $anterior = $cache['dados'] ?? [];
        $dados = ['quando' => date('c')];
        foreach ([
            'perguntas'   => fn() => dash_perguntas($itens),
            'reclamacoes' => fn() => dash_reclamacoes($motivos, $pedidos),
            'etiquetas'   => fn() => dash_etiquetas(),
        ] as $bloco => $buscar) {
            try {
                $dados[$bloco] = $buscar();
            } catch (Throwable $e) {
                error_log("ml-app dash ($bloco): " . $e->getMessage());
                // Mantém o último resultado bom e avisa que está desatualizado
                $bom = isset($anterior[$bloco]) && !isset($anterior[$bloco]['erro']) ? $anterior[$bloco] : [];
                $dados[$bloco] = $bom + ['erro' => $e->getMessage(), 'desde' => $anterior[$bloco]['desde'] ?? ($anterior['quando'] ?? null)];
            }
        }
        ftruncate($fp, 0);
        rewind($fp);
        // Memórias limitadas: anúncios e pedidos mais recentes bastam
        $itens = array_slice($itens, -200, null, true);
        $pedidos = array_slice($pedidos, -100, null, true);
        fwrite($fp, json_encode(['quando' => time(), 'dados' => $dados, 'itens' => $itens, 'motivos' => $motivos, 'pedidos' => $pedidos], JSON_UNESCAPED_UNICODE));
        return $dados;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

// Zera a validade (mantém itens e motivos guardados) para a próxima leitura ir ao ML
function dash_ml_invalidar(): void
{
    $fp = fopen(DASH_CACHE, 'c+');
    flock($fp, LOCK_EX);
    $cache = json_decode((string) stream_get_contents($fp), true) ?: [];
    $cache['quando'] = 0;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($cache, JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
    fclose($fp);
}

function dash_responder(int $pergunta, string $texto): array
{
    $texto = trim($texto);
    if ($texto === '' || mb_strlen($texto) > 2000) {
        throw new InvalidArgumentException('A resposta precisa ter de 1 a 2000 caracteres.');
    }
    $r = dash_ml_post('/answers', ['question_id' => $pergunta, 'text' => $texto]);
    dash_ml_invalidar();
    return $r;
}

// O tokens.json do ml-jrf é 0600, então o Apache não lê: a API chama este arquivo pela linha de
// comando (como root, igual ao Atualizar de Vendas). Sempre responde um JSON.
//   php ml.php dados [--forcar]
//   php ml.php responder <id da pergunta> <texto>
if (PHP_SAPI === 'cli' && realpath($_SERVER['argv'][0] ?? '') === __FILE__) {
    $args = array_slice($_SERVER['argv'], 1);
    try {
        if (($args[0] ?? '') === 'responder') {
            dash_responder((int) ($args[1] ?? 0), (string) ($args[2] ?? ''));
            $saida = ['ok' => true];
        } else {
            $saida = dash_ml_dados(in_array('--forcar', $args, true));
        }
    } catch (Throwable $e) {
        $saida = ['erro' => $e->getMessage()];
    }
    echo json_encode($saida, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
}
