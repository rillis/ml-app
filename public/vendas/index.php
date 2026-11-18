<?php
require __DIR__ . '/../../src/auth.php';
$classeSelect = 'bg-gray-900 border border-gray-700 rounded-lg p-2 text-base sm:text-sm text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500';
$classeCartao = 'bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 mb-6';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendas</title>
    <script src="/assets/tailwind.js"></script>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen p-4 sm:p-8 font-sans selection:bg-blue-500 selection:text-white">
    <?php require __DIR__ . '/../../src/nav.php'; ?>
    <div class="max-w-5xl mx-auto">

        <header class="mb-6 flex flex-wrap justify-between items-end gap-4 border-b border-gray-700 pb-4">
            <h1 class="text-4xl font-bold">
                <span aria-hidden="true">💰</span>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400">Vendas</span>
            </h1>
            <div class="flex items-center gap-3">
                <span id="statusSinc" class="text-sm text-gray-400"></span>
                <button id="btnAtualizar" onclick="atualizar()"
                        class="bg-blue-600 hover:bg-blue-500 disabled:opacity-60 disabled:cursor-wait text-white font-semibold py-2 px-4 rounded-lg transition-colors shadow-lg whitespace-nowrap">
                    🔄 Atualizar
                </button>
            </div>
        </header>

        <div id="atalhos" class="flex flex-wrap gap-1.5 sm:gap-2 mb-3" aria-label="Atalhos de período">
            <?php foreach (['full' => 'Full', 'mtd' => 'MtD', 'm1' => 'M-1', 'm2' => 'M-2', '15d' => '15D', '7d' => '7D'] as $chave => $nome): ?>
                <button type="button" data-atalho="<?= $chave ?>" onclick="aplicarAtalho('<?= $chave ?>')"
                        class="px-2.5 sm:px-3 py-1 rounded-lg text-sm font-semibold border border-gray-700 text-gray-300 hover:border-blue-500 hover:text-white transition-colors"><?= $nome ?></button>
            <?php endforeach; ?>
        </div>

        <div class="flex flex-wrap items-end gap-3 mb-6">
            <label class="text-sm text-gray-400 flex flex-col gap-1">De
                <input id="de" type="date" class="<?= $classeSelect ?>" onchange="renderizar()">
            </label>
            <label class="text-sm text-gray-400 flex flex-col gap-1">Até
                <input id="ate" type="date" class="<?= $classeSelect ?>" onchange="renderizar()">
            </label>
            <label class="text-sm text-gray-400 flex flex-col gap-1">Comparar com
                <select id="comparacao" class="<?= $classeSelect ?>" onchange="renderizar()">
                    <option value="anterior">Período anterior</option>
                    <option value="uteis" selected>Período anterior (dias úteis)</option>
                    <option value="ano">Mesmo período do ano passado</option>
                </select>
            </label>
            <div class="text-sm text-gray-400 flex flex-col gap-1">Logística
                <div id="menuLogistica" class="relative">
                    <button type="button" onclick="alternarMenuLogistica()" aria-haspopup="true" aria-expanded="false"
                            class="<?= $classeSelect ?> min-w-[9rem] flex items-center justify-between gap-3">
                        <span id="rotuloLogistica">Full e normal</span><span aria-hidden="true" class="text-gray-400 text-xs">▼</span>
                    </button>
                    <div id="opcoesLogistica" class="hidden absolute z-30 mt-1 left-0 min-w-full bg-gray-800 border border-gray-600 rounded-lg shadow-2xl p-1">
                        <label class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 text-gray-100 cursor-pointer whitespace-nowrap">
                            <input type="checkbox" value="1" checked onchange="aoMarcarLogistica(this)" class="w-4 h-4 accent-blue-500"> Full
                        </label>
                        <label class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 text-gray-100 cursor-pointer whitespace-nowrap">
                            <input type="checkbox" value="0" checked onchange="aoMarcarLogistica(this)" class="w-4 h-4 accent-blue-500"> Normal
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <p id="descricaoPeriodo" class="text-sm text-gray-400 mb-3"></p>
        <div id="indicadores" class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6"></div>

        <section class="<?= $classeCartao ?>">
            <div class="flex flex-wrap justify-between items-baseline gap-2 mb-4">
                <h2 class="text-lg font-semibold text-gray-200">Faturamento acumulado no período</h2>
                <div id="legendaAcumulado" class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-300"></div>
            </div>
            <div id="graficoAcumulado" class="relative"></div>
            <p id="notaAcumulado" class="text-xs text-gray-500 mt-1"></p>
        </section>

        <section class="<?= $classeCartao ?>">
            <div class="flex flex-wrap justify-between items-baseline gap-2 mb-4">
                <h2 class="text-lg font-semibold text-gray-200">Faturamento por mês</h2>
                <div id="legendaMensal" class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-300"></div>
            </div>
            <div id="graficoMensal" class="relative"></div>
        </section>

        <section class="<?= $classeCartao ?>">
            <div class="flex flex-wrap justify-between items-baseline gap-2 mb-1">
                <h2 class="text-lg font-semibold text-gray-200">Evolução da projeção do mês</h2>
                <div id="legendaEvolucao" class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-300"></div>
            </div>
            <p class="text-xs text-gray-500 mb-4">Mês atual inteiro, Full e normal, sem os filtros acima. Cada linha roxa é a projeção feita na manhã de um dia, com os dias completos até a véspera; a mais forte é a de hoje.</p>
            <div id="graficoEvolucao" class="relative"></div>
            <h3 class="text-sm font-semibold text-gray-300 mt-6 mb-2">Fechamento projetado em cada dia</h3>
            <div id="graficoFechamentos" class="relative"></div>
        </section>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <section class="<?= $classeCartao ?> !mb-0 md:!mb-6">
                <h2 class="text-lg font-semibold text-gray-200 mb-4">Full × normal</h2>
                <div id="divisaoLogistica"></div>
            </section>
            <section class="<?= $classeCartao ?>">
                <h2 class="text-lg font-semibold text-gray-200 mb-4">Premium × Clássico</h2>
                <div id="divisaoAnuncio"></div>
            </section>
        </div>

        <section class="<?= $classeCartao ?>">
            <div class="flex flex-wrap justify-between items-baseline gap-2 mb-4">
                <h2 class="text-lg font-semibold text-gray-200">Faturamento por dia da semana</h2>
                <div id="legendaSemana" class="flex gap-4 text-sm text-gray-300"></div>
            </div>
            <div id="graficoSemana" class="relative"></div>
        </section>

        <section class="<?= $classeCartao ?>">
            <div class="flex flex-wrap justify-between items-baseline gap-2 mb-4">
                <h2 class="text-lg font-semibold text-gray-200">Cancelamentos por mês</h2>
                <div id="legendaCancelamentos" class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-300"></div>
            </div>
            <div id="graficoCancelamentos" class="relative"></div>
        </section>

        <section class="<?= $classeCartao ?>">
            <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                <h2 class="text-lg font-semibold text-gray-200">SKUs no período</h2>
                <input id="buscaSku" placeholder="Buscar SKU ou título..." oninput="renderizarSkus()"
                       class="<?= $classeSelect ?> w-full sm:w-64">
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-gray-400 border-b border-gray-700">
                        <tr>
                            <th class="text-left font-medium py-2 pr-3">SKU</th>
                            <th class="text-right font-medium py-2 px-1.5 sm:px-3"><span class="sm:hidden">Un.</span><span class="hidden sm:inline">Unidades</span></th>
                            <th class="text-right font-medium py-2 px-1.5 sm:px-3">Faturamento</th>
                            <th class="text-right font-medium py-2 px-1.5 sm:px-3 whitespace-nowrap"><span class="sm:hidden">vs</span><span class="hidden sm:inline">vs comparação</span></th>
                            <th class="hidden sm:table-cell text-right font-medium py-2 pl-3">Full</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaSkus"></tbody>
                </table>
            </div>
            <button id="btnTodosSkus" onclick="mostrarTodosSkus = true; renderizarSkus()"
                    class="hidden mt-4 text-sm text-blue-400 hover:text-blue-300"></button>
        </section>
    </div>

    <div id="dica" class="hidden fixed z-50 pointer-events-none bg-gray-950 border border-gray-600 rounded-lg shadow-2xl px-3 py-2 text-sm text-gray-100"></div>

<script src="/assets/ui.js?v=<?= filemtime(__DIR__ . '/../assets/ui.js') ?>"></script>
<script>
// Mesma ordem de cores validada dos Insights (fundo #1f2937). A cor acompanha a logística, nunca a posição.
const COR_FULL = '#3987e5';
const COR_NORMAL = '#d95926';
// Próximas da mesma ordem validada, para o tipo de anúncio não repetir as cores da logística
const COR_PREMIUM = '#199e70';
const COR_CLASSICO = '#c98500';
const COR_ATUAL = '#3987e5';
// Faturamento por mês: realizado em azul, projeção do mês atual em roxo (claro = Full, escuro = normal)
const COR_REAL_FULL = '#93c5fd';
const COR_REAL_NORMAL = '#2f6fd6';
const COR_PROJ_FULL = '#c4b5fd';
const COR_PROJ_NORMAL = '#7c4ddb';
const COR_PROJECAO = '#a78bfa';
// Cancelamentos: realizado mais escuro, projeção do mês atual mais clara
const COR_CANCELADO = '#d04545';
const COR_CANCELADO_PROJ = '#f2a0a0';
// Erro médio do backtest (fev–out/2026) da projeção, pelo dia do mês
const ERRO_PROJECAO = [[3, 17.8], [7, 14.5], [10, 9.8], [15, 6.3], [20, 4.6], [25, 4.5]];
const COR_COMPARACAO = '#9ca3af';
const COR_FUNDO = '#1f2937';
const COR_GRADE = '#374151';
const COR_BASE = '#4b5563';
const COR_TEXTO_EIXO = '#9ca3af';
const COR_TEXTO_ROTULO = '#d1d5db';
const ALTURA_GRAFICO = 260;
const LIMITE_SKUS = 25;
const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

let dados = { dias: [], skus: [], titulos: {}, feriados: [], cancelados: [], sincronizacao: {} };
let feriados = new Set();
let periodoVisivel = null;
let mostrarTodosSkus = false;

const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
const moeda = v => Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const moedaCurta = v => v >= 1000 ? 'R$ ' + (v / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil' : moeda(v);
const milhar = (v, casas = 1) => v >= 1000 ? (v / 1000).toFixed(casas) + 'k' : String(Math.round(v));
const inteiro = v => Number(v).toLocaleString('pt-BR');
const pct = v => v.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%';
const soma = lista => lista.reduce((t, v) => t + v, 0);

// ===== Datas (strings AAAA-MM-DD no fuso do navegador) =====
const doisDig = n => String(n).padStart(2, '0');
const paraTexto = d => `${d.getFullYear()}-${doisDig(d.getMonth() + 1)}-${doisDig(d.getDate())}`;
const paraData = t => { const [a, m, d] = t.split('-').map(Number); return new Date(a, m - 1, d); };
const somarDias = (t, n) => { const d = paraData(t); d.setDate(d.getDate() + n); return paraTexto(d); };
const diasEntre = (a, b) => Math.round((paraData(b) - paraData(a)) / 86400000);
const ultimoDiaDoMes = (ano, mes) => new Date(ano, mes + 1, 0).getDate();
const dataCurta = t => { const d = paraData(t); return `${doisDig(d.getDate())}/${doisDig(d.getMonth() + 1)}/${d.getFullYear()}`; };
const mesCurto = chave => { const [a, m] = chave.split('-').map(Number); return `${MESES[m - 1]}/${String(a).slice(2)}`; };

function mudarMes(t, n) {
    const d = paraData(t);
    const alvo = new Date(d.getFullYear(), d.getMonth() + n, 1);
    alvo.setDate(Math.min(d.getDate(), ultimoDiaDoMes(alvo.getFullYear(), alvo.getMonth())));
    return paraTexto(alvo);
}

const ehUtil = t => { const d = paraData(t).getDay(); return d !== 0 && d !== 6 && !feriados.has(t); };
function contarUteis(de, ate) {
    let n = 0;
    for (let dia = de; dia <= ate; dia = somarDias(dia, 1)) if (ehUtil(dia)) n++;
    return n;
}

// Mês até hoje
function periodoPadrao() {
    const hoje = paraTexto(new Date());
    document.getElementById('de').value = hoje.slice(0, 8) + '01';
    document.getElementById('ate').value = hoje;
}

function periodos() {
    const hoje = paraTexto(new Date());
    let de = document.getElementById('de').value || hoje.slice(0, 8) + '01';
    let ate = document.getElementById('ate').value || hoje;
    if (de > ate) [de, ate] = [ate, de];
    const modo = document.getElementById('comparacao').value;

    let cDe, cAte, uteis = null;
    if (modo === 'ano') {
        cDe = mudarMes(de, -12); cAte = mudarMes(ate, -12);
    } else if (de.slice(8) === '01' && de.slice(0, 7) === ate.slice(0, 7)) {
        // Começa no dia 1 e fica dentro do mês: mesmos dias do mês anterior (1 a 17 contra 1 a 17)
        cDe = mudarMes(de, -1);
        const d = paraData(cDe);
        const ultimo = ultimoDiaDoMes(d.getFullYear(), d.getMonth());
        const mesInteiro = paraData(ate).getDate() === ultimoDiaDoMes(paraData(ate).getFullYear(), paraData(ate).getMonth());
        cAte = paraTexto(new Date(d.getFullYear(), d.getMonth(), mesInteiro ? ultimo : Math.min(paraData(ate).getDate(), ultimo)));
    } else {
        const tamanho = diasEntre(de, ate) + 1;
        cAte = somarDias(de, -1); cDe = somarDias(cAte, -(tamanho - 1));
    }

    const calendario = { de: cDe, ate: cAte }; // sem dias úteis: base do gráfico de acumulado
    if (modo === 'uteis') {
        // Mesmo início do período anterior, andando até juntar a mesma quantidade de dias úteis
        uteis = contarUteis(de, ate < hoje ? ate : hoje);
        cAte = cDe;
        let contados = ehUtil(cDe) ? 1 : 0;
        while (contados < uteis) {
            cAte = somarDias(cAte, 1);
            if (ehUtil(cAte)) contados++;
        }
        if (uteis === 0) cAte = cDe;
    }
    return { atual: { de, ate }, comparacao: { de: cDe, ate: cAte }, calendario, uteis };
}

// ===== Agregação =====
const logisticas = new Set([0, 1]); // 1 = Full, 0 = normal
const filtroLogistica = () => linha => logisticas.has(linha[2]);

function alternarMenuLogistica(abrir) {
    const painel = document.getElementById('opcoesLogistica');
    const aberto = abrir ?? painel.classList.contains('hidden');
    painel.classList.toggle('hidden', !aberto);
    document.querySelector('#menuLogistica button').setAttribute('aria-expanded', String(aberto));
}

function aoMarcarLogistica(caixa) {
    const valor = Number(caixa.value);
    if (!caixa.checked && logisticas.size === 1) { caixa.checked = true; return; } // pelo menos uma marcada
    caixa.checked ? logisticas.add(valor) : logisticas.delete(valor);
    document.getElementById('rotuloLogistica').textContent = logisticas.size === 2 ? 'Full e normal' : logisticas.has(1) ? 'Só Full' : 'Só normal';
    renderizar();
}

document.addEventListener('click', e => { if (!document.getElementById('menuLogistica').contains(e.target)) alternarMenuLogistica(false); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') alternarMenuLogistica(false); });

function resumo({ de, ate }) {
    const passa = filtroLogistica();
    const dias = dados.dias.filter(d => d[0] >= de && d[0] <= ate && logisticas.has(d[1]));
    const skus = dados.skus.filter(s => s[0] >= de && s[0] <= ate && passa(s));
    const faturamento = soma(dias.map(d => d[3]));
    const vendas = soma(dias.map(d => d[2]));
    return {
        faturamento, vendas,
        unidades: soma(skus.map(s => s[4])),
        ticket: vendas ? faturamento / vendas : 0,
        mediaDia: faturamento / (diasEntre(de, ate) + 1),
        skus, dias,
    };
}

function temDados({ de }) {
    return dados.dias.length > 0 && de >= dados.dias[0][0];
}

function variacao(atual, anterior, temBase) {
    if (!temBase || !anterior) return '<span class="text-gray-500">sem base de comparação</span>';
    const d = (atual - anterior) / anterior * 100;
    if (Math.abs(d) < 0.05) return '<span class="text-gray-400">= igual</span>';
    return d > 0 ? `<span class="text-emerald-400">▲ ${pct(d)}</span>` : `<span class="text-red-400">▼ ${pct(-d)}</span>`;
}

// ===== Telas =====
// ===== Atalhos de período =====
function datasDoAtalho(chave) {
    const hoje = paraTexto(new Date());
    const inicioMes = hoje.slice(0, 8) + '01';
    const mesInteiro = n => {
        const de = mudarMes(inicioMes, -n);
        const d = paraData(de);
        return [de, paraTexto(new Date(d.getFullYear(), d.getMonth() + 1, 0))];
    };
    return {
        full: [dados.dias.length ? dados.dias[0][0] : inicioMes, hoje],
        mtd: [inicioMes, hoje],
        m1: mesInteiro(1),
        m2: mesInteiro(2),
        '15d': [somarDias(hoje, -14), hoje],
        '7d': [somarDias(hoje, -6), hoje],
    }[chave];
}

function aplicarAtalho(chave) {
    const [de, ate] = datasDoAtalho(chave);
    document.getElementById('de').value = de;
    document.getElementById('ate').value = ate;
    renderizar();
}

// Destaca o atalho que bate com as datas escolhidas (vale também para datas digitadas)
function marcarAtalho({ de, ate }) {
    document.querySelectorAll('[data-atalho]').forEach(botao => {
        const [aDe, aAte] = datasDoAtalho(botao.dataset.atalho);
        const ativo = aDe === de && aAte === ate;
        botao.classList.toggle('bg-blue-600', ativo);
        botao.classList.toggle('border-blue-600', ativo);
        botao.classList.toggle('text-white', ativo);
        botao.setAttribute('aria-pressed', String(ativo));
    });
}

function renderizar() {
    const p = periodos();
    periodoVisivel = p;
    marcarAtalho(p.atual);
    const atual = resumo(p.atual);
    const comp = resumo(p.comparacao);
    const temBase = temDados(p.comparacao);

    document.getElementById('descricaoPeriodo').innerHTML =
        `${dataCurta(p.atual.de)} a ${dataCurta(p.atual.ate)} <span class="text-gray-500">· comparando com ${dataCurta(p.comparacao.de)} a ${dataCurta(p.comparacao.ate)}${p.uteis !== null ? ` (${p.uteis} dia${p.uteis === 1 ? '' : 's'} út${p.uteis === 1 ? 'il' : 'eis'} em cada)` : ''} · canceladas fora</span>`;

    const cartoes = [
        ['Faturamento', moeda(atual.faturamento), variacao(atual.faturamento, comp.faturamento, temBase), moeda(comp.faturamento)],
        ['Vendas', inteiro(atual.vendas), variacao(atual.vendas, comp.vendas, temBase), inteiro(comp.vendas)],
        ['Unidades', inteiro(atual.unidades), variacao(atual.unidades, comp.unidades, temBase), inteiro(comp.unidades)],
        ['Ticket médio', moeda(atual.ticket), variacao(atual.ticket, comp.ticket, temBase), moeda(comp.ticket)],
        ['Média por dia', moeda(atual.mediaDia), variacao(atual.mediaDia, comp.mediaDia, temBase), moeda(comp.mediaDia)],
    ];
    document.getElementById('indicadores').innerHTML = cartoes.map(([rotulo, valor, detalhe, antes]) => `
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 min-w-0">
            <div class="text-xs uppercase tracking-wide text-gray-400">${rotulo}</div>
            <div class="text-lg sm:text-xl font-bold tabular-nums mt-1 whitespace-nowrap">${valor}</div>
            <div class="text-xs mt-1 tabular-nums">${detalhe}</div>
            ${temBase ? `<div class="text-xs text-gray-500 mt-0.5 tabular-nums">antes: ${antes}</div>` : ''}
        </div>`).join('');

    renderizarMensal();
    renderizarEvolucao();
    renderizarAcumulado(p);
    renderizarDivisoes(atual, comp, temBase);
    renderizarSemana(p.atual);
    renderizarCancelamentos();
    mostrarTodosSkus = false;
    renderizarSkus();
}

function escalaY(maximo) {
    if (maximo <= 0) return { topo: 1, passos: [0, 1] };
    const bruto = maximo / 4;
    const potencia = Math.pow(10, Math.floor(Math.log10(bruto)));
    const passo = [1, 2, 2.5, 5, 10].map(m => m * potencia).find(v => v >= bruto);
    const topo = Math.ceil(maximo / passo) * passo;
    const passos = [];
    for (let v = 0; v <= topo + passo / 2; v += passo) passos.push(v);
    return { topo, passos };
}

function grade(passos, y, esquerda, direita) {
    return passos.map(v => `
        <line x1="${esquerda}" x2="${direita}" y1="${y(v).toFixed(1)}" y2="${y(v).toFixed(1)}" stroke="${v === 0 ? COR_BASE : COR_GRADE}" stroke-width="1"/>
        <text x="${esquerda - 8}" y="${(y(v) + 4).toFixed(1)}" text-anchor="end" fill="${COR_TEXTO_EIXO}" font-size="11" style="font-variant-numeric:tabular-nums">${v ? moedaCurta(v) : '0'}</text>`).join('');
}

function mostrarDica(evento, html) {
    const dica = document.getElementById('dica');
    dica.innerHTML = html;
    dica.classList.remove('hidden');
    const largura = dica.offsetWidth;
    let x = evento.clientX + 14;
    if (x + largura > window.innerWidth - 8) x = evento.clientX - largura - 14;
    dica.style.left = Math.max(8, x) + 'px';
    dica.style.top = Math.max(8, evento.clientY - dica.offsetHeight - 12) + 'px';
}
const esconderDica = () => document.getElementById('dica').classList.add('hidden');
const linhaDica = (cor, nome, valor) => `<div class="flex justify-between gap-4"><span class="flex items-center gap-2"><span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:${cor}"></span>${esc(nome)}</span><span class="tabular-nums">${valor}</span></div>`;
// Barras: quadrado. Linhas (linha = true): traço, contínuo ou tracejado como no gráfico.
// estilo: '' contínua, 'tracejado' ou 'pontilhado'
const legenda = itens => itens.map(([cor, nome, linha, estilo]) => `<span class="flex items-center gap-2"><span class="inline-block ${linha ? 'w-5 border-t-2' + (estilo === 'tracejado' ? ' border-dashed' : estilo === 'pontilhado' ? ' border-dotted' : '') : 'w-2.5 h-2.5 rounded-sm'}" style="${linha ? 'border-color:' + cor : 'background:' + cor}"></span>${nome}</span>`).join('');

// ===== Projeção do mês atual =====
// Média de 3 métodos (melhor resultado no backtest): perfil da semana, ritmo dos últimos 28 dias e dos últimos 7.
// Usa só dias completos (até ontem). O restante previsto é dividido entre Full e normal pelo mix do mês até agora.
const tipoDia = t => feriados.has(t) ? 6 : (paraData(t).getDay() + 6) % 7; // feriado conta como domingo

function faturamentoPorDia() {
    const mapa = new Map();
    dados.dias.forEach(([dia, full, , fat]) => {
        if (!mapa.has(dia)) mapa.set(dia, [0, 0]);
        mapa.get(dia)[full] += fat;
    });
    return mapa;
}

// Peso de cada dia da semana nas 8 semanas que terminam em `ate` (1 = média)
function pesosSemana(ate, valor) {
    const grupos = [[], [], [], [], [], [], []];
    for (let i = 0; i < 56; i++) {
        const dia = somarDias(ate, -i);
        grupos[tipoDia(dia)].push(valor(dia));
    }
    const media = soma(grupos.flat()) / 56;
    return grupos.map(g => (g.length && media ? soma(g) / g.length / media : 1));
}

function erroProjecao(dia) {
    if (dia <= ERRO_PROJECAO[0][0]) return ERRO_PROJECAO[0][1];
    for (let k = 1; k < ERRO_PROJECAO.length; k++) {
        const [d1, e1] = ERRO_PROJECAO[k - 1], [d2, e2] = ERRO_PROJECAO[k];
        if (dia <= d2) return e1 + (e2 - e1) * (dia - d1) / (d2 - d1);
    }
    return ERRO_PROJECAO[ERRO_PROJECAO.length - 1][1];
}

// Projeção do fechamento do mês feita na manhã de `hoje`: só enxerga os dias completos (até a véspera).
// valor(dia) = faturamento do dia. Devolve null se ainda não há base para nenhum método.
function projetarMes(hoje, valor) {
    const inicio = hoje.slice(0, 8) + '01';
    const d = paraData(hoje);
    const N = ultimoDiaDoMes(d.getFullYear(), d.getMonth());
    const dias = Array.from({ length: N }, (_, i) => somarDias(inicio, i));
    const ontem = somarDias(hoje, -1);
    const c = d.getDate() - 1; // dias completos no mês
    const completos = dias.slice(0, c);
    const mtd = soma(completos.map(valor));
    const pesoInicio = pesosSemana(somarDias(inicio, -1), valor);
    const pesoOntem = pesosSemana(ontem, valor);
    const pesoDe = (pesos, lista) => soma(lista.map(x => pesos[tipoDia(x)]));

    const metodos = [];
    const decorrido = pesoDe(pesoInicio, completos);
    if (decorrido > 0) metodos.push(mtd / decorrido * pesoDe(pesoInicio, dias));
    const pesoRestante = pesoDe(pesoOntem, dias.slice(c));
    [28, 7].forEach(n => {
        const ultimos = Array.from({ length: n }, (_, i) => somarDias(ontem, -i));
        const base = pesoDe(pesoOntem, ultimos);
        if (base > 0) metodos.push(mtd + soma(ultimos.map(valor)) / base * pesoRestante);
    });
    if (!metodos.length) return null;
    return { total: soma(metodos) / metodos.length, dias, c, N, mtd, pesoOntem };
}

function projecaoM0() {
    const hoje = paraTexto(new Date());
    const porDia = faturamentoPorDia();
    const valor = dia => { const v = porDia.get(dia); return v ? v[0] + v[1] : 0; };
    const base = projetarMes(hoje, valor);
    if (!base) return null;
    const { total, dias, c, N, pesoOntem } = base;
    const real = [0, 0];
    dias.slice(0, c + 1).forEach(x => { const v = porDia.get(x); if (v) { real[0] += v[0]; real[1] += v[1]; } });
    const realizado = real[0] + real[1];
    const restante = Math.max(0, total - realizado);
    const futuros = dias.slice(c + 1);
    const pesosFuturos = futuros.map(x => pesoOntem[tipoDia(x)]);
    const somaFuturos = soma(pesosFuturos);
    return {
        mes: hoje.slice(0, 7), hojeIdx: c, N, real, realizado, total, restante,
        mix: realizado ? real[1] / realizado : 0, // participação do Full no realizado do mês
        diario: pesosFuturos.map(pf => (somaFuturos ? restante * pf / somaFuturos : 0)),
        erro: erroProjecao(c + 1),
    };
}

// Parte do restante previsto que cabe nas logísticas marcadas
const parteProjetada = proj => (logisticas.has(1) ? proj.mix : 0) + (logisticas.has(0) ? 1 - proj.mix : 0);

function renderizarMensal() {
    const area = document.getElementById('graficoMensal');
    const porMes = new Map();
    dados.dias.forEach(([dia, full, , fat]) => {
        const chave = dia.slice(0, 7);
        if (!porMes.has(chave)) porMes.set(chave, [0, 0]);
        porMes.get(chave)[full] += fat;
    });
    if (!porMes.size) { area.innerHTML = '<p class="text-sm text-gray-500 py-6 text-center">Sem vendas guardadas. Use o botão Atualizar.</p>'; return; }

    const proj = projecaoM0();
    const mesAtual = paraTexto(new Date()).slice(0, 7);
    // Preenche meses sem venda para o eixo não pular
    const meses = [];
    for (let m = [...porMes.keys()][0] + '-01'; m.slice(0, 7) <= mesAtual; m = mudarMes(m, 1)) meses.push(m.slice(0, 7));

    // De baixo para cima, sempre: normal, projeção normal, Full, projeção Full
    const series = [
        { nome: 'Normal', cor: COR_REAL_NORMAL, full: 0, valor: m => (porMes.get(m) || [0, 0])[0] },
        { nome: 'Projeção normal', cor: COR_PROJ_NORMAL, full: 0, proj: true, valor: m => (proj && m === proj.mes ? proj.restante * (1 - proj.mix) : 0) },
        { nome: 'Full', cor: COR_REAL_FULL, full: 1, valor: m => (porMes.get(m) || [0, 0])[1] },
        { nome: 'Projeção Full', cor: COR_PROJ_FULL, full: 1, proj: true, valor: m => (proj && m === proj.mes ? proj.restante * proj.mix : 0) },
    ].filter(s => logisticas.has(s.full) && (!s.proj || (proj && proj.restante > 0)));
    document.getElementById('legendaMensal').innerHTML = legenda(series.map(s => [s.cor, s.nome]));
    const totais = meses.map(m => soma(series.map(s => s.valor(m))));

    const largura = area.clientWidth;
    const margem = { esquerda: 76, direita: 8, topo: 18, baixo: 28 };
    const util = largura - margem.esquerda - margem.direita;
    const alturaUtil = ALTURA_GRAFICO - margem.topo - margem.baixo;
    const { topo, passos } = escalaY(Math.max(...totais));
    const y = v => margem.topo + alturaUtil - v / topo * alturaUtil;
    const faixa = util / meses.length;
    const barra = Math.min(24, faixa * 0.6);
    const pularRotulo = Math.ceil(meses.length / Math.max(1, Math.floor(util / 48)));

    const barras = meses.map((mes, i) => {
        const x = margem.esquerda + faixa * i + (faixa - barra) / 2;
        const valores = series.map(s => s.valor(mes));
        const ultimo = valores.reduce((u, v, k) => (v > 0 ? k : u), -1);
        let base = 0;
        const partes = series.map((s, k) => {
            const v = valores[k];
            if (v <= 0) return '';
            // 2px de respiro entre segmentos; só o segmento do topo tem a ponta arredondada
            const folga = base > 0 ? 2 : 0;
            const yBase = y(base) - folga, yTopo = y(base + v);
            base += v;
            const alto = yBase - yTopo;
            if (alto <= 0) return '';
            const r = Math.min(4, alto, barra / 2);
            return k === ultimo
                ? `<path d="M${x},${yBase} V${yTopo + r} Q${x},${yTopo} ${x + r},${yTopo} H${x + barra - r} Q${x + barra},${yTopo} ${x + barra},${yTopo + r} V${yBase} Z" fill="${s.cor}"/>`
                : `<rect x="${x}" y="${yTopo}" width="${barra}" height="${alto}" fill="${s.cor}"/>`;
        }).join('');
        const rotulo = (meses.length - 1 - i) % pularRotulo === 0
            ? `<text x="${(i === meses.length - 1 ? x + barra + 4 : x + barra / 2).toFixed(1)}" y="${ALTURA_GRAFICO - 8}" text-anchor="${i === meses.length - 1 ? 'end' : 'middle'}" fill="${COR_TEXTO_EIXO}" font-size="11">${mesCurto(mes)}${mes === mesAtual ? '*' : ''}</text>` : '';
        // Total no topo da coluna, resumido (96.9k); em coluna estreita sem a casa decimal
        const total = totais[i];
        const topoTexto = total > 0
            ? `<text x="${(x + barra / 2).toFixed(1)}" y="${(y(total) - 5).toFixed(1)}" text-anchor="middle" fill="${COR_TEXTO_ROTULO}" font-size="${faixa < 40 ? 9 : 11}" style="font-variant-numeric:tabular-nums">${milhar(total, faixa < 40 ? 0 : 1)}</text>` : '';
        return `${partes}${topoTexto}${rotulo}<rect data-alvo="${i}" x="${margem.esquerda + faixa * i}" y="${margem.topo}" width="${faixa}" height="${alturaUtil}" fill="transparent"/>`;
    }).join('');

    area.innerHTML = `<svg width="${largura}" height="${ALTURA_GRAFICO}" role="img" aria-label="Faturamento por mês, ${mesCurto(meses[0])} a ${mesCurto(meses[meses.length - 1])}${proj ? ', com a projeção do mês atual' : ''}" class="block">
        ${grade(passos, y, margem.esquerda, largura - margem.direita)}${barras}</svg>
        <p class="text-xs text-gray-500 mt-1">* mês em andamento${proj ? ` · projeção: ${moeda(totais[meses.length - 1])} (± ${pct(proj.erro)}, erro médio histórico neste ponto do mês)` : ''}</p>`;

    area.querySelectorAll('[data-alvo]').forEach(alvo => {
        const i = Number(alvo.dataset.alvo);
        alvo.addEventListener('pointermove', e => {
            const mes = meses[i];
            const ehProjecao = proj && mes === proj.mes;
            const reais = series.filter(s => !s.proj), projetadas = series.filter(s => s.proj);
            const realizado = soma(reais.map(s => s.valor(mes)));
            let html = `<div class="font-semibold mb-1">${mesCurto(mes)}${mes === mesAtual ? ' (parcial)' : ''}</div>`
                + [...reais].reverse().map(s => linhaDica(s.cor, s.nome, moeda(s.valor(mes)))).join('')
                + `<div class="flex justify-between gap-4 border-t border-gray-700 mt-1 pt-1"><span>${ehProjecao ? 'Realizado' : 'Total'}</span><span class="tabular-nums">${moeda(realizado)}</span></div>`;
            if (ehProjecao && projetadas.length) {
                html += `<div class="mt-2"></div>` + [...projetadas].reverse().map(s => linhaDica(s.cor, s.nome, '+ ' + moeda(s.valor(mes)))).join('')
                    + `<div class="flex justify-between gap-4 border-t border-gray-700 mt-1 pt-1 font-semibold"><span>Projeção do mês</span><span class="tabular-nums">${moeda(totais[i])}</span></div>`
                    + `<div class="text-xs text-gray-400 mt-1">± ${pct(proj.erro)}: ${moeda(totais[i] * (1 - proj.erro / 100))} a ${moeda(totais[i] * (1 + proj.erro / 100))}</div>`;
            }
            mostrarDica(e, html);
        });
        alvo.addEventListener('pointerleave', esconderDica);
    });
}

// Acumulado dia a dia de `de` até `ate` (null depois de `limite`), nas logísticas marcadas
function acumuladoDe(porDia, de, ate, limite) {
    const valores = [];
    let total = 0;
    for (let dia = de; dia <= ate; dia = somarDias(dia, 1)) {
        if (limite && dia > limite) { valores.push(null); continue; }
        const v = porDia.get(dia);
        if (v) total += (logisticas.has(0) ? v[0] : 0) + (logisticas.has(1) ? v[1] : 0);
        valores.push(total);
    }
    return valores;
}

const MESES_LONGOS = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
const mesLongo = t => `${MESES_LONGOS[paraData(t).getMonth()]}/${t.slice(0, 4)}`;

// A comparação aqui ignora os dias úteis: período anterior (ou ano passado) no calendário.
// Período que começa no dia 1 de um mês vira o mês inteiro, com a comparação também inteira.
function renderizarAcumulado(p) {
    const area = document.getElementById('graficoAcumulado');
    const hoje = paraTexto(new Date());
    const porDia = faturamentoPorDia();
    const { de, ate } = p.atual;
    const mesAlinhado = de.slice(8) === '01' && de.slice(0, 7) === ate.slice(0, 7);

    let fimEixo = ate, compDe = p.calendario.de, compAte = p.calendario.ate, nota;
    if (mesAlinhado) {
        const d = paraData(de), c = paraData(compDe);
        fimEixo = paraTexto(new Date(d.getFullYear(), d.getMonth() + 1, 0));
        compAte = paraTexto(new Date(c.getFullYear(), c.getMonth() + 1, 0));
        nota = `Cinza: ${mesLongo(compDe)} inteiro.`;
    } else {
        nota = `Cinza: ${dataCurta(compDe)} a ${dataCurta(compAte)}.`;
    }
    if (document.getElementById('comparacao').value === 'uteis') nota += ' Aqui a comparação é pelo calendário, sem a regra dos dias úteis.';

    const atual = acumuladoDe(porDia, de, fimEixo, ate < hoje ? ate : hoje);
    const series = [{ nome: 'Realizado', cor: COR_ATUAL, valores: atual, de }];

    const proj = projecaoM0();
    if (proj && mesAlinhado && de.slice(0, 7) === proj.mes && ate >= hoje) {
        const parte = parteProjetada(proj);
        const valores = atual.map(() => null);
        let total = atual[proj.hojeIdx];
        valores[proj.hojeIdx] = total;
        proj.diario.forEach((v, k) => { total += v * parte; valores[proj.hojeIdx + 1 + k] = total; });
        if (proj.diario.length) {
            series.push({ nome: 'Projeção', cor: COR_PROJECAO, valores, de, estilo: 'pontilhado' });
            nota += ` Roxo: projeção até o fim do mês (± ${pct(proj.erro)}).`;
        }
    }
    const temBase = temDados({ de: compDe });
    if (temBase) series.push({ nome: 'Comparação', cor: COR_COMPARACAO, valores: acumuladoDe(porDia, compDe, compAte), de: compDe, estilo: 'tracejado' });
    else nota = 'Sem dados guardados para a comparação.';
    document.getElementById('legendaAcumulado').innerHTML = series.length > 1 ? legenda(series.map(s => [s.cor, s.nome, true, s.estilo || ''])) : '';
    document.getElementById('notaAcumulado').textContent = nota;

    const tamanho = Math.max(...series.map(s => s.valores.length));
    const largura = area.clientWidth;
    const margem = { esquerda: 76, direita: 16, topo: 20, baixo: 28 };
    const util = largura - margem.esquerda - margem.direita;
    const alturaUtil = ALTURA_GRAFICO - margem.topo - margem.baixo;
    const { topo, passos } = escalaY(Math.max(1, ...series.flatMap(s => s.valores.filter(v => v !== null))));
    const x = i => margem.esquerda + (tamanho > 1 ? i / (tamanho - 1) * util : util / 2);
    const y = v => margem.topo + alturaUtil - v / topo * alturaUtil;
    const ESTILOS = { tracejado: 'stroke-dasharray="5 4"', pontilhado: 'stroke-dasharray="1 5"' };

    const linhas = series.map(s => {
        const pontos = s.valores.map((v, i) => (v === null || v === undefined ? null : `${x(i).toFixed(1)},${y(v).toFixed(1)}`)).filter(Boolean);
        return `<polyline points="${pontos.join(' ')}" fill="none" stroke="${s.cor}" stroke-width="${s.estilo === 'pontilhado' ? 2.5 : 2}" stroke-linejoin="round" stroke-linecap="round" ${ESTILOS[s.estilo] || ''}/>`;
    }).join('');
    const fim = s => { for (let i = s.valores.length - 1; i >= 0; i--) if (s.valores[i] !== null && s.valores[i] !== undefined) return i; return -1; };
    const rotulo = (px, py, texto, ancora) => `<text x="${px.toFixed(1)}" y="${py.toFixed(1)}" text-anchor="${ancora}" fill="${COR_TEXTO_ROTULO}" font-size="11" stroke="${COR_FUNDO}" stroke-width="3" style="font-variant-numeric:tabular-nums;paint-order:stroke">${esc(texto)}</text>`;
    // Rótulos nas pontas: do mais alto para o mais baixo, empurrando para baixo quando colidem
    const pontas = series.map(s => ({ s, i: fim(s) })).filter(c => c.i >= 0)
        .filter(c => !(c.s.nome === 'Realizado' && series.some(o => o.nome === 'Projeção'))) // o realizado continua na projeção
        .sort((a, b) => b.s.valores[b.i] - a.s.valores[a.i]);
    let ultimoY = -Infinity;
    const finais = pontas.map(({ s, i }) => {
        const py = Math.max(y(s.valores[i]) - 10, ultimoY + 14);
        ultimoY = py;
        const ancora = i > tamanho / 2 ? 'end' : 'start';
        return `<circle cx="${x(i).toFixed(1)}" cy="${y(s.valores[i]).toFixed(1)}" r="4" fill="${s.cor}" stroke="${COR_FUNDO}" stroke-width="2"/>`
            + rotulo(x(i) + (ancora === 'end' ? -8 : 8), py, moedaCurta(s.valores[i]), ancora);
    }).join('');
    const marcaHoje = series.some(s => s.nome === 'Projeção') && proj
        ? `<circle cx="${x(proj.hojeIdx).toFixed(1)}" cy="${y(atual[proj.hojeIdx]).toFixed(1)}" r="4" fill="${COR_ATUAL}" stroke="${COR_FUNDO}" stroke-width="2"/>` : '';
    const rotuloX = i => (mesAlinhado ? String(i + 1) : dataCurta(somarDias(de, i)).slice(0, 5));
    const pular = Math.ceil(tamanho / Math.max(1, Math.floor(util / (mesAlinhado ? 30 : 56))));
    const eixo = Array.from({ length: tamanho }, (_, i) => i).filter(i => i % pular === 0 || i === tamanho - 1)
        .filter(i => i === tamanho - 1 || tamanho - 1 - i >= pular / 2)
        .map(i => `<text x="${x(i).toFixed(1)}" y="${ALTURA_GRAFICO - 8}" text-anchor="${i === 0 && tamanho > 1 ? 'start' : i === tamanho - 1 && tamanho > 1 ? 'end' : 'middle'}" fill="${COR_TEXTO_EIXO}" font-size="11">${rotuloX(i)}</text>`).join('');

    area.innerHTML = `<svg width="${largura}" height="${ALTURA_GRAFICO}" role="img" aria-label="Faturamento acumulado dia a dia${series.length > 1 ? ', com ' + series.slice(1).map(s => s.nome.toLowerCase()).join(' e ') : ''}" class="block" style="touch-action:pan-y">
        ${grade(passos, y, margem.esquerda, largura - margem.direita)}${linhas}${marcaHoje}${finais}${eixo}
        <g data-marcador class="hidden"><line data-mira y1="${margem.topo}" y2="${margem.topo + alturaUtil}" stroke="${COR_TEXTO_EIXO}" stroke-width="1"/>
            ${series.map((s, k) => `<circle data-ponto="${k}" r="4" fill="${s.cor}" stroke="${COR_FUNDO}" stroke-width="2"/>`).join('')}</g>
        <rect data-alvo x="${margem.esquerda - 10}" y="0" width="${util + 20}" height="${ALTURA_GRAFICO - margem.baixo}" fill="transparent"/></svg>`;

    const svg = area.querySelector('svg');
    const marcador = svg.querySelector('[data-marcador]');
    svg.querySelector('[data-alvo]').addEventListener('pointermove', e => {
        const rel = e.clientX - svg.getBoundingClientRect().left;
        const i = Math.min(tamanho - 1, Math.max(0, Math.round((rel - margem.esquerda) / (util / Math.max(1, tamanho - 1)))));
        marcador.classList.remove('hidden');
        const mira = marcador.querySelector('[data-mira]');
        mira.setAttribute('x1', x(i)); mira.setAttribute('x2', x(i));
        const visivel = s => s.valores[i] !== null && s.valores[i] !== undefined;
        series.forEach((s, k) => {
            const ponto = marcador.querySelector(`[data-ponto="${k}"]`);
            ponto.classList.toggle('hidden', !visivel(s));
            if (visivel(s)) { ponto.setAttribute('cx', x(i)); ponto.setAttribute('cy', y(s.valores[i])); }
        });
        mostrarDica(e, series.filter(visivel).map(s => linhaDica(s.cor, `${s.nome} · ${dataCurta(somarDias(s.de, i)).slice(0, 5)}`, moeda(s.valores[i]))).join('') || '<span class="text-gray-400">Sem dados neste dia</span>');
    });
    svg.addEventListener('pointerleave', () => { marcador.classList.add('hidden'); esconderDica(); });
}

// ===== Evolução da projeção (imune aos filtros) =====
function renderizarEvolucao() {
    const area = document.getElementById('graficoEvolucao');
    const areaFech = document.getElementById('graficoFechamentos');
    const porDia = faturamentoPorDia();
    const valor = dia => { const v = porDia.get(dia); return v ? v[0] + v[1] : 0; };
    const hoje = paraTexto(new Date());
    const inicio = hoje.slice(0, 8) + '01';
    const diaHoje = paraData(hoje).getDate();
    const N = ultimoDiaDoMes(paraData(hoje).getFullYear(), paraData(hoje).getMonth());
    const inicioAnt = mudarMes(inicio, -1);
    const Nant = ultimoDiaDoMes(paraData(inicioAnt).getFullYear(), paraData(inicioAnt).getMonth());

    // Índice j = acumulado ao fim do dia j (j = 0 é o começo do mês, zero)
    const acumular = (de, quantos) => { const v = [0]; for (let k = 0; k < quantos; k++) v.push(v[k] + valor(somarDias(de, k))); return v; };
    const realizado = acumular(inicio, diaHoje);   // até hoje (hoje ainda parcial)
    const anterior = acumular(inicioAnt, Nant);

    // Projeção feita na manhã do dia r: parte do acumulado até r-1 e distribui o resto pelos dias r..N
    const projecoes = [];
    for (let r = 1; r <= diaHoje; r++) {
        const base = projetarMes(somarDias(inicio, r - 1), valor);
        if (!base) continue;
        const pesos = base.dias.slice(r - 1).map(x => base.pesoOntem[tipoDia(x)]);
        const somaPesos = soma(pesos);
        const restante = Math.max(0, base.total - realizado[r - 1]);
        const valores = Array(N + 1).fill(null);
        valores[r - 1] = realizado[r - 1];
        pesos.forEach((p, k) => { valores[r + k] = valores[r - 1 + k] + (somaPesos ? restante * p / somaPesos : 0); });
        projecoes.push({ r, total: valores[N], valores });
    }
    if (!projecoes.length) {
        area.innerHTML = areaFech.innerHTML = '<p class="text-sm text-gray-500 py-6 text-center">Sem base para projetar ainda.</p>';
        return;
    }
    const ultima = projecoes[projecoes.length - 1];
    document.getElementById('legendaEvolucao').innerHTML = legenda([
        [COR_ATUAL, 'Realizado', true], [COR_COMPARACAO, mesLongo(inicioAnt), true, 'tracejado'], [COR_PROJECAO, 'Projeções', true],
    ]);

    // --- Leque de projeções ---
    const tamanho = Math.max(N, Nant) + 1;
    const largura = area.clientWidth;
    const margem = { esquerda: 76, direita: 16, topo: 20, baixo: 28 };
    const util = largura - margem.esquerda - margem.direita;
    const altura = 300;
    const alturaUtil = altura - margem.topo - margem.baixo;
    const { topo, passos } = escalaY(Math.max(...anterior, ...projecoes.map(p => p.total), ...realizado));
    const x = j => margem.esquerda + j / (tamanho - 1) * util;
    const y = v => margem.topo + alturaUtil - v / topo * alturaUtil;
    const caminho = valores => valores.map((v, j) => (v === null ? null : `${x(j).toFixed(1)},${y(v).toFixed(1)}`)).filter(Boolean).join(' ');
    // Mais antiga quase apagada, mais recente forte: o leque mostra a direção sem virar um emaranhado
    const opacidade = k => projecoes.length === 1 ? 1 : 0.15 + 0.55 * k / (projecoes.length - 1);
    const leque = projecoes.map((p, k) => `<polyline data-r="${p.r}" points="${caminho(p.valores)}" fill="none" stroke="${COR_PROJECAO}" stroke-width="${p === ultima ? 2.5 : 1.25}" stroke-opacity="${p === ultima ? 1 : opacidade(k).toFixed(2)}" stroke-linejoin="round" stroke-linecap="round" ${p === ultima ? 'stroke-dasharray="1 5"' : ''}/>`).join('');
    const rotulo = (px, py, texto, ancora) => `<text x="${px.toFixed(1)}" y="${py.toFixed(1)}" text-anchor="${ancora}" fill="${COR_TEXTO_ROTULO}" font-size="11" stroke="${COR_FUNDO}" stroke-width="3" style="font-variant-numeric:tabular-nums;paint-order:stroke">${esc(texto)}</text>`;
    const pontas = [
        { v: anterior[Nant], j: Nant, cor: COR_COMPARACAO, texto: moedaCurta(anterior[Nant]) },
        { v: ultima.total, j: N, cor: COR_PROJECAO, texto: moedaCurta(ultima.total) + ' hoje' },
    ].sort((a, b) => b.v - a.v);
    let ultimoY = -Infinity;
    const finais = pontas.map(p => {
        const py = Math.max(y(p.v) - 10, ultimoY + 14);
        ultimoY = py;
        return `<circle cx="${x(p.j).toFixed(1)}" cy="${y(p.v).toFixed(1)}" r="4" fill="${p.cor}" stroke="${COR_FUNDO}" stroke-width="2"/>` + rotulo(x(p.j) - 8, py, p.texto, 'end');
    }).join('');
    const pular = Math.ceil(tamanho / Math.max(1, Math.floor(util / 30)));
    // Eixo pelo dia da projeção: a posição j (fim do dia j) é a manhã do dia j + 1
    const diasEixo = Array.from({ length: N }, (_, j) => j + 1).filter(r => r === 1 || r % pular === 0 || r === N);
    const eixo = diasEixo.map(r => `<text x="${x(r - 1).toFixed(1)}" y="${altura - 8}" text-anchor="${r === 1 ? 'start' : 'middle'}" fill="${COR_TEXTO_EIXO}" font-size="11">${r}</text>`).join('');

    area.innerHTML = `<svg width="${largura}" height="${altura}" role="img" aria-label="Acumulado do mês com ${projecoes.length} projeções, uma por dia, e o mês anterior inteiro" class="block" style="touch-action:pan-y">
        ${grade(passos, y, margem.esquerda, largura - margem.direita)}
        <polyline points="${caminho(anterior)}" fill="none" stroke="${COR_COMPARACAO}" stroke-width="2" stroke-dasharray="5 4" stroke-linejoin="round"/>
        ${leque}
        <polyline points="${caminho(realizado)}" fill="none" stroke="${COR_ATUAL}" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
        <circle cx="${x(diaHoje).toFixed(1)}" cy="${y(realizado[diaHoje]).toFixed(1)}" r="4" fill="${COR_ATUAL}" stroke="${COR_FUNDO}" stroke-width="2"/>
        ${finais}${eixo}
        <g data-marcador class="hidden"><line data-mira y1="${margem.topo}" y2="${margem.topo + alturaUtil}" stroke="${COR_TEXTO_EIXO}" stroke-width="1"/></g>
        <rect data-alvo x="${margem.esquerda - 10}" y="0" width="${util + 20}" height="${altura - margem.baixo}" fill="transparent"/></svg>`;

    // --- Fechamento projetado em cada dia ---
    const larguraF = areaFech.clientWidth;
    const alturaF = 170;
    const mF = { esquerda: margem.esquerda, direita: margem.direita, topo: 16, baixo: 26 };
    const utilF = larguraF - mF.esquerda - mF.direita;
    const altUtilF = alturaF - mF.topo - mF.baixo;
    const valoresF = projecoes.map(p => p.total);
    const minF = Math.min(...valoresF, anterior[Nant]), maxF = Math.max(...valoresF, anterior[Nant]);
    const folgaF = (maxF - minF) * 0.15 || maxF * 0.1 || 1;
    const baseF = Math.max(0, minF - folgaF), topoF = maxF + folgaF;
    // Mesma escala do gráfico de cima: a projeção da manhã do dia r fica em j = r - 1 (fim do dia anterior)
    const xF = r => mF.esquerda + (r - 1) / (tamanho - 1) * utilF;
    const yF = v => mF.topo + altUtilF - (v - baseF) / (topoF - baseF) * altUtilF;
    const refY = yF(anterior[Nant]);
    const pontosF = projecoes.map(p => `<circle data-ponto-r="${p.r}" cx="${xF(p.r).toFixed(1)}" cy="${yF(p.total).toFixed(1)}" r="${p === ultima ? 4.5 : 3}" fill="${COR_PROJECAO}" stroke="${COR_FUNDO}" stroke-width="1.5"/>`).join('');
    const eixoF = diasEixo.map(r => `<text x="${xF(r).toFixed(1)}" y="${alturaF - 6}" text-anchor="${r === 1 ? 'start' : 'middle'}" fill="${COR_TEXTO_EIXO}" font-size="11">${r}</text>`).join('');
    const marcasF = [baseF + (topoF - baseF) * 0.15, (baseF + topoF) / 2, topoF - (topoF - baseF) * 0.15];
    areaFech.innerHTML = `<svg width="${larguraF}" height="${alturaF}" role="img" aria-label="Fechamento do mês projetado em cada dia" class="block">
        ${marcasF.map(v => `<line x1="${mF.esquerda}" x2="${larguraF - mF.direita}" y1="${yF(v).toFixed(1)}" y2="${yF(v).toFixed(1)}" stroke="${COR_GRADE}"/><text x="${mF.esquerda - 8}" y="${(yF(v) + 4).toFixed(1)}" text-anchor="end" fill="${COR_TEXTO_EIXO}" font-size="11">${moedaCurta(v)}</text>`).join('')}
        <line x1="${mF.esquerda}" x2="${larguraF - mF.direita}" y1="${refY.toFixed(1)}" y2="${refY.toFixed(1)}" stroke="${COR_COMPARACAO}" stroke-dasharray="5 4"/>
        ${rotulo(larguraF - mF.direita, refY - 6, mesLongo(inicioAnt) + ': ' + moedaCurta(anterior[Nant]), 'end')}
        <polyline points="${projecoes.map(p => `${xF(p.r).toFixed(1)},${yF(p.total).toFixed(1)}`).join(' ')}" fill="none" stroke="${COR_PROJECAO}" stroke-width="1.5" stroke-opacity="0.6"/>
        ${pontosF}${eixoF}
        <rect data-alvo x="${mF.esquerda - 10}" y="0" width="${utilF + 20}" height="${alturaF - mF.baixo}" fill="transparent"/></svg>`;

    // Passar o mouse num dia (em qualquer dos dois) destaca a projeção feita naquele dia
    const svg = area.querySelector('svg');
    const marcador = svg.querySelector('[data-marcador]');
    const destacar = (r, e) => {
        const p = projecoes.find(q => q.r === r);
        svg.querySelectorAll('[data-r]').forEach(l => {
            const ativo = Number(l.dataset.r) === r;
            l.setAttribute('stroke-opacity', ativo ? '1' : '0.08');
            l.setAttribute('stroke-width', ativo ? '2.5' : '1.25');
        });
        areaFech.querySelectorAll('[data-ponto-r]').forEach(c => c.setAttribute('r', Number(c.dataset.pontoR) === r ? 6 : 3));
        marcador.classList.remove('hidden');
        const mira = marcador.querySelector('[data-mira]');
        mira.setAttribute('x1', x(r - 1)); mira.setAttribute('x2', x(r - 1));
        const dia = somarDias(inicio, r - 1);
        const titulo = `Projeção do dia ${dataCurta(dia).slice(0, 5)} <span class="font-normal text-gray-400">(${r > 1 ? 'dados até ' + dataCurta(somarDias(dia, -1)).slice(0, 5) : 'sem dias completos'})</span>`;
        mostrarDica(e, `<div class="font-semibold mb-1">${titulo}</div>`
            + (p ? linhaDica(COR_PROJECAO, 'Fechamento projetado', moeda(p.total)) : '<div class="text-gray-400">Sem projeção neste dia</div>')
            + linhaDica(COR_ATUAL, `Realizado até ${dataCurta(somarDias(dia, -1)).slice(0, 5)}`, moeda(realizado[r - 1]))
            + linhaDica(COR_COMPARACAO, `${mesLongo(inicioAnt)} até o dia ${r - 1}`, r - 1 <= Nant ? moeda(anterior[r - 1]) : '—')
            + (p && r > 1 && projecoes.find(q => q.r === r - 1) ? `<div class="text-xs text-gray-400 mt-1">vs projeção da véspera: ${variacao(p.total, projecoes.find(q => q.r === r - 1).total, true)}</div>` : ''));
    };
    const limpar = () => {
        projecoes.forEach((p, k) => {
            const l = svg.querySelector(`[data-r="${p.r}"]`);
            l.setAttribute('stroke-opacity', p === ultima ? '1' : opacidade(k).toFixed(2));
            l.setAttribute('stroke-width', p === ultima ? '2.5' : '1.25');
        });
        areaFech.querySelectorAll('[data-ponto-r]').forEach(c => c.setAttribute('r', Number(c.dataset.pontoR) === ultima.r ? 4.5 : 3));
        marcador.classList.add('hidden');
        esconderDica();
    };
    svg.querySelector('[data-alvo]').addEventListener('pointermove', e => {
        const rel = e.clientX - svg.getBoundingClientRect().left;
        const j = Math.round((rel - margem.esquerda) / (util / (tamanho - 1)));
        destacar(Math.min(diaHoje, Math.max(1, j + 1)), e);
    });
    svg.addEventListener('pointerleave', limpar);
    const svgF = areaFech.querySelector('svg');
    svgF.querySelector('[data-alvo]').addEventListener('pointermove', e => {
        const rel = e.clientX - svgF.getBoundingClientRect().left;
        destacar(Math.min(diaHoje, Math.max(1, Math.round((rel - mF.esquerda) / (utilF / (tamanho - 1))) + 1)), e);
    });
    svgF.addEventListener('pointerleave', limpar);
}

// Uma barra só, dividida entre as duas partes (somam 100%). Cada lado traz %, valor, unidades e a
// mudança da participação contra a comparação, em pontos percentuais.
function barraDividida(alvo, itens) {
    const area = document.getElementById(alvo);
    const total = soma(itens.map(i => i.valor));
    if (!total) { area.innerHTML = '<p class="text-sm text-gray-500 py-4 text-center">Sem vendas no período.</p>'; return; }
    const totalAntes = soma(itens.map(i => i.antes ?? 0));
    const participacao = i => i.valor / total * 100;
    const mudanca = i => {
        if (i.antes === null || !totalAntes) return '';
        const pp = participacao(i) - i.antes / totalAntes * 100;
        if (Math.abs(pp) < 0.05) return '<span class="text-gray-500">= comparação</span>';
        return `<span class="text-gray-400">${pp > 0 ? '▲' : '▼'} ${Math.abs(pp).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} p.p. vs comparação</span>`;
    };
    const lado = (i, direita) => `
        <div class="min-w-0 ${direita ? 'text-right' : ''}">
            <div class="flex items-center gap-2 text-sm text-gray-300 ${direita ? 'justify-end' : ''}">
                <span class="inline-block w-2.5 h-2.5 rounded-sm" style="background:${i.cor}"></span>${esc(i.nome)}
            </div>
            <div class="text-2xl font-bold tabular-nums text-gray-100">${pct(participacao(i))}</div>
            <div class="text-xs text-gray-400 tabular-nums">${moeda(i.valor)} · ${inteiro(i.unidades)} un.</div>
            <div class="text-xs tabular-nums mt-0.5">${mudanca(i)}</div>
        </div>`;
    const visiveis = itens.filter(i => i.valor > 0);
    const segmentos = visiveis.map((i, k) => `<div class="h-full ${k === 0 ? 'rounded-l' : ''} ${k === visiveis.length - 1 ? 'rounded-r' : ''}"
            style="width:${participacao(i)}%;background:${i.cor}" title="${esc(i.nome)}: ${pct(participacao(i))}"></div>`).join('');
    area.innerHTML = `
        <div class="flex justify-between gap-4 mb-3">${lado(itens[0], false)}${lado(itens[1], true)}</div>
        <div class="flex h-3 gap-[2px]" role="img" aria-label="${itens.map(i => `${esc(i.nome)} ${pct(participacao(i))}`).join(', ')}">${segmentos}</div>`;
}

function renderizarDivisoes(atual, comp, temBase) {
    const parte = (filtro) => {
        const linhas = atual.skus.filter(filtro);
        const antes = temBase ? soma(comp.skus.filter(filtro).map(s => s[5])) : null;
        return { valor: soma(linhas.map(s => s[5])), unidades: soma(linhas.map(s => s[4])), antes };
    };
    barraDividida('divisaoLogistica', [
        { nome: 'Full', cor: COR_FULL, ...parte(s => s[2] === 1) },
        { nome: 'Normal', cor: COR_NORMAL, ...parte(s => s[2] === 0) },
    ]);
    barraDividida('divisaoAnuncio', [
        { nome: 'Premium', cor: COR_PREMIUM, ...parte(s => s[3] === 1) },
        { nome: 'Clássico', cor: COR_CLASSICO, ...parte(s => s[3] === 0) },
    ]);
}

// Participação de cada dia da semana no faturamento: histórico inteiro × período escolhido
function renderizarSemana({ de, ate }) {
    const area = document.getElementById('graficoSemana');
    const NOMES = ['seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'];
    const somar = filtro => {
        const v = [0, 0, 0, 0, 0, 0, 0];
        dados.dias.forEach(([dia, full, , fat]) => {
            if (logisticas.has(full) && filtro(dia)) v[(paraData(dia).getDay() + 6) % 7] += fat;
        });
        const total = soma(v);
        return { valores: v, partes: v.map(x => total ? x / total * 100 : 0) };
    };
    const series = [
        { nome: 'Histórico', cor: COR_COMPARACAO, ...somar(() => true) },
        { nome: 'Período', cor: COR_ATUAL, ...somar(dia => dia >= de && dia <= ate) },
    ];
    document.getElementById('legendaSemana').innerHTML = legenda(series.map(s => [s.cor, s.nome]));

    const largura = area.clientWidth;
    const margem = { esquerda: 44, direita: 8, topo: 18, baixo: 28 };
    const util = largura - margem.esquerda - margem.direita;
    const alturaUtil = ALTURA_GRAFICO - margem.topo - margem.baixo;
    const maximo = Math.max(...series.flatMap(s => s.partes));
    const passo = maximo > 20 ? 10 : 5;
    const topo = Math.max(passo, Math.ceil(maximo / passo) * passo);
    const y = v => margem.topo + alturaUtil - v / topo * alturaUtil;
    const faixa = util / 7;
    const estreito = faixa < 60;
    const vao = estreito ? 2 : 8; // vão entre as duas colunas do dia: abre espaço para os rótulos com decimal
    const barra = Math.min(24, (faixa * (estreito ? 0.92 : 0.7) - vao) / 2);
    const marcas = [];
    for (let v = 0; v <= topo; v += passo) marcas.push(v);
    const grade = marcas.map(v => `
        <line x1="${margem.esquerda}" x2="${largura - margem.direita}" y1="${y(v).toFixed(1)}" y2="${y(v).toFixed(1)}" stroke="${v === 0 ? COR_BASE : COR_GRADE}" stroke-width="1"/>
        <text x="${margem.esquerda - 8}" y="${(y(v) + 4).toFixed(1)}" text-anchor="end" fill="${COR_TEXTO_EIXO}" font-size="11">${v}%</text>`).join('');
    const coluna = (x, v, cor) => {
        const alto = y(0) - y(v);
        if (alto <= 0) return '';
        const r = Math.min(4, alto, barra / 2), t = y(v);
        return `<path d="M${x},${y(0)} V${t + r} Q${x},${t} ${x + r},${t} H${x + barra - r} Q${x + barra},${t} ${x + barra},${t + r} V${y(0)} Z" fill="${cor}"/>`
            + `<text x="${(x + barra / 2).toFixed(1)}" y="${(t - 4).toFixed(1)}" text-anchor="middle" fill="${COR_TEXTO_ROTULO}" font-size="${estreito ? 9 : 11}" style="font-variant-numeric:tabular-nums">${Math.round(v)}%</text>`;
    };
    const grupos = NOMES.map((nome, i) => {
        const x0 = margem.esquerda + faixa * i + (faixa - (barra * 2 + vao)) / 2;
        return series.map((s, k) => coluna(x0 + k * (barra + vao), s.partes[i], s.cor)).join('')
            + `<text x="${(margem.esquerda + faixa * i + faixa / 2).toFixed(1)}" y="${ALTURA_GRAFICO - 8}" text-anchor="middle" fill="${COR_TEXTO_EIXO}" font-size="11">${nome}</text>`
            + `<rect data-dia="${i}" x="${margem.esquerda + faixa * i}" y="${margem.topo}" width="${faixa}" height="${alturaUtil}" fill="transparent"/>`;
    }).join('');
    area.innerHTML = `<svg width="${largura}" height="${ALTURA_GRAFICO}" role="img" aria-label="Participação de cada dia da semana no faturamento, histórico e período" class="block">${grade}${grupos}</svg>`;
    area.querySelectorAll('[data-dia]').forEach(alvo => {
        const i = Number(alvo.dataset.dia);
        alvo.addEventListener('pointermove', e => mostrarDica(e, `<div class="font-semibold mb-1">${NOMES[i]}</div>`
            + series.map(s => linhaDica(s.cor, s.nome, `${pct(s.partes[i])} · ${moeda(s.valores[i])}`)).join('')));
        alvo.addEventListener('pointerleave', esconderDica);
    });
}

// Vendas canceladas por mês da venda: valor em colunas; quantidade e % do mês no rótulo e na dica
function renderizarCancelamentos() {
    const area = document.getElementById('graficoCancelamentos');
    const mesAtual = paraTexto(new Date()).slice(0, 7);
    const somar = linhas => {
        const mapa = new Map();
        linhas.forEach(([dia, full, vendas, fat]) => {
            if (!logisticas.has(full)) return;
            const chave = dia.slice(0, 7);
            if (!mapa.has(chave)) mapa.set(chave, { vendas: 0, valor: 0 });
            mapa.get(chave).vendas += vendas;
            mapa.get(chave).valor += fat;
        });
        return mapa;
    };
    const cancelados = somar(dados.cancelados || []);
    const validos = somar(dados.dias);
    const inicio = [...validos.keys(), ...cancelados.keys()].sort()[0];
    if (!inicio) { area.innerHTML = '<p class="text-sm text-gray-500 py-6 text-center">Sem dados.</p>'; return; }
    const meses = [];
    for (let m = inicio + '-01'; m.slice(0, 7) <= mesAtual; m = mudarMes(m, 1)) meses.push(m.slice(0, 7));
    const info = meses.map(m => {
        const c = cancelados.get(m) || { vendas: 0, valor: 0 };
        const v = validos.get(m) || { vendas: 0, valor: 0 };
        const bruto = c.valor + v.valor;
        return { ...c, pctValor: bruto ? c.valor / bruto * 100 : 0, pctVendas: c.vendas + v.vendas ? c.vendas / (c.vendas + v.vendas) * 100 : 0, projetado: 0 };
    });

    // Projeção do mês atual: mantém o % cancelado do mês até agora sobre o valor vendido projetado.
    // % = cancelado ÷ (válidas + canceladas), então cancelado final = válidas projetadas × % ÷ (1 − %).
    const proj = projecaoM0();
    const iAtual = meses.indexOf(mesAtual);
    if (proj && iAtual >= 0 && info[iAtual].pctValor < 100) {
        const validasProjetadas = (logisticas.has(0) ? proj.real[0] : 0) + (logisticas.has(1) ? proj.real[1] : 0) + proj.restante * parteProjetada(proj);
        const parte = info[iAtual].pctValor / 100;
        info[iAtual].projetado = Math.max(0, validasProjetadas * parte / (1 - parte) - info[iAtual].valor);
    }
    const temProjecao = iAtual >= 0 && info[iAtual].projetado > 0;
    document.getElementById('legendaCancelamentos').innerHTML = legenda([[COR_CANCELADO, 'Cancelado'], ...(temProjecao ? [[COR_CANCELADO_PROJ, 'Projeção']] : [])]);

    const largura = area.clientWidth;
    const margem = { esquerda: 76, direita: 8, topo: 18, baixo: 28 };
    const util = largura - margem.esquerda - margem.direita;
    const alturaUtil = ALTURA_GRAFICO - margem.topo - margem.baixo;
    const { topo, passos } = escalaY(Math.max(1, ...info.map(i => i.valor + i.projetado)));
    const y = v => margem.topo + alturaUtil - v / topo * alturaUtil;
    const faixa = util / meses.length;
    const barra = Math.min(24, faixa * 0.6);
    const pularRotulo = Math.ceil(meses.length / Math.max(1, Math.floor(util / 48)));

    const colunas = meses.map((mes, i) => {
        const x = margem.esquerda + faixa * i + (faixa - barra) / 2;
        const { valor, projetado } = info[i];
        // Ponta arredondada só no segmento do topo; 2px de respiro entre realizado e projeção
        const segmento = (base, v, cor, topoDaColuna) => {
            const yBase = y(base) - (base > 0 ? 2 : 0), t = y(base + v), alto = yBase - t;
            if (alto <= 0) return '';
            if (!topoDaColuna) return `<rect x="${x}" y="${t}" width="${barra}" height="${alto}" fill="${cor}"/>`;
            const r = Math.min(4, alto, barra / 2);
            return `<path d="M${x},${yBase} V${t + r} Q${x},${t} ${x + r},${t} H${x + barra - r} Q${x + barra},${t} ${x + barra},${t + r} V${yBase} Z" fill="${cor}"/>`;
        };
        let marca = '';
        if (valor > 0) marca += segmento(0, valor, COR_CANCELADO, projetado <= 0);
        if (projetado > 0) marca += segmento(valor, projetado, COR_CANCELADO_PROJ, true);
        if (valor + projetado > 0) {
            marca += `<text x="${(x + barra / 2).toFixed(1)}" y="${(y(valor + projetado) - 5).toFixed(1)}" text-anchor="middle" fill="${COR_TEXTO_ROTULO}" font-size="${faixa < 40 ? 9 : 11}" style="font-variant-numeric:tabular-nums">${milhar(valor + projetado, faixa < 40 ? 0 : 1)}</text>`;
        }
        const ultimo = i === meses.length - 1;
        const rotulo = (meses.length - 1 - i) % pularRotulo === 0
            ? `<text x="${(ultimo ? x + barra + 4 : x + barra / 2).toFixed(1)}" y="${ALTURA_GRAFICO - 8}" text-anchor="${ultimo ? 'end' : 'middle'}" fill="${COR_TEXTO_EIXO}" font-size="11">${mesCurto(mes)}${mes === mesAtual ? '*' : ''}</text>` : '';
        return `${marca}${rotulo}<rect data-alvo="${i}" x="${margem.esquerda + faixa * i}" y="${margem.topo}" width="${faixa}" height="${alturaUtil}" fill="transparent"/>`;
    }).join('');

    area.innerHTML = `<svg width="${largura}" height="${ALTURA_GRAFICO}" role="img" aria-label="Valor cancelado por mês da venda" class="block">
        ${grade(passos, y, margem.esquerda, largura - margem.direita)}${colunas}</svg>
        <p class="text-xs text-gray-500 mt-1">Pelo mês da venda. % = parte do valor vendido no mês (válidas + canceladas) que foi cancelada.${temProjecao ? ' Projeção: o mesmo % do mês atual aplicado à projeção de vendas.' : ''}</p>`;

    area.querySelectorAll('[data-alvo]').forEach(alvo => {
        const i = Number(alvo.dataset.alvo);
        alvo.addEventListener('pointermove', e => mostrarDica(e, `<div class="font-semibold mb-1">${mesCurto(meses[i])}${meses[i] === mesAtual ? ' (parcial)' : ''}</div>`
            + linhaDica(COR_CANCELADO, 'Valor cancelado', `${moeda(info[i].valor)} · ${pct(info[i].pctValor)}`)
            + `<div class="flex justify-between gap-4 text-gray-300"><span>Vendas canceladas</span><span class="tabular-nums">${inteiro(info[i].vendas)} · ${pct(info[i].pctVendas)}</span></div>`
            + (info[i].projetado > 0
                ? `<div class="mt-2"></div>` + linhaDica(COR_CANCELADO_PROJ, 'Projeção', '+ ' + moeda(info[i].projetado))
                  + `<div class="flex justify-between gap-4 border-t border-gray-700 mt-1 pt-1 font-semibold"><span>Cancelado no mês (proj.)</span><span class="tabular-nums">${moeda(info[i].valor + info[i].projetado)}</span></div>`
                  + `<div class="text-xs text-gray-400 mt-1">Mantém os ${pct(info[i].pctValor)} cancelados até agora sobre a projeção do mês</div>`
                : '')));
        alvo.addEventListener('pointerleave', esconderDica);
    });
}

function porSku(linhas) {
    const mapa = new Map();
    linhas.forEach(([, sku, full, , un, fat]) => {
        if (!mapa.has(sku)) mapa.set(sku, { unidades: 0, faturamento: 0, unidadesFull: 0 });
        const item = mapa.get(sku);
        item.unidades += un; item.faturamento += fat;
        if (full) item.unidadesFull += un;
    });
    return mapa;
}

function renderizarSkus() {
    if (!periodoVisivel) return;
    const atual = porSku(resumo(periodoVisivel.atual).skus);
    const comp = porSku(resumo(periodoVisivel.comparacao).skus);
    const temBase = temDados(periodoVisivel.comparacao);
    const termo = document.getElementById('buscaSku').value.trim().toUpperCase();
    const linhas = [...new Set([...atual.keys(), ...comp.keys()])]
        .map(sku => ({ sku, titulo: dados.titulos[sku] || '', a: atual.get(sku) || { unidades: 0, faturamento: 0, unidadesFull: 0 }, c: comp.get(sku) }))
        .filter(l => !termo || l.sku.toUpperCase().includes(termo) || l.titulo.toUpperCase().includes(termo))
        .sort((x, y) => y.a.faturamento - x.a.faturamento || (y.c?.faturamento || 0) - (x.c?.faturamento || 0));
    const visiveis = mostrarTodosSkus || termo ? linhas : linhas.slice(0, LIMITE_SKUS);

    document.getElementById('tabelaSkus').innerHTML = visiveis.length ? visiveis.map(l => `
        <tr class="border-b border-gray-700/60">
            <td class="py-2 pr-3 max-w-[7rem] sm:max-w-none">
                <div class="font-semibold text-gray-100 break-all sm:break-normal">${l.sku ? esc(l.sku) : '<span class="text-gray-500">(sem SKU)</span>'}</div>
                <div class="text-xs text-gray-500 truncate sm:max-w-xs" title="${esc(l.titulo)}">${esc(l.titulo)}</div>
            </td>
            <td class="py-2 px-1.5 sm:px-3 text-right tabular-nums">${inteiro(l.a.unidades)}</td>
            <td class="py-2 px-1.5 sm:px-3 text-right tabular-nums whitespace-nowrap">${moeda(l.a.faturamento)}</td>
            <td class="py-2 px-1.5 sm:px-3 text-right tabular-nums whitespace-nowrap text-xs">${!temBase ? '<span class="text-gray-500">—</span>' : !l.c ? '<span class="text-sky-400">novo</span>' : variacao(l.a.faturamento, l.c.faturamento, true)}</td>
            <td class="hidden sm:table-cell py-2 pl-3 text-right tabular-nums text-gray-300">${l.a.unidades ? pct(l.a.unidadesFull / l.a.unidades * 100) : '—'}</td>
        </tr>`).join('') : '<tr><td colspan="5" class="py-6 text-center text-gray-500">Nenhum SKU no período.</td></tr>';

    const botao = document.getElementById('btnTodosSkus');
    botao.classList.toggle('hidden', visiveis.length >= linhas.length);
    botao.textContent = `Mostrar todos os ${linhas.length} SKUs`;
}

function mostrarSincronizacao() {
    const s = dados.sincronizacao || {};
    const el = document.getElementById('statusSinc');
    if (s.ultima && s.ultima.erro) {
        el.innerHTML = `<span class="text-amber-400" title="${esc(s.ultima.erro)}">⚠ última atualização falhou</span>`;
    } else if (s.ultima_ok) {
        const [data, hora] = s.ultima_ok.split(' ');
        el.textContent = `Atualizado em ${dataCurta(data).slice(0, 5)} às ${hora.slice(0, 5)}`;
    } else {
        el.textContent = 'Nunca atualizado';
    }
}

async function carregar() {
    const r = await fetch('api.php?action=dados');
    dados = await r.json();
    feriados = new Set(dados.feriados || []);
    mostrarSincronizacao();
    renderizar();
}

async function atualizar() {
    const botao = document.getElementById('btnAtualizar');
    botao.disabled = true;
    botao.textContent = '⏳ Atualizando...';
    try {
        const r = await fetch('api.php?action=atualizar', { method: 'POST' });
        const resposta = await r.json();
        if (!r.ok) throw new Error(resposta.error || 'Falha ao atualizar');
        const mudancas = resposta.novos + resposta.alterados;
        const detalhe = mudancas
            ? [resposta.novos && `${inteiro(resposta.novos)} pedido${resposta.novos > 1 ? 's' : ''} novo${resposta.novos > 1 ? 's' : ''}`,
               resposta.alterados && `${inteiro(resposta.alterados)} alterado${resposta.alterados > 1 ? 's' : ''}`].filter(Boolean).join(' e ')
            : 'Nada mudou desde a última atualização';
        ML.toast(`${detalhe} (${inteiro(resposta.lidos)} conferidos em ${String(resposta.segundos).replace('.', ',')} s)`, mudancas ? 'sucesso' : 'info');
        if (resposta.full && resposta.full.erro) ML.toast('Estoque Full não atualizou: ' + resposta.full.erro, 'aviso');
        await carregar();
    } catch (e) {
        ML.toast(e.message, 'erro');
        await carregar();
    } finally {
        botao.disabled = false;
        botao.textContent = '🔄 Atualizar';
    }
}

let redimensionar;
window.addEventListener('resize', () => {
    clearTimeout(redimensionar);
    redimensionar = setTimeout(() => { if (periodoVisivel) renderizar(); }, 150);
});

periodoPadrao();
carregar();
</script>
</body>
</html>
