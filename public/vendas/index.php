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
                    <option value="uteis">Período anterior (dias úteis)</option>
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
                <h2 class="text-lg font-semibold text-gray-200">Faturamento por mês</h2>
                <div id="legendaMensal" class="flex gap-4 text-sm text-gray-300"></div>
            </div>
            <div id="graficoMensal" class="relative"></div>
        </section>

        <section class="<?= $classeCartao ?>">
            <div class="flex flex-wrap justify-between items-baseline gap-2 mb-4">
                <h2 class="text-lg font-semibold text-gray-200">Faturamento acumulado no período</h2>
                <div id="legendaAcumulado" class="flex gap-4 text-sm text-gray-300"></div>
            </div>
            <div id="graficoAcumulado" class="relative"></div>
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
const COR_COMPARACAO = '#9ca3af';
const COR_FUNDO = '#1f2937';
const COR_GRADE = '#374151';
const COR_BASE = '#4b5563';
const COR_TEXTO_EIXO = '#9ca3af';
const COR_TEXTO_ROTULO = '#d1d5db';
const ALTURA_GRAFICO = 260;
const LIMITE_SKUS = 25;
const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

let dados = { dias: [], skus: [], titulos: {}, feriados: [], sincronizacao: {} };
let feriados = new Set();
let periodoVisivel = null;
let mostrarTodosSkus = false;

const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
const moeda = v => Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const moedaCurta = v => v >= 1000 ? 'R$ ' + (v / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil' : moeda(v);
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
    return { atual: { de, ate }, comparacao: { de: cDe, ate: cAte }, uteis };
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
function renderizar() {
    const p = periodos();
    periodoVisivel = p;
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
    renderizarAcumulado(p, atual, comp, temBase);
    renderizarDivisoes(atual, comp, temBase);
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
const legenda = itens => itens.map(([cor, nome, linha, tracejado]) => `<span class="flex items-center gap-2"><span class="inline-block ${linha ? 'w-5 border-t-2' + (tracejado ? ' border-dashed' : '') : 'w-2.5 h-2.5 rounded-sm'}" style="${linha ? 'border-color:' + cor : 'background:' + cor}"></span>${nome}</span>`).join('');

function renderizarMensal() {
    const area = document.getElementById('graficoMensal');
    const porMes = new Map();
    dados.dias.forEach(([dia, full, , fat]) => {
        const chave = dia.slice(0, 7);
        if (!porMes.has(chave)) porMes.set(chave, [0, 0]);
        porMes.get(chave)[full] += fat;
    });
    if (!porMes.size) { area.innerHTML = '<p class="text-sm text-gray-500 py-6 text-center">Sem vendas guardadas. Use o botão Atualizar.</p>'; return; }

    // Preenche meses sem venda para o eixo não pular
    const meses = [];
    for (let m = [...porMes.keys()][0] + '-01'; m.slice(0, 7) <= paraTexto(new Date()).slice(0, 7); m = mudarMes(m, 1)) meses.push(m.slice(0, 7));
    const series = [
        { nome: 'Normal', cor: COR_NORMAL, idx: 0 },
        { nome: 'Full', cor: COR_FULL, idx: 1 },
    ].filter(s => logisticas.has(s.idx));
    document.getElementById('legendaMensal').innerHTML = series.length > 1 ? legenda(series.map(s => [s.cor, s.nome])) : '';
    const valor = (mes, s) => (porMes.get(mes) || [0, 0])[s.idx];
    const totais = meses.map(m => soma(series.map(s => valor(m, s))));

    const largura = area.clientWidth;
    const margem = { esquerda: 76, direita: 8, topo: 12, baixo: 28 };
    const util = largura - margem.esquerda - margem.direita;
    const alturaUtil = ALTURA_GRAFICO - margem.topo - margem.baixo;
    const { topo, passos } = escalaY(Math.max(...totais));
    const y = v => margem.topo + alturaUtil - v / topo * alturaUtil;
    const faixa = util / meses.length;
    const barra = Math.min(24, faixa * 0.6);
    const mesAtual = paraTexto(new Date()).slice(0, 7);
    const pularRotulo = Math.ceil(meses.length / Math.max(1, Math.floor(util / 48)));

    const barras = meses.map((mes, i) => {
        const x = margem.esquerda + faixa * i + (faixa - barra) / 2;
        let base = 0;
        const partes = series.map((s, k) => {
            const v = valor(mes, s);
            if (v <= 0) return '';
            const y0 = y(base), y1 = y(base + v);
            base += v;
            const ultimo = series.slice(k + 1).every(o => valor(mes, o) <= 0);
            // 2px de respiro entre segmentos; só o segmento do topo tem a ponta arredondada
            const alto = Math.max(0, y0 - y1 - (base > v ? 2 : 0));
            const yTopo = y0 - (base > v ? 2 : 0) - alto;
            if (alto <= 0) return '';
            return ultimo
                ? `<path d="M${x},${yTopo + alto} V${yTopo + Math.min(4, alto)} Q${x},${yTopo} ${x + Math.min(4, barra / 2)},${yTopo} H${x + barra - Math.min(4, barra / 2)} Q${x + barra},${yTopo} ${x + barra},${yTopo + Math.min(4, alto)} V${yTopo + alto} Z" fill="${s.cor}"/>`
                : `<rect x="${x}" y="${yTopo}" width="${barra}" height="${alto}" fill="${s.cor}"/>`;
        }).join('');
        const rotulo = (meses.length - 1 - i) % pularRotulo === 0
            ? `<text x="${(x + barra / 2).toFixed(1)}" y="${ALTURA_GRAFICO - 8}" text-anchor="middle" fill="${COR_TEXTO_EIXO}" font-size="11">${mesCurto(mes)}${mes === mesAtual ? '*' : ''}</text>` : '';
        return `<g data-mes="${i}">${partes}</g>${rotulo}
            <rect data-alvo="${i}" x="${margem.esquerda + faixa * i}" y="${margem.topo}" width="${faixa}" height="${alturaUtil}" fill="transparent"/>`;
    }).join('');

    area.innerHTML = `<svg width="${largura}" height="${ALTURA_GRAFICO}" role="img" aria-label="Faturamento por mês, ${mesCurto(meses[0])} a ${mesCurto(meses[meses.length - 1])}" class="block">
        ${grade(passos, y, margem.esquerda, largura - margem.direita)}${barras}</svg>
        <p class="text-xs text-gray-500 mt-1">* mês em andamento</p>`;

    area.querySelectorAll('[data-alvo]').forEach(alvo => {
        const i = Number(alvo.dataset.alvo);
        alvo.addEventListener('pointermove', e => {
            const mes = meses[i];
            mostrarDica(e, `<div class="font-semibold mb-1">${mesCurto(mes)}${mes === mesAtual ? ' (parcial)' : ''}</div>`
                + [...series].reverse().map(s => linhaDica(s.cor, s.nome, moeda(valor(mes, s)))).join('')
                + (series.length > 1 ? `<div class="flex justify-between gap-4 border-t border-gray-700 mt-1 pt-1"><span>Total</span><span class="tabular-nums">${moeda(totais[i])}</span></div>` : ''));
        });
        alvo.addEventListener('pointerleave', esconderDica);
    });
}

function acumulado(resumoPeriodo, { de, ate }, limite) {
    const porDia = new Map();
    resumoPeriodo.dias.forEach(d => porDia.set(d[0], (porDia.get(d[0]) || 0) + d[3]));
    const valores = [];
    let total = 0;
    for (let i = 0, dia = de; dia <= ate; i++, dia = somarDias(dia, 1)) {
        if (limite && dia > limite) { valores.push(null); continue; }
        total += porDia.get(dia) || 0;
        valores.push(total);
    }
    return valores;
}

function renderizarAcumulado(p, atual, comp, temBase) {
    const area = document.getElementById('graficoAcumulado');
    const hoje = paraTexto(new Date());
    const series = [{ nome: 'Período atual', cor: COR_ATUAL, valores: acumulado(atual, p.atual, hoje), de: p.atual.de }];
    if (temBase) series.push({ nome: 'Comparação', cor: COR_COMPARACAO, valores: acumulado(comp, p.comparacao), de: p.comparacao.de, tracejado: true });
    document.getElementById('legendaAcumulado').innerHTML = series.length > 1 ? legenda(series.map(s => [s.cor, s.nome, true, s.tracejado])) : '';

    const tamanho = Math.max(...series.map(s => s.valores.length));
    const largura = area.clientWidth;
    const margem = { esquerda: 76, direita: 16, topo: 20, baixo: 28 };
    const util = largura - margem.esquerda - margem.direita;
    const alturaUtil = ALTURA_GRAFICO - margem.topo - margem.baixo;
    const { topo, passos } = escalaY(Math.max(1, ...series.flatMap(s => s.valores.filter(v => v !== null))));
    const x = i => margem.esquerda + (tamanho > 1 ? i / (tamanho - 1) * util : util / 2);
    const y = v => margem.topo + alturaUtil - v / topo * alturaUtil;

    const linhas = series.map(s => {
        const pontos = s.valores.map((v, i) => v === null ? null : `${x(i).toFixed(1)},${y(v).toFixed(1)}`).filter(Boolean);
        return `<polyline points="${pontos.join(' ')}" fill="none" stroke="${s.cor}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" ${s.tracejado ? 'stroke-dasharray="5 4"' : ''}/>`;
    }).join('');
    const fim = s => { for (let i = s.valores.length - 1; i >= 0; i--) if (s.valores[i] !== null) return i; return -1; };
    const rotulo = (px, py, texto, ancora) => `<text x="${px.toFixed(1)}" y="${py.toFixed(1)}" text-anchor="${ancora}" fill="${COR_TEXTO_ROTULO}" font-size="11" stroke="${COR_FUNDO}" stroke-width="3" style="font-variant-numeric:tabular-nums;paint-order:stroke">${esc(texto)}</text>`;
    const finais = series.map((s, k) => {
        const i = fim(s);
        if (i < 0) return '';
        // Rótulo do atual acima do ponto, da comparação abaixo, para não se cruzarem
        const outro = series[1 - k];
        const acima = !outro || s.valores[i] >= (outro.valores[Math.min(i, outro.valores.length - 1)] ?? 0);
        return `<circle cx="${x(i).toFixed(1)}" cy="${y(s.valores[i]).toFixed(1)}" r="4" fill="${s.cor}" stroke="${COR_FUNDO}" stroke-width="2"/>`
            + rotulo(x(i) - (i > tamanho / 2 ? 6 : -6), y(s.valores[i]) + (acima ? -10 : 18), moedaCurta(s.valores[i]), i > tamanho / 2 ? 'end' : 'start');
    }).join('');
    const pular = Math.ceil(tamanho / Math.max(1, Math.floor(util / 56)));
    const eixo = Array.from({ length: tamanho }, (_, i) => i).filter(i => (tamanho - 1 - i) % pular === 0)
        .map(i => `<text x="${x(i).toFixed(1)}" y="${ALTURA_GRAFICO - 8}" text-anchor="${i === 0 && tamanho > 1 ? 'start' : i === tamanho - 1 && tamanho > 1 ? 'end' : 'middle'}" fill="${COR_TEXTO_EIXO}" font-size="11">${dataCurta(somarDias(p.atual.de, i)).slice(0, 5)}</text>`).join('');

    area.innerHTML = `<svg width="${largura}" height="${ALTURA_GRAFICO}" role="img" aria-label="Faturamento acumulado dia a dia no período${temBase ? ' e na comparação' : ''}" class="block" style="touch-action:pan-y">
        ${grade(passos, y, margem.esquerda, largura - margem.direita)}${linhas}${finais}${eixo}
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
        series.forEach((s, k) => {
            const ponto = marcador.querySelector(`[data-ponto="${k}"]`);
            const v = s.valores[i];
            ponto.classList.toggle('hidden', v === null || v === undefined);
            if (v !== null && v !== undefined) { ponto.setAttribute('cx', x(i)); ponto.setAttribute('cy', y(v)); }
        });
        mostrarDica(e, series.map(s => {
            const v = s.valores[i];
            const dia = somarDias(s.de, i);
            return linhaDica(s.cor, `${s.nome} · ${dataCurta(dia).slice(0, 5)}`, v === null || v === undefined ? '—' : moeda(v));
        }).join(''));
    });
    svg.addEventListener('pointerleave', () => { marcador.classList.add('hidden'); esconderDica(); });
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
        ML.toast(`${inteiro(resposta.pedidos)} pedido(s) atualizados em ${String(resposta.segundos).replace('.', ',')} s`, 'sucesso');
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
