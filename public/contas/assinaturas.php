<?php
require __DIR__ . '/../../src/auth.php';
$paginaContas = 'assinaturas';
$classeCartao = 'bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 mb-6';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinaturas - Contas</title>
    <script src="/assets/tailwind.js"></script>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen p-4 sm:p-8 font-sans selection:bg-blue-500 selection:text-white">
    <?php require __DIR__ . '/../../src/nav.php'; ?>
    <div class="max-w-3xl mx-auto">
        <?php require __DIR__ . '/../../src/contas/cabecalho.php'; ?>

        <div id="semDados" class="hidden bg-gray-800 border border-gray-700 rounded-xl p-8 text-center">
            <p class="text-gray-300 mb-2">Nenhuma assinatura registrada ainda.</p>
            <p class="text-sm text-gray-400 mb-4">No mês, use o botão 📄 ao lado do valor de um cartão para ler a fatura em PDF. As assinaturas conhecidas são marcadas sozinhas na revisão.</p>
            <a href="./" class="inline-block bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2.5 px-6 rounded-xl transition-colors">Ir para o mês atual</a>
        </div>

        <div id="conteudo" class="hidden">
            <section class="<?= $classeCartao ?>">
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-xs text-gray-400 mb-1">Por mês</div>
                        <div id="totalMes" class="text-xl sm:text-2xl font-bold tabular-nums"></div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 mb-1">Por ano</div>
                        <div id="totalAno" class="text-xl sm:text-2xl font-bold tabular-nums"></div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 mb-1">Ativas</div>
                        <div id="quantidade" class="text-xl sm:text-2xl font-bold tabular-nums"></div>
                    </div>
                </div>
                <p id="notaTotal" class="text-xs text-gray-500 mt-3 text-center empty:hidden"></p>
            </section>

            <div id="porCartao"></div>

            <details id="blocoCanceladas" class="<?= $classeCartao ?>">
                <summary id="tituloCanceladas" class="cursor-pointer font-semibold text-gray-300 select-none"></summary>
                <div id="canceladas" class="mt-4 space-y-2"></div>
            </details>
        </div>
    </div>

    <script src="/assets/ui.js?v=<?= filemtime(__DIR__ . '/../assets/ui.js') ?>"></script>
    <script src="comum.js?v=<?= filemtime(__DIR__ . '/comum.js') ?>"></script>
    <script>
    let assinaturas = [];

    // Ativa que não veio na última fatura lida do cartão
    const talvezCancelada = a => !a.cancelada && a.ultimoMes < a.ultimaFatura;

    const BOTAO = 'px-3 py-1.5 rounded-lg border text-xs font-semibold transition-colors';

    function htmlAssinatura(a) {
        const detalhes = [
            `${Contas.moeda(a.valor)}/mês`,
            `desde ${Contas.mesCurto(a.primeiroMes)}`,
            `${a.cobrancas} cobrança(s)`,
            ...(a.cancelada || talvezCancelada(a) ? [`última em ${Contas.mesCurto(a.ultimoMes)}`] : []),
        ].join(' · ');
        const alertas = [];
        if (a.valorAnterior !== null && a.valorAnterior !== a.valor && !a.cancelada) {
            const subiu = a.valor > a.valorAnterior;
            alertas.push(`<span class="${subiu ? 'text-amber-300' : 'text-emerald-300'} font-semibold">${subiu ? '▲' : '▼'} era ${Contas.moeda(a.valorAnterior)}</span>`);
        }
        if (talvezCancelada(a)) {
            alertas.push(`<span class="text-amber-300 font-semibold">cancelada? Não veio na fatura de ${Contas.mesCurto(a.ultimaFatura)}</span>`);
        }
        const botoes = [
            a.cancelada
                ? `<button type="button" data-reativar="${a.id}" class="${BOTAO} border-gray-600 text-gray-300 hover:bg-gray-700 hover:text-white">Reativar</button>`
                : talvezCancelada(a) ? `<button type="button" data-cancelar="${a.id}" class="${BOTAO} border-amber-500/50 text-amber-200 hover:bg-amber-900/40">Cancelei</button>` : '',
            `<button type="button" data-remover="${a.id}" class="${BOTAO} border-gray-700 text-gray-400 hover:bg-gray-700 hover:text-white">Não é assinatura</button>`,
        ].join('');
        return `
            <div class="bg-gray-900 border ${talvezCancelada(a) ? 'border-amber-500/40' : 'border-gray-700'} rounded-lg p-3 ${a.cancelada ? 'opacity-70' : ''}">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
                    <input type="text" data-apelido="${a.id}" maxlength="100" autocomplete="off" placeholder="Dar um apelido" value="${Contas.esc(a.apelido || '')}"
                           aria-label="Apelido de ${Contas.esc(a.descricao)}"
                           class="w-full sm:w-56 bg-gray-800 border border-gray-700 rounded-lg p-2 text-sm font-semibold text-gray-100 placeholder:font-normal focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <span class="text-sm text-gray-400 break-words min-w-0 flex-1">${Contas.esc(a.descricao)}</span>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 mt-2">
                    <div class="text-xs text-gray-400">${detalhes}${alertas.length ? ' · ' + alertas.join(' · ') : ''}</div>
                    <div class="flex gap-2">${botoes}</div>
                </div>
            </div>`;
    }

    function renderizar() {
        document.getElementById('semDados').classList.toggle('hidden', assinaturas.length > 0);
        document.getElementById('conteudo').classList.toggle('hidden', assinaturas.length === 0);
        if (!assinaturas.length) return;

        const ativas = assinaturas.filter(a => !a.cancelada);
        const canceladas = assinaturas.filter(a => a.cancelada);
        const contando = ativas.filter(a => !talvezCancelada(a));
        const porMes = contando.reduce((total, a) => total + a.valor, 0);
        const duvidas = ativas.length - contando.length;
        document.getElementById('totalMes').textContent = Contas.moeda(porMes);
        document.getElementById('totalAno').textContent = Contas.moeda(porMes * 12);
        document.getElementById('quantidade').textContent = contando.length;
        document.getElementById('notaTotal').textContent = duvidas
            ? `${duvidas} assinatura(s) marcada(s) como "cancelada?" ficam fora dos totais até você confirmar.` : '';

        const porCartao = {};
        ativas.forEach(a => (porCartao[a.conta] ||= []).push(a));
        document.getElementById('porCartao').innerHTML = Object.keys(porCartao).sort().map(conta => {
            const lista = porCartao[conta].sort((x, y) => talvezCancelada(y) - talvezCancelada(x));
            const soma = lista.filter(a => !talvezCancelada(a)).reduce((total, a) => total + a.valor, 0);
            return `
                <section class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 mb-6">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                        <h2 class="text-lg font-semibold text-gray-200">${Contas.esc(conta)}</h2>
                        <span class="text-sm text-gray-400">${lista.length} assinatura(s) · ${Contas.moeda(soma)}/mês</span>
                    </div>
                    <div class="space-y-2">${lista.map(htmlAssinatura).join('')}</div>
                </section>`;
        }).join('') || '<p class="text-center text-gray-400 mb-6">Nenhuma assinatura ativa.</p>';

        document.getElementById('blocoCanceladas').classList.toggle('hidden', canceladas.length === 0);
        document.getElementById('tituloCanceladas').textContent = `✕ Canceladas (${canceladas.length})`;
        document.getElementById('canceladas').innerHTML = canceladas.map(htmlAssinatura).join('');
    }

    async function carregar() {
        try {
            assinaturas = (await Contas.api('assinaturas')).assinaturas;
            renderizar();
        } catch (erro) {
            ML.toast(erro.message || 'Não foi possível carregar as assinaturas.', 'erro');
        }
    }

    const conteudo = document.getElementById('conteudo');
    conteudo.addEventListener('change', async e => {
        if (!e.target.matches('[data-apelido]')) return;
        const assinatura = assinaturas.find(a => a.id === Number(e.target.dataset.apelido));
        const apelido = e.target.value.trim();
        if (apelido === (assinatura.apelido || '')) return;
        try {
            await Contas.api('assinatura_apelido', { id: assinatura.id, apelido });
            assinatura.apelido = apelido || null;
            ML.toast(apelido ? `Apelido salvo: ${apelido}` : 'Apelido removido.', 'sucesso');
        } catch (erro) {
            e.target.value = assinatura.apelido || '';
            ML.toast(erro.message || 'Não foi possível salvar o apelido.', 'erro');
        }
    });
    conteudo.addEventListener('keydown', e => {
        if (e.key === 'Enter' && e.target.matches('[data-apelido]')) e.target.blur();
    });
    conteudo.addEventListener('click', async e => {
        const botao = e.target.closest('[data-cancelar], [data-reativar], [data-remover]');
        if (!botao) return;
        const id = Number(botao.dataset.cancelar || botao.dataset.reativar || botao.dataset.remover);
        const assinatura = assinaturas.find(a => a.id === id);
        const nome = assinatura.apelido || assinatura.descricao;
        try {
            if (botao.dataset.remover) {
                const confirmou = await ML.confirmar(`${nome} não é assinatura? O histórico dela sai daqui e ela não volta a ser sugerida com os mesmos valores.`, {
                    titulo: 'Não é assinatura',
                    textoConfirmar: 'Remover',
                    perigo: true,
                });
                if (!confirmou) return;
                await Contas.api('assinatura_remover', { id });
                ML.toast(`${nome} removida.`, 'sucesso');
            } else {
                const cancelada = !!botao.dataset.cancelar;
                await Contas.api('assinatura_cancelar', { id, cancelada });
                ML.toast(cancelada ? `${nome} cancelada. Se voltar a cobrar, ela reaparece sozinha.` : `${nome} reativada.`, 'sucesso');
            }
            carregar();
        } catch (erro) {
            ML.toast(erro.message || 'Não foi possível concluir. Verifique a conexão.', 'erro');
        }
    });

    carregar();
    </script>
</body>
</html>
