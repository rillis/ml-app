<?php
require __DIR__ . '/../../src/auth.php';
$classeCampo = 'bg-gray-900 border border-gray-700 rounded-lg p-2 text-base sm:text-sm text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500';
$classeCartao = 'bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 mb-6';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Full</title>
    <script src="/assets/tailwind.js"></script>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen p-4 sm:p-8 font-sans selection:bg-blue-500 selection:text-white">
    <?php require __DIR__ . '/../../src/nav.php'; ?>
    <div class="max-w-5xl mx-auto">

        <header class="mb-6 flex flex-wrap justify-between items-end gap-4 border-b border-gray-700 pb-4">
            <h1 class="text-4xl font-bold">
                <span aria-hidden="true">🚚</span>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400">Full</span>
            </h1>
            <div class="flex items-center gap-3">
                <span id="statusSinc" class="text-sm text-gray-400"></span>
                <button id="btnAtualizar" onclick="atualizar()"
                        class="bg-blue-600 hover:bg-blue-500 disabled:opacity-60 disabled:cursor-wait text-white font-semibold py-2 px-4 rounded-lg transition-colors shadow-lg whitespace-nowrap">
                    🔄 Atualizar
                </button>
            </div>
        </header>

        <div class="grid grid-cols-3 sm:flex sm:flex-wrap items-end gap-3 mb-2">
            <label class="text-sm text-gray-400 flex flex-col gap-1" title="De quantos em quantos dias você quer criar um envio">Ciclo (dias)
                <input id="ciclo" type="number" min="1" max="90" inputmode="numeric" class="<?= $classeCampo ?> w-full sm:w-32" oninput="aoMudarParametro()">
            </label>
            <label class="text-sm text-gray-400 flex flex-col gap-1" title="Da criação do envio até o estoque ficar disponível no Full">Prazo (dias)
                <input id="prazo" type="number" min="0" max="60" inputmode="numeric" class="<?= $classeCampo ?> w-full sm:w-32" oninput="aoMudarParametro()">
            </label>
            <label class="text-sm text-gray-400 flex flex-col gap-1" title="Folga extra para venda acima da média ou atraso no envio">Segurança (dias)
                <input id="seguranca" type="number" min="0" max="60" inputmode="numeric" class="<?= $classeCampo ?> w-full sm:w-32" oninput="aoMudarParametro()">
            </label>
        </div>
        <p class="text-xs text-gray-500 mb-6">Uma linha por anúncio: o que vai para o Full fica preso ao anúncio. Venda por dia = a maior média entre os últimos 30 e 60 dias completos, só das vendas pelo Full daquele anúncio. Cada envio cobre prazo + ciclo + segurança. Anúncios que o ML junta no mesmo estoque são calculados juntos. Unidades já enviadas que o ML ainda não recebeu não aparecem no estoque.</p>

        <div id="resumo" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6"></div>

        <section class="<?= $classeCartao ?>">
            <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                <h2 class="text-lg font-semibold text-gray-200">Sugestão por anúncio</h2>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                        <input id="mostrarSemVenda" type="checkbox" class="w-4 h-4 accent-blue-500" onchange="renderizar()"> Mostrar sem vendas
                    </label>
                    <button id="btnCopiar" onclick="copiarEnvio()" title="Copiar anúncio, SKU e quantidade do envio"
                            class="bg-gray-900 hover:bg-gray-700 border border-gray-700 hover:border-blue-500 text-gray-300 hover:text-blue-400 rounded-lg px-3 py-2 text-sm transition-colors">📋 Copiar envio</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-gray-400 border-b border-gray-700">
                        <tr>
                            <th class="text-left font-medium py-2 pr-3">Anúncio e SKU</th>
                            <th class="text-right font-medium py-2 px-2 sm:px-3">Full</th>
                            <th class="text-right font-medium py-2 px-2 sm:px-3 hidden sm:table-cell">Local</th>
                            <th class="text-right font-medium py-2 px-2 sm:px-3 whitespace-nowrap hidden sm:table-cell">Vende/dia</th>
                            <th class="text-right font-medium py-2 px-2 sm:px-3">Dura</th>
                            <th class="text-right font-medium py-2 px-2 sm:px-3 hidden sm:table-cell whitespace-nowrap">Enviar até</th>
                            <th class="text-right font-medium py-2 pl-2 sm:pl-3">Enviar</th>
                        </tr>
                    </thead>
                    <tbody id="tabela" class="border-b border-gray-700/60"></tbody>
                </table>
            </div>
        </section>
    </div>

<script src="/assets/ui.js?v=<?= filemtime(__DIR__ . '/../assets/ui.js') ?>"></script>
<script>
const PADRAO = { ciclo: 14, prazo: 3, seguranca: 7 };
const CHAVE_PARAMETROS = 'full-parametros';
let dados = { anuncios: [], vendas: [], local: {}, sincronizacao: {} };
let linhasEnvio = [];

const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
const doisDig = n => String(n).padStart(2, '0');
const paraTexto = d => `${d.getFullYear()}-${doisDig(d.getMonth() + 1)}-${doisDig(d.getDate())}`;
const paraData = t => { const [a, m, d] = t.split('-').map(Number); return new Date(a, m - 1, d); };
const somarDias = (t, n) => { const d = paraData(t); d.setDate(d.getDate() + n); return paraTexto(d); };
const dataCurta = t => { const d = paraData(t); return `${doisDig(d.getDate())}/${doisDig(d.getMonth() + 1)}`; };
const DIAS_SEMANA = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
const decimal = (v, casas = 1) => v.toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas });

// Parâmetros ficam só neste navegador
function lerParametros() {
    let salvos = {};
    try { salvos = JSON.parse(localStorage.getItem(CHAVE_PARAMETROS) || '{}'); } catch (e) { salvos = {}; }
    Object.entries(PADRAO).forEach(([campo, valor]) => { document.getElementById(campo).value = salvos[campo] ?? valor; });
}
function parametros() {
    const ler = (campo, minimo) => { const v = parseInt(document.getElementById(campo).value, 10); return Number.isFinite(v) && v >= minimo ? v : PADRAO[campo]; };
    return { ciclo: ler('ciclo', 1), prazo: ler('prazo', 0), seguranca: ler('seguranca', 0) };
}
function aoMudarParametro() {
    try { localStorage.setItem(CHAVE_PARAMETROS, JSON.stringify(parametros())); } catch (e) { /* sem armazenamento: segue com o padrão */ }
    renderizar();
}

// ===== Cálculo =====
// Uma linha por anúncio (e variação): cada anúncio vende do seu jeito e o que vai para o Full fica preso a ele.
// Exceção: anúncios que o ML junta no mesmo estoque (mesmo inventory_id). Aí a conta de estoque é do grupo,
// com a soma das vendas, e a quantidade aparece só na linha principal (a que mais vende).
function calcular() {
    const { ciclo, prazo, seguranca } = parametros();
    const hoje = paraTexto(new Date());
    const ontem = somarDias(hoje, -1);
    const desde30 = somarDias(ontem, -29), desde60 = somarDias(ontem, -59);
    const vendidos = new Map(); // chave: anúncio (todas as variações) e anúncio:variação
    dados.vendas.forEach(([anuncio, variacao, dia, un]) => {
        if (dia > ontem || dia < desde60) return; // só dias completos
        [anuncio, variacao !== null ? `${anuncio}:${variacao}` : null].filter(Boolean).forEach(chave => {
            const v = vendidos.get(chave) || { d30: 0, d60: 0 };
            v.d60 += un;
            if (dia >= desde30) v.d30 += un;
            vendidos.set(chave, v);
        });
    });

    const linhas = dados.anuncios.map(a => {
        const v = vendidos.get(a.variacao !== null ? `${a.anuncio}:${a.variacao}` : a.anuncio) || { d30: 0, d60: 0 };
        const media30 = v.d30 / 30, media60 = v.d60 / 60;
        return { ...a, media30, media60, porDia: Math.max(media30, media60), local: dados.local[(a.sku || '').toUpperCase()] };
    });

    const grupos = new Map();
    linhas.forEach(l => { if (!grupos.has(l.inventory)) grupos.set(l.inventory, []); grupos.get(l.inventory).push(l); });
    grupos.forEach(grupo => {
        grupo.sort((x, y) => y.porDia - x.porDia || Number(y.ativo) - Number(x.ativo));
        const disponivel = grupo[0].disponivel;
        const porDia = grupo.reduce((t, l) => t + l.porDia, 0);
        const cobertura = porDia > 0 ? disponivel / porDia : Infinity;
        // Dias até precisar criar o envio: o que chega tem que chegar antes de entrar na margem de segurança
        const folga = porDia > 0 ? cobertura - (prazo + seguranca) : Infinity;
        const quantidade = porDia > 0 ? Math.max(0, Math.ceil(porDia * (prazo + ciclo + seguranca) - disponivel)) : 0;
        // Entra no envio de hoje quem não aguenta até o próximo envio (daqui a um ciclo)
        const noEnvio = porDia > 0 && folga < ciclo && quantidade > 0;
        const situacao = porDia === 0 ? 'sem_venda' : folga <= 0 ? 'atrasado' : noEnvio ? 'ciclo' : 'ok';
        const limite = Number.isFinite(folga) ? somarDias(hoje, Math.max(0, Math.floor(folga))) : null;
        grupo.forEach((l, k) => Object.assign(l, {
            principal: k === 0, grupo: grupo.length, demandaGrupo: porDia, cobertura, folga, quantidade, noEnvio: noEnvio && k === 0,
            noEnvioGrupo: noEnvio, situacao, limite,
        }));
    });
    return [...grupos.values()]
        .sort((a, b) => a[0].folga - b[0].folga || b[0].demandaGrupo - a[0].demandaGrupo || a[0].sku.localeCompare(b[0].sku))
        .flat();
}

// ===== Tela =====
const ROTULO_SITUACAO = {
    atrasado: ['Enviar já', 'bg-red-900/60 text-red-200 border-red-500/40'],
    ciclo: ['Neste envio', 'bg-amber-900/50 text-amber-200 border-amber-500/40'],
    ok: ['Ok', 'bg-emerald-900/40 text-emerald-200 border-emerald-500/30'],
    sem_venda: ['Sem vendas', 'bg-gray-900 text-gray-400 border-gray-600'],
};

function cartao(rotulo, valor, detalhe, destaque) {
    return `<div class="bg-gray-800 border ${destaque || 'border-gray-700'} rounded-xl p-4 min-w-0">
        <div class="text-xs uppercase tracking-wide text-gray-400">${rotulo}</div>
        <div class="text-xl sm:text-2xl font-bold tabular-nums mt-1">${valor}</div>
        <div class="text-xs text-gray-400 mt-1">${detalhe}</div></div>`;
}

function renderizar() {
    const { ciclo } = parametros();
    const linhas = calcular();
    linhasEnvio = linhas.filter(l => l.noEnvio);
    const comVenda = linhas.filter(l => l.principal && l.demandaGrupo > 0);
    const proximo = comVenda.length ? comVenda[0] : null;
    const hoje = paraTexto(new Date());
    const unidades = linhasEnvio.reduce((t, l) => t + l.quantidade, 0);
    const zerados = comVenda.filter(l => l.disponivel === 0).length;
    const semLocal = linhasEnvio.filter(l => l.local !== undefined && l.local < l.quantidade).length;

    let proximoTexto = '—', proximoDetalhe = 'nenhum anúncio com vendas', destaque = '';
    if (proximo) {
        if (proximo.folga <= 0) {
            proximoTexto = 'Hoje'; proximoDetalhe = `${esc(proximo.sku || proximo.titulo)} (${proximo.anuncio}) já está na margem de segurança`; destaque = 'border-red-500/60';
        } else {
            const dias = Math.floor(proximo.folga);
            proximoTexto = dias === 0 ? 'Hoje' : `${DIAS_SEMANA[paraData(proximo.limite).getDay()]} ${dataCurta(proximo.limite)}`;
            proximoDetalhe = dias === 0 ? `${esc(proximo.sku)} entra na margem amanhã` : `em ${dias} dia${dias > 1 ? 's' : ''}, por causa de ${esc(proximo.sku || proximo.titulo)}`;
            if (dias <= 2) destaque = 'border-amber-500/60';
        }
    }
    document.getElementById('resumo').innerHTML = [
        cartao('Próximo envio', proximoTexto, proximoDetalhe, destaque),
        cartao('Anúncios no envio', linhasEnvio.length, `não aguentam os próximos ${ciclo} dias`),
        cartao('Unidades no envio', unidades.toLocaleString('pt-BR'), semLocal ? `<span class="text-amber-300">${semLocal} sem estoque local suficiente</span>` : 'cobrem prazo + ciclo + segurança'),
        cartao('Zerados no Full', zerados, 'anúncios com vendas e 0 disponível', zerados ? 'border-red-500/60' : ''),
    ].join('');

    // Mesmo SKU com estoque em outro anúncio Full (outro estoque): só informativo, o estoque não passa de um para o outro
    const porSku = new Map();
    linhas.filter(l => l.principal).forEach(l => { if (l.sku) porSku.set(l.sku.toUpperCase(), (porSku.get(l.sku.toUpperCase()) || 0) + l.disponivel); });
    const emOutro = l => (l.sku ? porSku.get(l.sku.toUpperCase()) - l.disponivel : 0);

    const mostrarSemVenda = document.getElementById('mostrarSemVenda').checked;
    // Esconde só grupos inteiros sem venda, para não separar anúncios do mesmo estoque
    const visiveis = linhas.filter(l => mostrarSemVenda || l.demandaGrupo > 0);
    document.getElementById('tabela').innerHTML = visiveis.length ? visiveis.map(l => {
        const [rotulo, classe] = ROTULO_SITUACAO[l.situacao];
        const dura = !Number.isFinite(l.cobertura) ? '—' : l.cobertura >= 999 ? '999+' : `${Math.floor(l.cobertura)} d`;
        const localTexto = l.local === undefined ? '<span class="text-gray-600">—</span>'
            : `<span class="${l.noEnvio && l.local < l.quantidade ? 'text-amber-300' : ''}">${l.local}</span>`;
        const link = `https://produto.mercadolivre.com.br/${l.anuncio.replace(/^MLB/, 'MLB-')}`;
        const dividido = l.compartilha.length
            ? `<div class="text-xs text-violet-300">estoque dividido com ${l.compartilha.map(esc).join(', ')}${l.principal ? '' : ' · sugestão na linha de cima'}</div>` : '';
        const enviar = l.noEnvio ? l.quantidade
            : !l.principal && l.noEnvioGrupo ? '<span class="font-normal text-violet-300" title="Mesmo estoque do anúncio de cima">↑</span>'
            : l.demandaGrupo > 0 ? '<span class="font-normal">—</span>' : '';
        return `<tr class="${l.principal || l.grupo === 1 ? 'border-t' : ''} border-gray-700/60 ${l.noEnvio ? '' : 'text-gray-300'}">
            <td class="py-2 pr-2 sm:pr-3 max-w-[9.5rem] sm:max-w-none">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold text-gray-100 break-all sm:break-normal">${l.sku ? esc(l.sku) : '<span class="text-gray-500">(sem SKU)</span>'}</span>
                    ${l.principal ? `<span class="text-[11px] px-1.5 py-0.5 rounded border ${classe} whitespace-nowrap">${rotulo}</span>` : ''}
                    ${l.ativo ? '' : '<span class="text-[11px] px-1.5 py-0.5 rounded border border-gray-600 text-gray-400 whitespace-nowrap">pausado</span>'}
                </div>
                <div class="text-xs truncate sm:max-w-md"><a href="${link}" target="_blank" rel="noopener" class="text-blue-400 hover:underline tabular-nums">${esc(l.anuncio)}</a><span class="text-gray-500" title="${esc(l.titulo)}"> · ${esc(l.titulo)}</span></div>
                ${dividido}
                ${emOutro(l) > 0 ? `<div class="text-xs text-sky-300">outro anúncio deste SKU tem ${emOutro(l)} no Full</div>` : ''}
            </td>
            <td class="py-2 px-2 sm:px-3 text-right tabular-nums">${l.disponivel}${l.indisponivel ? `<div class="text-[11px] text-gray-500" title="Unidades no Full que não estão à venda">+${l.indisponivel} indisp.</div>` : ''}</td>
            <td class="py-2 px-2 sm:px-3 text-right tabular-nums hidden sm:table-cell">${localTexto}</td>
            <td class="py-2 px-2 sm:px-3 text-right tabular-nums whitespace-nowrap hidden sm:table-cell" title="30 dias: ${decimal(l.media30, 2)}/dia · 60 dias: ${decimal(l.media60, 2)}/dia">${l.porDia > 0 ? decimal(l.porDia, 2) : '0'}</td>
            <td class="py-2 px-2 sm:px-3 text-right tabular-nums whitespace-nowrap ${l.situacao === 'atrasado' ? 'text-red-300' : ''}">${dura}</td>
            <td class="py-2 px-2 sm:px-3 text-right tabular-nums whitespace-nowrap hidden sm:table-cell">${l.limite ? (l.folga <= 0 ? '<span class="text-red-300">já passou</span>' : dataCurta(l.limite)) : '—'}</td>
            <td class="py-2 pl-2 sm:pl-3 text-right tabular-nums font-semibold ${l.noEnvio ? 'text-white' : 'text-gray-500'}">${enviar}</td>
        </tr>`;
    }).join('') : '<tr><td colspan="7" class="py-6 text-center text-gray-500">Nenhum anúncio no Full. Use o botão Atualizar.</td></tr>';
}

async function copiarEnvio() {
    if (!linhasEnvio.length) { ML.toast('Nada para enviar agora.', 'info'); return; }
    const texto = linhasEnvio.map(l => `${l.anuncio}\t${l.sku}\t${l.quantidade}`).join('\n');
    try {
        await navigator.clipboard.writeText(texto);
        ML.toast(`${linhasEnvio.length} anúncios copiados (anúncio, SKU e quantidade).`, 'sucesso');
    } catch (e) {
        ML.toast('Não foi possível copiar.', 'erro');
    }
}

function mostrarSincronizacao() {
    const s = dados.sincronizacao || {};
    const el = document.getElementById('statusSinc');
    if (!s.estoque_em) { el.textContent = 'Estoque nunca atualizado'; return; }
    const [data, hora] = s.estoque_em.split(' ');
    el.textContent = `Estoque de ${dataCurta(data)} às ${hora.slice(0, 5)}`;
}

async function carregar() {
    const r = await fetch('api.php?action=dados');
    dados = await r.json();
    mostrarSincronizacao();
    renderizar();
}

async function atualizar() {
    const botao = document.getElementById('btnAtualizar');
    botao.disabled = true;
    botao.textContent = '⏳ Atualizando...';
    try {
        const r = await fetch('/vendas/api.php?action=atualizar', { method: 'POST' });
        const resposta = await r.json();
        if (!r.ok) throw new Error(resposta.error || 'Falha ao atualizar');
        if (resposta.full && resposta.full.erro) throw new Error('Vendas ok, mas o estoque Full falhou: ' + resposta.full.erro);
        ML.toast(`Estoque de ${resposta.full ? resposta.full.anuncios : 0} anúncios atualizado; ${resposta.novos + resposta.alterados} venda(s) nova(s) ou alterada(s).`, 'sucesso');
    } catch (e) {
        ML.toast(e.message, 'erro');
    } finally {
        await carregar();
        botao.disabled = false;
        botao.textContent = '🔄 Atualizar';
    }
}

lerParametros();
carregar();
</script>
</body>
</html>
