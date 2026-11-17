<?php
require __DIR__ . '/../../src/auth.php';
$paginaContas = 'parcelas';
$classeCartao = 'bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 mb-6';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parcelas - Contas</title>
    <script src="/assets/tailwind.js"></script>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen p-4 sm:p-8 font-sans selection:bg-blue-500 selection:text-white">
    <?php require __DIR__ . '/../../src/nav.php'; ?>
    <div class="max-w-3xl mx-auto">
        <?php require __DIR__ . '/../../src/contas/cabecalho.php'; ?>

        <div id="semDados" class="hidden bg-gray-800 border border-gray-700 rounded-xl p-8 text-center">
            <p class="text-gray-300 mb-2">Nenhuma compra parcelada registrada ainda.</p>
            <p class="text-sm text-gray-400 mb-4">No mês, use o botão 📄 ao lado do valor de um cartão para ler a fatura em PDF.</p>
            <a href="./" class="inline-block bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2.5 px-6 rounded-xl transition-colors">Ir para o mês atual</a>
        </div>

        <div id="conteudo" class="hidden">
            <section class="<?= $classeCartao ?>">
                <h2 class="text-lg font-semibold text-gray-200 mb-1">Próximas faturas</h2>
                <p class="text-xs text-gray-500 mb-4">Soma das parcelas já conhecidas em cada mês, pelo último valor lido de cada compra.</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm tabular-nums">
                        <thead id="projecaoCabecalho" class="text-gray-400"></thead>
                        <tbody id="projecaoCorpo"></tbody>
                    </table>
                </div>
            </section>

            <div id="andamento"></div>

            <details id="blocoQuitadas" class="<?= $classeCartao ?>">
                <summary id="tituloQuitadas" class="cursor-pointer font-semibold text-gray-300 select-none"></summary>
                <div id="quitadas" class="mt-4 space-y-2"></div>
            </details>
        </div>
    </div>

    <script src="/assets/ui.js?v=<?= filemtime(__DIR__ . '/../assets/ui.js') ?>"></script>
    <script src="comum.js?v=<?= filemtime(__DIR__ . '/comum.js') ?>"></script>
    <script>
    const MESES_PROJECAO = 6;
    let compras = [];
    let mesHoje = '';

    function mesFim(c) {
        return Contas.somarMeses(c.mesInicio, c.total - 1);
    }

    function htmlCompra(c, ativa) {
        const faltam = c.total - c.ultimoNumero;
        const progresso = Math.round(c.ultimoNumero / c.total * 100);
        // Valores pelo último valor de parcela lido (variações de centavos entre faturas ficam de fora)
        const detalhes = `${Contas.moeda(c.valor)}/mês. `
            + `${Contas.moeda(c.ultimoNumero * c.valor)} / ${Contas.moeda(c.total * c.valor)}, `
            + `Restando: ${Contas.moeda(faltam * c.valor)}. `
            + `${c.ultimoNumero}/${c.total} parcelas (${Contas.mesCurto(c.mesInicio)} - ${Contas.mesCurto(mesFim(c))})`;
        return `
            <div class="bg-gray-900 border border-gray-700 rounded-lg p-3 ${ativa ? '' : 'opacity-70'}">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
                    <input type="text" data-apelido="${c.id}" maxlength="100" autocomplete="off" placeholder="Dar um apelido" value="${Contas.esc(c.apelido || '')}"
                           aria-label="Apelido de ${Contas.esc(c.descricao)}"
                           class="w-full sm:w-56 bg-gray-800 border border-gray-700 rounded-lg p-2 text-sm font-semibold text-gray-100 placeholder:font-normal focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <span class="text-sm text-gray-400 break-words min-w-0">${Contas.esc(c.descricao)}</span>
                </div>
                <div class="mt-2 h-1.5 rounded-full bg-gray-700 overflow-hidden" aria-hidden="true">
                    <div class="h-full ${faltam ? 'bg-blue-500' : 'bg-emerald-500'}" style="width: ${progresso}%"></div>
                </div>
                <div class="text-xs text-gray-400 mt-2">${detalhes}</div>
            </div>`;
    }

    function renderizarProjecao(ativas) {
        const meses = Array.from({ length: MESES_PROJECAO }, (_, i) => Contas.somarMeses(mesHoje, i));
        const cartoes = [...new Set(ativas.map(c => c.conta))].sort();
        const soma = (conta, mes) => ativas
            .filter(c => (conta === null || c.conta === conta) && c.mesInicio <= mes && mesFim(c) >= mes)
            .reduce((total, c) => total + c.valor, 0);
        const celula = valor => valor ? Contas.moeda(valor) : '<span class="text-gray-600">—</span>';

        document.getElementById('projecaoCabecalho').innerHTML = `<tr>
            <th class="text-left font-medium py-2 pr-3">Cartão</th>
            ${meses.map(m => `<th class="text-right font-medium py-2 px-2 whitespace-nowrap">${Contas.mesCurto(m)}</th>`).join('')}
        </tr>`;
        document.getElementById('projecaoCorpo').innerHTML = cartoes.map(conta => `<tr class="border-t border-gray-700">
            <td class="py-2 pr-3 text-gray-300 whitespace-nowrap">${Contas.esc(conta)}</td>
            ${meses.map(m => `<td class="text-right py-2 px-2 whitespace-nowrap">${celula(soma(conta, m))}</td>`).join('')}
        </tr>`).join('') + `<tr class="border-t border-gray-600 font-semibold">
            <td class="py-2 pr-3">Total</td>
            ${meses.map(m => `<td class="text-right py-2 px-2 whitespace-nowrap">${celula(soma(null, m))}</td>`).join('')}
        </tr>`;
    }

    function renderizar() {
        document.getElementById('semDados').classList.toggle('hidden', compras.length > 0);
        document.getElementById('conteudo').classList.toggle('hidden', compras.length === 0);
        if (!compras.length) return;

        const ativas = compras.filter(c => mesFim(c) >= mesHoje);
        const quitadas = compras.filter(c => mesFim(c) < mesHoje);
        renderizarProjecao(ativas);

        const porCartao = {};
        ativas.forEach(c => (porCartao[c.conta] ||= []).push(c));
        document.getElementById('andamento').innerHTML = Object.keys(porCartao).sort().map(conta => {
            const lista = porCartao[conta];
            const restante = lista.reduce((total, c) => total + (c.total - c.ultimoNumero) * c.valor, 0);
            return `
                <section class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 mb-6">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                        <h2 class="text-lg font-semibold text-gray-200">${Contas.esc(conta)}</h2>
                        <span class="text-sm text-gray-400">${lista.length} compra(s) · faltam ${Contas.moeda(restante)}</span>
                    </div>
                    <div class="space-y-2">${lista.map(c => htmlCompra(c, true)).join('')}</div>
                </section>`;
        }).join('') || '<p class="text-center text-gray-400 mb-6">Nenhuma compra com parcelas a vencer.</p>';

        document.getElementById('blocoQuitadas').classList.toggle('hidden', quitadas.length === 0);
        document.getElementById('tituloQuitadas').textContent = `✓ Quitadas (${quitadas.length})`;
        document.getElementById('quitadas').innerHTML = quitadas.map(c => htmlCompra(c, false)).join('');
    }

    async function carregar() {
        try {
            const dados = await Contas.api('parcelamentos');
            compras = dados.parcelamentos;
            mesHoje = dados.hoje.slice(0, 7);
            renderizar();
        } catch (erro) {
            ML.toast(erro.message || 'Não foi possível carregar as parcelas.', 'erro');
        }
    }

    document.getElementById('conteudo').addEventListener('change', async e => {
        if (!e.target.matches('[data-apelido]')) return;
        const compra = compras.find(c => c.id === Number(e.target.dataset.apelido));
        const apelido = e.target.value.trim();
        if (apelido === (compra.apelido || '')) return;
        try {
            await Contas.api('apelido', { id: compra.id, apelido });
            compra.apelido = apelido || null;
            ML.toast(apelido ? `Apelido salvo: ${apelido}` : 'Apelido removido.', 'sucesso');
        } catch (erro) {
            e.target.value = compra.apelido || '';
            ML.toast(erro.message || 'Não foi possível salvar o apelido.', 'erro');
        }
    });
    document.getElementById('conteudo').addEventListener('keydown', e => {
        if (e.key === 'Enter' && e.target.matches('[data-apelido]')) e.target.blur();
    });

    carregar();
    </script>
</body>
</html>
