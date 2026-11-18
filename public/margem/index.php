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
                    <p class="text-xs text-gray-500 mb-2">Cole os códigos MLB (separados por vírgula, espaço ou um por linha). Só eles são buscados de novo no ML e trocados na última busca; os outros anúncios ficam como estão.</p>
                    <textarea id="campoParcial" rows="3" placeholder="MLB6926478482, MLB6624766636" class="<?= $classeCampo ?> w-full font-mono"></textarea>
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
                                <th class="text-right <?= $classeTh ?> pr-0" data-ordem="diferenca" title="Preço que dá a margem alvo e a diferença para o preço atual">Sugerido</th>
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
