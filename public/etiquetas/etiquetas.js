'use strict';
// Aba Etiquetas: a lista, o processamento e os PDFs vêm do ml-etiquetas-web via api.php.

const $ = (sel) => document.querySelector(sel);
const brl = (n) => n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const dataCurta = (iso) => iso ? new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) : '';
const horaCurta = (iso) => iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
const urlFiscal = (v) => `https://myaccount.mercadolivre.com.br/fiscal-information/order/${(v.pedidos || [])[0] || v.venda}`;
const urlArquivo = (pasta, nome) => `api.php?r=arquivo&pasta=${encodeURIComponent(pasta)}&nome=${encodeURIComponent(nome)}`;

const ETAPAS = {
    fila: 'na fila',
    conferindo: 'conferindo',
    emitindo: 'emitindo NF',
    autorizando: 'aguardando SEFAZ',
    aguardando_etiqueta: 'aguardando etiqueta',
    pronta: 'pronta',
    erro: 'erro',
};

const TAG = 'text-xs font-semibold px-2 py-0.5 rounded-md';
const COR = {
    nf: 'bg-amber-900/40 text-amber-300',
    pronta: 'bg-emerald-900/40 text-emerald-300',
    b2b: 'bg-blue-900/40 text-blue-300',
    proc: 'bg-gray-700 text-gray-300',
    erro: 'bg-red-900/40 text-red-300 border border-red-800',
    aviso: 'bg-amber-900/30 text-amber-200 border border-amber-800',
};

let vendas = [];
const bloqueada = (v) => v.em_processamento || v.sem_dados_fiscais;
const marcadas = new Set();
let jaCarregou = false;
let pollJob = null;

function el(tag, attrs = {}, ...filhos) {
    const e = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        if (k === 'class') e.className = v;
        else if (k.startsWith('on')) e.addEventListener(k.slice(2), v);
        else if (v !== null && v !== undefined && v !== false) e.setAttribute(k, v === true ? '' : v);
    }
    for (const f of filhos.flat()) {
        if (f === null || f === undefined || f === false) continue;
        e.append(f instanceof Node ? f : document.createTextNode(String(f)));
    }
    return e;
}

const alerta = (tipo, texto) => el('div', { class: `rounded-lg px-3 py-2 my-2 text-sm ${COR[tipo]}` }, texto);

async function api(rota, opcoes = {}) {
    const r = await fetch('api.php?r=' + rota, {
        ...opcoes,
        headers: { 'Content-Type': 'application/json', ...(opcoes.headers || {}) },
    });
    const corpo = await r.json().catch(() => null);
    if (!r.ok) throw new Error((corpo && corpo.erro) || `Erro ${r.status}`);
    return corpo;
}

function abrir(id) { const m = $(id); m.classList.remove('hidden'); m.classList.add('flex'); }
function fechar(id) { const m = $(id); m.classList.add('hidden'); m.classList.remove('flex'); }
document.querySelectorAll('[data-fechar]').forEach((b) => b.addEventListener('click', () => fechar('#' + b.closest('[id^="modal"]').id)));

// ------------------------------------------------------------------ lista

async function carregarVendas(forcar = false) {
    $('#atualizado').textContent = 'Atualizando…';
    $('#btnAtualizar').disabled = true;
    try {
        const r = await api('vendas' + (forcar ? '&atualizar=1' : ''));
        const anteriores = new Set(vendas.map((v) => String(v.venda)));
        vendas = r.vendas;
        for (const v of vendas) {
            const id = String(v.venda);
            if (bloqueada(v)) marcadas.delete(id);
            else if (!jaCarregou || !anteriores.has(id)) marcadas.add(id); // novas vêm marcadas
        }
        for (const id of [...marcadas]) if (!vendas.some((v) => String(v.venda) === id)) marcadas.delete(id);
        jaCarregou = true;
        $('#atualizado').textContent = 'Atualizado às ' + new Date(r.atualizado_em * 1000).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    } catch (err) {
        $('#atualizado').textContent = 'Falha ao atualizar: ' + err.message;
    } finally {
        $('#btnAtualizar').disabled = false;
    }
    desenharLista();
}

function desenharLista() {
    const semFiscal = vendas.filter((v) => v.sem_dados_fiscais).length;
    const nf = vendas.filter((v) => v.situacao === 'nf_pendente').length - semFiscal;
    const prontas = vendas.length - nf - semFiscal;
    const chip = 'px-3 py-1.5 rounded-full text-sm font-semibold';
    $('#resumo').replaceChildren(...[
        el('span', { class: `${chip} ${COR.nf}` }, `${nf} aguardando NF`),
        el('span', { class: `${chip} ${COR.pronta}` }, prontas === 1 ? '1 pronta para imprimir' : `${prontas} prontas para imprimir`),
        semFiscal ? el('span', { class: `${chip} ${COR.erro}` }, `${semFiscal} sem dados fiscais`) : null,
    ].filter(Boolean));

    $('#lista').replaceChildren(...vendas.map((v) => {
        const id = String(v.venda);
        const travada = bloqueada(v);
        const check = el('input', { type: 'checkbox', class: 'w-5 h-5 mt-0.5 accent-blue-500', disabled: travada });
        check.checked = marcadas.has(id) && !travada;
        check.addEventListener('change', () => { check.checked ? marcadas.add(id) : marcadas.delete(id); atualizarBarra(); });
        const nfPendente = v.situacao === 'nf_pendente';
        const card = el('div', { class: 'bg-gray-800 border rounded-xl p-4 flex gap-3 ' + (v.em_processamento ? 'border-gray-700 opacity-50' : travada ? 'border-red-900' : 'border-gray-700 cursor-pointer hover:border-gray-500 transition-colors') },
            check,
            el('div', { class: 'flex-1 min-w-0' },
                el('div', { class: 'flex flex-wrap justify-between gap-x-4 gap-y-1' },
                    el('div', { class: 'min-w-0' },
                        el('div', { class: 'font-semibold' }, v.comprador),
                        el('div', { class: 'text-sm text-gray-400' }, `${v.apelido || ''} · venda ${id}`)),
                    el('div', { class: 'sm:text-right whitespace-nowrap' },
                        el('div', { class: 'tabular-nums' }, brl(v.total)),
                        el('div', { class: 'text-sm text-gray-400' }, v.entrega ? 'entrega ' + dataCurta(v.entrega) : ''))),
                el('div', { class: 'text-sm mt-2 space-y-0.5' }, v.itens.map((it) => el('div', { class: 'break-words' },
                    `${it.qtd}x `, el('strong', {}, it.sku || '(sem SKU)'), el('span', { class: 'text-gray-400' }, ` — ${it.titulo}`)))),
                el('div', { class: 'flex flex-wrap items-center gap-2 mt-2' },
                    v.sem_dados_fiscais
                        ? [el('span', { class: `${TAG} ${COR.erro}` }, 'Anúncio sem dados fiscais'),
                            el('a', { class: 'text-xs font-semibold text-red-300 hover:text-red-200 underline', href: urlFiscal(v), target: '_blank', rel: 'noopener' }, 'Corrigir ↗')]
                        : el('span', { class: `${TAG} ${nfPendente ? COR.nf : COR.pronta}` }, nfPendente ? 'Aguardando NF' : 'Pronta para imprimir'),
                    v.b2b ? el('span', { class: `${TAG} ${COR.b2b}` }, 'B2B') : null,
                    v.em_processamento ? el('span', { class: `${TAG} ${COR.proc}` }, 'Em processamento') : null,
                    el('span', { class: 'text-xs text-gray-500' }, 'vendida ' + horaCurta(v.data)))),
        );
        card.addEventListener('click', (e) => {
            if (travada || e.target === check) return;
            check.checked = !check.checked;
            check.dispatchEvent(new Event('change'));
        });
        return card;
    }));
    $('#vazio').classList.toggle('hidden', vendas.length > 0);
    atualizarBarra();
}

const selecionaveis = () => vendas.filter((v) => !bloqueada(v));
const selecionadas = () => selecionaveis().filter((v) => marcadas.has(String(v.venda)));

function atualizarBarra() {
    const sel = selecionadas();
    const todas = selecionaveis();
    $('#checkTodas').checked = todas.length > 0 && sel.length === todas.length;
    $('#checkTodas').indeterminate = sel.length > 0 && sel.length < todas.length;
    $('#selecionadas').textContent = sel.length ? `${sel.length} selecionada(s)` : '';
    $('#btnProcessar').textContent = sel.length ? `Processar ${sel.length}` : 'Processar';
    $('#btnProcessar').disabled = sel.length === 0 || pollJob !== null;
}

$('#checkTodas').addEventListener('change', (e) => {
    for (const v of selecionaveis()) e.target.checked ? marcadas.add(String(v.venda)) : marcadas.delete(String(v.venda));
    desenharLista();
});
$('#btnAtualizar').addEventListener('click', () => { carregarVendas(true); carregarHistorico(); });

// ------------------------------------------------------------------ processar

$('#btnProcessar').addEventListener('click', () => {
    const sel = selecionadas();
    const nf = sel.filter((v) => v.situacao === 'nf_pendente').length;
    $('#confirmarTexto').textContent =
        `${sel.length} venda(s): ${nf} NF-e serão emitidas (não dá para desfazer) e ` +
        `${sel.length} etiqueta(s) serão baixadas, marcando como impressas no Mercado Livre.`;
    abrir('#modalConfirmar');
});

$('#btnConfirmar').addEventListener('click', async () => {
    const ids = selecionadas().map((v) => String(v.venda));
    $('#btnConfirmar').disabled = true;
    try {
        const job = await api('processar', { method: 'POST', body: JSON.stringify({ vendas: ids }) });
        fechar('#modalConfirmar');
        mostrarJob(job);
        iniciarPoll();
    } catch (err) {
        $('#confirmarTexto').replaceChildren(alerta('erro', err.message));
    } finally {
        $('#btnConfirmar').disabled = false;
    }
});

function icone(etapa) {
    const base = 'w-5 h-5 rounded-full inline-flex items-center justify-center text-xs font-bold shrink-0';
    if (etapa === 'pronta') return el('span', { class: `${base} bg-emerald-500 text-white` }, '✓');
    if (etapa === 'erro') return el('span', { class: `${base} bg-red-500 text-white` }, '✕');
    if (etapa === 'fila') return el('span', { class: `${base} border-2 border-dashed border-gray-600` });
    return el('span', { class: `${base} border-[3px] border-blue-900 border-t-blue-400 girando` });
}

function mostrarJob(job) {
    const total = job.vendas.length;
    const feitas = job.vendas.filter((v) => v.etapa === 'pronta' || v.etapa === 'erro').length;
    $('#procTitulo').textContent = job.status === 'gerando' ? 'Gerando os PDFs…' : `Processando ${feitas}/${total}…`;
    const inicio = job.iniciado_em || job.criado_em;
    const min = Math.floor((Date.now() - new Date(inicio)) / 60000);
    $('#procSub').textContent = `Iniciado às ${new Date(inicio).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}` + (min ? ` · ${min} min` : '');
    $('#procLista').replaceChildren(...job.vendas.map((v) => el('li', { class: 'border border-gray-700 rounded-lg px-3 py-2' },
        el('div', { class: 'flex items-center gap-3' },
            icone(v.etapa),
            el('div', { class: 'flex-1 min-w-0' },
                el('div', {}, v.comprador),
                el('div', { class: 'text-sm text-gray-400' }, `venda ${v.venda}` + (v.nf ? ` · NF ${v.nf}` : ''))),
            el('span', { class: 'text-sm text-gray-400 text-right' }, ETAPAS[v.etapa] || v.etapa)),
        v.erro ? el('div', { class: 'text-sm text-red-400 mt-1 ml-8' }, v.erro) : null,
    )));
    abrir('#modalProcessando');
}

function iniciarPoll() {
    if (pollJob) return;
    pollJob = setInterval(verificarJob, 3000);
    atualizarBarra();
}

function pararPoll() {
    clearInterval(pollJob);
    pollJob = null;
    atualizarBarra();
}

async function verificarJob() {
    let job;
    try { job = await api('job'); } catch { return; }
    if (!job) { pararPoll(); fechar('#modalProcessando'); return; }
    if (['fila', 'processando', 'gerando'].includes(job.status)) {
        mostrarJob(job);
        iniciarPoll();
        return;
    }
    pararPoll();
    fechar('#modalProcessando');
    if (job.status === 'concluido' && !job.visto) {
        mostrarResultado(job.resultado || {}, job);
        carregarVendas(true);
        carregarHistorico();
    }
}

// ------------------------------------------------------------------ resultado

let jobDoResultado = null;

function mostrarResultado(res, job = null) {
    jobDoResultado = job;
    const erros = res.erros || [];
    $('#resTitulo').textContent = res.pasta ? (erros.length ? 'Pronto, com erros' : 'Pronto') : 'Nenhuma etiqueta gerada';

    const downloads = [];
    if (res.danfe) downloads.push(el('a', { class: CLASSE_PRIMARIO, href: urlArquivo(res.pasta, res.danfe) }, '⬇ DANFEs'));
    if (res.etiquetas) downloads.push(el('a', { class: CLASSE_PRIMARIO, href: urlArquivo(res.pasta, res.etiquetas) }, '⬇ Etiquetas + resumo'));
    $('#resDownloads').replaceChildren(...downloads);

    $('#resErros').replaceChildren(...erros.map((e) => alerta('erro', (e.comprador ? `${e.comprador} (venda ${e.venda}): ` : '') + e.erro)));
    $('#resMensagem').value = res.mensagem || '';
    for (const id of ['#resMensagemTitulo', '#resMensagem', '#btnCopiar']) $(id).classList.toggle('hidden', !res.mensagem);

    const est = res.estoque;
    const blocos = [];
    if (est) {
        blocos.push(el('h3', { class: 'font-semibold text-gray-200 mt-4 mb-2' }, 'Estoque de devoluções'));
        if (est.erro) blocos.push(alerta('aviso', 'Não consegui consultar o estoque: ' + est.erro));
        else if (!est.exatos.length && !est.parecidos.length) blocos.push(el('p', { class: 'text-sm text-gray-400' }, 'Nenhum item das vendas está no estoque de devoluções.'));
        for (const [rotulo, lista] of [['Mesmo SKU', est.exatos], ['Parecido', est.parecidos]]) {
            if (!lista || !lista.length) continue;
            blocos.push(el('div', { class: 'mb-2' },
                el('div', { class: 'text-sm font-semibold text-gray-300' }, rotulo),
                el('ul', { class: 'list-disc pl-5 text-sm' }, lista.map((m) => el('li', {}, `${m.levando}x ${m.vendido} (${m.nome}) → tem ${m.total}x ${m.no_estoque}`)))));
        }
    }
    $('#resEstoque').replaceChildren(...blocos);
    $('#resAvisos').replaceChildren(...(res.avisos || []).map((a) => alerta('aviso', a)));
    abrir('#modalResultado');
}

$('#btnFecharResultado').addEventListener('click', async () => {
    fechar('#modalResultado');
    if (jobDoResultado) {
        jobDoResultado = null;
        try { await api('visto', { method: 'POST', body: '{}' }); } catch { /* sem problema */ }
    }
});

$('#btnCopiar').addEventListener('click', async () => {
    const t = $('#resMensagem');
    try { await navigator.clipboard.writeText(t.value); }
    catch { t.select(); document.execCommand('copy'); }
    $('#btnCopiar').textContent = 'Copiada ✓';
    setTimeout(() => { $('#btnCopiar').textContent = 'Copiar mensagem'; }, 1500);
});

// ------------------------------------------------------------------ histórico

async function carregarHistorico() {
    let itens = [];
    try { itens = await api('historico'); } catch { /* mantém vazio */ }
    const box = $('#historico');
    if (!itens.length) { box.replaceChildren(el('p', { class: 'text-sm text-gray-400' }, 'Nenhum processamento ainda.')); return; }
    const botao = CLASSE_BOTAO.replace('py-2 px-4', 'py-1.5 px-3 text-sm');
    box.replaceChildren(...itens.map((h) => el('div', { class: 'bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 flex flex-wrap justify-between items-center gap-2' },
        el('div', {},
            el('div', {}, horaCurta(h.criado_em)),
            el('div', { class: 'text-sm text-gray-400' }, `${h.vendas.length} venda(s)` + (h.vendas.some((v) => v.nf) ? ' · NF ' + h.vendas.map((v) => v.nf).filter(Boolean).join(', ') : ''))),
        el('div', { class: 'flex flex-wrap gap-2' },
            h.danfe ? el('a', { class: botao, href: urlArquivo(h.pasta, h.danfe) }, '⬇ DANFEs') : null,
            h.etiquetas ? el('a', { class: botao, href: urlArquivo(h.pasta, h.etiquetas) }, '⬇ Etiquetas') : null,
            el('button', { type: 'button', class: botao, onclick: () => mostrarResultado(h) }, 'Ver mensagem')),
    )));
}

// ------------------------------------------------------------------ início

carregarVendas();
carregarHistorico();
verificarJob();
setInterval(() => { if (!pollJob && document.visibilityState === 'visible') carregarVendas(); }, 60000);
