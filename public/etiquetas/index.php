<?php
require __DIR__ . '/../../src/auth.php';
$classeBotao = 'bg-gray-800 border border-gray-700 hover:border-blue-500 text-gray-100 font-semibold py-2 px-4 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap';
$classePrimario = 'bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2 px-4 rounded-lg transition-colors shadow-lg disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap';
$classeModal = 'hidden fixed inset-0 z-[60] bg-black/70 backdrop-blur-sm justify-center items-center p-4';
$classeCaixa = 'bg-gray-800 border border-gray-700 rounded-2xl shadow-2xl w-full max-h-[90vh] overflow-y-auto p-5 sm:p-6';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiquetas</title>
    <script src="/assets/tailwind.js"></script>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <style>
        @keyframes gira { to { transform: rotate(360deg); } }
        .girando { animation: gira .8s linear infinite; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen p-4 sm:p-8 font-sans selection:bg-blue-500 selection:text-white">
    <?php require __DIR__ . '/../../src/nav.php'; ?>
    <div class="max-w-5xl mx-auto">

        <header class="mb-6 flex flex-wrap justify-between items-end gap-4 border-b border-gray-700 pb-4">
            <div>
                <h1 class="text-4xl font-bold">
                    <span aria-hidden="true">🏷️</span>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400">Etiquetas</span>
                </h1>
                <p id="atualizado" class="text-sm text-gray-400 mt-1">Carregando…</p>
            </div>
            <button id="btnAtualizar" type="button" class="<?= $classePrimario ?>">🔄 Atualizar</button>
        </header>

        <div id="resumo" class="flex flex-wrap gap-2 mb-4"></div>

        <label class="inline-flex items-center gap-2 text-sm text-gray-300 cursor-pointer mb-3">
            <input type="checkbox" id="checkTodas" checked class="w-4 h-4 accent-blue-500"> Todas
        </label>
        <div id="lista" class="space-y-3"></div>
        <p id="vazio" class="hidden bg-gray-800 border border-gray-700 rounded-xl p-8 text-center text-gray-400">Nenhuma venda aguardando NF ou pronta para imprimir.</p>

        <div id="barraAcao" class="sticky bottom-4 mt-6 bg-gray-800 border border-gray-700 rounded-xl shadow-2xl p-3 sm:p-4 flex items-center justify-between gap-3">
            <span id="selecionadas" class="text-sm text-gray-400"></span>
            <button id="btnProcessar" type="button" class="<?= $classePrimario ?>" disabled>Processar</button>
        </div>

        <section class="mt-10">
            <h2 class="text-lg font-semibold text-gray-200 mb-3">Histórico <span class="text-sm font-normal text-gray-500">(últimos 30 dias)</span></h2>
            <div id="historico" class="space-y-2"></div>
        </section>
    </div>

    <!-- Confirmação -->
    <div id="modalConfirmar" class="<?= $classeModal ?>">
        <div role="dialog" aria-modal="true" class="<?= $classeCaixa ?> max-w-md">
            <h2 class="text-xl font-bold mb-3">Processar vendas?</h2>
            <p id="confirmarTexto" class="text-gray-300 text-sm"></p>
            <div class="flex justify-end gap-2 mt-5">
                <button type="button" class="<?= $classeBotao ?>" data-fechar>Cancelar</button>
                <button type="button" id="btnConfirmar" class="<?= $classePrimario ?>">Processar</button>
            </div>
        </div>
    </div>

    <!-- Processando -->
    <div id="modalProcessando" class="<?= $classeModal ?>">
        <div role="dialog" aria-modal="true" class="<?= $classeCaixa ?> max-w-2xl">
            <h2 id="procTitulo" class="text-xl font-bold">Processando…</h2>
            <p id="procSub" class="text-sm text-gray-400"></p>
            <ul id="procLista" class="mt-4 space-y-2"></ul>
            <p class="text-sm text-gray-500 mt-4">Pode fechar esta aba: o processamento continua no servidor.</p>
        </div>
    </div>

    <!-- Resultado -->
    <div id="modalResultado" class="<?= $classeModal ?>">
        <div role="dialog" aria-modal="true" class="<?= $classeCaixa ?> max-w-2xl">
            <h2 id="resTitulo" class="text-xl font-bold mb-3">Pronto</h2>
            <div id="resDownloads" class="flex flex-wrap gap-2 mb-3"></div>
            <div id="resErros"></div>
            <h3 id="resMensagemTitulo" class="font-semibold text-gray-200 mt-4 mb-2">Mensagem</h3>
            <textarea id="resMensagem" readonly rows="8" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-3 font-mono text-sm text-gray-100 resize-y"></textarea>
            <div id="resEstoque"></div>
            <div id="resAvisos"></div>
            <div class="flex flex-wrap justify-end gap-2 mt-5">
                <button type="button" id="btnCopiar" class="<?= $classeBotao ?>">Copiar mensagem</button>
                <button type="button" id="btnFecharResultado" class="<?= $classePrimario ?>">Fechar</button>
            </div>
        </div>
    </div>

    <script>
        const CLASSE_BOTAO = <?= json_encode($classeBotao) ?>;
        const CLASSE_PRIMARIO = <?= json_encode($classePrimario) ?>;
    </script>
    <script src="etiquetas.js"></script>
</body>
</html>
