<?php
// HTML dos painéis, usado na primeira carga (index.php) e nas atualizações (api.php).
// Textos do Mercado Livre são escritos por compradores: tudo passa por dash_h().

function dash_h(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dash_html_eventos(array $posts): string
{
    ob_start();
    foreach ($posts as $post):
        $icone = preg_match('/^[a-z_]+$/', $post['icone'] ?? '') ? $post['icone'] : null;
        ?>
        <a href="<?= dash_h($post['url']) ?>" target="_blank" rel="noopener" data-categoria="<?= dash_h($post['categoria']) ?>" class="block mt-1 break-all">
            <li class="p-2 <?= $post['bg_color'] ?> rounded border-l-4" style="border-left-color: #<?= dash_h($post['color_hex']) ?>;">
                <span class="text-xs text-gray-400 block"><?php if ($post['cancelado']): ?><span class="bg-red-900 text-red-300 text-xs font-semibold px-2 py-0.5 mr-2 rounded">Cancelado</span><?php endif; ?><?= dash_h($post['formatted_date']) ?> - <?= dash_h($post['res_id']) ?></span>
                <span class="font-sm text-white"><?= dash_h($post['titulo']) ?><?php if ($icone): ?> <img src="/imgs/<?= $icone ?>_icon.png" class="h-5 inline-block align-middle" alt=""><?php endif; ?><?= dash_h($post['texto']) ?><br><b><?= dash_h($post['detalhe']) ?></b></span>
            </li>
        </a>
        <?php
    endforeach;
    return ob_get_clean();
}

function dash_html_boletos(array $boletos): string
{
    ob_start();
    foreach ($boletos as $bol): ?>
        <a href="/boletos/" target="_blank" class="block mt-1 break-all">
            <li class="p-2 <?= $bol['bg_color'] ?> rounded flex flex-col sm:flex-row justify-between items-start sm:items-center border-l-4 <?= $bol['border_class'] ?>">
                <div class="mb-2 sm:mb-0">
                    <span class="font-medium text-white block mb-1"><?= dash_h($bol['descricao']) ?></span>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-400">Vence: <?= $bol['date_fmt'] ?></span>
                        <span class="<?= $bol['status_class'] ?> text-xs font-semibold px-2 py-0.5 rounded">
                            <?= $bol['status_text'] ?>
                        </span>
                        <?= $bol['vencido_tag'] ?>
                    </div>
                </div>
                <div class="text-lg font-bold text-red-400">
                    R$ <?= $bol['valor_fmt'] ?>
                </div>
            </li>
        </a>
    <?php endforeach;
    return ob_get_clean();
}
