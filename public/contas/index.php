<?php
require __DIR__ . '/../../src/auth.php';
$paginaContas = 'mes';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contas Pessoais</title>
    <script src="/assets/tailwind.js"></script>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen p-8 font-sans selection:bg-blue-500 selection:text-white">
    <?php require __DIR__ . '/../../src/nav.php'; ?>
    <div class="max-w-3xl mx-auto">
        <?php require __DIR__ . '/../../src/contas/cabecalho.php'; ?>

        <div class="flex items-center justify-between gap-3 mb-6">
            <button id="mesAnterior" type="button" aria-label="Mês anterior" class="h-11 w-11 rounded-xl bg-gray-800 border border-gray-700 hover:border-blue-500 text-xl transition-colors">‹</button>
            <div class="text-center">
                <h2 id="tituloMes" class="text-2xl font-bold">&nbsp;</h2>
                <p id="situacaoMes" class="text-sm text-gray-400 min-h-[1.25rem]"></p>
            </div>
            <button id="mesSeguinte" type="button" aria-label="Próximo mês" class="h-11 w-11 rounded-xl bg-gray-800 border border-gray-700 hover:border-blue-500 text-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed">›</button>
        </div>

        <div id="semContas" class="hidden bg-gray-800 border border-gray-700 rounded-xl p-8 text-center">
            <p class="text-gray-300 mb-4">Você ainda não cadastrou nenhuma conta.</p>
            <a href="cadastro.php" class="inline-block bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2.5 px-6 rounded-xl transition-colors">Cadastrar contas</a>
        </div>

        <div id="listaContas" class="space-y-3 mb-6"></div>

        <div id="barraResumo" class="hidden sticky bottom-4 bg-gray-800 border border-gray-700 rounded-xl shadow-2xl p-3 sm:p-4 flex items-center justify-between gap-3">
            <div>
                <div class="text-xl sm:text-2xl font-bold tabular-nums whitespace-nowrap" id="totalMes">R$ 0,00</div>
                <div class="text-sm text-gray-400" id="progressoMes"></div>
            </div>
            <button id="botaoMes" type="button" class="py-2.5 px-6 rounded-xl font-semibold transition-colors disabled:opacity-50 disabled:cursor-wait"></button>
        </div>
    </div>

    <script src="/assets/ui.js?v=<?= filemtime(__DIR__ . '/../assets/ui.js') ?>"></script>
    <script src="comum.js?v=<?= filemtime(__DIR__ . '/comum.js') ?>"></script>
    <script>
    const parametroMes = new URLSearchParams(location.search).get('mes');
    let estado = null;
    let filaGravacao = Promise.resolve(); // Grava um valor por vez, na ordem digitada

    const CLASSE_CAMPO = 'w-full sm:w-36 bg-gray-900 border rounded-lg p-3 text-right tabular-nums text-gray-100 focus:outline-none focus:ring-1 disabled:opacity-70 disabled:cursor-not-allowed';

    function contaAtrasada(conta) {
        if (estado.salvoEm || conta.valor !== null || !conta.dia) return false;
        const mesHoje = estado.hoje.slice(0, 7);
        if (estado.mes > mesHoje) return false;
        if (estado.mes < mesHoje) return true;
        const [ano, mes] = estado.mes.split('-').map(Number);
        const ultimoDia = new Date(ano, mes, 0).getDate();
        return Number(estado.hoje.slice(8)) > Math.min(conta.dia, ultimoDia);
    }

    function situacaoConta(conta) {
        if (conta.valor !== null) return '<span class="text-emerald-400 font-semibold">✓ Paga</span>';
        if (contaAtrasada(conta)) return `<span class="text-amber-400 font-semibold">⚠ Atrasada · dia ${conta.dia}</span>`;
        return conta.dia ? `<span class="text-gray-400">Vence dia ${conta.dia}</span>` : '<span class="text-gray-500">Sem vencimento</span>';
    }

    function htmlConta(conta) {
        const editavel = !estado.salvoEm;
        const anterior = conta.anterior === null || !editavel ? '' : `
            <span class="text-gray-500">· Último: ${Contas.moeda(conta.anterior)} (${Contas.mesCurto(conta.anteriorMes)})</span>
            ${conta.valor === null ? `<button type="button" data-usar="${conta.id}" class="text-blue-400 hover:text-blue-300 hover:underline font-semibold">usar</button>` : ''}`;
        const desfazer = editavel && conta.valor !== null
            ? `<button type="button" data-desfazer="${conta.id}" class="text-blue-400 hover:text-blue-300 hover:underline font-semibold">desfazer</button>`
            : '';
        const categoria = conta.categoria ? `<span class="text-gray-500">· ${Contas.esc(conta.categoria)}</span>` : '';
        return `
            <div data-conta="${conta.id}" class="bg-gray-800 border ${conta.valor !== null ? 'border-emerald-500/30' : 'border-gray-700'} rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 transition-colors">
                <div class="min-w-0">
                    <div class="font-semibold text-gray-100 break-words">${Contas.esc(conta.nome)}</div>
                    <div class="text-xs flex flex-wrap items-center gap-x-2 gap-y-1 mt-1">
                        <span data-situacao>${situacaoConta(conta)}</span> ${desfazer} ${categoria} ${anterior}
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:shrink-0">
                    <span class="text-gray-500 text-sm" aria-hidden="true">R$</span>
                    <input type="text" inputmode="decimal" autocomplete="off" placeholder="0,00" value="${Contas.valorCampo(conta.valor)}"
                           aria-label="Valor de ${Contas.esc(conta.nome)}" data-valor="${conta.id}" ${editavel ? '' : 'disabled'}
                           class="${CLASSE_CAMPO} border-gray-700 focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>`;
    }

    function atualizarResumo() {
        const preenchidas = estado.contas.filter(c => c.valor !== null);
        const total = preenchidas.reduce((soma, c) => soma + c.valor, 0);
        document.getElementById('totalMes').textContent = Contas.moeda(total);
        document.getElementById('progressoMes').textContent = `${preenchidas.length} de ${estado.contas.length} contas pagas`;
    }

    function renderizar() {
        document.getElementById('tituloMes').textContent = Contas.mesLabel(estado.mes);
        document.getElementById('situacaoMes').textContent = estado.salvoEm
            ? '🔒 Mês salvo: já entra nos Insights'
            : 'Em aberto: os valores são gravados enquanto você preenche';
        document.getElementById('mesSeguinte').disabled = estado.mes >= Contas.somarMeses(estado.hoje.slice(0, 7), 1);

        const vazio = estado.contas.length === 0;
        document.getElementById('semContas').classList.toggle('hidden', !vazio || !!estado.salvoEm);
        document.getElementById('barraResumo').classList.toggle('hidden', vazio && !estado.salvoEm);
        document.getElementById('listaContas').innerHTML = estado.contas.map(htmlConta).join('');

        const botao = document.getElementById('botaoMes');
        botao.textContent = estado.salvoEm ? 'Reabrir mês' : 'Salvar mês';
        botao.className = 'shrink-0 py-2.5 px-4 sm:px-6 rounded-xl font-semibold transition-colors disabled:opacity-50 disabled:cursor-wait ' +
            (estado.salvoEm ? 'border border-gray-600 text-gray-300 hover:bg-gray-700 hover:text-white' : 'bg-blue-600 hover:bg-blue-500 text-white shadow-lg');
        atualizarResumo();
    }

    async function carregarMes(mes, atualizarUrl = true) {
        try {
            estado = await Contas.api('mes', undefined, { mes });
            if (atualizarUrl) history.replaceState(null, '', '?mes=' + estado.mes);
            renderizar();
        } catch (erro) {
            ML.toast(erro.message || 'Não foi possível carregar o mês.', 'erro');
        }
    }

    function atualizarLinha(conta) {
        const linha = document.querySelector(`[data-conta="${conta.id}"]`);
        linha.outerHTML = htmlConta(conta);
    }

    // Altera a tela na hora e grava em seguida. Se falhar, recarrega o que está no banco.
    function gravarValor(conta, valor) {
        const mes = estado.mes;
        conta.valor = valor;
        atualizarResumo();
        filaGravacao = filaGravacao
            .then(() => Contas.api('valor', { conta_id: conta.id, mes, valor }))
            .catch(erro => {
                ML.toast(erro.message || 'Não foi possível salvar. Verifique a conexão.', 'erro');
                return carregarMes(mes, false);
            });
        return filaGravacao;
    }

    function aoAlterarValor(campo) {
        const conta = estado.contas.find(c => c.id === Number(campo.dataset.valor));
        const valor = Contas.lerValor(campo.value);
        if (valor === undefined) {
            campo.classList.add('border-red-500');
            ML.toast(`Valor inválido em ${conta.nome}. Use um formato como 187,45.`, 'erro');
            return;
        }
        campo.classList.remove('border-red-500');
        if (valor === conta.valor) {
            campo.value = Contas.valorCampo(valor);
            return;
        }
        gravarValor(conta, valor);
        const proximoFoco = document.activeElement?.dataset?.valor;
        atualizarLinha(conta);
        if (proximoFoco) document.querySelector(`[data-valor="${proximoFoco}"]`)?.focus();
    }

    async function salvarOuReabrir() {
        const botao = document.getElementById('botaoMes');
        const mes = estado.mes;
        await filaGravacao;

        if (estado.salvoEm) {
            const confirmou = await ML.confirmar(`Reabrir ${Contas.mesLabel(mes)} para edição? Ele sai dos Insights até ser salvo de novo.`, {
                titulo: 'Reabrir mês',
                textoConfirmar: 'Reabrir'
            });
            if (!confirmou) return;
        } else {
            const faltando = estado.contas.filter(c => c.valor === null).map(c => c.nome);
            if (faltando.length) {
                const confirmou = await ML.confirmar(`Sem valor em ${faltando.length} conta(s): ${faltando.join(', ')}. Salvar o mês mesmo assim? Elas ficam de fora dos Insights.`, {
                    titulo: 'Salvar mês',
                    textoConfirmar: 'Salvar mês'
                });
                if (!confirmou) return;
            }
        }

        botao.disabled = true;
        try {
            await Contas.api(estado.salvoEm ? 'reabrir' : 'salvar', { mes });
            ML.toast(estado.salvoEm ? 'Mês reaberto.' : `${Contas.mesLabel(mes)} salvo.`, 'sucesso');
            await carregarMes(mes);
        } catch (erro) {
            ML.toast(erro.message || 'Não foi possível concluir. Verifique a conexão.', 'erro');
        } finally {
            botao.disabled = false;
        }
    }

    const lista = document.getElementById('listaContas');
    lista.addEventListener('change', e => {
        if (e.target.matches('[data-valor]')) aoAlterarValor(e.target);
    });
    lista.addEventListener('keydown', e => {
        if (e.key !== 'Enter' || !e.target.matches('[data-valor]')) return;
        e.preventDefault();
        // Passa para a próxima conta; o evento change grava o valor ao sair do campo
        const campos = [...lista.querySelectorAll('[data-valor]')];
        const proximo = campos[campos.indexOf(e.target) + 1];
        if (proximo) proximo.focus(); else e.target.blur();
    });
    lista.addEventListener('click', e => {
        const usar = e.target.closest('[data-usar]');
        const desfazer = e.target.closest('[data-desfazer]');
        if (!usar && !desfazer) return;
        const conta = estado.contas.find(c => c.id === Number(usar ? usar.dataset.usar : desfazer.dataset.desfazer));
        gravarValor(conta, usar ? conta.anterior : null);
        atualizarLinha(conta);
    });
    document.getElementById('botaoMes').addEventListener('click', salvarOuReabrir);
    document.getElementById('mesAnterior').addEventListener('click', () => carregarMes(Contas.somarMeses(estado.mes, -1)));
    document.getElementById('mesSeguinte').addEventListener('click', () => carregarMes(Contas.somarMeses(estado.mes, 1)));

    carregarMes(/^\d{4}-(0[1-9]|1[0-2])$/.test(parametroMes || '') ? parametroMes : Contas.mesAtual(), false);
    </script>
</body>
</html>
