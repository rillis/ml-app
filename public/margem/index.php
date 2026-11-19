<?php
require __DIR__ . '/../../src/auth.php';
$classeBotao = 'bg-gray-900 hover:bg-gray-700 border border-gray-700 hover:border-blue-500 text-gray-200 hover:text-blue-300 font-semibold py-2 px-4 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap';
$classePrimario = 'bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2 px-4 rounded-lg transition-colors shadow-lg disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap';
$classeCampo = 'bg-gray-900 border border-gray-700 rounded-lg p-2 text-base sm:text-sm text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500';
$classeCartao = 'bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 mb-6';
$classeTh = 'font-medium py-2 px-2 sm:px-3 cursor-pointer select-none hover:text-gray-200 whitespace-nowrap';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Margem</title>
    <script src="/assets/tailwind.js"></script>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen p-4 sm:p-8 font-sans selection:bg-blue-500 selection:text-white">
    <?php require __DIR__ . '/../../src/nav.php'; ?>
    <div class="max-w-6xl mx-auto">

        <header class="mb-4 flex flex-wrap justify-between items-end gap-4 border-b border-gray-700 pb-4">
            <div>
                <h1 class="text-4xl font-bold">
                    <span aria-hidden="true">📈</span>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400">Margem</span>
                </h1>
                <p id="subtitulo" class="text-sm text-gray-400 mt-1">Carregando…</p>
            </div>
        </header>

        <nav id="abas" class="flex gap-1 sm:gap-2 mb-6 overflow-x-auto [scrollbar-width:none]" aria-label="Seções">
            <button type="button" data-aba="margem">💲 Margem</button>
            <button type="button" data-aba="simulador">🧪 Simulador</button>
            <button type="button" data-aba="clonar">🧬 Clonar</button>
            <button type="button" data-aba="skus">🔍 SKUs</button>
            <button type="button" data-aba="envio">📦 Envio</button>
            <button type="button" data-aba="tabelas">📄 Tabelas</button>
            <button type="button" data-aba="acesso">🔑 Acesso</button>
        </nav>

        <!-- Tarefa em andamento (ou a última, até marcar como vista) -->
        <section id="painelJob" class="hidden bg-gray-800 border border-blue-500/40 rounded-xl p-4 sm:p-5 mb-6" aria-live="polite"></section>

        <!-- ===== MARGEM ===== -->
        <div data-secao="margem" class="hidden">
            <section class="<?= $classeCartao ?>">
                <div class="flex flex-wrap justify-between items-start gap-4">
                    <div class="min-w-0">
                        <p id="infoColeta" class="text-sm text-gray-300"></p>
                        <p id="infoMetas" class="text-sm text-gray-400 mt-1"></p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button id="btnAtualizar" type="button" class="<?= $classePrimario ?> job" title="Busca todos os anúncios no ML e gera a planilha">🔄 Atualizar margens</button>
                        <button id="btnParcial" type="button" class="<?= $classeBotao ?>" title="Busca de novo só os anúncios que você escolher">📝 Só alguns</button>
                        <button id="btnRecalcular" type="button" class="<?= $classeBotao ?> job" title="Gera a planilha de novo com a última busca, sem chamar o ML">🧮 Recalcular</button>
                        <button id="btnMetas" type="button" class="<?= $classeBotao ?>">🎯 Margem alvo</button>
                    </div>
                </div>
                <form id="formMetas" class="hidden mt-4 pt-4 border-t border-gray-700 flex flex-wrap items-end gap-3">
                    <label class="text-sm text-gray-400 flex flex-col gap-1">Lucro (%)
                        <input id="campoLucro" type="text" inputmode="decimal" class="<?= $classeCampo ?> w-28">
                    </label>
                    <label class="text-sm text-gray-400 flex flex-col gap-1">Gordura (%)
                        <input id="campoGordura" type="text" inputmode="decimal" class="<?= $classeCampo ?> w-28">
                    </label>
                    <label class="text-sm text-gray-400 flex flex-col gap-1" title="Alíquota da nota sobre o preço de venda">Imposto (%)
                        <input id="campoImposto" type="text" inputmode="decimal" class="<?= $classeCampo ?> w-28">
                    </label>
                    <p class="text-sm text-gray-400 self-center">= margem alvo <strong id="totalMeta" class="text-gray-200"></strong></p>
                    <button type="submit" class="<?= $classePrimario ?>">Salvar</button>
                    <button type="button" id="btnMetasCancelar" class="<?= $classeBotao ?>">Cancelar</button>
                </form>
                <form id="formParcial" class="hidden mt-4 pt-4 border-t border-gray-700">
                    <label for="campoParcial" class="block text-sm text-gray-300 mb-1">Atualizar só estes anúncios</label>
                    <p class="text-xs text-gray-500 mb-2">Cole os códigos dos anúncios, com ou sem MLB (separados por vírgula, espaço ou um por linha). Só eles são buscados de novo no ML e trocados na última busca; os outros anúncios ficam como estão.</p>
                    <textarea id="campoParcial" rows="3" placeholder="MLB6926478482, 6624766636" class="<?= $classeCampo ?> w-full font-mono"></textarea>
                    <div class="flex flex-wrap items-center gap-2 mt-2">
                        <button type="submit" class="<?= $classePrimario ?> job">🔄 Atualizar estes</button>
                        <button type="button" id="btnParcialCancelar" class="<?= $classeBotao ?>">Cancelar</button>
                        <span id="contagemParcial" class="text-sm text-gray-400"></span>
                    </div>
                </form>
            </section>

            <div id="resumoMargem" class="empty:hidden grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6"></div>

            <section class="<?= $classeCartao ?>">
                <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                    <h2 class="text-lg font-semibold text-gray-200">Anúncios <span id="contagemMargem" class="text-sm font-normal text-gray-500"></span></h2>
                    <a id="baixarMargem" class="<?= $classeBotao ?> text-sm hidden" href="#">⬇️ Planilha</a>
                </div>
                <div class="flex flex-wrap gap-3 mb-4">
                    <input id="buscaMargem" type="search" placeholder="Buscar SKU, título ou código" class="<?= $classeCampo ?> flex-1 min-w-[12rem]">
                    <!-- Margens: várias marcadas ao mesmo tempo; todas marcadas por padrão -->
                    <div id="filtroMargem" class="relative">
                        <button type="button" id="btnFiltroMargem" aria-haspopup="true" aria-expanded="false" aria-controls="opcoesMargem"
                                class="<?= $classeCampo ?> w-full sm:w-auto min-w-[12rem] flex items-center justify-between gap-3 text-left">
                            <span id="rotuloFiltroMargem">Todas as margens</span><span class="text-gray-400 text-xs" aria-hidden="true">▼</span>
                        </button>
                        <div id="opcoesMargem" class="hidden absolute z-30 right-0 sm:left-0 sm:right-auto mt-1 w-56 bg-gray-800 border border-gray-600 rounded-lg shadow-2xl p-1">
                            <?php foreach (['abaixo' => 'Abaixo da meta', 'na' => 'Na meta', 'acima' => 'Acima da meta', 'sem' => 'Sem custo', 'gratis' => 'Só itens grátis'] as $valor => $rotulo): ?>
                                <label class="flex items-center gap-2 px-3 py-2 rounded-md text-sm text-gray-200 hover:bg-gray-700 cursor-pointer select-none">
                                    <input type="checkbox" value="<?= $valor ?>" checked class="w-4 h-4 accent-blue-500"> <?= $rotulo ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <select id="statusMargem" class="<?= $classeCampo ?>">
                        <option value="">Todos os status</option>
                        <option value="active">Ativos</option>
                        <option value="paused">Pausados</option>
                        <option value="outros">Outros</option>
                    </select>
                    <select id="tipoMargem" class="<?= $classeCampo ?>">
                        <option value="">Clássico e Premium</option>
                        <option value="Clássico">Clássico</option>
                        <option value="Premium">Premium</option>
                    </select>
                    <!-- some enquanto a busca não tiver o tipo de envio (buscas de antes dessa coluna) -->
                    <select id="envioMargem" class="<?= $classeCampo ?> hidden">
                        <option value="">Full e normal</option>
                        <option value="Full">Full</option>
                        <option value="Normal">Normal</option>
                    </select>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-gray-400 border-b border-gray-700">
                            <tr>
                                <th class="text-left <?= $classeTh ?> pl-0" data-ordem="sku">Anúncio e SKU</th>
                                <th class="text-right <?= $classeTh ?>" data-ordem="preco">Preço</th>
                                <th class="text-right <?= $classeTh ?> hidden sm:table-cell" data-ordem="recebe" title="Preço − taxa do ML − frete − adicional (o &quot;Você recebe&quot; do ML)">Você recebe</th>
                                <th class="text-right <?= $classeTh ?> hidden md:table-cell" data-ordem="custo_desc" title="Custo do fornecedor com desconto">Custo</th>
                                <th class="text-right <?= $classeTh ?> hidden sm:table-cell" data-ordem="lucro">Lucro</th>
                                <th class="text-right <?= $classeTh ?>" data-ordem="margem">Margem</th>
                                <th class="text-right <?= $classeTh ?>" data-ordem="diferenca" title="Preço que dá a margem alvo e a diferença para o preço atual">Sugerido</th>
                                <th class="text-center <?= $classeTh ?> pr-0" data-ordem="dmeta" title="Distância da margem até a meta, em pontos percentuais (para mais ou para menos)">Δ meta</th>
                            </tr>
                        </thead>
                        <tbody id="tabelaMargem"></tbody>
                    </table>
                </div>
                <button id="btnMaisMargem" type="button" class="hidden mt-4 w-full <?= $classeBotao ?>">Mostrar todos</button>
            </section>

            <details id="avancado" class="<?= $classeCartao ?> group">
                <summary class="cursor-pointer text-lg font-semibold text-gray-200 select-none">Avançado</summary>
                <div class="mt-4 space-y-6">
                    <div>
                        <h3 class="font-semibold text-gray-300 mb-2">Buscar só uma parte dos anúncios</h3>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="<?= $classeBotao ?> job" data-status="active">Só ativos</button>
                            <button type="button" class="<?= $classeBotao ?> job" data-status="paused">Só pausados</button>
                            <button type="button" id="btnDoZero" class="<?= $classeBotao ?> job hidden">Começar a busca do zero</button>
                        </div>
                    </div>
                    <form id="formTeste">
                        <h3 class="font-semibold text-gray-300 mb-2">Testar com alguns anúncios</h3>
                        <p class="text-xs text-gray-500 mb-2">Não mexe na busca completa. Gera uma planilha de teste separada.</p>
                        <div class="flex flex-wrap gap-2">
                            <input id="campoIds" type="text" placeholder="MLB6926478482, MLB6624766636" class="<?= $classeCampo ?> flex-1 min-w-[14rem]">
                            <button type="submit" class="<?= $classeBotao ?> job">🧪 Testar</button>
                        </div>
                        <div id="resultadoTeste" class="mt-3"></div>
                    </form>
                    <form id="formAntiga">
                        <h3 class="font-semibold text-gray-300 mb-2">Recalcular com uma busca mais antiga</h3>
                        <div class="flex flex-wrap gap-2">
                            <select id="campoColeta" class="<?= $classeCampo ?> flex-1 min-w-[14rem]"></select>
                            <button type="submit" class="<?= $classeBotao ?> job">🧮 Recalcular</button>
                        </div>
                    </form>
                    <div>
                        <h3 class="font-semibold text-gray-300 mb-2">Planilhas geradas</h3>
                        <ul id="planilhasMargem" class="text-sm space-y-1"></ul>
                    </div>
                </div>
            </details>
        </div>

        <!-- ===== SIMULADOR ===== -->
        <div data-secao="simulador" class="hidden">
            <section class="<?= $classeCartao ?>">
                <h2 class="text-lg font-semibold text-gray-200">Simulador de preço</h2>
                <p id="infoSimular" class="text-sm text-gray-400 mt-1 mb-4">Preço sugerido de um SKU com a taxa, o frete e o adc que você digitar, pelo custo da tabela de preços.</p>
                <form id="formSimular" class="flex flex-wrap items-end gap-3">
                    <label class="text-sm text-gray-400 flex flex-col gap-1 flex-1 min-w-[14rem]">SKU
                        <input id="simSku" type="text" autocomplete="off" spellcheck="false" placeholder="3xKA-250+KF-104" class="<?= $classeCampo ?> font-mono">
                    </label>
                    <label class="text-sm text-gray-400 flex flex-col gap-1" title="Tarifa de venda do ML, em % do preço">Taxa ML (%)
                        <input id="simTaxa" type="text" inputmode="decimal" placeholder="16,5" class="<?= $classeCampo ?> w-24">
                    </label>
                    <label class="text-sm text-gray-400 flex flex-col gap-1" title="Custo de envio que o ML cobra de você">Frete (R$)
                        <input id="simFrete" type="text" inputmode="decimal" placeholder="0" class="<?= $classeCampo ?> w-24">
                    </label>
                    <label class="text-sm text-gray-400 flex flex-col gap-1" title="Custo fixo por venda (abaixo de R$ 79)">Adc (R$)
                        <input id="simAdc" type="text" inputmode="decimal" placeholder="0" class="<?= $classeCampo ?> w-24">
                    </label>
                    <button type="submit" class="<?= $classePrimario ?>">Calcular</button>
                </form>
                <div id="resultadoSimular" class="mt-4 empty:hidden" aria-live="polite"></div>
            </section>
            <!-- Conta rápida, separada do simulador: taxa em R$ ÷ valor total -->
            <section class="<?= $classeCartao ?>">
                <h2 class="text-lg font-semibold text-gray-200">Taxa em %</h2>
                <p class="text-sm text-gray-400 mt-1 mb-4">Taxa em R$ ÷ valor total.</p>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="text-sm text-gray-400 flex flex-col gap-1">Valor total (R$)
                        <input id="pctTotal" type="text" inputmode="decimal" placeholder="100,00" class="<?= $classeCampo ?> w-32">
                    </label>
                    <label class="text-sm text-gray-400 flex flex-col gap-1">Taxa (R$)
                        <input id="pctTaxa" type="text" inputmode="decimal" placeholder="16,50" class="<?= $classeCampo ?> w-32">
                    </label>
                    <button type="button" id="pctResultado" title="Clique para copiar" class="text-2xl font-bold tabular-nums text-gray-500 py-1 px-2 rounded-lg hover:bg-gray-700 cursor-copy">—</button>
                </div>
            </section>
        </div>

        <!-- ===== CLONAR ===== -->
        <div data-secao="clonar" class="hidden">
            <section class="<?= $classeCartao ?>">
                <div class="flex flex-wrap justify-between items-start gap-4">
                    <div class="min-w-0">
                        <p class="text-sm text-gray-300">Anúncios ativos e pausados sem a outra opção de venda (Clássico sem Premium ou Premium sem Clássico), comparando pelo SKU e pelo produto do ML.</p>
                        <p class="text-xs text-gray-500 mt-1">Criar (um ou até 20 marcados): a opção nova nasce no envio normal pelo preço da margem alvo (frete/adc do original) e usa o mesmo estoque do original. Depois o preço é conferido a cada 10 s até cravar a margem; se a tarifa ou o frete do original mudar e ele sair da margem, o preço dele também é ajustado.</p>
                        <p id="infoClonar" class="text-sm text-gray-400 mt-2"></p>
                    </div>
                    <button id="btnPares" type="button" class="<?= $classePrimario ?> job" title="Lê todos os anúncios ativos e pausados no ML (não muda nada)">🔍 Buscar anúncios sem par</button>
                </div>
            </section>

            <section class="<?= $classeCartao ?>">
                <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                    <h2 class="text-lg font-semibold text-gray-200">Sem par <span id="contagemClonar" class="text-sm font-normal text-gray-500"></span></h2>
                    <button id="btnClonarMarcados" type="button" class="<?= $classePrimario ?> job text-sm" disabled>➕ Criar marcados</button>
                </div>
                <div class="flex flex-wrap gap-3 mb-4">
                    <input id="buscaClonar" type="search" placeholder="Buscar SKU, título ou código" class="<?= $classeCampo ?> flex-1 min-w-[12rem]">
                    <select id="faltaClonar" class="<?= $classeCampo ?>">
                        <option value="">Falta qualquer um</option>
                        <option value="Premium">Falta Premium</option>
                        <option value="Clássico">Falta Clássico</option>
                    </select>
                    <select id="statusClonar" class="<?= $classeCampo ?>">
                        <option value="">Ativos e pausados</option>
                        <option value="active">Ativos</option>
                        <option value="paused">Pausados</option>
                        <option value="erro">Com erro ao criar</option>
                    </select>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-gray-400 border-b border-gray-700">
                            <tr>
                                <th class="py-2 pr-2 w-8"><input id="marcarClonar" type="checkbox" title="Marcar os visíveis (até 20)" class="w-4 h-4 accent-blue-500 cursor-pointer"></th>
                                <th class="text-left font-medium py-2 pr-3">Anúncio e SKU</th>
                                <th class="text-right font-medium py-2 px-2 sm:px-3">Preço</th>
                                <th class="text-right font-medium py-2 px-2 sm:px-3" title="Margem do original na última planilha">Margem</th>
                                <th class="text-right font-medium py-2 pl-2 sm:pl-3">Criar</th>
                            </tr>
                        </thead>
                        <tbody id="tabelaClonar"></tbody>
                    </table>
                </div>
            </section>

            <section class="<?= $classeCartao ?>">
                <h2 class="text-lg font-semibold text-gray-200 mb-4">Criados</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-gray-400 border-b border-gray-700">
                            <tr>
                                <th class="text-left font-medium py-2 pr-3">Original → novo</th>
                                <th class="text-right font-medium py-2 px-2 sm:px-3">Preço</th>
                                <th class="text-right font-medium py-2 px-2 sm:px-3">Margem</th>
                                <th class="text-right font-medium py-2 pl-2 sm:pl-3">Situação</th>
                            </tr>
                        </thead>
                        <tbody id="historicoClonar"></tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Criação em andamento: não fecha enquanto roda (uma criação por vez) -->
        <div id="modalClonar" class="hidden fixed inset-0 z-[60] bg-black/70 backdrop-blur-sm justify-center items-center p-4">
            <div role="dialog" aria-modal="true" aria-labelledby="clonarTitulo" class="bg-gray-800 border border-gray-700 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-5 sm:p-6">
                <h2 id="clonarTitulo" class="text-xl font-bold">Criando…</h2>
                <p id="clonarSub" class="text-sm text-gray-400"></p>
                <ul id="clonarLista" class="mt-4 space-y-2" aria-live="polite"></ul>
                <div class="flex flex-wrap justify-between items-center gap-3 mt-4">
                    <p id="clonarRodape" class="text-sm text-gray-500">Pode fechar esta aba do navegador: a criação continua no servidor.</p>
                    <button type="button" id="btnClonarFechar" class="<?= $classePrimario ?> hidden">Fechar</button>
                </div>
            </div>
        </div>

        <!-- Dica flutuante dos elementos com data-dica (a tabela tem rolagem própria e cortaria uma dica comum) -->
        <div id="dica" role="tooltip" class="hidden fixed z-[80] max-w-sm bg-gray-950 border border-gray-600 rounded-lg shadow-2xl px-3 py-2 text-xs text-gray-200 whitespace-pre-line pointer-events-none"></div>

        <!-- Formato de venda e quantidade (erro UNITS_PER_PACK): grava e tenta criar de novo -->
        <div id="modalFormato" class="hidden fixed inset-0 z-[70] bg-black/70 backdrop-blur-sm justify-center items-center p-4">
            <form id="formFormato" role="dialog" aria-modal="true" aria-labelledby="formatoTitulo" class="bg-gray-800 border border-gray-700 rounded-2xl shadow-2xl w-full max-w-md p-5 sm:p-6">
                <h2 id="formatoTitulo" class="text-xl font-bold">Formato de venda</h2>
                <p id="formatoSub" class="text-sm text-gray-400 mt-1"></p>
                <p class="text-xs text-gray-500 mt-2">O ML pede a quantidade sempre que o formato de venda está preenchido. Esses campos não aparecem no painel: são gravados pela API no produto e depois a criação tenta de novo.</p>
                <div class="flex flex-wrap items-end gap-3 mt-4">
                    <label class="text-sm text-gray-400 flex flex-col gap-1">Formato de venda
                        <select id="formatoTipo" class="<?= $classeCampo ?>">
                            <option value="unidade">Unidade</option>
                            <option value="pack">Kit / pack</option>
                        </select>
                    </label>
                    <label class="text-sm text-gray-400 flex flex-col gap-1">Quantidade
                        <input id="formatoQtd" type="number" min="1" max="100" step="1" required class="<?= $classeCampo ?> w-24">
                    </label>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" id="btnFormatoCancelar" class="<?= $classeBotao ?>">Cancelar</button>
                    <button type="submit" class="<?= $classePrimario ?> job">Salvar e tentar de novo</button>
                </div>
            </form>
        </div>

        <!-- Plano de correção do GTIN duplicado -->
        <div id="modalGtin" class="hidden fixed inset-0 z-[60] bg-black/70 backdrop-blur-sm justify-center items-center p-4">
            <div role="dialog" aria-modal="true" aria-labelledby="gtinTitulo" class="bg-gray-800 border border-gray-700 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-5 sm:p-6">
                <h2 id="gtinTitulo" class="text-xl font-bold">Corrigir GTIN duplicado</h2>
                <p id="gtinTexto" class="text-sm text-gray-300 mt-2"></p>
                <ul id="gtinLista" class="mt-4 space-y-2"></ul>
                <p class="text-xs text-gray-500 mt-4">Quem perde o GTIN fica com "Meu produto não o tem" e o motivo indicado, como no painel do ML. A edição vale para todos os anúncios do mesmo produto.</p>
                <div class="flex justify-end gap-3 mt-4">
                    <button type="button" id="btnGtinCancelar" class="<?= $classeBotao ?>">Cancelar</button>
                    <button type="button" id="btnGtinCorrigir" class="<?= $classePrimario ?> job">🔧 Corrigir</button>
                </div>
            </div>
        </div>

        <!-- ===== SKUS ===== -->
        <div data-secao="skus" class="hidden">
            <section class="<?= $classeCartao ?>">
                <div class="flex flex-wrap justify-between items-start gap-4">
                    <div class="min-w-0">
                        <p class="text-sm text-gray-300">Quais SKUs da tabela de preços não têm nenhum anúncio e quais não têm anúncio solo.</p>
                        <p id="infoSkus" class="text-sm text-gray-400 mt-1"></p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button id="btnSkus" type="button" class="<?= $classePrimario ?> job">🔍 Comparar SKUs</button>
                        <a id="baixarSkus" class="<?= $classeBotao ?> hidden" href="#">⬇️ Planilha</a>
                    </div>
                </div>
            </section>

            <div id="resumoSkus" class="empty:hidden grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6"></div>

            <section class="<?= $classeCartao ?>">
                <div class="flex flex-wrap gap-3 mb-4">
                    <input id="buscaSkus" type="search" placeholder="Buscar SKU" class="<?= $classeCampo ?> flex-1 min-w-[12rem]">
                    <select id="filtroSkus" class="<?= $classeCampo ?>">
                        <option value="pendentes">Sem anúncio ou sem solo</option>
                        <option value="sem anuncio">Sem anúncio</option>
                        <option value="sem solo">Sem solo</option>
                        <option value="ok">Ok</option>
                        <option value="todos">Todos</option>
                    </select>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-gray-400 border-b border-gray-700">
                            <tr>
                                <th class="text-left font-medium py-2 pr-3">SKU</th>
                                <th class="text-right font-medium py-2 px-2 sm:px-3" title="Anúncios só com este SKU / com outros SKUs, qualquer status">Anúncios solo / comp</th>
                                <th class="text-right font-medium py-2 px-2 sm:px-3" title="O mesmo, só dos anúncios ativos">Ativos solo / comp</th>
                                <th class="text-right font-medium py-2 pl-2 sm:pl-3">Situação</th>
                            </tr>
                        </thead>
                        <tbody id="tabelaSkus"></tbody>
                    </table>
                </div>
            </section>

            <section id="secaoForaTabela" class="<?= $classeCartao ?> hidden">
                <h2 class="text-lg font-semibold text-gray-200 mb-1">Anúncios com peça fora da tabela</h2>
                <p class="text-xs text-gray-500 mb-4">Para ligar essas peças à tabela, use o apelidos.xlsx (aba Tabelas).</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-gray-400 border-b border-gray-700">
                            <tr>
                                <th class="text-left font-medium py-2 pr-3">Anúncio e SKU</th>
                                <th class="text-left font-medium py-2 pl-3">Fora da tabela</th>
                            </tr>
                        </thead>
                        <tbody id="tabelaForaTabela"></tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- ===== ENVIO ===== -->
        <div data-secao="envio" class="hidden">
            <section class="<?= $classeCartao ?>">
                <h2 class="text-lg font-semibold text-gray-200 mb-1">Somar os SKUs de um envio do Full</h2>
                <p class="text-sm text-gray-400 mb-4">Envie o PDF de instruções de preparação (Inbound-…-preparation-instructions.pdf). Os kits são abertos e os SKUs iguais, somados.</p>
                <label id="areaEnvio" class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-gray-600 hover:border-blue-500 rounded-xl p-8 cursor-pointer text-gray-400 hover:text-blue-300 transition-colors text-center">
                    <span class="text-3xl" aria-hidden="true">📄</span>
                    <span id="textoEnvio">Clique para escolher o PDF ou arraste aqui</span>
                    <input id="arquivoEnvio" type="file" accept=".pdf,application/pdf" class="sr-only">
                </label>
            </section>
            <section id="resultadoEnvio" class="<?= $classeCartao ?> hidden">
                <div class="flex flex-wrap justify-between items-center gap-3 mb-3">
                    <div>
                        <h2 id="envioTitulo" class="text-lg font-semibold text-gray-200"></h2>
                        <p id="envioSub" class="text-sm text-gray-400"></p>
                    </div>
                    <button id="btnCopiarEnvio" type="button" class="<?= $classePrimario ?>">📋 Copiar</button>
                </div>
                <textarea id="envioTexto" readonly rows="14" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-3 font-mono text-sm text-gray-100 resize-y"></textarea>
            </section>
        </div>

        <!-- ===== TABELAS ===== -->
        <div data-secao="tabelas" class="hidden">
            <p class="text-sm text-gray-400 mb-4">As duas planilhas são usadas pela margem e pelo comparador de SKUs. Depois de trocar, recalcule a margem. A versão anterior fica guardada no servidor.</p>
            <div class="grid sm:grid-cols-2 gap-4 mb-6">
                <section class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6" data-tabela="precos">
                    <h2 class="text-lg font-semibold text-gray-200">precos.xlsx</h2>
                    <p class="text-sm text-gray-400 mb-1">Tabela do fornecedor: SKU e PRECO, sem o desconto.</p>
                    <p class="info text-sm text-gray-300 mb-4"></p>
                    <div class="flex flex-wrap gap-2">
                        <a class="<?= $classeBotao ?>" href="api.php?r=tabela&nome=precos">⬇️ Baixar</a>
                        <label class="<?= $classeBotao ?> cursor-pointer">⬆️ Trocar<input type="file" accept=".xlsx" class="sr-only"></label>
                    </div>
                </section>
                <section class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6" data-tabela="apelidos">
                    <h2 class="text-lg font-semibold text-gray-200">apelidos.xlsx</h2>
                    <p class="text-sm text-gray-400 mb-1">SKUs de anúncio que não dá para ligar à tabela automaticamente.</p>
                    <p class="info text-sm text-gray-300 mb-4"></p>
                    <div class="flex flex-wrap gap-2">
                        <a class="<?= $classeBotao ?>" href="api.php?r=tabela&nome=apelidos">⬇️ Baixar</a>
                        <label class="<?= $classeBotao ?> cursor-pointer">⬆️ Trocar<input type="file" accept=".xlsx" class="sr-only"></label>
                    </div>
                </section>
            </div>
        </div>

        <!-- ===== ACESSO ===== -->
        <div data-secao="acesso" class="hidden">
            <section class="<?= $classeCartao ?>">
                <h2 class="text-lg font-semibold text-gray-200 mb-1">Acesso ao ML</h2>
                <p class="text-xs text-gray-500 mb-3">App do ML próprio do ml-tools, separado do ml-jrf. O token renova sozinho.</p>
                <p id="infoToken" class="text-sm"></p>
                <p class="text-sm text-gray-400 mt-3">Só é preciso autorizar de novo se o ML recusar o acesso (ex.: 6 meses sem uso, permissão revogada).</p>
                <button id="btnGerarLink" type="button" class="<?= $classeBotao ?> mt-3">🔗 Gerar link de autorização</button>
                <form id="formAutorizar" class="hidden mt-4 pt-4 border-t border-gray-700 space-y-3">
                    <p class="text-sm text-gray-300">1. Abra o link logado na <strong>conta principal</strong> da loja e autorize:</p>
                    <a id="linkAutorizar" href="#" target="_blank" rel="noopener" class="block text-sm text-blue-400 hover:text-blue-300 break-all"></a>
                    <p class="text-sm text-gray-300">2. Copie a URL para onde o ML redirecionou e cole aqui:</p>
                    <div class="flex flex-wrap gap-2">
                        <input id="campoRetorno" type="text" placeholder="https://…?code=…&state=…" class="<?= $classeCampo ?> flex-1 min-w-[14rem]">
                        <button type="submit" class="<?= $classePrimario ?>">Concluir</button>
                    </div>
                </form>
            </section>
            <form id="formRps" class="<?= $classeCartao ?>">
                <h2 class="text-lg font-semibold text-gray-200 mb-1">Requisições por segundo</h2>
                <p class="text-sm text-gray-400 mb-3">Limite das buscas no ML. Vale a partir da próxima busca. Se aparecerem muitos 429 no fim da busca, diminua.</p>
                <div class="flex flex-wrap items-end gap-2">
                    <input id="campoRps" type="text" inputmode="decimal" class="<?= $classeCampo ?> w-32" aria-label="Requisições por segundo">
                    <button type="submit" class="<?= $classePrimario ?>">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const CLASSE_BOTAO = <?= json_encode($classeBotao) ?>;
        const CLASSE_PRIMARIO = <?= json_encode($classePrimario) ?>;
    </script>
    <script src="/assets/ui.js?v=<?= filemtime(__DIR__ . '/../assets/ui.js') ?>"></script>
    <script src="margem.js?v=<?= filemtime(__DIR__ . '/margem.js') ?>"></script>
</body>
</html>
