<?php
// Painel da fila do ml-jrf (tabela ml_jrf.webhook_queue): consultas, ações e HTML.
// Conteúdo vindo do ML (recursos, erros, notas) sempre passa por dash_h().
require_once __DIR__ . '/banco.php';
date_default_timezone_set('America/Sao_Paulo');

const FILA_HEALTH_URL = 'http://192.168.15.11:52003/health';

const FILA_TIPOS = [
    'orders_v2' => 'Venda',
    'messages'  => 'Mensagem',
    'questions' => 'Pergunta',
    'claims'    => 'Reclamação',
    'debug'     => 'Sem handler',
];

const FILA_STATUS = [
    'pending'    => ['Pendente', 'bg-blue-900 text-blue-300'],
    'processing' => ['Processando', 'bg-purple-900 text-purple-300'],
    'done'       => ['Enviado', 'bg-emerald-900 text-emerald-300'],
    'skipped'    => ['Pulado', 'bg-gray-700 text-gray-300'],
    'failed'     => ['Falhou', 'bg-red-900 text-red-300'],
];

function fila_saude(): array
{
    $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
    $corpo = @file_get_contents(FILA_HEALTH_URL, false, $ctx);
    $js = $corpo === false ? null : json_decode($corpo, true);
    if (!is_array($js)) {
        return ['ok' => false, 'texto' => 'ml-jrf sem resposta no /health'];
    }
    if (($js['status'] ?? '') === 'ok') {
        return ['ok' => true, 'texto' => 'ml-jrf OK · worker ativo há ' . (int)($js['worker_heartbeat_s'] ?? 0) . 's · token OK'];
    }
    return ['ok' => false, 'texto' => implode('; ', $js['problems'] ?? ['ml-jrf com problema'])];
}

function fila_resumo(PDO $pdo): array
{
    $r = $pdo->query("
        SELECT
          COALESCE(SUM(status = 'pending'), 0)    AS pendentes,
          COALESCE(SUM(status = 'processing'), 0) AS processando,
          COALESCE(SUM(status = 'failed'), 0)     AS falhas,
          COALESCE(SUM(status = 'done'    AND updated_at >= NOW() - INTERVAL 1 DAY), 0) AS enviados_24h,
          COALESCE(SUM(status = 'skipped' AND updated_at >= NOW() - INTERVAL 1 DAY), 0) AS pulados_24h
        FROM webhook_queue
        WHERE status IN ('pending', 'processing', 'failed')
           OR (updated_at >= NOW() - INTERVAL 1 DAY AND (note IS NULL OR note NOT LIKE 'importado%'))
    ")->fetch();
    return array_map('intval', $r);
}

// Falhas e pendentes que já falharam ao menos uma vez
function fila_atencao(PDO $pdo): array
{
    return $pdo->query("
        SELECT id, handler, dedup_key, status, attempts, next_attempt_at, last_error, updated_at
        FROM webhook_queue
        WHERE status = 'failed' OR (status = 'pending' AND attempts > 0)
        ORDER BY status = 'failed' DESC, updated_at DESC
        LIMIT 100
    ")->fetchAll();
}

function fila_recentes(PDO $pdo): array
{
    return $pdo->query("
        SELECT id, handler, dedup_key, status, attempts, note, created_at, updated_at
        FROM webhook_queue
        WHERE note IS NULL OR note NOT LIKE 'importado%'
        ORDER BY updated_at DESC, id DESC
        LIMIT 50
    ")->fetchAll();
}

// Reprocessa uma falha (ou tenta já um pendente em espera). Nunca mexe em job em processamento.
function fila_reprocessar(PDO $pdo, ?int $id): int
{
    $sql = "UPDATE webhook_queue SET status = 'pending', attempts = 0, next_attempt_at = NOW(), last_error = NULL
            WHERE status IN ('failed', 'pending')";
    if ($id === null) {
        $stmt = $pdo->prepare($sql . " AND status = 'failed'");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare($sql . ' AND id = ?');
        $stmt->execute([$id]);
    }
    return $stmt->rowCount();
}

function fila_descartar(PDO $pdo, int $id, string $usuario): int
{
    $stmt = $pdo->prepare("UPDATE webhook_queue SET status = 'skipped', note = ?, next_attempt_at = NOW()
                           WHERE id = ? AND status IN ('failed', 'pending')");
    $stmt->execute([mb_substr('descartado no painel por ' . $usuario, 0, 255), $id]);
    return $stmt->rowCount();
}

function fila_link(array $job): ?string
{
    if ($job['handler'] === 'orders_v2' && preg_match('#^/orders/(\d+)#', $job['dedup_key'], $m)) {
        return 'https://www.mercadolivre.com.br/vendas/' . $m[1] . '/detalhe';
    }
    if ($job['handler'] === 'claims') {
        return 'https://www.mercadolivre.com.br/post-purchase/post-sales?main.filter=problems-to-manage&history.filter=true#side-menu';
    }
    return null;
}

function fila_badge(string $status): string
{
    [$rotulo, $classes] = FILA_STATUS[$status] ?? [$status, 'bg-gray-700 text-gray-300'];
    return '<span class="' . $classes . ' text-xs font-semibold px-2 py-0.5 rounded whitespace-nowrap">' . dash_h($rotulo) . '</span>';
}

function fila_data(?string $data): string
{
    return $data ? (new DateTime($data))->format('d/m H:i:s') : '-';
}

function fila_recurso(array $job): string
{
    $texto = dash_h($job['dedup_key']);
    $link = fila_link($job);
    return $link ? '<a href="' . dash_h($link) . '" target="_blank" rel="noopener" class="text-blue-400 hover:underline">' . $texto . '</a>' : $texto;
}

function fila_html_atencao(array $jobs): string
{
    if (!$jobs) {
        return '<p class="text-sm text-gray-500 text-center py-6">Nada precisando de atenção. 🎉</p>';
    }
    ob_start(); ?>
    <table class="w-full text-sm">
        <thead class="text-xs uppercase text-gray-400 border-b border-gray-700">
            <tr><th class="text-left py-2 pr-2">#</th><th class="text-left pr-2">Tipo</th><th class="text-left pr-2">Recurso</th><th class="text-left pr-2">Status</th><th class="text-left pr-2">Erro</th><th class="text-right">Ações</th></tr>
        </thead>
        <tbody>
        <?php foreach ($jobs as $j):
            $espera = $j['status'] === 'pending' ? 'próxima tentativa ' . fila_data($j['next_attempt_at']) : 'em ' . fila_data($j['updated_at']); ?>
            <tr class="border-b border-gray-700/60 align-top">
                <td class="py-2 pr-2 text-gray-400"><?= (int)$j['id'] ?></td>
                <td class="pr-2 whitespace-nowrap"><?= dash_h(FILA_TIPOS[$j['handler']] ?? $j['handler']) ?></td>
                <td class="pr-2 break-all"><?= fila_recurso($j) ?></td>
                <td class="pr-2"><?= fila_badge($j['status']) ?><span class="block text-xs text-gray-500 mt-1"><?= (int)$j['attempts'] ?> tentativa(s) · <?= dash_h($espera) ?></span></td>
                <td class="pr-2 text-xs text-red-300 break-all" title="<?= dash_h($j['last_error']) ?>"><?= dash_h(mb_strimwidth($j['last_error'] ?? '', 0, 160, '…')) ?></td>
                <td class="text-right whitespace-nowrap">
                    <button type="button" data-acao="reprocessar" data-id="<?= (int)$j['id'] ?>" class="px-3 py-2 md:px-2 md:py-1 rounded text-xs font-semibold bg-blue-600 hover:bg-blue-500 text-white"><?= $j['status'] === 'failed' ? 'Reprocessar' : 'Tentar agora' ?></button>
                    <button type="button" data-acao="descartar" data-id="<?= (int)$j['id'] ?>" class="px-3 py-2 md:px-2 md:py-1 rounded text-xs font-semibold bg-gray-700 hover:bg-gray-600 text-gray-200">Descartar</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php return ob_get_clean();
}

function fila_html_recentes(array $jobs): string
{
    if (!$jobs) {
        return '<p class="text-sm text-gray-500 text-center py-6">Nenhuma notificação ainda.</p>';
    }
    ob_start(); ?>
    <table class="w-full text-sm">
        <thead class="text-xs uppercase text-gray-400 border-b border-gray-700">
            <tr><th class="text-left py-2 pr-2">Quando</th><th class="text-left pr-2">Tipo</th><th class="text-left pr-2">Recurso</th><th class="text-left pr-2">Status</th><th class="text-left">Observação</th></tr>
        </thead>
        <tbody>
        <?php foreach ($jobs as $j): ?>
            <tr class="border-b border-gray-700/60 align-top">
                <td class="py-2 pr-2 text-gray-400 whitespace-nowrap"><?= dash_h(fila_data($j['updated_at'])) ?></td>
                <td class="pr-2 whitespace-nowrap"><?= dash_h(FILA_TIPOS[$j['handler']] ?? $j['handler']) ?></td>
                <td class="pr-2 break-all"><?= fila_recurso($j) ?></td>
                <td class="pr-2"><?= fila_badge($j['status']) ?></td>
                <td class="text-xs text-gray-400"><?= dash_h($j['note']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php return ob_get_clean();
}

function fila_dados(PDO $pdo): array
{
    $saude = fila_saude();
    return [
        'saude'    => $saude,
        'resumo'   => fila_resumo($pdo),
        'atencao'  => fila_html_atencao(fila_atencao($pdo)),
        'recentes' => fila_html_recentes(fila_recentes($pdo)),
    ];
}
