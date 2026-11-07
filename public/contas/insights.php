<?php
require __DIR__ . '/../../src/auth.php';
$paginaContas = 'insights';
$classeSelect = 'bg-gray-900 border border-gray-700 rounded-lg p-2 text-sm text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500';
$classeCartao = 'bg-gray-800 border border-gray-700 rounded-xl p-6 mb-6';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insights - Contas</title>
    <script src="/assets/tailwind.js"></script>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen p-8 font-sans selection:bg-blue-500 selection:text-white">
    <?php require __DIR__ . '/../../src/nav.php'; ?>
    <div class="max-w-5xl mx-auto">
        <?php require __DIR__ . '/../../src/contas/cabecalho.php'; ?>

        <div id="semDados" class="hidden bg-gray-800 border border-gray-700 rounded-xl p-8 text-center">
            <p class="text-gray-300 mb-4">Os Insights usam os meses salvos, e você ainda não salvou nenhum.</p>
            <a href="./" class="inline-block bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2.5 px-6 rounded-xl transition-colors">Ir para o mês atual</a>
        </div>

        <div id="conteudo" class="hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                <p id="resumoPeriodo" class="text-sm text-gray-400"></p>
                <label class="text-sm text-gray-400 flex items-center gap-2">Período
                    <select id="periodo" class="<?= $classeSelect ?>">
                        <option value="6">Últimos 6 meses salvos</option>
                        <option value="12" selected>Últimos 12 meses salvos</option>
                        <option value="0">Todos</option>
                    </select>
                </label>
            </div>

            <div id="indicadores" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6"></div>
            <div id="alertas" class="hidden bg-gray-800 border border-amber-500/40 rounded-xl p-6 mb-6"></div>

            <section class="<?= $classeCartao ?>">
                <h2 class="text-xl font-bold text-gray-200 mb-4">Total por mês</h2>
                <div id="graficoTotal"></div>
            </section>

            <section class="<?= $classeCartao ?>">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <h2 class="text-xl font-bold text-gray-200">Evolução por conta</h2>
                    <select id="contaEvolucao" aria-label="Filtrar por conta" class="<?= $classeSelect ?>"></select>
                </div>
                <div id="estatisticasConta" class="hidden grid-cols-2 sm:grid-cols-4 gap-3 mb-4"></div>
                <div id="graficoConta"></div>
                <div id="legendaConta" class="flex flex-wrap gap-x-4 gap-y-2 mt-3"></div>
                <p id="notaConta" class="hidden text-xs text-gray-500 mt-2"></p>
            </section>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <section class="<?= $classeCartao ?> lg:mb-6 mb-0">
                    <h2 id="tituloCategoria" class="text-xl font-bold text-gray-200 mb-4">Gastos por categoria</h2>
                    <div id="graficoCategoria"></div>
                </section>
                <section class="<?= $classeCartao ?>">
                    <h2 id="tituloPeso" class="text-xl font-bold text-gray-200 mb-4">Contas que mais pesam</h2>
                    <div id="graficoPeso"></div>
                </section>
            </div>

            <section class="<?= $classeCartao ?>">
                <h2 class="text-xl font-bold text-gray-200 mb-1">Comparação entre meses</h2>
                <p class="text-sm text-gray-400 mb-4">Até 6 meses salvos, agrupados por categoria. A variação compara o último mês com o anterior.</p>
                <div id="tabelaComparacao" class="overflow-x-auto"></div>
            </section>
        </div>
    </div>

    <div id="dica" role="tooltip" class="hidden fixed z-50 pointer-events-none bg-gray-950 border border-gray-600 rounded-lg px-3 py-2 text-xs text-gray-100 shadow-2xl"></div>

    <script src="/assets/ui.js?v=<?= filemtime(__DIR__ . '/../assets/ui.js') ?>"></script>
    <script src="comum.js?v=<?= filemtime(__DIR__ . '/comum.js') ?>"></script>
    <script>
    const COR_BARRA = '#3b82f6';
    // Ordem fixa validada para o fundo do cartão (#1f2937). A cor acompanha a conta, nunca a posição.
    const CORES_SERIES = ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767'];
    const COR_OUTRAS = '#9ca3af';
    const COR_TOTAL = '#3b82f6';
    const COR_FUNDO = '#1f2937';
    const COR_GRADE = '#374151';
    const COR_BASE = '#4b5563';
    const COR_TEXTO_EIXO = '#9ca3af';
    const COR_TEXTO_ROTULO = '#d1d5db';
    const ALTURA_GRAFICO = 280;
    const LARGURA_ROTULO_MES = 54;
    const MAXIMO_NOMES_DIRETOS = 4;
    const graficos = {}; // Guarda os dados de cada gráfico para redesenhar ao mudar a largura
    const SEM_CATEGORIA = 'Sem categoria';
    const PREFIXO_CATEGORIA = 'categoria:';
    const FILTRO_TODAS_CATEGORIAS = 'todas-categorias';
    const LIMITE_ALERTA = 1.2; // 20% acima da própria média
    const MINIMO_MESES_ALERTA = 3;
    const DIFERENCA_MINIMA_ALERTA = 10; // Em reais: evita alerta por centavos em contas pequenas
    const { esc, moeda, mesCurto, mesLabel } = Contas;
    let dados = { meses: [], lancamentos: [] };

    const soma = valores => valores.reduce((total, v) => total + v, 0);
    const media = valores => valores.length ? soma(valores) / valores.length : 0;

    function periodoAtual() {
        const quantidade = Number(document.getElementById('periodo').value);
        const meses = quantidade ? dados.meses.slice(-quantidade) : dados.meses;
        const lancamentos = dados.lancamentos.filter(l => meses.includes(l.mes));
        return { meses, lancamentos };
    }

    function totalDoMes(lancamentos, mes) {
        return soma(lancamentos.filter(l => l.mes === mes).map(l => l.valor));
    }

    function variacaoHtml(atual, anterior) {
        if (!anterior) return '<span class="text-gray-500">sem base de comparação</span>';
        const diferenca = atual - anterior;
        if (Math.abs(diferenca) < 0.005) return '<span class="text-gray-400">= igual ao mês anterior</span>';
        const percentual = Math.abs(diferenca / anterior * 100).toLocaleString('pt-BR', { maximumFractionDigits: 1 });
        return diferenca > 0
            ? `<span class="text-red-400">▲ ${moeda(diferenca)} (${percentual}%)</span>`
            : `<span class="text-emerald-400">▼ ${moeda(-diferenca)} (${percentual}%)</span>`;
    }

    function cartaoIndicador(rotulo, valor, detalhe) {
        return `
            <div class="bg-gray-800 border border-gray-700 rounded-xl p-4">
                <div class="text-sm text-gray-400 mb-1">${rotulo}</div>
                <div class="text-2xl font-bold tabular-nums break-words">${valor}</div>
                <div class="text-xs mt-1 text-gray-400">${detalhe}</div>
            </div>`;
    }

    function renderizarIndicadores({ meses, lancamentos }) {
        const totais = meses.map(m => totalDoMes(lancamentos, m));
        const ultimoMes = meses[meses.length - 1];
        const ultimoTotal = totais[totais.length - 1];
        const contasPorPeso = pesoPorChave(lancamentos, l => l.nome);
        const maiorConta = contasPorPeso[0];
        document.getElementById('indicadores').innerHTML = [
            cartaoIndicador(`Último mês (${mesCurto(ultimoMes)})`, moeda(ultimoTotal), variacaoHtml(ultimoTotal, totais[totais.length - 2])),
            cartaoIndicador('Média mensal', moeda(media(totais)), `${meses.length} mes(es) no período`),
            cartaoIndicador('Total no período', moeda(soma(totais)), `de ${mesCurto(meses[0])} a ${mesCurto(ultimoMes)}`),
            cartaoIndicador('Conta que mais pesa', esc(maiorConta?.chave ?? '-'), maiorConta ? `${moeda(maiorConta.total)} (${(maiorConta.total / soma(totais) * 100).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%)` : '')
        ].join('');
    }

    // Avisa quando o último mês salvo ficou bem acima do histórico anterior da própria conta.
    function renderizarAlertas() {
        const ultimoMes = dados.meses[dados.meses.length - 1];
        const porConta = {};
        dados.lancamentos.forEach(l => (porConta[l.contaId] ||= { nome: l.nome, itens: [] }).itens.push(l));

        const alertas = Object.values(porConta).flatMap(conta => {
            const atual = conta.itens.find(i => i.mes === ultimoMes);
            const anteriores = conta.itens.filter(i => i.mes < ultimoMes).map(i => i.valor);
            if (!atual || anteriores.length < MINIMO_MESES_ALERTA) return [];
            const referencia = media(anteriores);
            return referencia > 0 && atual.valor > referencia * LIMITE_ALERTA && atual.valor - referencia >= DIFERENCA_MINIMA_ALERTA
                ? [{ nome: conta.nome, valor: atual.valor, referencia, excesso: atual.valor / referencia }]
                : [];
        }).sort((a, b) => b.excesso - a.excesso);

        const area = document.getElementById('alertas');
        area.classList.toggle('hidden', alertas.length === 0);
        area.innerHTML = `
            <h2 class="text-lg font-bold text-amber-300 mb-2">⚠ Acima do normal em ${mesLabel(ultimoMes)}</h2>
            <ul class="space-y-1 text-sm text-gray-300">
                ${alertas.map(a => `<li><b>${esc(a.nome)}</b>: ${moeda(a.valor)}, contra média de ${moeda(a.referencia)} (+${((a.excesso - 1) * 100).toLocaleString('pt-BR', { maximumFractionDigits: 0 })}%)</li>`).join('')}
            </ul>`;
    }

    function escalaVertical(maximo) {
        if (!maximo) return { passo: 1, topo: 4 };
        const bruto = maximo / 4;
        const potencia = 10 ** Math.floor(Math.log10(bruto));
        const fator = bruto / potencia;
        const passo = (fator <= 1 ? 1 : fator <= 2 ? 2 : fator <= 2.5 ? 2.5 : fator <= 5 ? 5 : 10) * potencia;
        return { passo, topo: passo * Math.ceil(maximo / passo) };
    }

    // Curva monotônica: suaviza sem passar acima ou abaixo dos valores reais, como fariam
    // outras curvas, que poderiam até sugerir gasto negativo.
    function curvaMonotona(pontos) {
        const n = pontos.length;
        if (n < 2) return '';
        const dx = [], inclinacao = [];
        for (let i = 0; i < n - 1; i++) {
            dx[i] = pontos[i + 1].x - pontos[i].x;
            inclinacao[i] = (pontos[i + 1].y - pontos[i].y) / dx[i];
        }
        const tangente = [inclinacao[0]];
        for (let i = 1; i < n - 1; i++) {
            const a = inclinacao[i - 1], b = inclinacao[i];
            tangente[i] = a * b <= 0 ? 0 : 3 * (dx[i - 1] + dx[i]) / ((2 * dx[i] + dx[i - 1]) / a + (dx[i] + 2 * dx[i - 1]) / b);
        }
        tangente[n - 1] = inclinacao[n - 2];

        let caminho = `M${pontos[0].x.toFixed(1)},${pontos[0].y.toFixed(1)}`;
        for (let i = 0; i < n - 1; i++) {
            const c1x = pontos[i].x + dx[i] / 3, c1y = pontos[i].y + tangente[i] * dx[i] / 3;
            const c2x = pontos[i + 1].x - dx[i] / 3, c2y = pontos[i + 1].y - tangente[i + 1] * dx[i] / 3;
            caminho += `C${c1x.toFixed(1)},${c1y.toFixed(1)} ${c2x.toFixed(1)},${c2y.toFixed(1)} ${pontos[i + 1].x.toFixed(1)},${pontos[i + 1].y.toFixed(1)}`;
        }
        return caminho;
    }

    // Um mês sem valor interrompe a linha, em vez de ligar os vizinhos como se houvesse dado.
    function trechosContinuos(valores, x, y) {
        const trechos = [];
        let atual = [];
        valores.forEach((valor, i) => {
            if (valor === null) {
                if (atual.length) trechos.push(atual);
                atual = [];
            } else {
                atual.push({ x: x(i), y: y(valor) });
            }
        });
        if (atual.length) trechos.push(atual);
        return trechos;
    }

    function graficoLinhas(alvo, meses, series, opcoes = {}) {
        graficos[alvo] = { meses, series, opcoes };
        desenharGrafico(alvo);
    }

    function desenharGrafico(alvo) {
        const { meses, series, opcoes } = graficos[alvo];
        const area = document.getElementById(alvo);
        esconderDica(); // O gráfico anterior sai da tela sem avisar que o ponteiro deixou de estar nele
        const temDados = series.some(s => s.valores.some(v => v !== null));
        if (!meses.length || !temDados) {
            area.innerHTML = '<p class="text-sm text-gray-500 py-6 text-center">Sem dados no período.</p>';
            return;
        }

        const largura = Math.max(area.clientWidth, 260);
        const margem = { topo: 28, direita: 14, baixo: 26, esquerda: 50 };
        const larguraUtil = largura - margem.esquerda - margem.direita;
        const alturaUtil = ALTURA_GRAFICO - margem.topo - margem.baixo;
        const x = i => margem.esquerda + (meses.length === 1 ? larguraUtil / 2 : i * larguraUtil / (meses.length - 1));
        const maiorValor = Math.max(...series.flatMap(s => s.valores.filter(v => v !== null)), opcoes.referencia ?? 0);
        const { passo, topo } = escalaVertical(maiorValor);
        const y = valor => margem.topo + alturaUtil - valor / topo * alturaUtil;

        let grade = '';
        for (let valor = 0; valor <= topo + passo / 2; valor += passo) {
            const py = y(valor).toFixed(1);
            grade += `<line x1="${margem.esquerda}" x2="${largura - margem.direita}" y1="${py}" y2="${py}" stroke="${valor === 0 ? COR_BASE : COR_GRADE}" stroke-width="1"/>
                <text x="${margem.esquerda - 8}" y="${py}" dy="4" text-anchor="end" fill="${COR_TEXTO_EIXO}" font-size="11" style="font-variant-numeric:tabular-nums">${valor.toLocaleString('pt-BR', { maximumFractionDigits: 2 })}</text>`;
        }

        const pularMeses = Math.max(1, Math.ceil(meses.length * LARGURA_ROTULO_MES / Math.max(larguraUtil, 1)));
        const eixo = meses.map((mes, i) => {
            const distanciaDoFim = meses.length - 1 - i;
            if (distanciaDoFim % pularMeses !== 0) return '';
            const ancora = meses.length > 1 && i === 0 ? 'start' : meses.length > 1 && i === meses.length - 1 ? 'end' : 'middle';
            return `<text x="${x(i).toFixed(1)}" y="${ALTURA_GRAFICO - 8}" text-anchor="${ancora}" fill="${COR_TEXTO_EIXO}" font-size="11">${mesCurto(mes)}</text>`;
        }).join('');

        const linhas = series.map(s => trechosContinuos(s.valores, x, y).map(trecho => trecho.length > 1
            ? `<path d="${curvaMonotona(trecho)}" fill="none" stroke="${s.cor}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>`
            : `<circle cx="${trecho[0].x.toFixed(1)}" cy="${trecho[0].y.toFixed(1)}" r="4" fill="${s.cor}"/>`).join('')).join('');

        const fimDe = s => {
            for (let i = s.valores.length - 1; i >= 0; i--) if (s.valores[i] !== null) return i;
            return -1;
        };
        const marcadorEm = (s, i) => `<circle cx="${x(i).toFixed(1)}" cy="${y(s.valores[i]).toFixed(1)}" r="4" fill="${s.cor}" stroke="${COR_FUNDO}" stroke-width="2"/>`;
        const pontosFinais = series.map(s => {
            if (opcoes.marcadores) return s.valores.map((v, i) => (v === null ? '' : marcadorEm(s, i))).join('');
            const i = fimDe(s);
            return i < 0 ? '' : marcadorEm(s, i);
        }).join('');

        const rotulo = (px, py, texto, ancora = 'middle') => `<text x="${px.toFixed(1)}" y="${py.toFixed(1)}" text-anchor="${ancora}" fill="${COR_TEXTO_ROTULO}" font-size="11" stroke="${COR_FUNDO}" stroke-width="3" style="font-variant-numeric:tabular-nums;paint-order:stroke">${esc(texto)}</text>`;
        let rotulos = '';
        if (series.length === 1) {
            // Linha única: rótulo só no último valor e no maior, nunca em todos os pontos
            const valores = series[0].valores;
            const ultimo = fimDe(series[0]);
            const maior = valores.reduce((m, v, i) => (v !== null && (m < 0 || v > valores[m]) ? i : m), -1);
            if (ultimo >= 0) {
                // Linha chegando de cima: o rótulo vai abaixo do ponto para não cruzar com ela
                const veioDeCima = ultimo > 0 && valores[ultimo - 1] !== null && valores[ultimo - 1] > valores[ultimo];
                rotulos += rotulo(x(ultimo), y(valores[ultimo]) + (veioDeCima ? 20 : -10), moeda(valores[ultimo]), 'end');
            }
            if (maior >= 0 && maior !== ultimo && x(ultimo) - x(maior) > 130) {
                const ancora = maior === 0 ? 'start' : 'middle';
                rotulos += rotulo(x(maior), y(valores[maior]) - 10, moeda(valores[maior]), ancora);
            }
        } else if (series.length <= MAXIMO_NOMES_DIRETOS) {
            // Poucas linhas: nome junto ao fim. Se dois nomes colidem, a legenda e os valores cobrem.
            const usados = [];
            series.map(s => ({ s, i: fimDe(s) })).filter(c => c.i >= 0)
                .sort((a, b) => y(a.s.valores[a.i]) - y(b.s.valores[b.i]))
                .forEach(({ s, i }) => {
                    const py = y(s.valores[i]) - 10;
                    if (usados.some(u => Math.abs(u - py) < 14)) return;
                    usados.push(py);
                    rotulos += rotulo(x(i), py, s.nome, 'end');
                });
        }

        let referencia = '';
        if (opcoes.referencia !== undefined) {
            const py = y(opcoes.referencia);
            referencia = `<line x1="${margem.esquerda}" x2="${largura - margem.direita}" y1="${py.toFixed(1)}" y2="${py.toFixed(1)}" stroke="${COR_TEXTO_EIXO}" stroke-width="1" stroke-dasharray="4 4"/>
                ${rotulo(margem.esquerda + 4, py - 6, 'média ' + moeda(opcoes.referencia), 'start')}`;
        }

        const descricao = series.length === 1
            ? `${series[0].nome}, ${mesCurto(meses[0])} a ${mesCurto(meses[meses.length - 1])}. Use as setas para ver cada mês.`
            : `${series.length} contas, ${mesCurto(meses[0])} a ${mesCurto(meses[meses.length - 1])}. Use as setas para ver cada mês.`;

        area.innerHTML = `
            <svg width="${largura}" height="${ALTURA_GRAFICO}" viewBox="0 0 ${largura} ${ALTURA_GRAFICO}" role="img" tabindex="0" aria-label="${esc(descricao)}" class="block outline-none focus-visible:ring-1 focus-visible:ring-blue-500 rounded" style="touch-action:pan-y">
                ${grade}${referencia}${linhas}${pontosFinais}${rotulos}${eixo}
                <g data-marcador class="hidden">
                    <line data-mira y1="${margem.topo}" y2="${margem.topo + alturaUtil}" stroke="${COR_TEXTO_EIXO}" stroke-width="1"/>
                    ${series.map((s, i) => `<circle data-ponto="${i}" r="4" fill="${s.cor}" stroke="${COR_FUNDO}" stroke-width="2"/>`).join('')}
                </g>
                <rect data-alvo x="${margem.esquerda - 10}" y="0" width="${larguraUtil + 20}" height="${ALTURA_GRAFICO - margem.baixo}" fill="transparent"/>
            </svg>`;

        const svg = area.querySelector('svg');
        const marcador = svg.querySelector('[data-marcador]');
        let mesAtivo = meses.length - 1;

        const mostrar = indice => {
            mesAtivo = indice;
            marcador.classList.remove('hidden');
            const mira = marcador.querySelector('[data-mira]');
            mira.setAttribute('x1', x(indice));
            mira.setAttribute('x2', x(indice));
            series.forEach((s, i) => {
                const ponto = marcador.querySelector(`[data-ponto="${i}"]`);
                const valor = s.valores[indice];
                ponto.classList.toggle('hidden', valor === null);
                if (valor !== null) {
                    ponto.setAttribute('cx', x(indice));
                    ponto.setAttribute('cy', y(valor));
                }
            });
            mostrarDica(svg, x(indice), meses[indice], series, indice);
        };
        const esconder = () => {
            marcador.classList.add('hidden');
            esconderDica();
        };

        svg.querySelector('[data-alvo]').addEventListener('pointermove', e => {
            const relativo = e.clientX - svg.getBoundingClientRect().left;
            const passoX = meses.length > 1 ? larguraUtil / (meses.length - 1) : 1;
            mostrar(Math.min(meses.length - 1, Math.max(0, Math.round((relativo - margem.esquerda) / passoX))));
        });
        svg.addEventListener('pointerleave', esconder);
        svg.addEventListener('focus', () => mostrar(mesAtivo));
        svg.addEventListener('blur', esconder);
        svg.addEventListener('keydown', e => {
            if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                e.preventDefault();
                mostrar(Math.min(meses.length - 1, Math.max(0, mesAtivo + (e.key === 'ArrowRight' ? 1 : -1))));
            } else if (e.key === 'Escape') {
                esconder();
            }
        });
    }

    function pesoPorChave(lancamentos, chave) {
        const totais = {};
        lancamentos.forEach(l => {
            const k = chave(l);
            totais[k] = (totais[k] || 0) + l.valor;
        });
        return Object.entries(totais).map(([k, total]) => ({ chave: k, total })).sort((a, b) => b.total - a.total);
    }

    function graficoHorizontal(alvo, itens) {
        const area = document.getElementById(alvo);
        if (!itens.length) {
            area.innerHTML = '<p class="text-sm text-gray-500 py-6 text-center">Sem dados no período.</p>';
            return;
        }
        const total = soma(itens.map(i => i.total)) || 1;
        const maximo = itens[0].total || 1;
        area.innerHTML = `<div class="space-y-3">${itens.map(item => {
            const percentual = (item.total / total * 100).toLocaleString('pt-BR', { maximumFractionDigits: 1 });
            return `
                <div>
                    <div class="flex justify-between gap-3 text-sm mb-1">
                        <span class="text-gray-200 break-words min-w-0">${esc(item.chave)}</span>
                        <span class="text-gray-300 tabular-nums whitespace-nowrap">${moeda(item.total)} · ${percentual}%</span>
                    </div>
                    <div class="h-2 bg-gray-900 rounded-r"><div class="h-2 rounded-r" style="width:${item.total / maximo * 100}%;background:${COR_BARRA}"></div></div>
                </div>`;
        }).join('')}</div>`;
    }

    // As primeiras contas com algum valor ficam com as 8 cores, sempre na mesma ordem.
    function coresDasContas() {
        const ids = [...new Set(dados.lancamentos.filter(l => l.valor > 0).map(l => l.contaId))].sort((a, b) => a - b);
        return new Map(ids.slice(0, CORES_SERIES.length).map((id, i) => [id, CORES_SERIES[i]]));
    }

    function coresDasCategorias() {
        const nomes = [...new Set(dados.lancamentos.filter(l => l.valor > 0).map(l => l.categoria || SEM_CATEGORIA))].sort((a, b) => a.localeCompare(b, 'pt-BR'));
        return new Map(nomes.slice(0, CORES_SERIES.length).map((nome, i) => [nome, CORES_SERIES[i]]));
    }

    function renderizarEvolucaoConta({ meses, lancamentos }) {
        const select = document.getElementById('contaEvolucao');
        const contas = [...new Map(dados.lancamentos.map(l => [l.contaId, l.nome])).entries()].sort((a, b) => a[1].localeCompare(b[1], 'pt-BR'));
        const selecionada = select.value;
        const categorias = [...new Set(dados.lancamentos.map(l => l.categoria || SEM_CATEGORIA))].sort((a, b) => a.localeCompare(b, 'pt-BR'));
        const opcao = (valor, nome) => `<option value="${esc(valor)}" ${valor === selecionada ? 'selected' : ''}>${esc(nome)}</option>`;
        select.innerHTML = '<option value="">Todas as contas</option>'
            + opcao(FILTRO_TODAS_CATEGORIAS, 'Todas as categorias')
            + `<optgroup label="Categorias">${categorias.map(c => opcao(PREFIXO_CATEGORIA + c, c)).join('')}</optgroup>`
            + `<optgroup label="Contas">${contas.map(([id, nome]) => opcao(String(id), nome)).join('')}</optgroup>`;

        const cores = coresDasContas();
        const coresCategorias = coresDasCategorias();
        const valoresDa = id => meses.map(mes => lancamentos.find(l => l.mes === mes && l.contaId === id)?.valor ?? null);
        const valoresDaCategoria = categoria => meses.map(mes => {
            const doMes = lancamentos.filter(l => l.mes === mes && (l.categoria || SEM_CATEGORIA) === categoria).map(l => l.valor);
            return doMes.length ? soma(doMes) : null;
        });
        const estatisticas = document.getElementById('estatisticasConta');
        const legenda = document.getElementById('legendaConta');
        const nota = document.getElementById('notaConta');

        if (select.value && select.value !== FILTRO_TODAS_CATEGORIAS) {
            const categoria = select.value.startsWith(PREFIXO_CATEGORIA) ? select.value.slice(PREFIXO_CATEGORIA.length) : null;
            const id = categoria === null ? Number(select.value) : null;
            // Categoria vira uma linha só, com a soma das contas dela em cada mês
            const valores = categoria === null ? valoresDa(id) : valoresDaCategoria(categoria);
            const presentes = valores.filter(v => v !== null);
            const mediaConta = media(presentes);
            const estatistica = (rotulo, valor) => `
                <div class="bg-gray-900 rounded-lg p-3">
                    <div class="text-xs text-gray-400">${rotulo}</div>
                    <div class="font-bold tabular-nums">${presentes.length ? moeda(valor) : '-'}</div>
                </div>`;
            estatisticas.innerHTML = [
                estatistica('Último', presentes[presentes.length - 1]),
                estatistica('Média', mediaConta),
                estatistica('Maior', Math.max(...presentes)),
                estatistica('Menor', Math.min(...presentes))
            ].join('');
            estatisticas.classList.remove('hidden');
            estatisticas.classList.add('grid');
            legenda.innerHTML = '';
            nota.classList.add('hidden');
            const nome = categoria ?? contas.find(([contaId]) => contaId === id)?.[1] ?? '';
            const cor = (categoria === null ? cores.get(id) : coresCategorias.get(categoria)) ?? COR_OUTRAS;
            graficoLinhas('graficoConta', meses, [{ nome, cor, valores }], presentes.length > 1 ? { referencia: mediaConta } : {});
            return;
        }

        estatisticas.classList.add('hidden');
        estatisticas.classList.remove('grid');

        // Item zerado em todo o período viraria uma linha colada no zero, sem informação
        const porCategoria = select.value === FILTRO_TODAS_CATEGORIAS;
        const itens = porCategoria
            ? categorias.map(nome => ({ chave: nome, nome, filtro: PREFIXO_CATEGORIA + nome, cor: coresCategorias.get(nome), valores: valoresDaCategoria(nome) }))
            : [...contas].sort((a, b) => a[0] - b[0]).map(([id, nome]) => ({ chave: id, nome, filtro: String(id), cor: cores.get(id), valores: valoresDa(id) }));
        const comValor = itens.filter(item => item.valores.some(v => v > 0));
        const zerados = itens.filter(item => !comValor.includes(item)).map(item => item.nome);
        const series = comValor.filter(item => item.cor);

        const excedentes = comValor.filter(item => !item.cor);
        if (excedentes.length === 1) {
            // Sozinha, a conta não precisa de agrupamento: segue em cinza, mas com o próprio nome
            series.push({ ...excedentes[0], cor: COR_OUTRAS });
        } else if (excedentes.length > 1) {
            series.push({
                nome: 'Outras',
                cor: COR_OUTRAS,
                valores: meses.map((_, i) => {
                    const valores = excedentes.map(item => item.valores[i]).filter(v => v !== null);
                    return valores.length ? soma(valores) : null;
                })
            });
        }

        const tituloIsolar = porCategoria ? 'Ver só esta categoria' : 'Ver só esta conta';
        legenda.innerHTML = series.map(s => `
            <${s.filtro ? `button type="button" data-isolar="${esc(s.filtro)}" title="${tituloIsolar}"` : 'span'} class="flex items-center gap-2 text-sm text-gray-300 ${s.filtro ? 'hover:text-white' : ''}">
                <span class="inline-block w-4 h-0.5 rounded" style="background:${s.cor}" aria-hidden="true"></span>${esc(s.nome)}
            </${s.filtro ? 'button' : 'span'}>`).join('');
        nota.textContent = zerados.length ? `Zeradas no período, sem linha: ${zerados.join(', ')}.` : '';
        nota.classList.toggle('hidden', !zerados.length);
        graficoLinhas('graficoConta', meses, series);
    }

    function renderizarComparacao({ meses, lancamentos }) {
        const colunas = meses.slice(-6);
        const ultimo = colunas[colunas.length - 1];
        const anterior = colunas[colunas.length - 2];
        const noPeriodo = lancamentos.filter(l => colunas.includes(l.mes));
        const categoriaDe = l => l.categoria || SEM_CATEGORIA;

        const valorDe = (id, mes) => noPeriodo.find(l => l.contaId === id && l.mes === mes)?.valor ?? null;
        const totalCategoria = (categoria, mes) => {
            const valores = noPeriodo.filter(l => categoriaDe(l) === categoria && l.mes === mes).map(l => l.valor);
            return valores.length ? soma(valores) : null;
        };
        const diferenca = (atual, antes) => atual !== null && antes !== null && antes !== undefined ? atual - antes : null;

        const grupos = [...new Set(noPeriodo.map(categoriaDe))].map(categoria => {
            const contas = [...new Map(noPeriodo.filter(l => categoriaDe(l) === categoria).map(l => [l.contaId, l.nome])).entries()]
                .map(([id, nome]) => ({ id, nome, variacao: anterior ? diferenca(valorDe(id, ultimo), valorDe(id, anterior)) : null }))
                .sort((a, b) => (b.variacao ?? -Infinity) - (a.variacao ?? -Infinity) || a.nome.localeCompare(b.nome, 'pt-BR'));
            return {
                categoria,
                contas,
                totalUltimo: totalCategoria(categoria, ultimo) ?? 0,
                variacao: anterior ? diferenca(totalCategoria(categoria, ultimo), totalCategoria(categoria, anterior)) : null
            };
        }).sort((a, b) => b.totalUltimo - a.totalUltimo || a.categoria.localeCompare(b.categoria, 'pt-BR'));

        const celulaVariacao = valor => {
            if (valor === null) return '<span class="text-gray-500">-</span>';
            if (Math.abs(valor) < 0.005) return '<span class="text-gray-400">=</span>';
            return valor > 0 ? `<span class="text-red-400">▲ ${moeda(valor)}</span>` : `<span class="text-emerald-400">▼ ${moeda(-valor)}</span>`;
        };
        const celulaValor = valor => valor === null ? '-' : moeda(valor);
        const totalAnterior = anterior ? totalDoMes(noPeriodo, anterior) : null;

        const corpos = grupos.map(grupo => `
            <tbody class="divide-y divide-gray-700/50 border-b border-gray-700">
                <tr class="bg-gray-900/70 font-bold">
                    <th scope="rowgroup" class="p-2 text-left text-gray-100">${esc(grupo.categoria)}</th>
                    ${colunas.map(m => `<td class="p-2 text-right tabular-nums text-gray-100">${celulaValor(totalCategoria(grupo.categoria, m))}</td>`).join('')}
                    <td class="p-2 text-right tabular-nums">${celulaVariacao(grupo.variacao)}</td>
                </tr>
                ${grupo.contas.map(c => `
                    <tr class="hover:bg-gray-700/30">
                        <td class="p-2 pl-6 text-gray-300">${esc(c.nome)}</td>
                        ${colunas.map(m => `<td class="p-2 text-right tabular-nums text-gray-300">${celulaValor(valorDe(c.id, m))}</td>`).join('')}
                        <td class="p-2 text-right tabular-nums">${celulaVariacao(c.variacao)}</td>
                    </tr>`).join('')}
            </tbody>`).join('');

        document.getElementById('tabelaComparacao').innerHTML = `
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="text-gray-400 border-b border-gray-700">
                    <tr>
                        <th class="text-left p-2 font-semibold">Categoria / conta</th>
                        ${colunas.map(m => `<th class="text-right p-2 font-semibold">${mesCurto(m)}</th>`).join('')}
                        <th class="text-right p-2 font-semibold">Variação</th>
                    </tr>
                </thead>
                ${corpos}
                <tfoot class="border-t border-gray-600 font-bold">
                    <tr>
                        <td class="p-2">Total</td>
                        ${colunas.map(m => `<td class="p-2 text-right tabular-nums">${moeda(totalDoMes(noPeriodo, m))}</td>`).join('')}
                        <td class="p-2 text-right tabular-nums">${celulaVariacao(totalAnterior === null ? null : totalDoMes(noPeriodo, ultimo) - totalAnterior)}</td>
                    </tr>
                </tfoot>
            </table>`;
    }

    function renderizar() {
        const vazio = dados.meses.length === 0;
        document.getElementById('semDados').classList.toggle('hidden', !vazio);
        document.getElementById('conteudo').classList.toggle('hidden', vazio);
        if (vazio) return;

        const periodo = periodoAtual();
        document.getElementById('resumoPeriodo').textContent = `${dados.meses.length} mes(es) salvo(s) no total`;
        renderizarIndicadores(periodo);
        renderizarAlertas();
        graficoLinhas('graficoTotal', periodo.meses, [{ nome: 'Total', cor: COR_TOTAL, valores: periodo.meses.map(mes => totalDoMes(periodo.lancamentos, mes)) }], { marcadores: true });
        renderizarEvolucaoConta(periodo);
        const ultimoMes = periodo.meses[periodo.meses.length - 1];
        const doUltimoMes = periodo.lancamentos.filter(l => l.mes === ultimoMes && l.valor > 0);
        document.getElementById('tituloCategoria').textContent = `Gastos por categoria em ${mesCurto(ultimoMes)}`;
        graficoHorizontal('graficoCategoria', pesoPorChave(doUltimoMes, l => l.categoria || SEM_CATEGORIA));
        document.getElementById('tituloPeso').textContent = `Contas que mais pesam em ${mesCurto(ultimoMes)}`;
        graficoHorizontal('graficoPeso', pesoPorChave(doUltimoMes, l => l.nome));
        renderizarComparacao(periodo);
    }

    // Caixa com os valores de todas as linhas no mês apontado. Nomes entram por textContent.
    function mostrarDica(svg, xLocal, mes, series, indice) {
        const dica = document.getElementById('dica');
        dica.replaceChildren();
        const titulo = document.createElement('div');
        titulo.className = 'text-gray-400 mb-1';
        titulo.textContent = mesLabel(mes);
        dica.appendChild(titulo);

        [...series].sort((a, b) => (b.valores[indice] ?? -1) - (a.valores[indice] ?? -1)).forEach(s => {
            const linha = document.createElement('div');
            linha.className = 'flex items-center gap-2 py-0.5';
            const chave = document.createElement('span');
            chave.className = 'inline-block w-3 h-0.5 rounded shrink-0';
            chave.style.background = s.cor;
            const valor = document.createElement('b');
            valor.className = 'tabular-nums text-gray-100';
            valor.textContent = s.valores[indice] === null ? 'sem valor' : moeda(s.valores[indice]);
            const nome = document.createElement('span');
            nome.className = 'text-gray-400';
            nome.textContent = s.nome;
            linha.append(chave, valor, nome);
            dica.appendChild(linha);
        });

        dica.classList.remove('hidden');
        const caixa = svg.getBoundingClientRect();
        const pontoX = caixa.left + xLocal;
        const esquerda = pontoX + 14 + dica.offsetWidth > innerWidth - 8 ? pontoX - 14 - dica.offsetWidth : pontoX + 14;
        dica.style.left = Math.max(8, esquerda) + 'px';
        dica.style.top = Math.max(8, Math.min(caixa.top + 8, innerHeight - dica.offsetHeight - 8)) + 'px';
    }
    const esconderDica = () => document.getElementById('dica').classList.add('hidden');
    addEventListener('scroll', esconderDica, { passive: true });

    let esperaLargura;
    addEventListener('resize', () => {
        clearTimeout(esperaLargura);
        esperaLargura = setTimeout(() => Object.keys(graficos).forEach(desenharGrafico), 150);
    });

    document.getElementById('legendaConta').addEventListener('click', e => {
        const botao = e.target.closest('[data-isolar]');
        if (!botao) return;
        document.getElementById('contaEvolucao').value = botao.dataset.isolar;
        renderizarEvolucaoConta(periodoAtual());
    });

    document.getElementById('periodo').addEventListener('change', renderizar);
    document.getElementById('contaEvolucao').addEventListener('change', () => renderizarEvolucaoConta(periodoAtual()));

    Contas.api('insights').then(resposta => {
        dados = resposta;
        renderizar();
    }).catch(erro => ML.toast(erro.message || 'Não foi possível carregar os Insights.', 'erro'));
    </script>
</body>
</html>
