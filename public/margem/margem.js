'use strict';
// Aba Margem: tudo vem do ml-tools-web via api.php (busca no ML, cálculo, comparador, envio e tabelas).

const $ = sel => document.querySelector(sel);
const $$ = sel => [...document.querySelectorAll(sel)];
const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
const brl = n => n == null ? '—' : n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const pct = (n, casas = 1) => n == null ? '—' : (n * 100).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas }) + '%';
const pctMeta = n => (n * 100).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) + '%';
const quando = iso => iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
const linkAnuncio = codigo => `https://produto.mercadolivre.com.br/${String(codigo).replace(/^MLB/, 'MLB-')}`;
// Código do anúncio: clicar no código copia; a seta abre o anúncio em nova guia (como na aba Full)
const codigoAnuncio = codigo => `<button type="button" data-copiar="${esc(codigo)}" title="Copiar código do anúncio" class="text-blue-400 hover:text-blue-300 tabular-nums cursor-copy">${esc(codigo)}</button><a href="${linkAnuncio(codigo)}" target="_blank" rel="noopener" title="Abrir anúncio em nova guia" aria-label="Abrir anúncio ${esc(codigo)} em nova guia" class="inline-block ml-1 px-0.5 text-gray-400 hover:text-blue-300">↗</a>`;
const urlPlanilha =(modulo, nome) => `api.php?r=arquivo&modulo=${modulo}&nome=${encodeURIComponent(nome)}`;

const LIMITE_LINHAS = 300;
const NOME_TAREFA = { atualizar: 'Atualização das margens', recalcular: 'Recálculo da margem', teste: 'Teste da margem', parcial: 'Atualização de alguns anúncios', skus: 'Comparação de SKUs', editar: 'Edição à mão', pares: 'Busca de anúncios sem par', clonar: 'Criação da opção de venda', gtin: 'Correção do GTIN duplicado', formato: 'Formato de venda e nova tentativa' };
const STATUS = { active: 'ativo', paused: 'pausado', closed: 'encerrado', under_review: 'em revisão', inactive: 'inativo' };

let estado = null;          // /api/estado
let margem = null;          // última planilha de custo (json)
let skus = null;            // último comparador (json)
let clonar = null;          // anúncios sem par + histórico (json)
let divergentes = new Map(); // código -> frete/adc que o painel do ML cobra (data/frete-divergente.json)
const marcadosClonar = new Set();  // códigos marcados para criar em lote
const MAX_CLONAR = 20;
let ordem = { campo: 'dmeta', dir: -1 };  // padrão: mais longe da meta primeiro
let mostrarTodosMargem = false;
let pollJob = null;
let jobRodando = false;

async function api(rota, opcoes = {}) {
    const r = await fetch('api.php?r=' + rota, opcoes);
    const corpo = await r.json().catch(() => null);
    if (!r.ok) throw new Error((corpo && corpo.erro) || `Erro ${r.status}`);
    return corpo;
}
const postJson = (rota, dados = {}) => api(rota, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(dados) });

function cartao(rotulo, valor, detalhe = '', destaque = '') {
    return `<div class="bg-gray-800 border ${destaque || 'border-gray-700'} rounded-xl p-4 min-w-0">
        <div class="text-xs uppercase tracking-wide text-gray-400">${rotulo}</div>
        <div class="text-xl sm:text-2xl font-bold tabular-nums mt-1">${valor}</div>
        <div class="text-xs text-gray-400 mt-1">${detalhe}</div></div>`;
}
const pilula = (texto, classe) => `<span class="text-[11px] px-1.5 py-0.5 rounded border ${classe} whitespace-nowrap">${esc(texto)}</span>`;

// ===================================================================== abas

function abrirAba(aba) {
    if (!$(`[data-secao="${aba}"]`)) aba = 'margem';
    $$('[data-secao]').forEach(s => s.classList.toggle('hidden', s.dataset.secao !== aba));
    $$('#abas [data-aba]').forEach(b => {
        const ativa = b.dataset.aba === aba;
        b.className = `shrink-0 whitespace-nowrap px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors ${ativa ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white'}`;
        b.setAttribute('aria-current', ativa ? 'page' : 'false');
    });
    if (history.replaceState) history.replaceState(null, '', '#' + aba);
    if (aba === 'tabelas') carregarTabelas();
    if (aba === 'simulador') $('#simSku').focus();
    if (aba === 'clonar') carregarClonar();
}

// ===================================================================== estado geral

async function carregarEstado() {
    estado = await api('estado');
    const c = estado.coleta;
    const parada = c && !c.concluida;
    $('#btnAtualizar').textContent = parada ? `▶️ Continuar a atualização (${c.feitos}/${c.total})` : '🔄 Atualizar margens';
    $('#btnDoZero').classList.toggle('hidden', !parada);
    const p = estado.partes;
    $('#infoMetas').innerHTML = `Margem alvo <strong class="text-gray-200">${pctMeta(estado.meta)}</strong>`
        + ` <span class="text-gray-500">= lucro ${pctMeta(p.lucro)} + gordura ${pctMeta(p.gordura)} + imposto ${pctMeta(p.imposto)}</span>`;
    $('#campoColeta').innerHTML = estado.coletas.length
        ? estado.coletas.map((x, i) => `<option value="${esc(x.nome)}">${esc(x.nome)} (${quando(x.quando)})${i === 0 ? ' · mais recente' : ''}</option>`).join('')
        : '<option value="">Nenhuma busca ainda</option>';
    $('#infoToken').innerHTML = estado.token.ok
        ? `<span class="text-emerald-300">✅ ${esc(estado.token.texto)}</span>`
        : `<span class="text-red-300">⚠️ ${esc(estado.token.texto)}</span>`;
    $('#campoRps').value = String(estado.rps).replace('.', ',');
    mostrarJob(estado.job);
}

// ===================================================================== tarefa (job)

function mostrarJob(job) {
    const painel = $('#painelJob');
    jobRodando = !!job && job.estado === 'rodando';
    $$('.job').forEach(b => { b.disabled = jobRodando; });
    if (clonar) atualizarMarcados();  // "Criar marcados" só com algo marcado
    if (!job || (!jobRodando && job.visto)) {
        painel.classList.add('hidden');
        return;
    }
    painel.classList.remove('hidden');
    const nome = NOME_TAREFA[job.tipo] || 'Tarefa';
    const log = (job.log || []).length
        ? `<pre class="mt-3 bg-gray-900 border border-gray-700 rounded-lg p-3 text-xs text-gray-400 overflow-x-auto max-h-48 whitespace-pre-wrap break-words">${esc(job.log.join('\n'))}</pre>` : '';
    if (jobRodando) {
        const p = job.progresso;
        const porc = p && p.total ? Math.min(100, Math.round(100 * p.feitos / p.total)) : null;
        painel.className = 'bg-gray-800 border border-blue-500/40 rounded-xl p-4 sm:p-5 mb-6';
        painel.innerHTML = `
            <div class="flex flex-wrap justify-between items-start gap-3">
                <div class="min-w-0">
                    <h2 class="font-semibold text-gray-100"><span class="inline-block animate-spin" aria-hidden="true">⏳</span> ${esc(nome)}</h2>
                    <p class="text-sm text-gray-400">${esc(job.descricao)}${job.passos > 1 ? ` · passo ${job.passo} de ${job.passos}` : ''} · desde ${quando(job.inicio)}</p>
                </div>
                <button type="button" id="btnParar" class="${CLASSE_BOTAO} text-sm">⏹️ Parar</button>
            </div>
            ${porc !== null ? `
            <div class="mt-3">
                <div class="flex justify-between text-xs text-gray-400 mb-1"><span>${esc(p.rotulo)}</span><span class="tabular-nums">${p.feitos.toLocaleString('pt-BR')} / ${p.total.toLocaleString('pt-BR')} (${porc}%)</span></div>
                <div class="h-2 bg-gray-900 rounded-full overflow-hidden"><div class="h-full bg-blue-500 transition-all" style="width:${porc}%"></div></div>
            </div>` : ''}
            ${log}
            <p class="text-xs text-gray-500 mt-3">Pode fechar esta aba: a tarefa continua no servidor.</p>`;
        $('#btnParar').addEventListener('click', pararJob);
        return;
    }
    const cores = { ok: 'border-emerald-500/50', erro: 'border-red-500/50', parado: 'border-amber-500/50' };
    const icone = { ok: '✅', erro: '❌', parado: '⏸️' }[job.estado] || '';
    const modulo = job.tipo === 'skus' ? 'skus' : 'margem';
    // criação da opção de venda: o resumo do loop fica no histórico da aba Clonar; o log mostra o que aconteceu
    painel.className = `bg-gray-800 border ${cores[job.estado] || 'border-gray-700'} rounded-xl p-4 sm:p-5 mb-6`;
    painel.innerHTML = `
        <div class="flex flex-wrap justify-between items-start gap-3">
            <div class="min-w-0">
                <h2 class="font-semibold text-gray-100">${icone} ${esc(nome)}</h2>
                <p class="text-sm text-gray-300 break-words">${esc(job.mensagem)}</p>
                <p class="text-xs text-gray-500">${quando(job.inicio)} → ${quando(job.fim)}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                ${job.arquivo ? `<a class="${CLASSE_PRIMARIO} text-sm" href="${urlPlanilha(modulo, job.arquivo)}">⬇️ ${esc(job.arquivo)}</a>` : ''}
                <button type="button" id="btnVisto" class="${CLASSE_BOTAO} text-sm">Ok</button>
            </div>
        </div>
        ${job.estado !== 'ok' || ['clonar', 'gtin', 'formato'].includes(job.tipo) ? log : ''}`;
    $('#btnVisto').addEventListener('click', async () => {
        await postJson('visto').catch(() => null);
        painel.classList.add('hidden');
    });
}

function acompanharJob() {
    if (pollJob) return;
    pollJob = setInterval(async () => {
        let job;
        try {
            job = (await api('job')).job;
        } catch (e) {
            return; // falha passageira: tenta de novo no próximo ciclo
        }
        mostrarJob(job);
        if (job && ['clonar', 'formato'].includes(job.tipo)) await mostrarModalClonar(job);
        if (!job || job.estado !== 'rodando') {
            clearInterval(pollJob);
            pollJob = null;
            if (job) {
                const tipo = { ok: 'sucesso', erro: 'erro', parado: 'aviso' }[job.estado] || 'info';
                ML.toast(`${NOME_TAREFA[job.tipo] || 'Tarefa'}: ${job.mensagem}`, tipo);
            }
            await recarregarTudo(job && job.tipo);
        }
    }, 2000);
}

async function iniciarJob(dados) {
    try {
        const { job } = await postJson('iniciar', dados);
        mostrarJob(job);
        if (['clonar', 'formato'].includes(job.tipo)) await mostrarModalClonar(job);
        acompanharJob();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (e) {
        ML.toast(e.message, 'erro');
    }
}

async function pararJob() {
    const ok = await ML.confirmar('Parar a tarefa? O que já foi buscado fica salvo e dá para continuar depois.', { titulo: 'Parar', textoConfirmar: 'Parar', perigo: true });
    if (!ok) return;
    await postJson('parar').catch(e => ML.toast(e.message, 'erro'));
}

async function recarregarTudo(tipo) {
    await carregarEstado().catch(e => ML.toast(e.message, 'erro'));
    if (tipo === 'skus') await carregarSkus();
    else if (tipo === 'teste') await carregarTeste();
    else if (tipo === 'pares' || tipo === 'gtin') await carregarClonar();
    else if (tipo === 'clonar' || tipo === 'formato') await Promise.all([carregarClonar(), carregarMargem()]);
    else await carregarMargem();
    if ($('#avancado').open) carregarPlanilhas();
}

// ===================================================================== margem

function situacao(l) {
    if (l.custo == null) return 'sem';
    if (l.so_gratis) return 'gratis';
    if (l.margem == null) return 'sem';
    const { meta, tolerancia } = margem;
    return l.margem < meta - tolerancia ? 'abaixo' : l.margem > meta + tolerancia ? 'acima' : 'na';
}

async function carregarMargem() {
    try {
        const [m, d] = await Promise.all([api('margem'), api('frete_divergente').catch(() => null)]);
        margem = m.resultado;
        if (d) divergentes = new Map(d.anuncios.map(x => [x.codigo, x]));
    } catch (e) {
        ML.toast(e.message, 'erro');
        return;
    }
    if (margem) {
        margem.linhas.forEach(l => {
            l.situacao = situacao(l);
            // o "Você recebe" do ML: preço menos a taxa, o frete (frete grátis) e o adicional (sem frete grátis)
            l.recebe = Math.round((l.preco * (1 - l.taxa) - l.frete - l.adc) * 100) / 100;
            // distância da margem até a meta, em valor absoluto (vazio sem custo ou só com itens grátis)
            l.dmeta = l.margem == null || l.situacao === 'sem' || l.situacao === 'gratis' ? null : Math.abs(l.margem - margem.meta);
        });
    }
    renderMargem();
    renderClonar();  // a aba Clonar mostra a margem do original
}

function renderMargem() {
    const sub = $('#subtitulo');
    if (!margem) {
        sub.textContent = 'Nenhuma planilha de margem ainda.';
        $('#infoColeta').textContent = 'Use Atualizar margens para buscar os anúncios no ML.';
        $('#resumoMargem').innerHTML = '';
        $('#tabelaMargem').innerHTML = '<tr><td colspan="8" class="py-6 text-center text-gray-500">Nenhuma planilha ainda.</td></tr>';
        $('#baixarMargem').classList.add('hidden');
        return;
    }
    // filtros de tipo e envio só aparecem quando a planilha tem a informação
    $('#tipoMargem').classList.toggle('hidden', !margem.linhas.some(l => l.tipo));
    $('#envioMargem').classList.toggle('hidden', !margem.linhas.some(l => l.envio));
    sub.textContent = `Planilha de ${quando(margem.gerado)} · busca no ML de ${quando(margem.coleta_data)}`;
    $('#infoColeta').innerHTML = `Busca no ML de <strong class="text-gray-200">${quando(margem.coleta_data)}</strong> · ${margem.linhas.length.toLocaleString('pt-BR')} anúncios`
        + (margem.meta !== estado?.meta || (margem.imposto || 0) !== (estado?.partes.imposto || 0) ? ' · <span class="text-amber-300">margem alvo mudou: recalcule</span>' : '');
    const baixar = $('#baixarMargem');
    baixar.href = urlPlanilha('margem', margem.planilha);
    baixar.classList.remove('hidden');

    const conta = s => margem.linhas.filter(l => l.situacao === s).length;
    const tol = margem.tolerancia;
    $('#resumoMargem').innerHTML = [
        cartao('Anúncios', margem.linhas.length.toLocaleString('pt-BR'), `${conta('gratis')} só com itens grátis`),
        cartao('Abaixo da meta', conta('abaixo'), `margem &lt; ${pctMeta(margem.meta - tol)}`, conta('abaixo') ? 'border-red-500/60' : ''),
        cartao('Na meta', conta('na'), `${pctMeta(margem.meta - tol)} a ${pctMeta(margem.meta + tol)}`),
        cartao('Acima da meta', conta('acima'), `margem &gt; ${pctMeta(margem.meta + tol)}`, conta('acima') ? 'border-amber-500/60' : ''),
        cartao('Sem custo', conta('sem'), 'SKU sem preço na tabela', conta('sem') ? 'border-red-500/60' : ''),
    ].join('');
    renderTabelaMargem();
}

function linhasFiltradas() {
    const busca = $('#buscaMargem').value.trim().toUpperCase();
    const marcadas = new Set($$('#opcoesMargem input:checked').map(c => c.value));
    const status = $('#statusMargem').value;
    const tipo = $('#tipoMargem').value;
    const envio = $('#envioMargem').value;
    const lista = margem.linhas.filter(l =>
        (!tipo || l.tipo === tipo) && (!envio || l.envio === envio) &&
        (!busca || `${l.codigo} ${l.sku} ${l.titulo}`.toUpperCase().includes(busca))
        && marcadas.has(l.situacao)
        && (!status || (status === 'outros' ? !['active', 'paused'].includes(l.status) : l.status === status)));
    const { campo, dir } = ordem;
    return lista.sort((a, b) => {
        const x = a[campo], y = b[campo];
        if (x == null && y == null) return 0;
        if (x == null) return 1; // vazios sempre no fim
        if (y == null) return -1;
        return (typeof x === 'string' ? x.localeCompare(y, 'pt-BR') : x - y) * dir;
    });
}

// Etiquetas do anúncio: tipo (Clássico/Premium) e envio (Full/Normal)
const COR_TIPO = { 'Premium': 'border-violet-500/50 text-violet-200 bg-violet-900/30', 'Clássico': 'border-sky-600/50 text-sky-200 bg-sky-900/20' };
const COR_ENVIO = { 'Full': 'border-emerald-500/50 text-emerald-200 bg-emerald-900/30', 'Normal': 'border-gray-600 text-gray-300' };
const etiquetas = l => (l.tipo ? pilula(l.tipo, COR_TIPO[l.tipo] || 'border-gray-600 text-gray-400') : '')
    + (l.envio ? pilula(l.envio === 'Full' ? '⚡ Full' : 'Normal', COR_ENVIO[l.envio] || 'border-gray-600 text-gray-400') : '');

// Diferença para a meta em pontos percentuais: 0,35 pp
const pp = n => (n * 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' pp';
const COR_DMETA = { abaixo: 'text-red-300', na: 'text-gray-400', acima: 'text-amber-300' };

const COR_MARGEM = {
    abaixo: 'bg-red-900/60 text-red-200 border-red-500/40',
    na: 'bg-emerald-900/40 text-emerald-200 border-emerald-500/30',
    acima: 'bg-amber-900/50 text-amber-200 border-amber-500/40',
    gratis: 'bg-gray-900 text-gray-300 border-gray-600',
};

function diferenca(valor, base) {
    if (valor == null) return '';
    if (Math.abs(valor) < 0.005) return '<div class="text-[11px] text-gray-500">igual</div>';
    const cor = valor > 0 ? 'text-red-300' : 'text-sky-300';
    return `<div class="text-[11px] ${cor} tabular-nums">${valor > 0 ? '+' : '−'}${brl(Math.abs(valor)).replace('R$', '').trim()} (${pct(Math.abs(valor) / base)})</div>`;
}

function renderTabelaMargem() {
    if (!margem) return;
    const lista = linhasFiltradas();
    const visiveis = mostrarTodosMargem ? lista : lista.slice(0, LIMITE_LINHAS);
    $('#contagemMargem').textContent = lista.length === margem.linhas.length ? '' : `(${lista.length} de ${margem.linhas.length})`;
    const mais = $('#btnMaisMargem');
    mais.classList.toggle('hidden', visiveis.length >= lista.length);
    mais.textContent = `Mostrar todos (${lista.length.toLocaleString('pt-BR')})`;
    $$('th[data-ordem]').forEach(th => {
        th.querySelector('.seta')?.remove();
        if (th.dataset.ordem === ordem.campo) th.insertAdjacentHTML('beforeend', `<span class="seta text-blue-400"> ${ordem.dir > 0 ? '▲' : '▼'}</span>`);
    });
    $('#tabelaMargem').innerHTML = visiveis.length ? visiveis.map(l => {
        const status = l.status && l.status !== 'active' ? pilula(STATUS[l.status] || l.status, 'border-gray-600 text-gray-400') : '';
        const margemCel = l.situacao === 'sem'
            ? `<span class="text-red-300 text-xs" title="${esc(l.como)}">sem custo</span>`
            : pilula(l.situacao === 'gratis' ? 'grátis' : pct(l.margem, 2), COR_MARGEM[l.situacao]);
        return `<tr class="border-t border-gray-700/60 align-top">
            <td class="py-2 pr-2 sm:pr-3 max-w-[11rem] sm:max-w-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold text-gray-100 break-all sm:break-normal">${l.sku ? esc(l.sku) : '<span class="text-gray-500">(sem SKU)</span>'}</span>${etiquetas(l)}${status}${iconeDivergente(l)}<button type="button" data-editar="${esc(l.codigo)}" class="job text-xs px-1 rounded text-gray-500 hover:text-blue-300 hover:bg-gray-700 disabled:opacity-40" ${jobRodando ? 'disabled' : ''} title="Editar preço, tarifa, frete e adc à mão" aria-label="Editar ${esc(l.sku || l.codigo)}">✏️</button>
                </div>
                <div class="text-xs truncate">${codigoAnuncio(l.codigo)}<span class="text-gray-500" title="${esc(l.titulo)}"> · ${esc(l.titulo)}</span></div>
                ${l.situacao === 'sem' ? `<div class="text-xs text-red-300">${esc(l.como)}</div>` : ''}
            </td>
            <td class="py-2 px-2 sm:px-3 text-right tabular-nums whitespace-nowrap" title="Taxa ${pct(l.taxa)} · frete ${brl(l.frete)} · adicional ${brl(l.adc)}">${brl(l.preco)}</td>
            <td class="py-2 px-2 sm:px-3 text-right tabular-nums whitespace-nowrap hidden sm:table-cell" title="${brl(l.preco)} − taxa ${brl(l.preco * l.taxa)} (${pct(l.taxa)}) − frete ${brl(l.frete)} − adicional ${brl(l.adc)}">${brl(l.recebe)}</td>
            <td class="py-2 px-2 sm:px-3 text-right tabular-nums whitespace-nowrap hidden md:table-cell" title="Sem desconto: ${brl(l.custo)}">${brl(l.custo_desc)}</td>
            <td class="py-2 px-2 sm:px-3 text-right tabular-nums whitespace-nowrap hidden sm:table-cell ${l.lucro < 0 ? 'text-red-300' : ''}" title="Você recebe ${brl(l.recebe)} − custo ${brl(l.custo_desc)} = ${brl(l.lucro)}. Depois do imposto (${brl(l.imposto || 0)}): ${brl(l.lucro_liquido ?? l.lucro)}">${brl(l.lucro)}</td>
            <td class="py-2 px-2 sm:px-3 text-right">${margemCel}</td>
            <td class="py-2 px-2 sm:px-3 text-right tabular-nums whitespace-nowrap">${l.preco_sugerido == null ? '—' : brl(l.preco_sugerido)}${diferenca(l.diferenca, l.preco)}</td>
            <td class="py-2 pl-2 sm:pl-3 text-center tabular-nums whitespace-nowrap ${COR_DMETA[l.situacao] || 'text-gray-500'}" title="${l.dmeta == null ? '' : `Margem ${pct(l.margem, 2)} ${l.margem < margem.meta ? 'abaixo' : 'acima'} da meta ${pctMeta(margem.meta)}`}">${l.dmeta == null ? '—' : pp(l.dmeta)}</td>
        </tr>`;
    }).join('') : '<tr><td colspan="8" class="py-6 text-center text-gray-500">Nenhum anúncio com esses filtros.</td></tr>';
}

async function carregarTeste() {
    const area = $('#resultadoTeste');
    let r;
    try {
        r = (await api('margem_teste')).resultado;
    } catch (e) {
        return;
    }
    if (!r) { area.innerHTML = ''; return; }
    area.innerHTML = `<div class="bg-gray-900 border border-gray-700 rounded-lg p-3 text-sm">
        <div class="flex flex-wrap justify-between gap-2 mb-2"><span class="text-gray-400">Último teste: ${quando(r.gerado)}</span>
        <a class="text-blue-400 hover:text-blue-300" href="${urlPlanilha('margem', r.planilha)}">⬇️ ${esc(r.planilha)}</a></div>
        ${r.linhas.map(l => `<div class="flex flex-wrap justify-between gap-2 border-t border-gray-800 py-1">
            <span><span class="text-gray-200 font-semibold">${esc(l.sku || '(sem SKU)')}</span> <span class="text-gray-500">${esc(l.codigo)}</span></span>
            <span class="tabular-nums text-gray-300">${brl(l.preco)} · margem ${l.custo == null ? 'sem custo' : pct(l.margem, 2)} · sugerido ${brl(l.preco_sugerido)}</span>
        </div>`).join('')}</div>`;
}

async function carregarPlanilhas() {
    const ul = $('#planilhasMargem');
    try {
        const { planilhas } = await api('planilhas&modulo=margem');
        ul.innerHTML = planilhas.length ? planilhas.map(p => `<li class="flex justify-between gap-3">
            <a class="text-blue-400 hover:text-blue-300 break-all" href="${urlPlanilha('margem', p.nome)}">${esc(p.nome)}</a>
            <span class="text-gray-500 whitespace-nowrap">${quando(p.quando)}</span></li>`).join('') : '<li class="text-gray-500">Nenhuma planilha.</li>';
    } catch (e) {
        ul.innerHTML = `<li class="text-red-300">${esc(e.message)}</li>`;
    }
}

async function atualizarMargens() {
    const c = estado && estado.coleta;
    if (c && !c.concluida) {
        // mesmo status da busca parada: com outro status a coleta descartaria o progresso
        return iniciarJob({ tipo: 'atualizar', status: c.status });
    }
    const ok = await ML.confirmar('Busca todos os anúncios no ML e gera a planilha de margem. Leva alguns minutos.', { titulo: 'Atualizar margens', textoConfirmar: 'Atualizar' });
    if (ok) iniciarJob({ tipo: 'atualizar', status: 'todos' });
}

async function salvarConfig(dados, sucesso) {
    try {
        estado = { ...estado, ...(await postJson('config', dados)) };
        await carregarEstado();
        ML.toast(sucesso, 'sucesso');
        return true;
    } catch (e) {
        ML.toast(e.message, 'erro');
        return false;
    }
}

// ===================================================================== skus

async function carregarSkus() {
    try {
        skus = (await api('skus')).resultado;
    } catch (e) {
        ML.toast(e.message, 'erro');
        return;
    }
    renderSkus();
}

function renderSkus() {
    const baixar = $('#baixarSkus');
    if (!skus) {
        $('#infoSkus').textContent = 'Ainda não foi feita nenhuma comparação.';
        $('#resumoSkus').innerHTML = '';
        $('#tabelaSkus').innerHTML = '<tr><td colspan="4" class="py-6 text-center text-gray-500">Use Comparar SKUs.</td></tr>';
        baixar.classList.add('hidden');
        $('#secaoForaTabela').classList.add('hidden');
        return;
    }
    $('#infoSkus').textContent = `Última comparação: ${quando(skus.gerado)} · ${skus.anuncios.toLocaleString('pt-BR')} anúncios`;
    baixar.href = urlPlanilha('skus', skus.planilha);
    baixar.classList.remove('hidden');
    const k = skus.contagem;
    $('#resumoSkus').innerHTML = [
        cartao('SKUs da tabela', skus.skus.length.toLocaleString('pt-BR')),
        cartao('Sem anúncio', k['sem anuncio'], 'nenhum anúncio, qualquer status', k['sem anuncio'] ? 'border-red-500/60' : ''),
        cartao('Sem solo', k['sem solo'], 'só aparecem em kits', k['sem solo'] ? 'border-amber-500/60' : ''),
        cartao('Fora da tabela', skus.sem_tabela.length, 'anúncios com peça sem preço', skus.sem_tabela.length ? 'border-amber-500/60' : ''),
    ].join('');

    const busca = $('#buscaSkus').value.trim().toUpperCase();
    const filtro = $('#filtroSkus').value;
    const lista = skus.skus.filter(s => (!busca || s.sku.toUpperCase().includes(busca))
        && (filtro === 'todos' || (filtro === 'pendentes' ? s.situacao !== 'ok' : s.situacao === filtro)));
    const cor = { 'sem anuncio': 'bg-red-900/60 text-red-200 border-red-500/40', 'sem solo': 'bg-amber-900/50 text-amber-200 border-amber-500/40', ok: 'bg-emerald-900/40 text-emerald-200 border-emerald-500/30' };
    $('#tabelaSkus').innerHTML = lista.length ? lista.map(s => `<tr class="border-t border-gray-700/60">
        <td class="py-2 pr-3 font-semibold text-gray-100 break-all">${esc(s.sku)}</td>
        <td class="py-2 px-2 sm:px-3 text-right tabular-nums">${s.solo} / ${s.comp}</td>
        <td class="py-2 px-2 sm:px-3 text-right tabular-nums">${s.ativos_solo} / ${s.ativos_comp}</td>
        <td class="py-2 pl-2 sm:pl-3 text-right">${pilula(s.situacao === 'sem anuncio' ? 'sem anúncio' : s.situacao, cor[s.situacao])}</td>
    </tr>`).join('') : '<tr><td colspan="4" class="py-6 text-center text-gray-500">Nenhum SKU com esses filtros.</td></tr>';

    $('#secaoForaTabela').classList.toggle('hidden', !skus.sem_tabela.length);
    $('#tabelaForaTabela').innerHTML = skus.sem_tabela.map(a => `<tr class="border-t border-gray-700/60 align-top">
        <td class="py-2 pr-3 max-w-[12rem] sm:max-w-md">
            <div class="font-semibold text-gray-100 break-all">${esc(a.sku || '(sem SKU)')}</div>
            <div class="text-xs truncate">${codigoAnuncio(a.codigo)}<span class="text-gray-500"> · ${esc(a.titulo)}</span></div>
        </td>
        <td class="py-2 pl-3 text-amber-200 break-all">${esc(a.fora_da_tabela)}</td>
    </tr>`).join('');
}

// ===================================================================== envio

async function enviarPdf(arquivo) {
    if (!arquivo) return;
    const texto = $('#textoEnvio');
    texto.textContent = `Lendo ${arquivo.name}…`;
    const dados = new FormData();
    dados.append('arquivo', arquivo);
    try {
        const r = await api('envio', { method: 'POST', body: dados });
        $('#envioTitulo').textContent = `Envio #${r.frete}`;
        $('#envioSub').textContent = `${r.produtos} produtos · ${r.unidades} unidades · ${r.skus.length} SKUs depois de abrir os kits`;
        $('#envioTexto').value = r.skus.map(s => `${s.qtd}x ${s.sku}`).join('\n');
        $('#resultadoEnvio').classList.remove('hidden');
        texto.textContent = 'Clique para escolher outro PDF ou arraste aqui';
    } catch (e) {
        ML.toast(e.message, 'erro');
        texto.textContent = 'Clique para escolher o PDF ou arraste aqui';
    } finally {
        $('#arquivoEnvio').value = '';
    }
}

// ===================================================================== tabelas

async function carregarTabelas() {
    try {
        const t = await api('tabelas');
        Object.entries(t).forEach(([nome, info]) => {
            const el = $(`[data-tabela="${nome}"] .info`);
            if (!info.existe) el.innerHTML = '<span class="text-red-300">Arquivo não encontrado.</span>';
            else if (info.erro) el.innerHTML = `<span class="text-red-300">Não consegui ler: ${esc(info.erro)}</span>`;
            else el.textContent = `${info.linhas.toLocaleString('pt-BR')} ${nome === 'precos' ? 'SKUs com preço' : 'apelidos'} · alterada em ${quando(info.quando)}`;
        });
    } catch (e) {
        ML.toast(e.message, 'erro');
    }
}

async function trocarTabela(nome, input) {
    const arquivo = input.files[0];
    input.value = '';
    if (!arquivo) return;
    const ok = await ML.confirmar(`Trocar o ${nome}.xlsx pelo arquivo "${arquivo.name}"? A versão atual fica guardada no servidor.`, { titulo: 'Trocar tabela', textoConfirmar: 'Trocar' });
    if (!ok) return;
    const dados = new FormData();
    dados.append('arquivo', arquivo);
    try {
        await api(`trocar_tabela&nome=${nome}`, { method: 'POST', body: dados });
        await carregarTabelas();
        ML.toast(`${nome}.xlsx trocada.`, 'sucesso');
        if (margem && !jobRodando && await ML.confirmar('Recalcular a margem agora com a tabela nova? Não busca no ML.', { titulo: 'Recalcular', textoConfirmar: 'Recalcular' })) {
            iniciarJob({ tipo: 'recalcular' });
        }
    } catch (e) {
        ML.toast(e.message, 'erro');
    }
}

// ===================================================================== clonar

async function carregarClonar() {
    try {
        clonar = await api('clonar');
    } catch (e) {
        ML.toast(e.message, 'erro');
        return;
    }
    renderClonar();
}

const NOME_SITUACAO_CLONE = {
    ok: ['cravado', 'bg-emerald-900/40 text-emerald-200 border-emerald-500/30'],
    fora: ['sem cravar', 'bg-amber-900/50 text-amber-200 border-amber-500/40'],
    ajustando: ['ajustando', 'border-blue-500/50 text-blue-200'],
    criando: ['criando', 'border-blue-500/50 text-blue-200'],
    parado: ['parado', 'bg-amber-900/50 text-amber-200 border-amber-500/40'],
    erro: ['erro', 'bg-red-900/60 text-red-200 border-red-500/40'],
};

function renderClonar() {
    if (!clonar) return;
    const p = clonar.pares;
    $('#infoClonar').innerHTML = p
        ? `Busca de ${quando(p.gerado)} · ${p.analisados.toLocaleString('pt-BR')} anúncios ativos e pausados` + (p.teste ? ' · <span class="text-amber-300">teste com alguns anúncios</span>' : '')
            + (p.sem_produto ? ` · ${p.sem_produto} sem user product (modelo antigo) ficaram de fora` : '')
            + (p.so_gratis ? ` · ${p.so_gratis} só com itens grátis ficaram de fora` : '')
            + (p.gtin_duplicado ? ` · <span class="text-red-300">${p.gtin_duplicado} com GTIN duplicado</span>` : '')
        : 'Ainda não buscou: use Buscar anúncios sem par.';
    // margem do original na última planilha, quando ele estiver lá
    const daMargem = new Map((margem?.linhas || []).map(l => [l.codigo, l]));
    const busca = $('#buscaClonar').value.trim().toUpperCase();
    const falta = $('#faltaClonar').value, status = $('#statusClonar').value;
    const todas = p ? p.linhas : [];
    // última tentativa de criar de cada anúncio (o histórico vem do mais novo para o mais antigo)
    const ultimaTentativa = new Map();
    for (const h of clonar.historico || []) if (!ultimaTentativa.has(h.original)) ultimaTentativa.set(h.original, h);
    const erroDe = l => { const h = ultimaTentativa.get(l.codigo); return h && h.status === 'erro' ? h : null; };
    const lista = todas.filter(l => (!busca || `${l.codigo} ${l.sku} ${l.titulo}`.toUpperCase().includes(busca))
        && (!falta || l.falta === falta)
        && (!status || (status === 'erro' ? erroDe(l) : l.status === status)));
    $('#contagemClonar').textContent = p ? (lista.length === todas.length ? `(${todas.length})` : `(${lista.length} de ${todas.length})`) : '';
    $('#tabelaClonar').innerHTML = lista.length ? lista.map(l => {
        const m = daMargem.get(l.codigo);
        const margemCel = !m ? '<span class="text-gray-500">—</span>'
            : m.situacao === 'sem' ? '<span class="text-red-300 text-xs">sem custo</span>'
            : pilula(m.situacao === 'gratis' ? 'grátis' : pct(m.margem, 2), COR_MARGEM[m.situacao]);
        const pausado = l.status !== 'active' ? pilula(STATUS[l.status] || l.status, 'border-gray-600 text-gray-400') : '';
        const gtinRuim = l.gtin && !l.gtin.corrigido;
        const erro = erroDe(l);
        const erroPilula = erro ? `<span data-dica="${esc(`Último erro (${quando(erro.inicio)}):\n${(erro.erro || '').replace(/^O ML recusou a criação: /, '')}`)}" tabindex="0" class="cursor-help">${pilula('Erro ao criar', 'bg-red-900/60 text-red-200 border-red-500/40')}</span>` : '';
        const gtinTitulo = l.gtin ? `GTIN ${l.gtin.codigo} também em: ` + l.gtin.outros.map(o => `${o.sku} (${o.codigo})`).join(', ') : '';
        const gtinPilula = !l.gtin ? '' : gtinRuim
            ? `<span data-dica="${esc(gtinTitulo)}" tabindex="0" class="cursor-help">${pilula('GTIN duplicado', 'bg-red-900/60 text-red-200 border-red-500/40')}</span>`
            : `<span data-dica="${esc(gtinTitulo)}" tabindex="0" class="cursor-help">${pilula('GTIN corrigido', 'border-emerald-500/40 text-emerald-300')}</span>`;
        const acao = gtinRuim
            ? `<button type="button" data-gtin="${esc(l.codigo)}" class="${CLASSE_BOTAO} text-sm job whitespace-nowrap" ${jobRodando ? 'disabled' : ''} title="${esc(gtinTitulo)}">🔧 Corrigir GTIN</button>`
            : `<button type="button" data-clonar="${esc(l.codigo)}" class="${CLASSE_BOTAO} text-sm job whitespace-nowrap" ${jobRodando ? 'disabled' : ''}>➕ ${esc(l.falta)}</button>`;
        return `<tr class="border-t border-gray-700/60 align-top">
            <td class="py-2 pr-2">${gtinRuim ? '' : `<input type="checkbox" data-marcar="${esc(l.codigo)}" ${marcadosClonar.has(l.codigo) ? 'checked' : ''} class="w-4 h-4 mt-1 accent-blue-500 cursor-pointer" aria-label="Marcar ${esc(l.sku || l.codigo)}">`}</td>
            <td class="py-2 pr-3 max-w-[13rem] sm:max-w-md">
                <div class="flex flex-wrap items-center gap-2"><span class="font-semibold text-gray-100 break-all sm:break-normal">${l.sku ? esc(l.sku) : '<span class="text-gray-500">(sem SKU)</span>'}</span>${etiquetas(l)}${pausado}${l.catalogo ? pilula('catálogo', 'border-gray-600 text-gray-400') : ''}${gtinPilula}${erroPilula}</div>
                <div class="text-xs truncate">${codigoAnuncio(l.codigo)}<span class="text-gray-500" title="${esc(l.titulo)}"> · ${esc(l.titulo)}</span></div>
            </td>
            <td class="py-2 px-2 sm:px-3 text-right tabular-nums whitespace-nowrap">${brl(l.preco)}</td>
            <td class="py-2 px-2 sm:px-3 text-right">${margemCel}</td>
            <td class="py-2 pl-2 sm:pl-3 text-right"><div class="flex flex-wrap justify-end gap-2">${erro && erroFormato(erro.erro) ? `<button type="button" data-formato="${esc(l.codigo)}" data-sku="${esc(l.sku)}" class="${CLASSE_BOTAO} text-sm job whitespace-nowrap" ${jobRodando ? 'disabled' : ''} title="Corrigir formato de venda e quantidade e tentar de novo">✏️ Formato</button>` : ''}${acao}</div></td>
        </tr>`;
    }).join('') : `<tr><td colspan="5" class="py-6 text-center text-gray-500">${p ? 'Nenhum anúncio sem par com esses filtros.' : 'Use Buscar anúncios sem par.'}</td></tr>`;
    // marcados que saíram da lista (já clonados) deixam de contar
    const existentes = new Set(todas.map(l => l.codigo));
    for (const c of [...marcadosClonar]) if (!existentes.has(c)) marcadosClonar.delete(c);
    const marcaveis = lista.filter(l => !(l.gtin && !l.gtin.corrigido));
    $('#marcarClonar').checked = marcaveis.length > 0 && marcaveis.slice(0, MAX_CLONAR).every(l => marcadosClonar.has(l.codigo));
    atualizarMarcados();

    const hist = clonar.historico || [];
    $('#historicoClonar').innerHTML = hist.length ? hist.map(h => {
        const [nome, cor] = NOME_SITUACAO_CLONE[h.status] || [h.status, 'border-gray-600 text-gray-400'];
        const avisos = h.avisos || [];
        return `<tr class="border-t border-gray-700/60 align-top">
            <td class="py-2 pr-3">
                <div class="flex flex-wrap items-center gap-2"><span class="font-semibold text-gray-100">${esc(h.sku || '')}</span>
                ${h.tipo_original ? pilula(h.tipo_original, COR_TIPO[h.tipo_original] || '') : ''}<span class="text-gray-500">→</span>${h.tipo_novo ? pilula(h.tipo_novo, COR_TIPO[h.tipo_novo] || '') : ''}</div>
                <div class="text-xs">${codigoAnuncio(h.original)}${h.novo ? ` <span class="text-gray-500">→</span> ${codigoAnuncio(h.novo)}` : ''} <span class="text-gray-500">· ${quando(h.inicio)}</span></div>
                ${avisos.length ? `<ul class="text-xs text-amber-300 mt-1 list-disc pl-4">${avisos.map(a => `<li>${esc(a)}</li>`).join('')}</ul>` : ''}
                ${h.erro ? `<p class="text-xs text-red-300 mt-1">${esc(h.erro)}</p>` : ''}
            </td>
            <td class="py-2 px-2 sm:px-3 text-right tabular-nums whitespace-nowrap">${h.preco_final != null ? brl(h.preco_final) : brl(h.preco_inicial)}${h.preco_inicial != null && h.preco_final != null && Math.abs(h.preco_final - h.preco_inicial) >= 0.005 ? `<div class="text-[11px] text-gray-500">criado a ${brl(h.preco_inicial)}</div>` : ''}</td>
            <td class="py-2 px-2 sm:px-3 text-right tabular-nums">${h.margem_final != null ? pct(h.margem_final, 2) : '—'}</td>
            <td class="py-2 pl-2 sm:pl-3 text-right">${pilula(nome, cor)}</td>
        </tr>`;
    }).join('') : '<tr><td colspan="4" class="py-6 text-center text-gray-500">Nenhuma opção de venda criada ainda.</td></tr>';
}

function atualizarMarcados() {
    const n = marcadosClonar.size;
    $('#btnClonarMarcados').textContent = n ? `➕ Criar marcados (${n})` : '➕ Criar marcados';
    $('#btnClonarMarcados').disabled = !n || jobRodando;
}

async function criarMarcados() {
    const linhas = (clonar?.pares?.linhas || []).filter(l => marcadosClonar.has(l.codigo));
    if (!linhas.length) return;
    const faltas = ['Premium', 'Clássico'].map(t => [t, linhas.filter(l => l.falta === t).length]).filter(([, n]) => n)
        .map(([t, n]) => `${n} ${t}`).join(' e ');
    const ok = await ML.confirmar(`Criar ${faltas} no ML (${linhas.length} anúncio${linhas.length > 1 ? 's' : ''})? Cria todos pelo preço da margem alvo e depois confere todos a cada 10 s até cravar a margem.`,
        { titulo: 'Criar marcados', textoConfirmar: 'Criar' });
    if (!ok) return;
    const itens = linhas.map(l => l.codigo);
    marcadosClonar.clear();
    iniciarJob({ tipo: 'clonar', itens });
}

// ----- modal de andamento da criação (um anúncio por linha: fila, girando, ✓ ou ✕)

const ETAPA_CLONE = { fila: 'na fila', criando: 'criando', conferindo: 'conferindo', ok: 'cravado', fora: 'sem cravar', erro: 'erro', parado: 'parado' };

function iconeClone(etapa) {
    const base = 'w-5 h-5 rounded-full inline-flex items-center justify-center text-xs font-bold shrink-0';
    if (etapa === 'ok') return `<span class="${base} bg-emerald-500 text-white" aria-hidden="true">✓</span>`;
    if (etapa === 'erro' || etapa === 'parado') return `<span class="${base} bg-red-500 text-white" aria-hidden="true">✕</span>`;
    if (etapa === 'fora') return `<span class="${base} bg-amber-500 text-gray-900" aria-hidden="true">!</span>`;
    if (etapa === 'fila') return `<span class="${base} border-2 border-dashed border-gray-600" aria-hidden="true"></span>`;
    return `<span class="${base} border-[3px] border-blue-900 border-t-blue-400 animate-spin" aria-hidden="true"></span>`;
}

function abrirModalClonar() {
    const m = $('#modalClonar');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

async function mostrarModalClonar(job) {
    if (!job || !['clonar', 'formato'].includes(job.tipo)) return;
    let andamento = null;
    try { andamento = (await api('clonar_andamento')).andamento; } catch (e) { /* tenta no próximo ciclo */ }
    const codigos = (job.opcoes?.itens || job.opcoes?.item || '').split(',').filter(Boolean);
    // andamento de uma criação anterior: mostra os desta ainda na fila
    if (!andamento || (andamento.inicio || '') < (job.inicio || '')) andamento = null;
    const daLista = new Map((clonar?.pares?.linhas || []).map(l => [l.codigo, l]));
    const itens = andamento ? andamento.itens : codigos.map(c => ({ original: c, etapa: 'fila' }));
    const rodando = job.estado === 'rodando';
    const passoCriar = job.tipo === 'formato' ? 2 : 1;  // no "formato", antes de criar vem a correção
    if (!rodando) itens.forEach(i => { if (['fila', 'criando', 'conferindo'].includes(i.etapa)) i.etapa = 'parado'; });

    const total = itens.length;
    const conta = e => itens.filter(i => i.etapa === e).length;
    const prontos = itens.filter(i => ['ok', 'fora', 'erro', 'parado'].includes(i.etapa)).length;
    const leitura = Math.max(0, ...itens.filter(i => i.etapa === 'conferindo').map(i => i.rodada || 0));
    let titulo;
    if (!rodando) titulo = job.estado === 'ok' ? `Pronto: ${conta('ok')} de ${total} cravado${conta('ok') !== 1 ? 's' : ''}` : (job.estado === 'parado' ? 'Parado' : 'Terminou com erro');
    else if (job.passo < passoCriar) titulo = 'Corrigindo o formato de venda…';
    else if (job.passo > passoCriar) titulo = 'Atualizando a planilha de margem…';
    else if (conta('fila') || conta('criando')) titulo = `Criando ${total - conta('fila')}/${total}…`;
    else titulo = `Conferindo a margem${leitura ? ` · leitura ${leitura} de ${andamento?.rodadas || 10}` : ''}…`;
    $('#clonarTitulo').textContent = titulo;
    const min = Math.floor((Date.now() - new Date(job.inicio)) / 60000);
    $('#clonarSub').textContent = `${prontos} de ${total} terminado${total !== 1 ? 's' : ''} · iniciado às ${new Date(job.inicio).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}` + (min ? ` · ${min} min` : '');

    $('#clonarLista').innerHTML = itens.map(i => {
        const l = daLista.get(i.original) || {};
        const sku = i.sku || l.sku || i.original;
        const tipo = i.tipo_novo || l.falta || '';
        const valores = i.preco != null && i.etapa !== 'erro'
            ? ` · ${brl(i.preco)}${i.margem != null ? ` · ${pct(i.margem, 2)}` : ''}` : '';
        const rodada = i.etapa === 'conferindo' && i.rodada ? ` (leitura ${i.rodada})` : '';
        const cor = { ok: 'text-emerald-300', fora: 'text-amber-300', erro: 'text-red-300', parado: 'text-red-300' }[i.etapa] || 'text-gray-400';
        return `<li class="border border-gray-700 rounded-lg px-3 py-2">
            <div class="flex items-center gap-3">
                ${iconeClone(i.etapa)}
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2"><span class="font-semibold text-gray-100">${esc(sku)}</span>${tipo ? pilula(tipo, COR_TIPO[tipo] || '') : ''}</div>
                    <div class="text-xs text-gray-400">${esc(i.original)}${i.novo ? ` → ${codigoAnuncio(i.novo)}` : ''}</div>
                </div>
                <span class="text-sm ${cor} text-right whitespace-nowrap">${esc(ETAPA_CLONE[i.etapa] || i.etapa)}${rodada}<span class="tabular-nums">${valores}</span></span>
            </div>
            ${i.erro ? `<div class="text-sm text-red-400 mt-1 ml-8">${esc(i.erro)}</div>` : ''}
            ${!rodando && i.etapa === 'erro' && erroFormato(i.erro) ? `<div class="mt-2 ml-8"><button type="button" data-formato="${esc(i.original)}" data-sku="${esc(sku)}" class="${CLASSE_BOTAO} text-sm">✏️ Corrigir e tentar de novo</button></div>` : ''}
            ${(i.avisos || []).map(a => `<div class="text-xs text-amber-300 mt-1 ml-8">${esc(a)}</div>`).join('')}
        </li>`;
    }).join('');
    $('#clonarRodape').textContent = rodando ? 'Pode fechar esta aba do navegador: a criação continua no servidor.' : (job.estado === 'ok' ? '' : (job.mensagem || ''));
    $('#btnClonarFechar').classList.toggle('hidden', rodando);
    abrirModalClonar();
}

async function fecharModalClonar() {
    const m = $('#modalClonar');
    m.classList.add('hidden');
    m.classList.remove('flex');
    await postJson('visto').catch(() => null);
    $('#painelJob').classList.add('hidden');
}

// ----- GTIN duplicado: plano (quem fica e quem perde o GTIN) e correção

let gtinItem = null;

async function abrirPlanoGtin(codigo) {
    let plano;
    try {
        ML.toast('Montando o plano…', 'info');
        plano = (await api('clonar_gtin&item=' + encodeURIComponent(codigo))).plano;
    } catch (e) {
        ML.toast(e.message, 'erro');
        return;
    }
    gtinItem = codigo;
    $('#gtinTitulo').textContent = `Corrigir GTIN ${plano.gtin}`;
    $('#gtinTexto').textContent = plano.texto;
    $('#gtinLista').innerHTML = plano.grupos.map(g => {
        const fica = g.fica, perde = plano.decidido && !g.fica;
        const icone = fica ? '<span class="w-5 h-5 rounded-full inline-flex items-center justify-center text-xs font-bold bg-emerald-500 text-white shrink-0">✓</span>'
            : perde ? '<span class="w-5 h-5 rounded-full inline-flex items-center justify-center text-xs font-bold bg-red-500 text-white shrink-0">✕</span>'
            : '<span class="w-5 h-5 rounded-full inline-flex items-center justify-center text-xs font-bold border-2 border-dashed border-gray-600 shrink-0"></span>';
        const anuncios = g.anuncios.map(a => `${codigoAnuncio(a.codigo)}${a.catalogo ? ' <span class="text-gray-500">(catálogo)</span>' : ''}`).join(' · ');
        return `<li class="border border-gray-700 rounded-lg px-3 py-2">
            <div class="flex items-center gap-3">${icone}
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2"><span class="font-semibold text-gray-100">${esc(g.sku || '(sem SKU)')}</span>${g.kit ? pilula('kit', 'border-gray-600 text-gray-400') : ''}${g.alvo ? pilula('o que você quer clonar', 'border-blue-500/50 text-blue-200') : ''}</div>
                    <div class="text-xs text-gray-400">${anuncios}</div>
                    <div class="text-xs text-gray-500">${esc(g.dominio || '')} · categoria ${esc(g.categoria || '')}</div>
                </div>
                <span class="text-sm text-right ${fica ? 'text-emerald-300' : perde ? 'text-red-300' : 'text-gray-400'}">${fica ? 'fica com o GTIN' : perde ? `perde o GTIN<div class="text-xs text-gray-400">${esc(g.motivo)}</div>` : ''}</span>
            </div>
        </li>`;
    }).join('');
    $('#btnGtinCorrigir').classList.toggle('hidden', !plano.decidido);
    $('#btnGtinCorrigir').disabled = jobRodando;
    const m = $('#modalGtin');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function fecharPlanoGtin() {
    const m = $('#modalGtin');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

// ----- frete/adc divergente: o painel do ML usa medidas próprias que a API não mostra (só aviso visual)

function iconeDivergente(l) {
    const d = divergentes.get(l.codigo);
    if (!d) return '';
    const campo = d.campo === 'adc' ? 'adc' : 'frete';
    const nome = campo === 'adc' ? 'Adc' : 'Frete';
    const atual = l[campo];
    const corrigido = atual != null && Math.abs(atual - d.painel) < 0.005;
    const medida = d.medida_ml ? ` (${d.medida_ml})` : '';  // "medidas próprias (39x34x17 cm, 3,84 kg)"
    const texto = corrigido
        ? `${nome} corrigido à mão: ${brl(atual)}, o valor do painel do ML, que usa medidas próprias${medida}. A próxima busca desfaz e volta o valor da API.`
        : `${nome} pode estar errado: o painel do ML usa medidas próprias${medida} e cobra ${brl(d.painel)}; a API traz ${brl(atual ?? d.api)}. Corrija com ✏️.`;
    const extra = [d.obs, d.conferido ? `Conferido em ${d.conferido.split('-').reverse().join('/')}.` : ''].filter(Boolean).join(' ');
    const cor = corrigido ? 'border-gray-500 text-gray-400' : 'border-amber-400 text-amber-300 bg-amber-900/30';
    return `<span data-dica="${esc(texto + (extra ? '\n' + extra : ''))}" tabindex="0" aria-label="${esc(texto)}" class="inline-flex items-center justify-center w-4 h-4 rounded-full border text-[10px] font-bold italic font-serif cursor-help ${cor}">i</span>`;
}

// ----- edição à mão de um anúncio (aba Margem)

let editando = null;
const numBR = v => String(+(+v).toFixed(4)).replace('.', ',');
const lerNum = s => { let t = String(s).replace(/R\$|\s/g, ''); if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.'); return t === '' ? NaN : Number(t); };

function abrirEdicao(codigo) {
    const l = margem?.linhas.find(x => x.codigo === codigo);
    if (!l) return;
    editando = l;
    $('#editarTitulo').textContent = `Editar ${l.sku || codigo}`;
    $('#editarSub').innerHTML = `${esc(codigo)} · ${esc(l.titulo || '')}`;
    $('#editarPreco').value = numBR(l.preco);
    $('#editarTaxa').value = numBR(l.taxa * 100);
    $('#editarFrete').value = numBR(l.frete);
    $('#editarAdc').value = numBR(l.adc);
    previaEdicao();
    const m = $('#modalEditar');
    m.classList.remove('hidden');
    m.classList.add('flex');
    $('#editarFrete').focus();
}

function fecharEdicao() {
    const m = $('#modalEditar');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

// margem com os valores digitados (mesma conta da planilha)
function previaEdicao() {
    const l = editando;
    const p = lerNum($('#editarPreco').value), t = lerNum($('#editarTaxa').value) / 100;
    const f = lerNum($('#editarFrete').value), a = lerNum($('#editarAdc').value);
    if ([p, t, f, a].some(Number.isNaN) || p <= 0) { $('#editarPrevia').textContent = 'Preencha os quatro valores com números.'; return; }
    const recebe = p * (1 - t) - f - a;
    if (l.custo_desc == null) { $('#editarPrevia').textContent = `Você recebe ${brl(recebe)} · sem custo na tabela`; return; }
    const lucro = recebe - l.custo_desc, m = lucro / p;
    const cor = m < margem.meta - margem.tolerancia ? 'text-red-300' : m > margem.meta + margem.tolerancia ? 'text-amber-300' : 'text-emerald-300';
    $('#editarPrevia').innerHTML = `Você recebe ${brl(recebe)} · lucro ${brl(lucro)} · margem <strong class="${cor}">${pct(m, 2)}</strong> <span class="text-gray-500">(antes ${pct(l.margem, 2)})</span>`;
}

async function salvarEdicao(e) {
    e.preventDefault();
    const l = editando;
    const novos = { preco: lerNum($('#editarPreco').value), taxa: lerNum($('#editarTaxa').value), frete: lerNum($('#editarFrete').value), adc: lerNum($('#editarAdc').value) };
    if (Object.values(novos).some(Number.isNaN)) { ML.toast('Preencha os quatro valores com números.', 'aviso'); return; }
    // manda só o que mudou
    const atuais = { preco: l.preco, taxa: l.taxa * 100, frete: l.frete, adc: l.adc };
    const dados = { tipo: 'editar', item: l.codigo };
    for (const k of Object.keys(novos)) if (Math.abs(novos[k] - atuais[k]) > 0.00001) dados[k] = novos[k];
    if (Object.keys(dados).length === 2) { ML.toast('Nada mudou.', 'info'); return; }
    fecharEdicao();
    iniciarJob(dados);
}

// ----- dica flutuante: aparece logo ao passar o mouse (ou focar) em qualquer elemento com data-dica

function ligarDicas() {
    const dica = $('#dica');
    const mostrar = el => {
        dica.textContent = el.dataset.dica;
        dica.classList.remove('hidden');
        const r = el.getBoundingClientRect(), d = dica.getBoundingClientRect();
        const x = Math.max(8, Math.min(r.left, innerWidth - d.width - 8));
        const y = r.bottom + 6 + d.height > innerHeight ? r.top - d.height - 6 : r.bottom + 6;
        dica.style.left = x + 'px';
        dica.style.top = y + 'px';
    };
    const esconder = () => dica.classList.add('hidden');
    document.addEventListener('mouseover', e => { const el = e.target.closest('[data-dica]'); if (el) mostrar(el); });
    document.addEventListener('mouseout', e => { if (e.target.closest('[data-dica]')) esconder(); });
    document.addEventListener('focusin', e => { const el = e.target.closest('[data-dica]'); if (el) mostrar(el); });
    document.addEventListener('focusout', esconder);
    addEventListener('scroll', esconder, true);
}

// ----- formato de venda e quantidade (erro UNITS_PER_PACK, com "Kit" ou com "Unidade")

const erroFormato = texto => /UNITS_PER_PACK|Quantidade de embalagens/i.test(texto || '');
let formatoItem = null;

function abrirFormato(codigo, sku) {
    formatoItem = codigo;
    // sugestão pelo SKU: kit soma as peças ("A+2xB" = 3); peça única = Unidade 1
    const partes = String(sku || '').split('+').map(p => p.trim()).filter(Boolean);
    const qtd = partes.reduce((s, p) => s + (parseInt((p.match(/^(\d+)\s*[xX]/) || [])[1], 10) || 1), 0) || 1;
    const kit = partes.length > 1 || qtd > 1;
    $('#formatoTitulo').textContent = `Formato de venda · ${sku || codigo}`;
    $('#formatoSub').textContent = codigo;
    $('#formatoTipo').value = kit ? 'pack' : 'unidade';
    $('#formatoQtd').value = kit ? qtd : 1;
    const m = $('#modalFormato');
    m.classList.remove('hidden');
    m.classList.add('flex');
    $('#formatoQtd').focus();
}

function fecharFormato() {
    const m = $('#modalFormato');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

async function salvarFormato(e) {
    e.preventDefault();
    const qtd = parseInt($('#formatoQtd').value, 10);
    if (!(qtd >= 1 && qtd <= 100)) { ML.toast('Quantidade de 1 a 100.', 'aviso'); return; }
    fecharFormato();
    if ($('#modalClonar').classList.contains('flex')) await fecharModalClonar();
    iniciarJob({ tipo: 'formato', item: formatoItem, formato: $('#formatoTipo').value, qtd });
}

async function criarOpcao(codigo) {
    const l = clonar?.pares?.linhas.find(x => x.codigo === codigo);
    if (!l) return;
    const ok = await ML.confirmar(`Criar o ${l.falta} de ${l.sku || codigo} (${codigo}) no ML? O anúncio novo entra ativo, no envio normal, pelo preço da margem alvo; depois o preço é conferido por alguns minutos até cravar a margem.`,
        { titulo: `Criar ${l.falta}`, textoConfirmar: 'Criar' });
    if (ok) iniciarJob({ tipo: 'clonar', item: codigo });
}

// ===================================================================== simulador

// Preço sugerido de um SKU com taxa, frete e adc digitados; mostra o custo de cada parte do SKU
async function simular() {
    const caixa = $('#resultadoSimular');
    let r;
    try {
        r = await postJson('simular', { sku: $('#simSku').value, taxa: $('#simTaxa').value, frete: $('#simFrete').value, adc: $('#simAdc').value });
    } catch (erro) {
        caixa.innerHTML = `<p class="text-sm text-red-300">${esc(erro.message)}</p>`;
        return;
    }
    const pecasDe = p => {
        // mostra as peças da tabela só quando não são a própria parte (apelido, barra, pontuação)
        const iguais = p.pecas.length === 1 && p.pecas[0].sku.toUpperCase() === p.parte.toUpperCase() && p.pecas[0].qtd === 1;
        if (!p.pecas.length || iguais) return '';
        return '<div class="text-xs text-gray-500 mt-0.5">' + p.pecas.map(x =>
            `${x.qtd > 1 ? x.qtd + '× ' : ''}<span class="font-mono">${esc(x.sku)}</span> ${x.gratis ? 'grátis' : brl(x.preco)}`).join(' + ') + '</div>';
    };
    const linhas = r.partes.map(p => `<tr class="border-t border-gray-700/60">
        <td class="py-1.5 pr-3"><span class="font-mono">${esc(p.parte)}</span>${pecasDe(p)}</td>
        <td class="py-1.5 px-3 text-right tabular-nums">${p.qtd}</td>
        <td class="py-1.5 px-3 text-right tabular-nums">${p.unitario == null ? '<span class="text-red-300">sem preço</span>' : brl(p.unitario)}</td>
        <td class="py-1.5 px-3 text-right tabular-nums">${brl(p.subtotal)}</td>
        <td class="py-1.5 pl-3 text-gray-400">${esc(p.como)}</td></tr>`).join('');
    const tabela = `<div class="overflow-x-auto"><table class="w-full text-sm">
        <thead class="text-gray-400 text-left"><tr><th class="font-medium py-1 pr-3">Parte do SKU</th><th class="font-medium py-1 px-3 text-right">Qtd</th>
        <th class="font-medium py-1 px-3 text-right">Custo un.</th><th class="font-medium py-1 px-3 text-right">Subtotal</th><th class="font-medium py-1 pl-3">Como achou</th></tr></thead>
        <tbody>${linhas}</tbody>
        <tfoot class="border-t border-gray-600"><tr><td class="py-1.5 pr-3 font-semibold" colspan="3">Custo da tabela</td>
        <td class="py-1.5 px-3 text-right tabular-nums font-semibold">${brl(r.custo)}</td><td class="py-1.5 pl-3 text-gray-400">${esc(r.custo == null ? '' : r.como)}</td></tr>
        ${r.custo == null ? '' : `<tr><td class="py-1 pr-3 text-gray-400" colspan="3">Com ${pct(r.desconto, 0)} de desconto do fornecedor</td>
        <td class="py-1 px-3 text-right tabular-nums">${brl(r.custo_desc)}</td><td></td></tr>`}</tfoot></table></div>`;

    let topo;
    if (r.custo == null) {
        topo = `<p class="text-sm text-red-300 mb-3">Sem custo: ${esc(r.falta.join(', ') || r.como)} não está na tabela de preços nem nos apelidos.</p>`;
    } else {
        const aviso = r.so_gratis ? '<p class="text-sm text-amber-300 mb-3">Este SKU só tem itens grátis: na planilha ele fica com o preço atual.</p>' : '';
        topo = aviso + `<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            ${cartao('Preço sugerido', brl(r.preco_sugerido), `margem alvo ${pctMeta(r.meta)}`, 'border-blue-500/60')}
            ${cartao('Você recebe', brl(r.recebe), `taxa ${brl(r.valor_taxa)} · frete ${brl(r.frete)} · adc ${brl(r.adc)}`)}
            ${cartao('Lucro', brl(r.lucro), `margem ${pct(r.margem, 2)} (antes do imposto)`)}
            ${cartao('Depois do imposto', brl(r.lucro_liquido), `imposto ${brl(r.imposto)}`)}
        </div>`;
    }
    caixa.innerHTML = topo + tabela;
}

// ===================================================================== eventos

// Taxa em %: taxa em R$ ÷ valor total, no formato 16.50%
function numeroDigitado(texto) {
    let t = String(texto).replace(/R\$|\s/g, '');
    if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.');
    return t === '' ? NaN : Number(t);
}
function calcularPct() {
    const total = numeroDigitado($('#pctTotal').value), taxa = numeroDigitado($('#pctTaxa').value);
    const ok = total > 0 && taxa >= 0;
    $('#pctResultado').textContent = ok ? (taxa / total * 100).toFixed(2) + '%' : '—';
    $('#pctResultado').classList.toggle('text-gray-500', !ok);
    $('#pctResultado').classList.toggle('text-blue-300', ok);
}

// Soma das três partes da margem alvo enquanto digita
function somarMeta() {
    const v = s => parseFloat(String($(s).value).replace(',', '.')) || 0;
    const total = v('#campoLucro') + v('#campoGordura') + v('#campoImposto');
    $('#totalMeta').textContent = total.toLocaleString('pt-BR', { maximumFractionDigits: 2 }) + '%';
}

// Dropdown de margens com várias opções marcadas: o rótulo resume o que está marcado
function ligarFiltroMargem() {
    const botao = $('#btnFiltroMargem');
    const painel = $('#opcoesMargem');
    const caixas = $$('#opcoesMargem input');
    const abrir = aberto => {
        painel.classList.toggle('hidden', !aberto);
        botao.setAttribute('aria-expanded', String(aberto));
    };
    const atualizarRotulo = () => {
        const marcadas = caixas.filter(c => c.checked);
        const nomes = marcadas.map(c => c.parentElement.textContent.trim());
        $('#rotuloFiltroMargem').textContent = marcadas.length === caixas.length ? 'Todas as margens'
            : marcadas.length === 0 ? 'Nenhuma margem'
            : marcadas.length <= 2 ? nomes.join(', ') : `${marcadas.length} margens`;
    };
    botao.addEventListener('click', () => abrir(painel.classList.contains('hidden')));
    caixas.forEach(c => c.addEventListener('change', atualizarRotulo));
    document.addEventListener('click', e => { if (!$('#filtroMargem').contains(e.target)) abrir(false); });
    $('#filtroMargem').addEventListener('keydown', e => {
        if (e.key === 'Escape') { abrir(false); botao.focus(); }
    });
    atualizarRotulo();
}

function ligarEventos() {
    document.addEventListener('click', async e => {
        const botao = e.target.closest('[data-copiar]');
        if (!botao) return;
        try {
            await navigator.clipboard.writeText(botao.dataset.copiar);
            ML.toast(`${botao.dataset.copiar} copiado.`, 'sucesso');
        } catch (erro) {
            ML.toast('Não foi possível copiar.', 'erro');
        }
    });

    $$('#abas [data-aba]').forEach(b => b.addEventListener('click', () => abrirAba(b.dataset.aba)));

    $('#btnAtualizar').addEventListener('click', atualizarMargens);
    $('#btnRecalcular').addEventListener('click', () => iniciarJob({ tipo: 'recalcular' }));
    $('#btnMetas').addEventListener('click', () => {
        const pct2 = v => String(+(v * 100).toFixed(2)).replace('.', ',');
        $('#campoLucro').value = pct2(estado.partes.lucro);
        $('#campoGordura').value = pct2(estado.partes.gordura);
        $('#campoImposto').value = pct2(estado.partes.imposto);
        somarMeta();
        $('#formMetas').classList.toggle('hidden');
    });
    $('#btnMetasCancelar').addEventListener('click', () => $('#formMetas').classList.add('hidden'));
    $('#formSimular').addEventListener('submit', e => { e.preventDefault(); simular(); });
    $('#btnPares').addEventListener('click', () => iniciarJob({ tipo: 'pares' }));
    ['#buscaClonar', '#faltaClonar', '#statusClonar'].forEach(s => $(s).addEventListener('input', renderClonar));
    $('#tabelaClonar').addEventListener('click', e => {
        const b = e.target.closest('[data-clonar]');
        if (b && !b.disabled) criarOpcao(b.dataset.clonar);
        const g = e.target.closest('[data-gtin]');
        if (g && !g.disabled) abrirPlanoGtin(g.dataset.gtin);
    });
    $('#tabelaClonar').addEventListener('change', e => {
        const c = e.target.closest('[data-marcar]');
        if (!c) return;
        if (c.checked && marcadosClonar.size >= MAX_CLONAR) {
            c.checked = false;
            ML.toast(`Até ${MAX_CLONAR} anúncios por vez.`, 'aviso');
            return;
        }
        if (c.checked) marcadosClonar.add(c.dataset.marcar);
        else marcadosClonar.delete(c.dataset.marcar);
        atualizarMarcados();
    });
    $('#marcarClonar').addEventListener('change', e => {
        const visiveis = $$('#tabelaClonar [data-marcar]').map(c => c.dataset.marcar);
        if (e.target.checked) {
            for (const c of visiveis) {
                if (marcadosClonar.size >= MAX_CLONAR) { ML.toast(`Marquei ${MAX_CLONAR}: é o máximo por vez.`, 'aviso'); break; }
                marcadosClonar.add(c);
            }
        } else {
            for (const c of visiveis) marcadosClonar.delete(c);
        }
        renderClonar();
    });
    $('#btnClonarMarcados').addEventListener('click', criarMarcados);
    $('#btnClonarFechar').addEventListener('click', fecharModalClonar);
    $('#btnGtinCancelar').addEventListener('click', fecharPlanoGtin);
    $('#btnFormatoCancelar').addEventListener('click', fecharFormato);
    $('#btnEditarCancelar').addEventListener('click', fecharEdicao);
    $('#formEditar').addEventListener('submit', salvarEdicao);
    ['#editarPreco', '#editarTaxa', '#editarFrete', '#editarAdc'].forEach(s => $(s).addEventListener('input', previaEdicao));
    $('#tabelaMargem').addEventListener('click', e => {
        const b = e.target.closest('[data-editar]');
        if (b && !b.disabled) abrirEdicao(b.dataset.editar);
    });
    ligarDicas();
    $('#formFormato').addEventListener('submit', salvarFormato);
    // o botão aparece na lista e no modal de andamento (conteúdo redesenhado): um ouvinte só
    document.addEventListener('click', e => {
        const b = e.target.closest('[data-formato]');
        if (b && !b.disabled) abrirFormato(b.dataset.formato, b.dataset.sku);
    });
    $('#btnGtinCorrigir').addEventListener('click', () => {
        fecharPlanoGtin();
        if (gtinItem) iniciarJob({ tipo: 'gtin', item: gtinItem });
    });
    ['#pctTotal', '#pctTaxa'].forEach(c => $(c).addEventListener('input', calcularPct));
    $('#pctResultado').addEventListener('click', async () => {
        const v = $('#pctResultado').textContent;
        if (v === '—') return;
        try { await navigator.clipboard.writeText(v); ML.toast(`Copiado: ${v}`, 'sucesso'); }
        catch (erro) { ML.toast('Não foi possível copiar.', 'erro'); }
    });
    ['#campoLucro', '#campoGordura', '#campoImposto'].forEach(s => $(s).addEventListener('input', somarMeta));

    // Atualizar só alguns anúncios: os códigos MLB colados viram a lista, sem repetir
    // com ou sem MLB: "MLB123", "MLB-123" e "123" viram MLB123
    const codigosParcial = () => [...new Set([...$('#campoParcial').value.toUpperCase().matchAll(/(?:MLB-?)?(\d{6,})/g)].map(m => 'MLB' + m[1]))];
    $('#btnParcial').addEventListener('click', () => {
        $('#formParcial').classList.toggle('hidden');
        if (!$('#formParcial').classList.contains('hidden')) $('#campoParcial').focus();
    });
    $('#btnParcialCancelar').addEventListener('click', () => $('#formParcial').classList.add('hidden'));
    $('#campoParcial').addEventListener('input', () => {
        const n = codigosParcial().length;
        $('#contagemParcial').textContent = n ? `${n} anúncio${n > 1 ? 's' : ''}` : '';
    });
    $('#formParcial').addEventListener('submit', async e => {
        e.preventDefault();
        const ids = codigosParcial();
        if (!ids.length) { ML.toast('Cole os códigos dos anúncios (MLB…).', 'aviso'); return; }
        await iniciarJob({ tipo: 'parcial', ids: ids.join(',') });
    });
    $('#formMetas').addEventListener('submit', async e => {
        e.preventDefault();
        const salvo = await salvarConfig({ lucro: $('#campoLucro').value, gordura: $('#campoGordura').value, imposto: $('#campoImposto').value }, 'Margem alvo salva.');
        if (!salvo) return;
        $('#formMetas').classList.add('hidden');
        renderMargem();
        if (margem && !jobRodando && await ML.confirmar('Recalcular a planilha agora com a margem alvo nova? Não busca no ML.', { titulo: 'Recalcular', textoConfirmar: 'Recalcular' })) {
            iniciarJob({ tipo: 'recalcular' });
        }
    });

    ['#buscaMargem', '#filtroMargem', '#statusMargem', '#tipoMargem', '#envioMargem'].forEach(s => $(s).addEventListener('input', () => { mostrarTodosMargem = false; renderTabelaMargem(); }));
    ligarFiltroMargem();
    $('#btnMaisMargem').addEventListener('click', () => { mostrarTodosMargem = true; renderTabelaMargem(); });
    $$('th[data-ordem]').forEach(th => th.addEventListener('click', () => {
        const campo = th.dataset.ordem;
        ordem = ordem.campo === campo ? { campo, dir: -ordem.dir } : { campo, dir: 1 };
        renderTabelaMargem();
    }));

    $$('[data-status]').forEach(b => b.addEventListener('click', async () => {
        const nome = b.dataset.status === 'active' ? 'só os anúncios ativos' : 'só os anúncios pausados';
        if (await ML.confirmar(`Buscar ${nome} no ML e gerar a planilha? A busca completa parada, se houver, é descartada.`, { titulo: 'Buscar no ML', textoConfirmar: 'Buscar' })) {
            iniciarJob({ tipo: 'atualizar', status: b.dataset.status });
        }
    }));
    $('#btnDoZero').addEventListener('click', async () => {
        if (await ML.confirmar('Descartar a busca parada e começar do zero?', { titulo: 'Começar do zero', textoConfirmar: 'Começar do zero', perigo: true })) {
            iniciarJob({ tipo: 'atualizar', status: 'todos', novo: true });
        }
    });
    $('#formTeste').addEventListener('submit', e => {
        e.preventDefault();
        iniciarJob({ tipo: 'teste', ids: $('#campoIds').value });
    });
    $('#formAntiga').addEventListener('submit', e => {
        e.preventDefault();
        if ($('#campoColeta').value) iniciarJob({ tipo: 'recalcular', coleta: $('#campoColeta').value });
    });
    $('#avancado').addEventListener('toggle', () => { if ($('#avancado').open) { carregarPlanilhas(); carregarTeste(); } });

    $('#btnSkus').addEventListener('click', async () => {
        if (await ML.confirmar('Busca o SKU de todos os anúncios no ML e compara com a tabela de preços. Leva alguns minutos.', { titulo: 'Comparar SKUs', textoConfirmar: 'Comparar' })) {
            iniciarJob({ tipo: 'skus' });
        }
    });
    ['#buscaSkus', '#filtroSkus'].forEach(s => $(s).addEventListener('input', renderSkus));

    $('#arquivoEnvio').addEventListener('change', e => enviarPdf(e.target.files[0]));
    const area = $('#areaEnvio');
    area.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('border-blue-500'); });
    area.addEventListener('dragleave', () => area.classList.remove('border-blue-500'));
    area.addEventListener('drop', e => {
        e.preventDefault();
        area.classList.remove('border-blue-500');
        enviarPdf(e.dataTransfer.files[0]);
    });
    $('#btnCopiarEnvio').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText($('#envioTexto').value);
            ML.toast('Lista copiada.', 'sucesso');
        } catch (e) {
            $('#envioTexto').select();
            ML.toast('Não foi possível copiar: o texto ficou selecionado.', 'aviso');
        }
    });

    $$('[data-tabela]').forEach(s => s.querySelector('input[type=file]').addEventListener('change', e => trocarTabela(s.dataset.tabela, e.target)));

    $('#btnGerarLink').addEventListener('click', async () => {
        try {
            const { url } = await postJson('acesso_url');
            const link = $('#linkAutorizar');
            link.href = url;
            link.textContent = url;
            $('#formAutorizar').classList.remove('hidden');
        } catch (e) {
            ML.toast(e.message, 'erro');
        }
    });
    $('#formAutorizar').addEventListener('submit', async e => {
        e.preventDefault();
        try {
            const r = await postJson('acesso_concluir', { retorno: $('#campoRetorno').value });
            ML.toast(`Autorizado: ${r.nickname} (id ${r.id}).`, 'sucesso');
            $('#formAutorizar').classList.add('hidden');
            $('#campoRetorno').value = '';
            await carregarEstado();
        } catch (e2) {
            ML.toast(e2.message, 'erro');
        }
    });
    $('#formRps').addEventListener('submit', e => {
        e.preventDefault();
        salvarConfig({ rps: $('#campoRps').value }, 'Limite salvo. Vale a partir da próxima busca.');
    });
}

async function iniciar() {
    ligarEventos();
    abrirAba(location.hash.slice(1) || 'margem');
    try {
        await carregarEstado();
    } catch (e) {
        $('#subtitulo').textContent = e.message;
        ML.toast(e.message, 'erro');
        return;
    }
    await Promise.all([carregarMargem(), carregarSkus(), carregarClonar()]);
    if (jobRodando) acompanharJob();
}

iniciar();
