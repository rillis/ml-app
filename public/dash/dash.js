'use strict';
// Dash: painel ao vivo. Banco (eventos, vendas do dia, boletos, sistema) a cada 30 s;
// ML (perguntas, reclamações, etiquetas) a cada 2 min ou na hora quando o webhook traz algo novo.
// Texto de comprador só entra por textContent (função el); innerHTML só no HTML já escapado pelo servidor (fila e logs).

const INTERVALO_BANCO = 30000;
const INTERVALO_ML = 120000;
const CHAVE_FILTRO = 'dash.filtroEventos';

const $ = (sel) => document.querySelector(sel);
const brl = (n) => (n ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const brlCurto = (n) => (n ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
const hora = (iso) => new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
const dataCurta = (iso) => iso ? new Date(iso.length === 10 ? iso + 'T12:00:00' : iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) : '';
const mesmoDia = (a, b) => a.toDateString() === b.toDateString();

function ha(iso) {
    const min = Math.max(0, Math.round((Date.now() - new Date(iso)) / 60000));
    if (min < 1) return 'agora';
    if (min < 60) return `há ${min} min`;
    const h = Math.floor(min / 60);
    if (h < 24) return `há ${h} h${min % 60 && h < 6 ? ' ' + (min % 60) + ' min' : ''}`;
    const d = Math.floor(h / 24);
    return `há ${d} dia${d > 1 ? 's' : ''}`;
}

function quandoEvento(iso) {
    const d = new Date(iso);
    const ontem = new Date(); ontem.setDate(ontem.getDate() - 1);
    return mesmoDia(d, new Date()) ? hora(iso) : (mesmoDia(d, ontem) ? 'ontem ' : dataCurta(iso) + ' ') + hora(iso);
}

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

const TAG = 'text-xs font-semibold px-1.5 py-0.5 rounded-md whitespace-nowrap';
const COR = {
    ok: 'bg-emerald-900/40 text-emerald-300',
    alerta: 'bg-amber-900/40 text-amber-300',
    perigo: 'bg-red-900/50 text-red-300',
    info: 'bg-blue-900/40 text-blue-300',
    neutro: 'bg-gray-700 text-gray-300',
    roxo: 'bg-purple-900/40 text-purple-300',
};
const vazio = (texto) => el('p', { class: 'text-sm text-gray-500 py-2' }, texto);
const linkExterno = (href, texto, classe = 'text-blue-400 hover:underline') => el('a', { href, target: '_blank', rel: 'noopener', class: classe }, texto);

let banco = null;
let ml = null;
let sinal = null;
let filtro = 'todos';
let eventosVistos = null;
let carregandoBanco = false;
let carregandoMl = false;
let erroBanco = null;
let erroMl = null;

async function api(rota, corpo) {
    const opcoes = { cache: 'no-store' };
    if (corpo) {
        opcoes.method = 'POST';
        opcoes.headers = { 'Content-Type': 'application/json' };
        opcoes.body = JSON.stringify(corpo);
    }
    const r = await fetch('api.php?r=' + rota, opcoes);
    let js = null;
    try { js = await r.json(); } catch (e) { /* resposta sem JSON */ }
    if (!r.ok) throw new Error((js && js.erro) || `Erro ${r.status}`);
    return js;
}

/* ===== Status do topo ===== */
function desenharStatus() {
    const ponto = $('#pontoStatus');
    const partes = [];
    if (banco) partes.push('banco ' + hora(banco.recebido));
    if (ml) partes.push('ML ' + hora(ml.quando));
    let texto = partes.length ? 'Atualizado: ' + partes.join(' · ') : 'Carregando…';
    if (erroBanco || erroMl) texto += ' · ' + (erroBanco ? 'falha no banco: ' + erroBanco : 'falha no ML: ' + erroMl);
    $('#statusTexto').textContent = texto;
    ponto.className = 'inline-block w-2 h-2 rounded-full ' + (erroBanco || erroMl ? 'bg-amber-400' : banco ? 'bg-emerald-400 animate-pulse' : 'bg-gray-500');
}

/* ===== Indicadores ===== */
const TOM_KPI = {
    normal: ['border-gray-700', 'text-gray-100'],
    ok: ['border-emerald-600', 'text-emerald-300'],
    alerta: ['border-amber-600', 'text-amber-300'],
    perigo: ['border-red-600', 'text-red-300'],
};
// Farol dos quatro primeiros cards: verde = nada para fazer, vermelho = algo esperando, amarelo = não deu para consultar
const FAROL = { ok: '🟢', perigo: '🔴', alerta: '🟡' };
function farol(chave, rotulo, valor, sub, tom) {
    kpi(chave, rotulo, `${FAROL[tom]} ${valor}`, sub, tom);
}
function kpi(chave, rotulo, valor, sub, tom = 'normal') {
    const b = document.querySelector(`[data-kpi="${chave}"]`);
    const [borda, cor] = TOM_KPI[tom];
    b.className = `text-left bg-gray-800 border ${borda} rounded-xl px-3 py-2.5 hover:border-gray-400 transition-colors min-w-0`;
    b.querySelector('[data-rotulo]').textContent = rotulo;
    const v = b.querySelector('[data-valor]');
    v.textContent = valor;
    v.className = `block text-2xl font-bold tabular-nums leading-tight truncate ${cor}`;
    b.querySelector('[data-sub]').textContent = sub || ' ';
}

function desenharKpis() {
    if (banco) {
        const { hoje, ontem } = banco.kpis;
        kpi('vendas', 'Vendas hoje', `${hoje.n} · ${brlCurto(hoje.total)}`,
            `ontem até agora: ${ontem.n} · ${brlCurto(ontem.total)}${hoje.full ? ` · ${hoje.full} Full` : ''}`);

        const b = banco.boletos;
        if (!b.length) {
            kpi('boletos', 'Boletos', '0', 'nada nos próximos 15 dias', 'ok');
        } else {
            const pior = ['critico', 'urgente', 'atencao', 'ok'].find((n) => b.some((x) => x.nivel === n));
            const prox = b[0];
            kpi('boletos', 'Boletos (15 dias)', brlCurto(b.reduce((s, x) => s + x.valor, 0)),
                `${b.length} · próximo ${prox.prazo}`, { critico: 'perigo', urgente: 'perigo', atencao: 'alerta', ok: 'normal' }[pior]);
        }

        const f = banco.fila;
        const problema = !banco.saude.ok || f.falhas > 0;
        farol('sistema', 'Sistema', banco.saude.ok ? (f.falhas ? `${f.falhas} falha${f.falhas > 1 ? 's' : ''}` : 'OK') : 'Problema',
            banco.saude.ok ? `fila: ${f.pendentes} pend. · ${f.enviados_24h} env. 24h` : banco.saude.texto, problema ? 'perigo' : 'ok');
    }
    if (ml) {
        const p = ml.perguntas;
        if (p.lista) {
            const antiga = p.lista[0];
            farol('perguntas', 'Perguntas sem resposta', String(p.total),
                antiga ? 'mais antiga ' + ha(antiga.data) : 'tudo respondido', p.total ? 'perigo' : 'ok');
        } else {
            farol('perguntas', 'Perguntas sem resposta', '?', 'falha ao consultar', 'alerta');
        }

        const r = ml.reclamacoes;
        if (r.lista) {
            // O número grande é só o que pede ação sua; as abertas em acompanhamento ficam no texto de baixo
            farol('reclamacoes', 'Reclamações com ação sua', String(r.acao),
                r.total ? `${r.total} aberta${r.total > 1 ? 's' : ''} no total` : 'nenhuma aberta', r.acao ? 'perigo' : 'ok');
        } else {
            farol('reclamacoes', 'Reclamações', '?', 'falha ao consultar', 'alerta');
        }

        const e = ml.etiquetas;
        if (e.lista) {
            const total = e.nf + e.prontas + e.sem_fiscal;
            const partes = [e.nf && `${e.nf} NF`, e.prontas && `${e.prontas} imprimir`, e.sem_fiscal && `${e.sem_fiscal} sem fiscal`].filter(Boolean);
            // Fora do expediente (fim de semana, feriado ou depois das 18h) o card não fica vermelho
            const expediente = !banco || banco.expediente;
            farol('etiquetas', 'Etiquetas', String(total),
                (partes.join(' · ') || 'nada pendente') + (total && !expediente ? ' · fora do expediente' : ''),
                total && expediente ? 'perigo' : 'ok');
        } else {
            farol('etiquetas', 'Etiquetas', '?', 'serviço indisponível', 'alerta');
        }

        const pend = (p.total || 0) + (r.acao || 0);
        document.title = pend ? `(${pend}) Dash` : 'Dash';
    }
}

/* ===== Precisa de você ===== */
function linhaClicavel(conteudo, aoClicar) {
    return el('li', {},
        el('button', { type: 'button', class: 'w-full text-left py-2 px-1 -mx-1 rounded hover:bg-gray-700/50 flex gap-3 items-start', onclick: aoClicar }, conteudo));
}

function avisoErro(id, bloco) {
    $(id).textContent = bloco && bloco.erro ? (bloco.desde ? 'desatualizado desde ' + hora(bloco.desde) : 'falha ao consultar') : '';
    $(id).title = (bloco && bloco.erro) || '';
}

function desenharPrecisa() {
    if (!ml) return;
    $('#mlQuando').textContent = hora(ml.quando);

    const p = ml.perguntas;
    avisoErro('#perguntasErro', p);
    $('#perguntas').replaceChildren(...(p.lista && p.lista.length
        ? p.lista.map((q) => linhaClicavel([
            q.item.foto ? el('img', { src: q.item.foto, alt: '', class: 'w-10 h-10 rounded object-cover bg-white flex-shrink-0' }) : null,
            el('div', { class: 'min-w-0 flex-1' },
                el('div', { class: 'text-sm text-gray-100 line-clamp-2 break-words' }, q.texto),
                el('div', { class: 'text-xs text-gray-400 truncate' }, `${q.item.sku || q.item_id} · ${q.item.titulo}`)),
            el('span', { class: `${TAG} ${(Date.now() - new Date(q.data)) > 3600000 ? COR.perigo : COR.alerta}` }, ha(q.data)),
        ], () => modalPergunta(q)))
        : [vazio(p.lista ? 'Nenhuma pergunta sem resposta. 🎉' : '—')]));

    const r = ml.reclamacoes;
    avisoErro('#reclamacoesErro', r);
    $('#reclamacoes').replaceChildren(...(r.lista && r.lista.length
        ? r.lista.map((c) => linhaClicavel([
            el('div', { class: 'min-w-0 flex-1' },
                el('div', { class: 'text-sm text-gray-100 truncate' }, `${c.tipo}${c.mediacao ? ' em mediação' : ''} · ${(c.itens || []).join(' + ') || 'venda ' + (c.pedido || '')}`),
                el('div', { class: 'text-xs text-gray-400 truncate' }, [c.devolucao, c.motivo].filter(Boolean).join(' · '))),
            c.precisa
                ? el('span', { class: `${TAG} ${COR.perigo}` }, c.prazo ? 'até ' + dataCurta(c.prazo) : 'ação sua')
                : el('span', { class: `${TAG} ${COR.neutro}` }, 'acompanhar'),
        ], () => modalReclamacao(c)))
        : [vazio(r.lista ? 'Nenhuma reclamação aberta.' : '—')]));

    const e = ml.etiquetas;
    avisoErro('#etiquetasErro', e);
    if (!e.lista) {
        $('#etiquetas').replaceChildren(vazio('—'));
    } else if (!e.lista.length) {
        $('#etiquetas').replaceChildren(vazio('Nada aguardando NF ou impressão.'));
    } else {
        $('#etiquetas').replaceChildren(
            el('a', { href: '/etiquetas/', class: 'w-full text-left py-2 px-1 -mx-1 rounded hover:bg-gray-700/50 flex flex-wrap items-center gap-2' },
                e.nf ? el('span', { class: `${TAG} ${COR.alerta}` }, `${e.nf} aguardando NF`) : null,
                e.prontas ? el('span', { class: `${TAG} ${COR.ok}` }, `${e.prontas} pronta${e.prontas > 1 ? 's' : ''} para imprimir`) : null,
                e.sem_fiscal ? el('span', { class: `${TAG} ${COR.perigo}` }, `${e.sem_fiscal} sem dados fiscais`) : null,
                el('span', { class: 'text-xs text-gray-400 ml-auto' }, 'mais antiga ' + ha(e.lista[0].data))),
            el('a', { href: '/etiquetas/', class: 'inline-block mt-1 text-sm text-blue-400 hover:underline' }, 'Abrir a aba Etiquetas →'));
    }
}

/* ===== Eventos ===== */
const FONTES = {
    venda: ['Vendas', 'bg-emerald-400'],
    pergunta: ['Perguntas', 'bg-purple-400'],
    mensagem: ['Mensagens', 'bg-cyan-400'],
    reclamacao: ['Reclamações', 'bg-red-500'],
};
const chaveEvento = (e) => e.fonte + e.quando + (e.pedido || e.pergunta || e.titulo);

function desenharFiltros() {
    const contagem = { todos: banco.eventos.length };
    banco.eventos.forEach((e) => { contagem[e.fonte] = (contagem[e.fonte] || 0) + 1; });
    const opcoes = [['todos', 'Todos'], ...Object.entries(FONTES).map(([k, [nome]]) => [k, nome])];
    $('#filtros').replaceChildren(...opcoes.map(([k, nome]) => el('button', {
        type: 'button', 'aria-pressed': String(filtro === k),
        class: `px-2.5 py-1 rounded-full text-xs font-semibold transition-colors ${filtro === k ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'}`,
        onclick: () => { filtro = k; try { localStorage.setItem(CHAVE_FILTRO, k); } catch (e) { /* sem armazenamento */ } desenharEventos(); },
    }, `${nome} ${contagem[k] || 0}`)));
}

function desenharEventos() {
    desenharFiltros();
    const lista = banco.eventos.filter((e) => filtro === 'todos' || e.fonte === filtro);
    const vistos = new Set(banco.eventos.map(chaveEvento));
    $('#eventos').replaceChildren(...(lista.length ? lista.map((e) => {
        const novo = eventosVistos && !eventosVistos.has(chaveEvento(e));
        const recente = Date.now() - new Date(e.quando) < 180000;
        let texto = e.titulo;
        let direita = null;
        let ponto = FONTES[e.fonte][1];
        if (e.fonte === 'venda') {
            if (e.tipo === 'venda_full') ponto = 'bg-teal-600';
            direita = el('span', { class: `text-xs tabular-nums ${e.cancelado ? 'text-gray-500 line-through' : 'text-gray-200'}` }, brl(e.valor));
        } else if (e.fonte === 'mensagem') {
            texto = `${e.autor}: ${e.titulo}`;
        } else if (e.fonte === 'pergunta') {
            texto = `${e.sku ? e.sku + ': ' : ''}${e.titulo}`;
        }
        return el('li', {},
            el('button', { type: 'button', onclick: () => modalEvento(e), class: `w-full text-left flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-700/50 ${novo ? 'novo' : ''} ${recente ? 'bg-blue-950/40' : ''}` },
                el('span', { class: 'text-xs text-gray-400 tabular-nums w-[4.75rem] flex-shrink-0 whitespace-nowrap' }, quandoEvento(e.quando)),
                el('span', { class: `w-2 h-2 rounded-full flex-shrink-0 ${ponto}`, title: FONTES[e.fonte][0] }),
                e.fonte === 'venda' && e.tipo === 'venda_full' ? el('span', { class: `${TAG} bg-teal-900/50 text-teal-300` }, 'Full') : null,
                e.cancelado ? el('span', { class: `${TAG} ${COR.perigo}` }, 'Cancelada') : null,
                el('span', { class: `text-sm truncate flex-1 ${e.cancelado ? 'text-gray-500' : 'text-gray-200'}` }, texto),
                direita));
    }) : [el('li', {}, vazio('Nenhum evento deste tipo.'))]));
    eventosVistos = vistos;
}

/* ===== Boletos e sistema ===== */
const SELO_BOLETO = {
    critico: 'bg-red-500 text-white animate-pulse',
    urgente: 'bg-orange-500 text-white',
    atencao: 'bg-yellow-500 text-gray-900',
    ok: 'bg-blue-500/15 text-blue-300',
};
function desenharLateral() {
    $('#boletos').replaceChildren(...(banco.boletos.length ? banco.boletos.map((b) => el('li', {},
        el('a', { href: '/boletos/', class: 'flex items-center justify-between gap-2 py-2 hover:bg-gray-700/40 rounded -mx-1 px-1' },
            el('div', { class: 'min-w-0' },
                el('div', { class: 'text-sm text-gray-100 truncate' }, b.descricao),
                el('div', { class: 'flex items-center gap-1.5 mt-0.5' },
                    el('span', { class: `${TAG} ${SELO_BOLETO[b.nivel]}` }, b.prazo),
                    el('span', { class: 'text-xs text-gray-400' }, dataCurta(b.vence) + (b.agendado ? ' · agendado' : ' · não agendado')))),
            el('span', { class: 'text-sm font-semibold text-red-300 tabular-nums whitespace-nowrap' }, brl(b.valor)))))
        : [el('li', {}, vazio('Nenhum boleto nos próximos 15 dias.'))]));

    const s = banco.saude;
    $('#saude').replaceChildren(el('span', { class: s.ok ? 'text-emerald-400' : 'text-red-400' }, '● '), s.texto);
    const f = banco.fila;
    $('#filaResumo').textContent = `Fila: ${f.pendentes} pendente(s) · ${f.processando} processando · ${f.falhas} falha(s) · ${f.enviados_24h} enviados e ${f.pulados_24h} pulados em 24 h`;
    $('#btnFila').classList.toggle('!border-red-600', f.falhas > 0);
}

/* ===== Modal ===== */
const modal = $('#modal');
let aoFecharModal = null;
function abrirModal(titulo, corpo, rodape = [], aoFechar = null) {
    $('#modalTitulo').textContent = titulo;
    $('#modalCorpo').replaceChildren(...[corpo].flat().filter(Boolean));
    $('#modalRodape').replaceChildren(...rodape.filter(Boolean));
    $('#modalRodape').classList.toggle('hidden', !rodape.filter(Boolean).length);
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    aoFecharModal = aoFechar;
}
function fecharModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    if (aoFecharModal) aoFecharModal();
    aoFecharModal = null;
}
modal.addEventListener('click', (ev) => { if (ev.target === modal || ev.target.closest('[data-fechar]')) fecharModal(); });
document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape' && !modal.classList.contains('hidden')) fecharModal(); });

const campo = (rotulo, valor) => valor === null || valor === undefined || valor === '' ? null
    : el('div', { class: 'flex gap-3 py-1 text-sm' }, el('span', { class: 'text-gray-400 w-28 flex-shrink-0' }, rotulo), el('span', { class: 'text-gray-100 min-w-0 break-words' }, valor));
const botaoLink = (href, texto, primario = false) => el('a', { href, target: '_blank', rel: 'noopener', class: (primario ? CLASSE_PRIMARIO : CLASSE_BOTAO) + ' text-sm' }, texto);

function cartaoAnuncio(item, itemId) {
    return el('div', { class: 'flex gap-3 items-start bg-gray-900/60 border border-gray-700 rounded-xl p-3' },
        item.foto ? el('img', { src: item.foto, alt: '', class: 'w-16 h-16 rounded-lg object-cover bg-white flex-shrink-0' }) : null,
        el('div', { class: 'min-w-0 text-sm' },
            el('div', { class: 'font-semibold text-gray-100 break-words' }, item.titulo),
            el('div', { class: 'text-gray-400 mt-0.5' }, [item.sku, itemId].filter(Boolean).join(' · ')),
            el('div', { class: 'flex flex-wrap gap-1.5 mt-1.5' },
                item.preco !== null ? el('span', { class: `${TAG} ${COR.neutro}` }, brl(item.preco)) : null,
                item.estoque !== null ? el('span', { class: `${TAG} ${item.estoque > 0 ? COR.ok : COR.perigo}` }, `${item.estoque} em estoque`) : null,
                item.full ? el('span', { class: `${TAG} bg-teal-900/50 text-teal-300` }, 'Full') : null,
                item.status && item.status !== 'active' ? el('span', { class: `${TAG} ${COR.alerta}` }, item.status) : null,
                item.link ? linkExterno(item.link, 'ver anúncio ↗', 'text-xs text-blue-400 hover:underline self-center') : null)));
}

function modalPergunta(q) {
    const texto = el('textarea', { rows: '4', maxlength: '2000', placeholder: 'Sua resposta…', class: 'w-full bg-gray-900 border border-gray-700 focus:border-blue-500 outline-none rounded-lg p-3 text-sm text-gray-100 resize-y' });
    const contador = el('span', { class: 'text-xs text-gray-500' }, '0/2000');
    const erro = el('p', { class: 'text-sm text-red-400 hidden' });
    const enviar = el('button', { type: 'button', class: CLASSE_PRIMARIO + ' text-sm' }, 'Responder');
    texto.addEventListener('input', () => { contador.textContent = `${texto.value.length}/2000`; erro.classList.add('hidden'); });

    async function responder() {
        const resposta = texto.value.trim();
        if (!resposta) {
            erro.textContent = 'Escreva a resposta primeiro.';
            erro.classList.remove('hidden');
            texto.focus();
            return;
        }
        enviar.disabled = true;
        enviar.textContent = 'Enviando…';
        try {
            await api('responder', { pergunta: q.id, texto: resposta });
            ml.perguntas.lista = ml.perguntas.lista.filter((x) => x.id !== q.id);
            ml.perguntas.total = Math.max(0, ml.perguntas.total - 1);
            desenharPrecisa();
            desenharKpis();
            fecharModal();
            carregarMl(true);
        } catch (e) {
            erro.textContent = 'Não foi possível responder: ' + e.message;
            erro.classList.remove('hidden');
            enviar.disabled = false;
            enviar.textContent = 'Responder';
        }
    }
    enviar.addEventListener('click', responder);
    texto.addEventListener('keydown', (ev) => { if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) responder(); });

    abrirModal('Pergunta ' + ha(q.data), [
        cartaoAnuncio(q.item, q.item_id),
        el('blockquote', { class: 'mt-4 border-l-4 border-purple-500 pl-3 text-gray-100 whitespace-pre-wrap break-words' }, q.texto),
        el('p', { class: 'text-xs text-gray-500 mt-1' }, `feita em ${new Date(q.data).toLocaleString('pt-BR')}`),
        el('div', { class: 'mt-4' }, texto, el('div', { class: 'flex justify-between items-center mt-1' }, erro, el('span', { class: 'ml-auto' }, contador))),
        el('p', { class: 'text-xs text-gray-500 mt-1' }, 'Ctrl+Enter envia. A resposta vai direto para o anúncio no Mercado Livre.'),
    ], [
        botaoLink('https://www.mercadolivre.com.br/perguntas/vendedor?status=unanswered', 'Abrir no ML ↗'),
        enviar,
    ]);
    setTimeout(() => texto.focus(), 50);
}

function modalReclamacao(c) {
    abrirModal(`${c.tipo}${c.mediacao ? ' em mediação' : ''} · venda ${c.pedido || ''}`, [
        c.precisa ? el('p', { class: `${COR.perigo} rounded-lg px-3 py-2 text-sm mb-3` },
            c.mediacao ? 'Em mediação com o Mercado Livre.' : 'O Mercado Livre espera uma ação sua' + (c.prazo ? ` até ${new Date(c.prazo).toLocaleString('pt-BR')}.` : '.')) : null,
        campo('Motivo', c.motivo),
        campo('Itens', (c.itens || []).join(' + ')),
        campo('Comprador', c.comprador),
        campo('Total', c.total !== null && c.total !== undefined ? brl(c.total) : null),
        campo('Devolução', c.devolucao),
        campo('Aberta', c.data ? `${new Date(c.data).toLocaleString('pt-BR')} (${ha(c.data)})` : null),
        campo('Atualizada', c.atualizada ? ha(c.atualizada) : null),
        campo('Ações possíveis', (c.acoes || []).join(' · ') || 'nenhuma'),
        campo('Reclamação', String(c.id)),
    ], [
        botaoLink('https://www.mercadolivre.com.br/post-purchase/post-sales?main.filter=problems-to-manage', 'Reclamações no ML ↗'),
        botaoLink(c.link, 'Abrir a venda ↗', true),
    ]);
}

function modalEvento(e) {
    if (e.fonte === 'pergunta' && ml && ml.perguntas.lista) {
        const aberta = ml.perguntas.lista.find((q) => q.id === e.pergunta);
        if (aberta) return modalPergunta(aberta);
    }
    const quando = campo('Quando', `${new Date(e.quando).toLocaleString('pt-BR')} (${ha(e.quando)})`);
    if (e.fonte === 'venda') {
        abrirModal(e.tipo === 'venda_full' ? 'Venda Full' : 'Venda', [
            e.cancelado ? el('p', { class: `${COR.perigo} rounded-lg px-3 py-2 text-sm mb-3` }, 'Venda cancelada.') : null,
            campo('Itens', e.titulo), campo('Total', brl(e.valor)), campo('Venda', e.pedido), quando,
        ], [botaoLink(e.link, 'Abrir a venda ↗', true)]);
    } else if (e.fonte === 'mensagem') {
        abrirModal('Mensagem de ' + e.autor, [
            el('blockquote', { class: 'border-l-4 border-cyan-500 pl-3 text-gray-100 whitespace-pre-wrap break-words mb-3' }, e.titulo),
            campo('Venda', e.pedido), quando,
        ], [botaoLink(e.link, 'Responder no ML ↗', true)]);
    } else if (e.fonte === 'pergunta') {
        abrirModal('Pergunta' + (e.sku ? ' · ' + e.sku : ''), [
            el('blockquote', { class: 'border-l-4 border-purple-500 pl-3 text-gray-100 whitespace-pre-wrap break-words mb-3' }, e.titulo),
            el('p', { class: `${COR.ok} rounded-lg px-3 py-2 text-sm mb-3` }, 'Já respondida (ou não está mais entre as sem resposta).'),
            campo('Anúncio', e.item_id), quando,
        ], [botaoLink('https://www.mercadolivre.com.br/perguntas/vendedor', 'Perguntas no ML ↗', true)]);
    } else {
        const aberta = ml && ml.reclamacoes.lista && ml.reclamacoes.lista.find((c) => c.pedido === String(e.pedido));
        if (aberta) return modalReclamacao(aberta);
        abrirModal('Reclamação · venda ' + e.pedido, [campo('Detalhe', e.titulo), quando],
            [botaoLink(e.link, 'Abrir a venda ↗', true)]);
    }
}

/* ===== Fila e logs ===== */
async function modalFila(aviso = '') {
    const corpo = el('div', {}, vazio('Carregando…'));
    abrirModal('Fila do webhook (ml-jrf)', corpo);
    try {
        const d = await api('fila');
        const r = d.resumo;
        const atencao = el('div', { class: 'overflow-x-auto' });
        atencao.innerHTML = d.atencao; // HTML escapado em src/dash/fila.php
        const recentes = el('div', { class: 'overflow-x-auto' });
        recentes.innerHTML = d.recentes;
        atencao.addEventListener('click', (ev) => {
            const b = ev.target.closest('[data-acao]');
            if (!b) return;
            const id = Number(b.dataset.id);
            if (b.dataset.acao === 'reprocessar') agirFila('reprocessar', { id });
            else agirFila('descartar', { id }, `Descartar a notificação #${id}? O alerta não será enviado.`);
        });
        corpo.replaceChildren(...[
            aviso ? el('p', { class: `${COR.info} rounded-lg px-3 py-2 text-sm mb-3` }, aviso) : null,
            el('p', { class: 'text-sm mb-3' }, el('span', { class: d.saude.ok ? 'text-emerald-400' : 'text-red-400' }, '● '), d.saude.texto),
            el('div', { class: 'flex flex-wrap gap-2 mb-4' },
                el('span', { class: `${TAG} ${COR.info}` }, `${r.pendentes} pendentes`),
                el('span', { class: `${TAG} ${COR.roxo}` }, `${r.processando} processando`),
                el('span', { class: `${TAG} ${r.falhas ? COR.perigo : COR.neutro}` }, `${r.falhas} falhas`),
                el('span', { class: `${TAG} ${COR.ok}` }, `${r.enviados_24h} enviados (24h)`),
                el('span', { class: `${TAG} ${COR.neutro}` }, `${r.pulados_24h} pulados (24h)`)),
            el('div', { class: 'flex items-center justify-between gap-2 mb-1' },
                el('h3', { class: 'font-semibold text-red-400' }, 'Precisam de atenção'),
                r.falhas ? el('button', { type: 'button', class: 'px-2 py-1 rounded text-xs font-semibold bg-blue-600 hover:bg-blue-500 text-white', onclick: () => agirFila('reprocessar', { todas: true }, 'Reprocessar todas as falhas?') }, 'Reprocessar todas') : null),
            el('p', { class: 'text-xs text-gray-500 mb-2' }, '"Reprocessar" zera as tentativas; "Descartar" marca como pulado sem enviar.'),
            atencao,
            el('h3', { class: 'font-semibold text-blue-400 mt-5 mb-2' }, 'Últimas 50 notificações'),
            recentes].filter(Boolean));
    } catch (e) {
        corpo.replaceChildren(vazio('Falha ao carregar a fila: ' + e.message));
    }
}

async function agirFila(acao, corpo, confirmacao) {
    if (confirmacao && !confirm(confirmacao)) return;
    let aviso;
    try {
        const r = await api(acao, corpo);
        aviso = r.alterados ? `${r.alterados} item(ns) atualizado(s).` : 'Nada mudou (o item já saiu desse estado).';
    } catch (e) {
        aviso = 'Erro: ' + e.message;
    }
    await modalFila(aviso);
    carregarBanco();
}

async function modalLogs() {
    const pre = el('pre', { class: 'whitespace-pre-wrap break-all font-mono text-xs text-gray-300' }, 'Carregando…');
    const caixa = el('div', { class: 'bg-gray-900 rounded-lg p-3 h-[65vh] overflow-y-auto rolagem' }, pre);
    const carregar = async () => {
        try {
            pre.innerHTML = (await api('logs')).logs; // escapado em src/dash/logs.php
        } catch (e) {
            pre.textContent = 'Falha ao carregar os logs: ' + e.message;
        }
        caixa.scrollTop = caixa.scrollHeight;
    };
    abrirModal('Logs do ml-jrf (últimas 300 linhas)', caixa,
        [el('button', { type: 'button', class: CLASSE_BOTAO + ' text-sm', onclick: carregar }, '↻ Atualizar')]);
    $('#modal [role=dialog]').classList.replace('max-w-2xl', 'max-w-5xl');
    aoFecharModal = () => $('#modal [role=dialog]').classList.replace('max-w-5xl', 'max-w-2xl');
    carregar();
}

/* ===== Carga ===== */
async function carregarBanco() {
    if (carregandoBanco) return;
    carregandoBanco = true;
    try {
        banco = await api('banco');
        banco.recebido = new Date().toISOString();
        erroBanco = null;
        desenharKpis();
        desenharEventos();
        desenharLateral();
        if (sinal !== null && banco.sinal !== sinal) carregarMl(true); // pergunta, reclamação ou venda nova
        sinal = banco.sinal;
    } catch (e) {
        console.error(e);
        erroBanco = e.message;
    } finally {
        carregandoBanco = false;
        desenharStatus();
    }
}

async function carregarMl(forcar = false) {
    if (carregandoMl) return;
    carregandoMl = true;
    $('#btnAtualizarMl').classList.add('animate-pulse');
    try {
        ml = await api('ml' + (forcar ? '&forcar=1' : ''));
        erroMl = null;
        desenharKpis();
        desenharPrecisa();
    } catch (e) {
        console.error(e);
        erroMl = e.message;
    } finally {
        carregandoMl = false;
        $('#btnAtualizarMl').classList.remove('animate-pulse');
        desenharStatus();
    }
}

const rolarPara = (sel) => () => {
    const alvo = $(sel);
    alvo.scrollIntoView({ behavior: 'smooth', block: 'center' });
    alvo.classList.add('novo');
    setTimeout(() => alvo.classList.remove('novo'), 3000);
};
const ACOES_KPI = {
    vendas: () => { filtro = 'venda'; desenharEventos(); },
    etiquetas: () => { location.href = '/etiquetas/'; },
    perguntas: () => ml && ml.perguntas.lista && ml.perguntas.lista.length ? modalPergunta(ml.perguntas.lista[0]) : rolarPara('#perguntas')(),
    reclamacoes: rolarPara('#reclamacoes'),
    boletos: () => { location.href = '/boletos/'; },
    sistema: () => modalFila(),
};
document.querySelectorAll('[data-kpi]').forEach((b) => b.addEventListener('click', () => ACOES_KPI[b.dataset.kpi]()));
$('#btnAtualizarMl').addEventListener('click', () => carregarMl(true));
$('#btnFila').addEventListener('click', () => modalFila());
$('#btnLogs').addEventListener('click', modalLogs);

try { filtro = localStorage.getItem(CHAVE_FILTRO) || 'todos'; } catch (e) { /* sem armazenamento */ }
if (filtro !== 'todos' && !FONTES[filtro]) filtro = 'todos';

carregarBanco();
carregarMl();
setInterval(carregarBanco, INTERVALO_BANCO);
setInterval(() => carregarMl(), INTERVALO_ML);
