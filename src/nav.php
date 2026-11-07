<?php
$modulos = [
    '/dash/'    => '📱 Dash',
    '/boletos/' => '📑 Boletos',
    '/estoque/' => '📦 Estoque',
    '/contas/'  => '💳 Contas',
];
$scriptAtual = $_SERVER['SCRIPT_NAME'] ?? '';
?>
<nav class="sticky top-0 z-40 flex-shrink-0 -mx-8 -mt-8 mb-6 px-8 py-3 bg-gray-800 border-b border-gray-700 shadow-lg flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2">
        <?php foreach ($modulos as $caminho => $nome): ?>
            <?php $ativo = str_starts_with($scriptAtual, $caminho); ?>
            <a href="<?= $caminho ?>"<?= $ativo ? ' aria-current="page"' : '' ?>
               class="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors <?= $ativo ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?>"><?= $nome ?></a>
        <?php endforeach; ?>
    </div>
    <div class="flex items-center gap-3 text-sm text-gray-400">
        <span>Usuário: <span class="text-blue-400"><?= htmlspecialchars($_SESSION['user'], ENT_QUOTES, 'UTF-8') ?></span></span>
        <a href="/logout.php" class="text-red-400 hover:text-red-300 hover:underline transition-colors">Sair</a>
    </div>
</nav>
