<?php
// [ícone, nome]: no celular só o nome aparece, para o menu caber em uma linha
$modulos = [
    '/dash/'    => ['📱', 'Dash'],
    '/vendas/'  => ['💰', 'Vendas'],
    '/full/'    => ['🚚', 'Full'],
    '/margem/'  => ['📈', 'Margem'],
    '/boletos/' => ['📑', 'Boletos'],
    '/estoque/' => ['📦', 'Estoque'],
    '/etiquetas/' => ['🏷️', 'Etiquetas'],
    '/contas/'  => ['💳', 'Contas'],
];
$scriptAtual = $_SERVER['SCRIPT_NAME'] ?? '';
?>
<!-- As margens negativas acompanham o p-4 / sm:p-8 do <body> de cada página -->
<nav class="sticky top-0 z-40 flex-shrink-0 -mx-4 -mt-4 px-3 sm:-mx-8 sm:-mt-8 sm:px-8 mb-6 py-2 sm:py-3 bg-gray-800 border-b border-gray-700 shadow-lg flex items-center justify-between gap-2 sm:gap-3">
    <div class="flex items-center gap-1 sm:gap-2 min-w-0 overflow-x-auto [scrollbar-width:none]">
        <?php foreach ($modulos as $caminho => [$icone, $nome]): ?>
            <?php $ativo = str_starts_with($scriptAtual, $caminho); ?>
            <a href="<?= $caminho ?>"<?= $ativo ? ' aria-current="page"' : '' ?>
               class="shrink-0 whitespace-nowrap px-2.5 sm:px-3 py-2 sm:py-1.5 rounded-lg text-sm font-semibold transition-colors <?= $ativo ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?>"><span class="hidden sm:inline"><?= $icone ?> </span><?= $nome ?></a>
        <?php endforeach; ?>
    </div>
    <div class="flex items-center gap-3 text-sm text-gray-400 shrink-0">
        <span class="hidden sm:inline">Usuário: <span class="text-blue-400"><?= htmlspecialchars($_SESSION['user'], ENT_QUOTES, 'UTF-8') ?></span></span>
    </div>
</nav>
