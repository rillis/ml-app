<?php
require __DIR__ . '/../../src/auth.php';
$classeBotao = 'bg-gray-800 border border-gray-700 hover:border-blue-500 text-gray-100 font-semibold py-2 px-4 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap';
$classePrimario = 'bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2 px-4 rounded-lg transition-colors shadow-lg disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap';
$classeCartao = 'bg-gray-800 border border-gray-700 rounded-xl flex flex-col min-h-0';
$classeTitulo = 'flex items-center justify-between gap-2 px-4 pt-3 pb-2 border-b border-gray-700 flex-shrink-0';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dash</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <script src="/assets/tailwind.js"></script>
    <style>
        .rolagem { scrollbar-width: thin; scrollbar-color: #374151 transparent; }
        .rolagem::-webkit-scrollbar { width: 8px; }
        .rolagem::-webkit-scrollbar-thumb { background: #374151; border-radius: 4px; }
        @keyframes chega { from { background-color: rgba(59, 130, 246, .35); } to { background-color: transparent; } }
        .novo { animation: chega 3s ease-out; }
    </style>
</head>
<!-- No computador (lg+) a página ocupa exatamente a janela e cada cartão rola por dentro; no celular a página rola -->
<body class="bg-gray-900 text-gray-100 p-4 sm:p-8 font-sans min-h-screen lg:h-screen flex flex-col lg:overflow-hidden">
    <?php require __DIR__ . '/../../src/nav.php'; ?>

    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 mb-3 flex-shrink-0">
        <h1 class="text-2xl font-bold">Painel ao vivo</h1>
        <p class="text-xs text-gray-400 flex items-center gap-2">
            <span id="pontoStatus" class="inline-block w-2 h-2 rounded-full bg-gray-500"></span>
            <span id="statusTexto">Carregando…</span>
        </p>
    </div>

    <!-- Indicadores -->
    <div id="kpis" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4 flex-shrink-0">
        <?php foreach (['etiquetas', 'perguntas', 'reclamacoes', 'sistema', 'boletos', 'vendas'] as $k): ?>
            <button type="button" data-kpi="<?= $k ?>" class="text-left bg-gray-800 border border-gray-700 rounded-xl px-3 py-2.5 hover:border-gray-500 transition-colors min-w-0">
                <span data-rotulo class="block text-xs text-gray-400 truncate">&nbsp;</span>
                <span data-valor class="block text-2xl font-bold tabular-nums leading-tight">–</span>
                <span data-sub class="block text-xs text-gray-400 truncate">&nbsp;</span>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 flex-1 min-h-0">

        <!-- Precisa de você -->
        <section class="<?= $classeCartao ?> lg:col-span-5 max-h-[80vh] lg:max-h-none">
            <div class="<?= $classeTitulo ?>">
                <h2 class="font-semibold text-amber-300">Precisa de você</h2>
                <button type="button" id="btnAtualizarMl" class="text-xs text-gray-400 hover:text-white" title="Consultar o ML agora">↻ <span id="mlQuando"></span></button>
            </div>
            <div class="flex-1 overflow-y-auto rolagem px-4 pb-3">
                <h3 class="text-xs uppercase tracking-wider text-gray-400 mt-3 mb-1 flex justify-between"><span>Perguntas sem resposta</span><span id="perguntasErro" class="normal-case tracking-normal text-amber-400"></span></h3>
                <ul id="perguntas" class="divide-y divide-gray-700/60"></ul>
                <h3 class="text-xs uppercase tracking-wider text-gray-400 mt-4 mb-1 flex justify-between"><span>Reclamações abertas</span><span id="reclamacoesErro" class="normal-case tracking-normal text-amber-400"></span></h3>
                <ul id="reclamacoes" class="divide-y divide-gray-700/60"></ul>
                <h3 class="text-xs uppercase tracking-wider text-gray-400 mt-4 mb-1 flex justify-between"><span>Etiquetas</span><span id="etiquetasErro" class="normal-case tracking-normal text-amber-400"></span></h3>
                <div id="etiquetas"></div>
            </div>
        </section>

        <!-- Eventos -->
        <section class="<?= $classeCartao ?> lg:col-span-4 h-[70vh] lg:h-auto">
            <div class="<?= $classeTitulo ?>">
                <h2 class="font-semibold text-blue-400">Eventos <span class="text-xs font-normal text-gray-500">ontem e hoje</span></h2>
            </div>
            <div id="filtros" class="flex flex-wrap gap-1.5 px-4 pt-2 pb-1 flex-shrink-0" role="group" aria-label="Filtrar eventos"></div>
            <ul id="eventos" class="flex-1 overflow-y-auto rolagem px-2 pb-2"></ul>
        </section>

        <!-- Boletos e sistema -->
        <div class="lg:col-span-3 flex flex-col gap-4 min-h-0">
            <section class="<?= $classeCartao ?> flex-1">
                <div class="<?= $classeTitulo ?>">
                    <h2 class="font-semibold text-red-400">Boletos <span class="text-xs font-normal text-gray-500">15 dias</span></h2>
                    <a href="/boletos/" class="text-xs text-gray-400 hover:text-white">abrir ↗</a>
                </div>
                <ul id="boletos" class="flex-1 overflow-y-auto rolagem px-4 pb-2 divide-y divide-gray-700/60"></ul>
            </section>
            <section class="<?= $classeCartao ?> flex-shrink-0">
                <div class="<?= $classeTitulo ?>">
                    <h2 class="font-semibold text-emerald-400">Sistema</h2>
                </div>
                <div class="px-4 py-3 text-sm space-y-2">
                    <p id="saude" class="text-gray-300"></p>
                    <p id="filaResumo" class="text-xs text-gray-400"></p>
                    <div class="flex gap-2 pt-1">
                        <button type="button" id="btnFila" class="<?= $classeBotao ?> !py-1.5 !px-3 text-sm flex-1">🔔 Fila</button>
                        <button type="button" id="btnLogs" class="<?= $classeBotao ?> !py-1.5 !px-3 text-sm flex-1">📜 Logs</button>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- Modal único: o conteúdo é montado pelo dash.js -->
    <div id="modal" class="hidden fixed inset-0 z-[60] bg-black/70 backdrop-blur-sm justify-center items-center p-4">
        <div role="dialog" aria-modal="true" aria-labelledby="modalTitulo" class="bg-gray-800 border border-gray-700 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
            <div class="flex items-start justify-between gap-3 p-5 pb-3 border-b border-gray-700 flex-shrink-0">
                <h2 id="modalTitulo" class="text-lg font-bold min-w-0 break-words"></h2>
                <button type="button" data-fechar class="text-gray-400 hover:text-white text-2xl leading-none px-1" aria-label="Fechar">×</button>
            </div>
            <div id="modalCorpo" class="p-5 overflow-y-auto rolagem flex-1 min-h-0"></div>
            <div id="modalRodape" class="hidden flex flex-wrap justify-end gap-2 p-5 pt-3 border-t border-gray-700 flex-shrink-0"></div>
        </div>
    </div>

    <script>
        const CLASSE_BOTAO = <?= json_encode($classeBotao) ?>;
        const CLASSE_PRIMARIO = <?= json_encode($classePrimario) ?>;
    </script>
    <script src="dash.js"></script>
</body>
</html>
