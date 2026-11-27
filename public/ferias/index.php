<?php
require __DIR__ . '/../../src/auth.php';
$classeCampo = 'bg-gray-900 border border-gray-700 rounded-lg p-2 text-base sm:text-sm text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500';
$classeCartao = 'bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 mb-6';
$classeBotao = 'bg-gray-900 hover:bg-gray-700 border border-gray-700 hover:border-blue-500 text-gray-300 hover:text-blue-400 rounded-lg px-3 py-2 text-sm transition-colors disabled:opacity-60 disabled:cursor-wait';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Férias</title>
    <script src="/assets/tailwind.js"></script>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen p-4 sm:p-8 font-sans selection:bg-blue-500 selection:text-white">
    <?php require __DIR__ . '/../../src/nav.php'; ?>
    <div class="max-w-5xl mx-auto">

        <header class="mb-6 flex flex-wrap justify-between items-end gap-4 border-b border-gray-700 pb-4">
            <h1 class="text-4xl font-bold">
                <span aria-hidden="true">🏖️</span>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400">Férias</span>
            </h1>
            <span id="statusTopo" class="text-sm text-gray-400"></span>
        </header>

        <p class="text-xs text-gray-500 mb-6">Em vez de pausar os anúncios (modo férias do ML), coloca prazo de disponibilidade em todos os anúncios ativos e pausados que não são Full, com contagem regressiva: no dia do início o prazo é o total de dias até o dia final e cai 1 a cada meia-noite; no dia final o prazo é 1. No dia seguinte (a volta) o prazo sai e as férias terminam. O prazo vale para o produto: anúncios ligados (Clássico e Premium) mudam juntos. Produtos ligados a um anúncio Full ou com Flex ficam de fora. Se um produto já tinha prazo maior, ele é mantido e volta ao original no fim. Anúncios novos entram na rodada do dia seguinte.</p>

        <!-- Sem férias em andamento -->
        <section id="cartaoNovo" class="<?= $classeCartao ?> hidden">
            <h2 class="text-lg font-semibold text-gray-200 mb-4">Programar férias</h2>
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <label class="text-sm text-gray-400 flex flex-col gap-1">Início
                    <input id="inicio" type="date" class="<?= $classeCampo ?> w-44" oninput="mostrarPrevia()">
                </label>
                <label class="text-sm text-gray-400 flex flex-col gap-1" title="Último dia fora (prazo 1). O prazo sai no dia seguinte. Até 60 dias contando o início (máximo do ML).">Dia final
                    <input id="fim" type="date" class="<?= $classeCampo ?> w-44" oninput="mostrarPrevia()">
                </label>
                <button id="btnAtivar" onclick="ativar()"
                        class="bg-blue-600 hover:bg-blue-500 disabled:opacity-60 disabled:cursor-wait text-white font-semibold py-2 px-4 rounded-lg transition-colors shadow-lg whitespace-nowrap">🏖️ Ativar férias</button>
            </div>
            <p id="previaTexto" class="text-sm text-gray-300 mb-3"></p>
            <div id="previa" class="flex flex-wrap gap-1.5"></div>
        </section>

        <!-- Férias agendadas ou ativas -->
        <section id="cartaoAtual" class="<?= $classeCartao ?> hidden">
            <div class="flex flex-wrap justify-between items-start gap-4 mb-4">
                <div>
                    <div id="atualSelo" class="inline-block text-xs font-semibold px-2 py-1 rounded-md mb-2"></div>
                    <div id="atualTitulo" class="text-2xl font-bold text-gray-100"></div>
                    <div id="atualDetalhe" class="text-sm text-gray-400 mt-1"></div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button id="btnAlterar" onclick="alterar()" class="<?= $classeBotao ?>">✏️ Alterar dia final</button>
                    <button id="btnRodar" onclick="rodar()" class="<?= $classeBotao ?>" title="Confere e grava o prazo de hoje de novo (ex.: depois de falhas)">🔁 Rodar de novo</button>
                    <button id="btnEncerrar" onclick="encerrar()" class="bg-gray-900 hover:bg-red-900/40 border border-gray-700 hover:border-red-500 text-gray-300 hover:text-red-300 rounded-lg px-3 py-2 text-sm transition-colors disabled:opacity-60 disabled:cursor-wait">⏹️ Encerrar agora</button>
                </div>
            </div>
            <form id="formFim" class="hidden flex flex-wrap items-end gap-3 mb-4 p-3 bg-gray-900/60 border border-gray-700 rounded-lg" onsubmit="salvarFim(event)">
                <label class="text-sm text-gray-400 flex flex-col gap-1">Novo dia final
                    <input id="novoFim" type="date" class="<?= $classeCampo ?> w-44" oninput="mostrarNovoFim()">
                </label>
                <button id="btnSalvarFim" type="submit" class="bg-blue-600 hover:bg-blue-500 disabled:opacity-60 disabled:cursor-wait text-white font-semibold py-2 px-4 rounded-lg transition-colors">Salvar</button>
                <button type="button" onclick="fecharFim()" class="<?= $classeBotao ?>">Cancelar</button>
                <p id="novoFimTexto" class="text-xs text-gray-400 w-full"></p>
            </form>
            <div id="progresso" class="hidden mb-4">
                <div class="flex justify-between text-xs text-gray-400 mb-1"><span id="progressoTexto"></span><span id="progressoNumero"></span></div>
                <div class="h-2 bg-gray-900 rounded-full overflow-hidden"><div id="progressoBarra" class="h-full bg-blue-500 transition-all" style="width:0%"></div></div>
            </div>
            <div id="contagem" class="flex flex-wrap gap-1.5"></div>
        </section>

        <section id="cartaoErros" class="<?= $classeCartao ?> hidden">
            <h2 class="text-lg font-semibold text-red-300 mb-1">Produtos com falha</h2>
            <p class="text-xs text-gray-500 mb-4">A rodada automática tenta de novo de hora em hora (até 4 rodadas por dia).</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-gray-400 border-b border-gray-700">
                        <tr>
                            <th class="text-left font-medium py-2 pr-3">Anúncios</th>
                            <th class="text-right font-medium py-2 px-3 whitespace-nowrap">No ML</th>
                            <th class="text-left font-medium py-2 pl-3">Erro</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaErros"></tbody>
                </table>
            </div>
        </section>

        <section id="cartaoRodadas" class="<?= $classeCartao ?> hidden">
            <h2 class="text-lg font-semibold text-gray-200 mb-4">Rodadas</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-gray-400 border-b border-gray-700">
                        <tr>
                            <th class="text-left font-medium py-2 pr-3">Quando</th>
                            <th class="text-right font-medium py-2 px-2 sm:px-3">Prazo</th>
                            <th class="text-right font-medium py-2 px-2 sm:px-3" title="Produtos com o prazo gravado nesta rodada">Gravados</th>
                            <th class="text-right font-medium py-2 px-2 sm:px-3 hidden sm:table-cell" title="Já estavam com o prazo certo">Iguais</th>
                            <th class="text-right font-medium py-2 px-2 sm:px-3 hidden sm:table-cell" title="Ligados a Full ou Flex">Fora</th>
                            <th class="text-right font-medium py-2 pl-2 sm:pl-3">Falhas</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaRodadas"></tbody>
                </table>
            </div>
        </section>
    </div>

<script src="/assets/ui.js?v=<?= filemtime(__DIR__ . '/../assets/ui.js') ?>"></script>
<script>
let estado = null;
let timer = null;
let rapidoAte = 0; // depois de disparar uma rodada, consulta rápido até ela aparecer

const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
const doisDig = n => String(n).padStart(2, '0');
const paraTexto = d => `${d.getFullYear()}-${doisDig(d.getMonth() + 1)}-${doisDig(d.getDate())}`;
const paraData = t => { const [a, m, d] = t.split('-').map(Number); return new Date(a, m - 1, d); };
const somarDias = (t, n) => { const d = paraData(t); d.setDate(d.getDate() + n); return paraTexto(d); };
const diasEntre = (a, b) => Math.round((paraData(b) - paraData(a)) / 86400000);
const DIAS_SEMANA = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
const dataCurta = t => { const d = paraData(t); return `${doisDig(d.getDate())}/${doisDig(d.getMonth() + 1)}`; };
const dataSemana = t => `${DIAS_SEMANA[paraData(t).getDay()]} ${dataCurta(t)}`;
const plural = (n, um, varios) => `${n} ${n === 1 ? um : varios}`;

// Prazo de cada dia, do início até a volta (prazo 0 = sai)
function calendario(inicio, dias) {
    return Array.from({ length: dias + 1 }, (_, i) => ({ dia: somarDias(inicio, i), prazo: dias - i }));
}

function chips(alvo, inicio, dias, hoje) {
    alvo.innerHTML = calendario(inicio, dias).map(({ dia, prazo }) => {
        const atual = dia === hoje;
        const passado = dia < hoje;
        const cor = prazo === 0 ? 'border-emerald-500/50 text-emerald-300'
            : atual ? 'border-blue-500 bg-blue-600/30 text-white' : passado ? 'border-gray-700 text-gray-600' : 'border-gray-700 text-gray-300';
        return `<div class="border rounded-lg px-2 py-1 text-center min-w-[4.5rem] ${cor}">
            <div class="text-[11px] ${atual ? 'text-blue-200' : 'text-gray-500'}">${dataSemana(dia)}</div>
            <div class="font-semibold">${prazo === 0 ? 'volta' : plural(prazo, 'dia', 'dias')}</div>
        </div>`;
    }).join('');
}

// Dias de prazo no início: do início ao dia final, contando os dois (no dia final o prazo é 1)
const diasAte = (inicio, fim) => diasEntre(inicio, fim) + 1;

function mostrarPrevia() {
    const inicio = document.getElementById('inicio').value;
    const fim = document.getElementById('fim').value;
    const texto = document.getElementById('previaTexto');
    const previa = document.getElementById('previa');
    previa.innerHTML = '';
    if (!inicio || inicio < estado.hoje) { texto.textContent = 'Escolha um início de hoje em diante.'; return; }
    if (!fim || fim < inicio) { texto.textContent = 'Escolha o dia final (o último dia fora), igual ou depois do início.'; return; }
    const dias = diasAte(inicio, fim);
    if (dias > estado.maxDias) { texto.textContent = `No máximo ${estado.maxDias} dias contando o início (limite do ML): dia final até ${dataSemana(somarDias(inicio, estado.maxDias - 1))}.`; return; }
    texto.textContent = `${inicio === estado.hoje ? 'Começa agora' : 'Começa ' + dataSemana(inicio) + ' à meia-noite'} com ${plural(dias, 'dia', 'dias')} de prazo, até ${dataSemana(fim)}; o prazo sai ${dataSemana(somarDias(fim, 1))}.`;
    chips(previa, inicio, dias, estado.hoje);
}

function mostrarNovoFim() {
    const f = estado.ferias;
    const fim = document.getElementById('novoFim').value;
    const texto = document.getElementById('novoFimTexto');
    const minimo = f.status === 'agendada' ? f.inicio : estado.hoje;
    if (!fim || fim < minimo) { texto.textContent = `Escolha um dia de ${dataSemana(minimo)} em diante. Para tirar o prazo hoje, use Encerrar agora.`; return null; }
    const dias = diasAte(f.inicio, fim);
    if (dias > estado.maxDias) { texto.textContent = `No máximo ${estado.maxDias} dias contando o início: dia final até ${dataSemana(somarDias(f.inicio, estado.maxDias - 1))}.`; return null; }
    const hoje = f.status === 'agendada' ? null : dias - diasEntre(f.inicio, estado.hoje);
    texto.textContent = `${plural(dias, 'dia', 'dias')} a partir de ${dataSemana(f.inicio)}; o prazo sai ${dataSemana(somarDias(fim, 1))}.` + (hoje ? ` Prazo de hoje passa a ${plural(hoje, 'dia', 'dias')}.` : '');
    return fim;
}

function renderizar() {
    const { ferias: f, hoje, rodadas, erros, resumo } = estado;
    const emAndamento = f && ['agendada', 'ativa', 'encerrando'].includes(f.status);
    document.getElementById('cartaoNovo').classList.toggle('hidden', emAndamento);
    document.getElementById('cartaoAtual').classList.toggle('hidden', !emAndamento);

    const ultima = rodadas[0];
    const rodando = ultima && !ultima.fim;
    const topo = document.getElementById('statusTopo');
    topo.textContent = ultima ? `Última rodada: ${dataCurta(ultima.inicio.slice(0, 10))} às ${ultima.inicio.slice(11, 16)}` : '';

    if (!emAndamento) {
        const entrada = document.getElementById('inicio');
        entrada.min = hoje;
        if (!entrada.value || entrada.value < hoje) entrada.value = hoje;
        const fim = document.getElementById('fim');
        fim.min = entrada.value;
        if (!fim.value) fim.value = somarDias(entrada.value, 9);
        mostrarPrevia();
        if (f) topo.textContent = `Últimas férias: ${dataCurta(f.inicio)}, ${plural(+f.dias, 'dia', 'dias')} (${f.status})` + (ultima ? ` · ${topo.textContent}` : '');
    } else {
        const dias = +f.dias;
        const volta = somarDias(f.inicio, dias);
        const prazoHoje = f.status === 'encerrando' ? 0 : Math.max(0, dias - diasEntre(f.inicio, hoje));
        const selo = document.getElementById('atualSelo');
        const SELOS = {
            agendada: ['Agendada', 'bg-amber-900/60 text-amber-200'],
            ativa: ['Ativa', 'bg-blue-900/60 text-blue-200'],
            encerrando: ['Encerrando', 'bg-emerald-900/60 text-emerald-200'],
        };
        selo.textContent = SELOS[f.status][0];
        selo.className = `inline-block text-xs font-semibold px-2 py-1 rounded-md mb-2 ${SELOS[f.status][1]}`;
        document.getElementById('atualTitulo').textContent = f.status === 'agendada'
            ? `Começa ${dataSemana(f.inicio)} com ${plural(dias, 'dia', 'dias')}`
            : f.status === 'encerrando' ? 'Tirando o prazo dos anúncios' : `Prazo de hoje: ${plural(prazoHoje, 'dia', 'dias')}`;
        const detalhe = [`Início ${dataSemana(f.inicio)}`, `dia final ${dataSemana(somarDias(volta, -1))}`, `prazo sai ${dataSemana(volta)}`];
        if (resumo && resumo.produtos) detalhe.push(`${plural(resumo.com_prazo, 'produto', 'produtos')} com prazo agora`);
        document.getElementById('atualDetalhe').textContent = detalhe.join(' · ');
        chips(document.getElementById('contagem'), f.inicio, dias, hoje);

        document.getElementById('btnAlterar').classList.toggle('hidden', f.status === 'encerrando');
        if (f.status === 'encerrando') fecharFim();
        document.getElementById('btnRodar').classList.toggle('hidden', f.status === 'agendada');
        document.getElementById('btnEncerrar').textContent = f.status === 'agendada' ? '✖️ Cancelar' : '⏹️ Encerrar agora';
        ['btnAlterar', 'btnRodar', 'btnEncerrar'].forEach(id => { document.getElementById(id).disabled = rodando && id === 'btnRodar'; });

        const prog = document.getElementById('progresso');
        prog.classList.toggle('hidden', !rodando);
        if (rodando) {
            const pct = ultima.total ? Math.round(100 * ultima.feitos / ultima.total) : 0;
            document.getElementById('progressoTexto').textContent = ultima.total
                ? `${+ultima.prazo === 0 ? 'Tirando o prazo' : 'Gravando ' + plural(+ultima.prazo, 'dia', 'dias')}… ${ultima.atualizados} gravados, ${ultima.falhas} falhas`
                : 'Lendo os anúncios no ML…';
            document.getElementById('progressoNumero').textContent = ultima.total ? `${ultima.feitos}/${ultima.total}` : '';
            document.getElementById('progressoBarra').style.width = pct + '%';
        }
    }

    document.getElementById('cartaoErros').classList.toggle('hidden', !erros.length);
    document.getElementById('tabelaErros').innerHTML = erros.map(p => `<tr class="border-b border-gray-700/60 align-top">
        <td class="py-2 pr-3"><div class="text-gray-200">${p.anuncios.map(a => `<a class="hover:text-blue-400" target="_blank" rel="noopener" href="https://produto.mercadolivre.com.br/MLB-${esc(a.replace(/^MLB/, ''))}">${esc(a)}</a>`).join(', ')}</div>
            <div class="text-xs text-gray-500">${esc(p.titulo)}</div></td>
        <td class="py-2 px-3 text-right whitespace-nowrap">${p.atual === null ? '—' : plural(+p.atual, 'dia', 'dias')}</td>
        <td class="py-2 pl-3 text-red-300 text-xs">${esc(p.erro)}</td></tr>`).join('');

    document.getElementById('cartaoRodadas').classList.toggle('hidden', !rodadas.length);
    document.getElementById('tabelaRodadas').innerHTML = rodadas.map(r => `<tr class="border-b border-gray-700/60" title="${esc(r.erro || '')}">
        <td class="py-2 pr-3 whitespace-nowrap">${dataSemana(r.inicio.slice(0, 10))} <span class="text-gray-500">${r.inicio.slice(11, 16)}</span>${!r.fim ? ' <span class="text-blue-400">rodando</span>' : r.erro ? ` <span class="text-red-400">⚠ ${esc(r.erro.slice(0, 60))}</span>` : ''}</td>
        <td class="py-2 px-2 sm:px-3 text-right">${+r.prazo === 0 ? 'tirar' : plural(+r.prazo, 'dia', 'dias')}</td>
        <td class="py-2 px-2 sm:px-3 text-right">${r.atualizados}</td>
        <td class="py-2 px-2 sm:px-3 text-right hidden sm:table-cell text-gray-400">${r.iguais}</td>
        <td class="py-2 px-2 sm:px-3 text-right hidden sm:table-cell text-gray-400">${r.pulados}</td>
        <td class="py-2 pl-2 sm:pl-3 text-right ${+r.falhas ? 'text-red-400 font-semibold' : 'text-gray-400'}">${r.falhas}</td></tr>`).join('');

    agendar(rodando || Date.now() < rapidoAte ? 3000 : 60000);
}

function agendar(ms) {
    clearTimeout(timer);
    timer = setTimeout(carregar, ms);
}

async function carregar() {
    try {
        const r = await fetch('api.php?action=estado');
        const dados = await r.json();
        if (!r.ok) throw new Error(dados.error || 'Falha ao carregar');
        estado = dados;
        renderizar();
    } catch (e) {
        ML.toast(e.message, 'erro');
        agendar(60000);
    }
}

async function enviar(acao, campos = {}) {
    const r = await fetch('api.php?action=' + acao, { method: 'POST', body: new URLSearchParams(campos) });
    const resposta = await r.json();
    if (!r.ok) throw new Error(resposta.error || 'Falha');
    if (resposta.rodando) rapidoAte = Date.now() + 20000;
    return resposta;
}

async function comBotao(id, tarefa) {
    const botao = document.getElementById(id);
    botao.disabled = true;
    try {
        await tarefa();
    } catch (e) {
        ML.toast(e.message, 'erro');
    } finally {
        botao.disabled = false;
        await carregar();
    }
}

function ativar() {
    const inicio = document.getElementById('inicio').value;
    const fim = document.getElementById('fim').value;
    const dias = inicio && fim ? diasAte(inicio, fim) : 0;
    if (!inicio || inicio < estado.hoje || dias < 1 || dias > estado.maxDias) { ML.toast('Confira o início e o dia final.', 'aviso'); return; }
    comBotao('btnAtivar', async () => {
        const agora = inicio === estado.hoje;
        const ok = await ML.confirmar(
            `${agora ? 'Agora' : 'Em ' + dataSemana(inicio)} todos os anúncios que não são Full recebem ${plural(dias, 'dia', 'dias')} de prazo de disponibilidade, caindo 1 por dia até 1 em ${dataSemana(fim)}; o prazo sai ${dataSemana(somarDias(fim, 1))}. Os anúncios continuam ativos.`,
            { titulo: 'Ativar férias', textoConfirmar: agora ? 'Ativar agora' : 'Agendar' });
        if (!ok) return;
        await enviar('ativar', { inicio, fim });
        ML.toast(agora ? 'Férias ativadas: gravando o prazo nos anúncios.' : 'Férias agendadas.', 'sucesso');
    });
}

function alterar() {
    const f = estado.ferias;
    const form = document.getElementById('formFim');
    const campo = document.getElementById('novoFim');
    campo.min = f.status === 'agendada' ? f.inicio : estado.hoje;
    campo.max = somarDias(f.inicio, estado.maxDias - 1);
    campo.value = somarDias(f.inicio, +f.dias - 1);
    form.classList.remove('hidden');
    mostrarNovoFim();
    campo.focus();
}

function fecharFim() {
    document.getElementById('formFim').classList.add('hidden');
}

function salvarFim(e) {
    e.preventDefault();
    const fim = mostrarNovoFim();
    if (!fim) return;
    comBotao('btnSalvarFim', async () => {
        const r = await enviar('alterar', { fim });
        fecharFim();
        ML.toast(r.rodando ? 'Dia final alterado: gravando o prazo novo.' : 'Dia final alterado.', 'sucesso');
    });
}

function rodar() {
    comBotao('btnRodar', async () => {
        await enviar('rodar');
        ML.toast('Rodada iniciada.', 'info');
    });
}

function encerrar() {
    comBotao('btnEncerrar', async () => {
        const agendada = estado.ferias.status === 'agendada';
        const ok = await ML.confirmar(agendada
            ? 'Cancelar as férias agendadas? Nenhum anúncio foi mexido ainda.'
            : 'Tirar o prazo de todos os anúncios agora? Produtos que já tinham prazo antes das férias voltam ao prazo original.',
            { titulo: agendada ? 'Cancelar férias' : 'Encerrar férias', textoConfirmar: agendada ? 'Cancelar férias' : 'Encerrar agora', perigo: true });
        if (!ok) return;
        await enviar('encerrar');
        ML.toast(agendada ? 'Férias canceladas.' : 'Encerrando: tirando o prazo dos anúncios.', 'sucesso');
    });
}

document.addEventListener('visibilitychange', () => { if (!document.hidden) carregar(); });
carregar();
</script>
</body>
</html>
