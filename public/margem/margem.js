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
const urlPlanilha = (modulo, nome) => `api.php?r=arquivo&modulo=${modulo}&nome=${encodeURIComponent(nome)}`;

const LIMITE_LINHAS = 300;
const NOME_TAREFA = { atualizar: 'Atualização das margens', recalcular: 'Recálculo da margem', teste: 'Teste da margem', skus: 'Comparação de SKUs' };
const STATUS = { active: 'ativo', paused: 'pausado', closed: 'encerrado', under_review: 'em revisão', inactive: 'inativo' };

let estado = null;          // /api/estado
let margem = null;          // última planilha de custo (json)
let skus = null;            // último comparador (json)
let ordem = { campo: 'margem', dir: 1 };
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
}

// ===================================================================== estado geral

async function carregarEstado() {
    estado = await api('estado');
    const c = estado.coleta;
    const parada = c && !c.concluida;
    $('#btnAtualizar').textContent = parada ? `▶️ Continuar a atualização (${c.feitos}/${c.total})` : '🔄 Atualizar margens';
    $('#btnDoZero').classList.toggle('hidden', !parada);
    $('#infoMetas').innerHTML = `Margem alvo <strong class="text-gray-200">${pctMeta(estado.meta)}</strong> · atacado <strong class="text-gray-200">${pctMeta(estado.meta_atacado)}</strong>`;
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
        ${job.estado !== 'ok' ? log : ''}`;
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
        margem = (await api('margem')).resultado;
    } catch (e) {
        ML.toast(e.message, 'erro');
        return;
    }
    if (margem) margem.linhas.forEach(l => { l.situacao = situacao(l); });
    renderMargem();
}

function renderMargem() {
    const sub = $('#subtitulo');
    if (!margem) {
        sub.textContent = 'Nenhuma planilha de margem ainda.';
        $('#infoColeta').textContent = 'Use Atualizar margens para buscar os anúncios no ML.';
        $('#resumoMargem').innerHTML = '';
        $('#tabelaMargem').innerHTML = '<tr><td colspan="7" class="py-6 text-center text-gray-500">Nenhuma planilha ainda.</td></tr>';
        $('#baixarMargem').classList.add('hidden');
        return;
    }
    sub.textContent = `Planilha de ${quando(margem.gerado)} · busca no ML de ${quando(margem.coleta_data)}`;
    $('#infoColeta').innerHTML = `Busca no ML de <strong class="text-gray-200">${quando(margem.coleta_data)}</strong> · ${margem.linhas.length.toLocaleString('pt-BR')} anúncios`
        + (margem.meta !== estado?.meta || margem.meta_atacado !== estado?.meta_atacado ? ' · <span class="text-amber-300">metas mudaram: recalcule</span>' : '');
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
    const lista = margem.linhas.filter(l =>
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
            : pilula(l.situacao === 'gratis' ? 'grátis' : pct(l.margem), COR_MARGEM[l.situacao]);
        return `<tr class="border-t border-gray-700/60 align-top">
            <td class="py-2 pr-2 sm:pr-3 max-w-[11rem] sm:max-w-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold text-gray-100 break-all sm:break-normal">${l.sku ? esc(l.sku) : '<span class="text-gray-500">(sem SKU)</span>'}</span>${status}
                </div>
                <div class="text-xs truncate"><a href="${linkAnuncio(l.codigo)}" target="_blank" rel="noopener" class="text-blue-400 hover:text-blue-300 tabular-nums">${esc(l.codigo)} ↗</a><span class="text-gray-500" title="${esc(l.titulo)}"> · ${esc(l.titulo)}</span></div>
                ${l.situacao === 'sem' ? `<div class="text-xs text-red-300">${esc(l.como)}</div>` : ''}
            </td>
            <td class="py-2 px-2 sm:px-3 text-right tabular-nums whitespace-nowrap" title="Taxa ${pct(l.taxa)} · frete ${brl(l.frete)} · adicional ${brl(l.adc)}">${brl(l.preco)}</td>
            <td class="py-2 px-2 sm:px-3 text-right tabular-nums whitespace-nowrap hidden md:table-cell" title="Sem desconto: ${brl(l.custo)}">${brl(l.custo_desc)}</td>
            <td class="py-2 px-2 sm:px-3 text-right tabular-nums whitespace-nowrap hidden sm:table-cell ${l.lucro < 0 ? 'text-red-300' : ''}">${brl(l.lucro)}</td>
            <td class="py-2 px-2 sm:px-3 text-right">${margemCel}</td>
            <td class="py-2 px-2 sm:px-3 text-right tabular-nums whitespace-nowrap">${l.preco_sugerido == null ? '—' : brl(l.preco_sugerido)}${diferenca(l.diferenca, l.preco)}</td>
            <td class="py-2 pl-2 sm:pl-3 text-right tabular-nums whitespace-nowrap hidden lg:table-cell">${l.preco_sugerido_atacado == null ? '—' : brl(l.preco_sugerido_atacado)}${diferenca(l.diferenca_atacado, l.preco)}</td>
        </tr>`;
    }).join('') : '<tr><td colspan="7" class="py-6 text-center text-gray-500">Nenhum anúncio com esses filtros.</td></tr>';
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
            <span class="tabular-nums text-gray-300">${brl(l.preco)} · margem ${l.custo == null ? 'sem custo' : pct(l.margem)} · sugerido ${brl(l.preco_sugerido)}</span>
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
            <div class="text-xs truncate"><a href="${linkAnuncio(a.codigo)}" target="_blank" rel="noopener" class="text-blue-400 hover:text-blue-300">${esc(a.codigo)} ↗</a><span class="text-gray-500"> · ${esc(a.titulo)}</span></div>
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

// ===================================================================== eventos

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
    $$('#abas [data-aba]').forEach(b => b.addEventListener('click', () => abrirAba(b.dataset.aba)));

    $('#btnAtualizar').addEventListener('click', atualizarMargens);
    $('#btnRecalcular').addEventListener('click', () => iniciarJob({ tipo: 'recalcular' }));
    $('#btnMetas').addEventListener('click', () => {
        $('#campoMeta').value = String(+(estado.meta * 100).toFixed(2)).replace('.', ',');
        $('#campoMetaAtacado').value = String(+(estado.meta_atacado * 100).toFixed(2)).replace('.', ',');
        $('#formMetas').classList.toggle('hidden');
    });
    $('#btnMetasCancelar').addEventListener('click', () => $('#formMetas').classList.add('hidden'));
    $('#formMetas').addEventListener('submit', async e => {
        e.preventDefault();
        const salvo = await salvarConfig({ meta: $('#campoMeta').value, meta_atacado: $('#campoMetaAtacado').value }, 'Metas salvas.');
        if (!salvo) return;
        $('#formMetas').classList.add('hidden');
        renderMargem();
        if (margem && !jobRodando && await ML.confirmar('Recalcular a planilha agora com as novas metas? Não busca no ML.', { titulo: 'Recalcular', textoConfirmar: 'Recalcular' })) {
            iniciarJob({ tipo: 'recalcular' });
        }
    });

    ['#buscaMargem', '#filtroMargem', '#statusMargem'].forEach(s => $(s).addEventListener('input', () => { mostrarTodosMargem = false; renderTabelaMargem(); }));
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
    await Promise.all([carregarMargem(), carregarSkus()]);
    if (jobRodando) acompanharJob();
}

iniciar();
