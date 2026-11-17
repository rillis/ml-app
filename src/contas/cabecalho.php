<?php
// Recebe $paginaContas: 'mes', 'parcelas', 'insights' ou 'cadastro'
$abasContas = [
    'mes'      => ['/contas/', '🗓️ Mês'],
    'parcelas' => ['/contas/parcelas.php', '🧾 Parcelas'],
    'insights' => ['/contas/insights.php', '📊 Insights'],
    'cadastro' => ['/contas/cadastro.php', '⚙️ Gerenciar contas'],
];
?>
<header class="mb-8 flex flex-wrap justify-between items-end gap-4 border-b border-gray-700 pb-4">
    <h1 class="text-4xl font-bold">
        <!-- Fora do gradiente: o texto transparente transformaria o emoji em um recorte azul -->
        <span aria-hidden="true">💳</span>
        <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400">Contas Pessoais</span>
    </h1>
    <div class="flex flex-wrap gap-2">
        <?php foreach ($abasContas as $chave => [$link, $nome]): ?>
            <?php $ativa = $chave === $paginaContas; ?>
            <a href="<?= $link ?>"<?= $ativa ? ' aria-current="page"' : '' ?>
               class="px-4 py-2 rounded-xl border text-sm font-semibold transition-colors <?= $ativa ? 'bg-gray-700 border-blue-500 text-white' : 'bg-gray-800 border-gray-700 text-gray-300 hover:bg-gray-700 hover:text-white' ?>"><?= $nome ?></a>
        <?php endforeach; ?>
    </div>
</header>
