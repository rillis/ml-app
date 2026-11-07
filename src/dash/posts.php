<?php
//Tratamento de datas de hoje
date_default_timezone_set('America/Sao_Paulo');
$now = new DateTime();
$nowts = $now->getTimestamp();
$today = date('Y-m-d');

//query dos posts: devolve só texto puro, o HTML é montado em parciais.php com o conteúdo escapado
$sqlPosts = "
    with
table_union as (
    select id original_id, 'messages' as type, post_time, res_id, concat('Nova mensagem de ', user, ':') titulo, null icone, null texto, message detalhe, concat('https://www.mercadolivre.com.br/vendas/novo/mensagens/',res_id) url from messages WHERE DATEDIFF(CURDATE(), post_time) BETWEEN 0 AND 1
    union all
    select id original_id, concat('orders_v2: ', order_type) as type, post_time, res_id, 'Nova venda' titulo, order_type icone, concat(': ', REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(items, '[', ''),']', ''), '''', ''),', ', '+'),',', '+'), '.') texto, concat('Total: R$ ', total_value) detalhe, concat('https://www.mercadolivre.com.br/vendas/',replace(res_id, '/orders/', ''),'/detalhe') url from orders_v2 where DATEDIFF(CURDATE(), post_time) BETWEEN 0 AND 1
    union all
    select id original_id, 'post_purchase' as type, post_time, res_id, concat('Nova reclamação na compra ', order_id, ':') titulo, null icone, null texto, concat(type, ' - ', reason) detalhe, concat('https://www.mercadolivre.com.br/vendas/',order_id,'/detalhe') url from post_purchase where DATEDIFF(CURDATE(), post_time) BETWEEN 0 AND 1
    union all
    select id original_id, 'questions' as type, post_time, res_id, concat('Nova pergunta no produto ', item_id, ' (', sku, '):') titulo, null icone, null texto, content detalhe, 'https://www.mercadolivre.com.br/perguntas/vendedor?page=1&period=15&sort=date_closed_asc&status=unanswered' url from questions where DATEDIFF(CURDATE(), post_time) BETWEEN 0 AND 1
)
select * from table_union order by post_time desc
";
$posts = $pdo->query($sqlPosts)->fetchAll(PDO::FETCH_ASSOC);

//criação da array de cancelados
$cancelados = [];
foreach ($posts as $post) {
    if (
        $post['type'] === 'post_purchase' &&
        str_contains($post['detalhe'] ?? '', 'Cancelamento da compra')
    ) {
        if (preg_match('/Nova reclamação na compra ([^:]+):/', $post['titulo'] ?? '', $matches)) {
            $cancelados[] = $matches[1];
        }
    }
}

//dicts
$colors = [
    'post_purchase'             => 'fc0303',
    'orders_v2: venda'          => '1cfc03',
    'orders_v2: venda_mute'     => '0a2c18',
    'orders_v2: venda_full'     => '1f8b4c',
    'questions'                 => '8b07f0',
    'messages'                  => '07e8f0'
];

// Grupos usados nos filtros do painel
$categorias = [
    'orders_v2'     => 'vendas',
    'questions'     => 'perguntas',
    'messages'      => 'mensagens',
    'post_purchase' => 'reclamacoes',
];
$contagemEventos = ['todos' => count($posts), 'vendas' => 0, 'perguntas' => 0, 'mensagens' => 0, 'reclamacoes' => 0];

// Prepara variáveis de interface
foreach ($posts as &$post) {
    $postTime = new DateTime($post['post_time']);
    $post['formatted_date'] = $postTime->format('d/m/Y H:i');

    $post['categoria'] = $categorias[explode(':', $post['type'])[0]] ?? 'todos';
    if ($post['categoria'] !== 'todos') {
        $contagemEventos[$post['categoria']]++;
    }

    $post['cancelado'] = str_starts_with($post['type'], 'orders_v2') && in_array(str_replace("/orders/", "", $post['res_id']), $cancelados);

    $post['color_hex'] = $colors[$post['type']] ?? 'ffffff';

    $diffSecs = $nowts - $postTime->getTimestamp();
    if ($diffSecs <= 61) {
        $post['bg_color'] = 'bg-red-700';
    } elseif ($diffSecs <= 181) {
        $post['bg_color'] = 'bg-green-700';
    } elseif ($diffSecs <= 3601) {
        $post['bg_color'] = 'bg-gray-700';
    } else {
        $post['bg_color'] = 'bg-gray-900';
    }
}
unset($post); // Limpa a referência

//ultimo evento
$lastEventText = "Nenhum evento recente";
if (!empty($posts)) {
    $lastEventDt = new DateTime($posts[0]['post_time']);
    $diff = $now->diff($lastEventDt);
    $parts = [];
    if ($diff->d > 0) $parts[] = $diff->d . ' dia' . ($diff->d > 1 ? 's' : '');
    if ($diff->h > 0) $parts[] = $diff->h . ' hora' . ($diff->h > 1 ? 's' : '');
    if ($diff->i > 0) $parts[] = $diff->i . ' minuto' . ($diff->i > 1 ? 's' : '');

    $timeString = empty($parts) ? 'agora mesmo' : 'há ' . implode(', ', $parts);
    $lastEventText = "Último evento: " . $lastEventDt->format('d/m/Y H:i') . " (" . $timeString . ")";
}
