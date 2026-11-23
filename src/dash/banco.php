<?php
// Blocos da Dash que vêm do banco (leves, atualizados a cada 30 s): indicadores de vendas do dia,
// eventos do webhook dos últimos 2 dias e boletos dos próximos 15 dias.
// Tudo sai como dado puro; o HTML é montado no navegador com textContent (texto de comprador).
require_once __DIR__ . '/../boletos/vencimento.php';
require_once __DIR__ . '/../boletos/nivel.php';

const DASH_FUSO = 'America/Sao_Paulo';

function dash_h(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// post_time do ml-jrf é gravado na hora local
function dash_iso(string $data): string
{
    return (new DateTime($data, new DateTimeZone(DASH_FUSO)))->format('c');
}

// "['1x KF-192', '2x KF-07']" -> "1x KF-192 + 2x KF-07"
function dash_itens(?string $itens): string
{
    preg_match_all("/'([^']*)'/", (string) $itens, $m);
    return implode(' + ', $m[1] ?: [trim((string) $itens, '[]')]);
}

function dash_cancelados(PDO $pdo): array
{
    return $pdo->query("
        SELECT DISTINCT order_id FROM post_purchase
        WHERE type LIKE 'Cancelamento%' AND post_time >= CURDATE() - INTERVAL 3 DAY
    ")->fetchAll(PDO::FETCH_COLUMN);
}

function dash_eventos(PDO $pdo): array
{
    $cancelados = array_flip(array_map('strval', dash_cancelados($pdo)));
    $linhas = $pdo->query("
        SELECT 'venda' AS fonte, id, post_time, res_id, order_type AS sub, items AS a, total_value AS b, NULL AS c FROM orders_v2
            WHERE post_time >= CURDATE() - INTERVAL 1 DAY
        UNION ALL
        SELECT 'pergunta', id, post_time, res_id, sku, item_id, content, NULL FROM questions
            WHERE post_time >= CURDATE() - INTERVAL 1 DAY
        UNION ALL
        SELECT 'mensagem', id, post_time, res_id, NULL, user, message, NULL FROM messages
            WHERE post_time >= CURDATE() - INTERVAL 1 DAY
        UNION ALL
        SELECT 'reclamacao', id, post_time, res_id, NULL, order_id, type, reason FROM post_purchase
            WHERE post_time >= CURDATE() - INTERVAL 1 DAY
        ORDER BY post_time DESC, id DESC
    ")->fetchAll();

    $eventos = [];
    foreach ($linhas as $l) {
        $e = ['fonte' => $l['fonte'], 'quando' => dash_iso($l['post_time'])];
        switch ($l['fonte']) {
            case 'venda':
                $pedido = str_replace('/orders/', '', $l['res_id']);
                $e += [
                    'titulo'    => dash_itens($l['a']),
                    'valor'     => (float) $l['b'],
                    'tipo'      => $l['sub'], // venda, venda_full, venda_mute
                    'pedido'    => $pedido,
                    'cancelado' => isset($cancelados[$pedido]),
                    'link'      => 'https://www.mercadolivre.com.br/vendas/' . $pedido . '/detalhe',
                ];
                break;
            case 'pergunta':
                $e += [
                    'titulo'   => $l['b'],
                    'sku'      => $l['sub'],
                    'item_id'  => $l['a'],
                    'pergunta' => (int) preg_replace('/\D/', '', $l['res_id']),
                    'link'     => 'https://www.mercadolivre.com.br/perguntas/vendedor?status=unanswered',
                ];
                break;
            case 'mensagem':
                $e += [
                    'titulo' => $l['b'],
                    'autor'  => $l['a'],
                    'pedido' => $l['res_id'],
                    'link'   => 'https://www.mercadolivre.com.br/vendas/novo/mensagens/' . $l['res_id'],
                ];
                break;
            case 'reclamacao':
                $e += [
                    'titulo' => trim(preg_replace('/^Cancelamento da compra por parte do comprador\.?/u', 'Cancelada pelo comprador', (string) $l['b']) . ' · ' . $l['c'], ' ·'),
                    'pedido' => $l['a'],
                    'link'   => 'https://www.mercadolivre.com.br/vendas/' . $l['a'] . '/detalhe',
                ];
                break;
        }
        $eventos[] = $e;
    }
    return $eventos;
}

// Vendas de hoje e de ontem até a mesma hora (sem as canceladas)
function dash_kpis(PDO $pdo): array
{
    $cancelados = dash_cancelados($pdo);
    $fora = $cancelados ? ' AND REPLACE(res_id, \'/orders/\', \'\') NOT IN (' . implode(',', array_fill(0, count($cancelados), '?')) . ')' : '';
    $sql = "SELECT COUNT(*) n, COALESCE(SUM(total_value), 0) total, COALESCE(SUM(order_type = 'venda_full'), 0) full
            FROM orders_v2 WHERE post_time >= ? AND post_time < ?" . $fora;
    $stmt = $pdo->prepare($sql);

    $agora = new DateTime('now', new DateTimeZone(DASH_FUSO));
    $hoje = (clone $agora)->setTime(0, 0);
    $ontem = (clone $hoje)->modify('-1 day');
    $ontemAgora = (clone $agora)->modify('-1 day');
    $f = fn(DateTime $d) => $d->format('Y-m-d H:i:s');

    $stmt->execute([$f($hoje), $f($agora->modify('+1 minute')), ...$cancelados]);
    $h = $stmt->fetch();
    $stmt->execute([$f($ontem), $f($ontemAgora), ...$cancelados]);
    $o = $stmt->fetch();
    return [
        'hoje'  => ['n' => (int) $h['n'], 'total' => (float) $h['total'], 'full' => (int) $h['full']],
        'ontem' => ['n' => (int) $o['n'], 'total' => (float) $o['total']],
    ];
}

function dash_boletos(PDO $pdo): array
{
    $feriados = $pdo->query("SELECT data FROM ml_boletos.feriados")->fetchAll(PDO::FETCH_COLUMN);
    $brutos = $pdo->query("
        SELECT data_vencimento, descricao, valor, agendado
        FROM ml_boletos.boletos
        WHERE pago = 0 AND deleted_at IS NULL
    ")->fetchAll();

    $hoje = boletoHoje();
    $boletos = [];
    foreach ($brutos as $b) {
        $real = calcularVencimentoReal($b['data_vencimento'], $feriados);
        $dias = boletoDias($real, $hoje);
        if ($dias > 15) {
            continue;
        }
        $boletos[] = [
            'descricao' => $b['descricao'],
            'valor'     => (float) $b['valor'],
            'vence'     => $real,
            'dias'      => $dias,
            'prazo'     => boletoPrazo($dias),
            'agendado'  => (bool) $b['agendado'],
            'nivel'     => boletoNivel(false, (bool) $b['agendado'], $dias),
        ];
    }
    usort($boletos, fn($a, $b) => strcmp($a['vence'], $b['vence']));
    return $boletos;
}

// Quando muda, a tela pede os blocos do ML na hora (pergunta ou reclamação nova pelo webhook)
function dash_sinal(PDO $pdo): string
{
    return implode('-', $pdo->query("
        SELECT (SELECT COALESCE(MAX(id), 0) FROM questions),
               (SELECT COALESCE(MAX(id), 0) FROM post_purchase),
               (SELECT COALESCE(MAX(id), 0) FROM orders_v2)
    ")->fetch(PDO::FETCH_NUM));
}
