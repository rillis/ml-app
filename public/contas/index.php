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
<body class="bg-gray-900 text-gray-100 min-h-screen p-4 sm:p-8 font-sans selection:bg-blue-500 selection:text-white">
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

    <input type="file" id="arquivoFatura" accept="application/pdf,.pdf" class="hidden">

    <!-- Revisão da fatura lida do PDF: nada é gravado antes de Aplicar -->
    <div id="modalFatura" class="hidden fixed inset-0 z-[60] bg-black/70 backdrop-blur-sm justify-center items-center p-4">
        <div role="dialog" aria-modal="true" aria-labelledby="faturaTitulo" class="bg-gray-800 border border-gray-700 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
            <div class="p-6 pb-4 border-b border-gray-700">
                <h2 id="faturaTitulo" class="text-xl font-bold"></h2>
                <p id="faturaArquivo" class="text-sm text-gray-400 break-all"></p>
            </div>
            <div class="p-6 overflow-y-auto space-y-5">
                <div id="faturaAvisos" class="space-y-2 text-sm empty:hidden"></div>
                <div class="flex flex-wrap items-end gap-4">
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-400 mb-1">Valor da fatura</span>
                        <span class="flex items-center gap-2">
                            <span class="text-gray-500 text-sm" aria-hidden="true">R$</span>
                            <input id="faturaValor" type="text" inputmode="decimal" autocomplete="off" placeholder="0,00"
                                   class="w-40 bg-gray-900 border border-gray-700 rounded-lg p-3 text-right tabular-nums text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </span>
                    </label>
                    <p id="faturaVencimento" class="text-sm text-gray-400 pb-3"></p>
                </div>
                <div>
                    <h3 id="faturaTituloParcelas" class="font-semibold text-gray-200 mb-1"></h3>
                    <p class="text-xs text-gray-500 mb-3">Desmarque o que não quiser acompanhar. O apelido segue para as próximas faturas da mesma compra.</p>
                    <div id="faturaParcelas" class="space-y-2"></div>
                </div>
                <div>
                    <h3 id="faturaTituloAssinaturas" class="font-semibold text-gray-200 mb-1"></h3>
                    <p class="text-xs text-gray-500 mb-3">Reconhecidas pela lista de serviços ou por já estarem na aba Assinaturas. Desmarcar = não é assinatura (não volta a ser sugerida).</p>
                    <div id="faturaAssinaturas" class="space-y-2"></div>
                    <div id="faturaSumiram" class="mt-3 text-sm empty:hidden"></div>
                    <details id="faturaOutrosBloco" class="mt-3">
                        <summary id="faturaOutrosTitulo" class="cursor-pointer text-sm text-gray-400 hover:text-gray-200 select-none"></summary>
                        <div id="faturaOutros" class="mt-2 space-y-2"></div>
                    </details>
                </div>
            </div>
            <div class="p-4 sm:p-6 pt-4 border-t border-gray-700 flex justify-end gap-3">
                <button type="button" id="faturaCancelar" class="px-5 py-2.5 rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">Cancelar</button>
                <button type="button" id="faturaAplicar" class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-semibold transition-colors shadow-lg disabled:opacity-50 disabled:cursor-wait">Aplicar</button>
            </div>
        </div>
    </div>

    <script src="/assets/ui.js?v=<?= filemtime(__DIR__ . '/../assets/ui.js') ?>"></script>
    <script src="comum.js?v=<?= filemtime(__DIR__ . '/comum.js') ?>"></script>
    <script src="fatura-pdf.js?v=<?= filemtime(__DIR__ . '/fatura-pdf.js') ?>"></script>
    <script src="assinaturas-conhecidas.js?v=<?= filemtime(__DIR__ . '/assinaturas-conhecidas.js') ?>"></script>
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

    function htmlParcelas(conta) {
        const lancadas = conta.parcelas.filter(p => !p.prevista);
        const lista = lancadas.length ? lancadas : conta.parcelas;
        if (!lista.length) return '';
        const prevista = !lancadas.length;
        const soma = lista.reduce((total, p) => total + (p.valor || 0), 0);
        const itens = lista.map(p => `
            <li class="flex justify-between gap-3">
                <span class="min-w-0 break-words">${Contas.esc(p.apelido || p.descricao)}${p.apelido ? ` <span class="text-gray-500">· ${Contas.esc(p.descricao)}</span>` : ''}</span>
                <span class="shrink-0 tabular-nums text-gray-300">${p.numero}/${p.total}${p.numero === p.total ? ' <span class="text-emerald-400" title="Última parcela">✓</span>' : ''} · ${p.valor === null ? '—' : Contas.moeda(p.valor)}</span>
            </li>`).join('');
        return `
            <details class="mt-3 border-t border-gray-700 pt-3 text-sm">
                <summary class="cursor-pointer text-gray-400 hover:text-gray-200 select-none">${prevista ? '🔮 Parcelas previstas' : '🧾 Parcelas'}: ${lista.length} · ${Contas.moeda(soma)}</summary>
                <ul class="mt-2 space-y-1 ${prevista ? 'text-gray-400 italic' : ''}">${itens}</ul>
            </details>`;
    }

    function htmlAssinaturas(conta) {
        if (!conta.assinaturas.length) return '';
        const soma = conta.assinaturas.reduce((total, a) => total + a.valor, 0);
        const itens = conta.assinaturas.map(a => `
            <li class="flex justify-between gap-3">
                <span class="min-w-0 break-words">${Contas.esc(a.apelido || a.descricao)}${a.apelido ? ` <span class="text-gray-500">· ${Contas.esc(a.descricao)}</span>` : ''}</span>
                <span class="shrink-0 tabular-nums text-gray-300">${Contas.moeda(a.valor)}</span>
            </li>`).join('');
        return `
            <details class="mt-3 border-t border-gray-700 pt-3 text-sm">
                <summary class="cursor-pointer text-gray-400 hover:text-gray-200 select-none">🔁 Assinaturas: ${conta.assinaturas.length} · ${Contas.moeda(soma)}</summary>
                <ul class="mt-2 space-y-1">${itens}</ul>
            </details>`;
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
        const botaoPdf = conta.cartao && editavel
            ? `<button type="button" data-pdf="${conta.id}" title="Ler fatura em PDF" aria-label="Ler fatura em PDF de ${Contas.esc(conta.nome)}"
                       class="shrink-0 h-12 w-12 rounded-lg bg-gray-900 border border-gray-700 hover:border-blue-500 text-lg transition-colors">📄</button>`
            : '';
        return `
            <div data-conta="${conta.id}" class="bg-gray-800 border ${conta.valor !== null ? 'border-emerald-500/30' : 'border-gray-700'} rounded-xl p-4 transition-colors">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
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
                    ${botaoPdf}
                </div>
            </div>
            ${htmlParcelas(conta)}
            ${htmlAssinaturas(conta)}
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
        if (desfazer && (conta.parcelas.some(p => !p.prevista) || conta.assinaturas.length)) {
            desfazerFatura(conta);
            return;
        }
        gravarValor(conta, usar ? conta.anterior : null);
        atualizarLinha(conta);
    });
    // ---------- Fatura em PDF ----------

    const senhasDaSessao = {}; // Só na memória da aba: some ao recarregar a página
    let contaDaFatura = null;
    let revisao = null;

    function ePdf(arquivo) {
        return arquivo.type === 'application/pdf' || /\.pdf$/i.test(arquivo.name);
    }

    async function lerFatura(conta, arquivo) {
        if (!ePdf(arquivo)) {
            ML.toast('Escolha um arquivo PDF.', 'erro');
            return;
        }
        const banco = FaturaPdf.BANCOS[conta.cartao];
        const mes = estado.mes;
        let tentativa = null;
        let usouGuardada = false;
        const pedirSenha = async errou => {
            if (!errou && senhasDaSessao[conta.cartao] && !usouGuardada) {
                usouGuardada = true;
                return (tentativa = senhasDaSessao[conta.cartao]);
            }
            tentativa = await ML.perguntarSenha({
                titulo: 'PDF com senha',
                dica: `A fatura ${banco} (${arquivo.name}) é protegida. A senha só é usada aqui no navegador.`,
                erro: errou ? 'Senha incorreta. Tente de novo.' : '',
            });
            return tentativa;
        };

        ML.toast(`Lendo ${arquivo.name}...`);
        try {
            const [resultado, conhecidos, assinaturas] = await Promise.all([
                FaturaPdf.lerArquivo(arquivo, conta.cartao, pedirSenha),
                Contas.api('parcelamentos').then(d => d.parcelamentos).catch(() => []),
                Contas.api('assinaturas').catch(() => ({ assinaturas: [], ignoradas: [] })),
            ]);
            if (tentativa) senhasDaSessao[conta.cartao] = tentativa;
            if (estado.mes !== mes) return;
            abrirRevisao(conta, arquivo.name, resultado, conhecidos, assinaturas);
        } catch (erro) {
            if (erro.name === 'Cancelado') return;
            console.error(erro);
            ML.toast(`${arquivo.name}: não foi possível ler a fatura. Preencha manualmente.`, 'erro');
        }
    }

    function aviso(texto, tipo) {
        const cores = { alerta: 'bg-amber-900/40 border-amber-500/40 text-amber-200', info: 'bg-gray-900 border-gray-700 text-gray-300' };
        return `<p class="border rounded-lg px-3 py-2 ${cores[tipo]}">${Contas.esc(texto)}</p>`;
    }

    function htmlItemAssinatura(a, i) {
        const etiquetas = {
            acompanhada: '<span class="text-emerald-400">já acompanhada</span>',
            valor: `<span class="text-amber-300 font-semibold">valor mudou: ${Contas.moeda(a.valorAnterior)} → ${Contas.moeda(a.valor)}</span>`,
            reativada: '<span class="text-amber-300">estava cancelada, voltou a cobrar</span>',
            nova: `<span class="text-blue-300">nova · ${Contas.esc(a.servico)}</span>`,
            ignorada: '<span class="text-gray-500">marcada antes como não assinatura</span>',
            outro: '',
        };
        const etiqueta = etiquetas[a.motivo];
        return `
            <div class="bg-gray-900 border border-gray-700 rounded-lg p-3 flex flex-col sm:flex-row sm:items-center gap-3">
                <label class="flex items-start gap-3 min-w-0 flex-1 cursor-pointer">
                    <input type="checkbox" data-assinatura-incluir="${i}" ${a.incluir ? 'checked' : ''} class="mt-1 h-4 w-4 accent-blue-500">
                    <span class="min-w-0">
                        <span class="block font-medium break-words">${Contas.esc(a.descricao)}</span>
                        <span class="block text-xs text-gray-400">${Contas.moeda(a.valor)}${etiqueta ? ' · ' + etiqueta : ''}</span>
                    </span>
                </label>
                <input type="text" data-assinatura-apelido="${i}" maxlength="100" autocomplete="off" placeholder="Apelido (opcional)" value="${Contas.esc(a.apelido)}"
                       aria-label="Apelido de ${Contas.esc(a.descricao)}"
                       class="w-full sm:w-48 bg-gray-800 border border-gray-700 rounded-lg p-2 text-sm text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>`;
    }

    function renderizarAssinaturasRevisao() {
        const { assinaturas, sumiram } = revisao;
        const marcadas = assinaturas.filter(a => a.incluir);
        const principais = assinaturas.map((a, i) => [a, i]).filter(([a]) => a.sugerida);
        const outros = assinaturas.map((a, i) => [a, i]).filter(([a]) => !a.sugerida);
        document.getElementById('faturaTituloAssinaturas').textContent = principais.length || marcadas.length
            ? `Assinaturas (${marcadas.length}) · ${Contas.moeda(marcadas.reduce((s, a) => s + a.valor, 0))}`
            : 'Nenhuma assinatura reconhecida';
        document.getElementById('faturaAssinaturas').innerHTML = principais.map(([a, i]) => htmlItemAssinatura(a, i)).join('');
        document.getElementById('faturaSumiram').innerHTML = sumiram.length ? aviso(
            `Não vieram nesta fatura: ${sumiram.map(a => `${a.apelido || a.descricao} (${Contas.moeda(a.valor)})`).join(', ')}. Na aba Assinaturas elas aparecem como "cancelada?".`, 'alerta') : '';
        document.getElementById('faturaOutrosBloco').classList.toggle('hidden', outros.length === 0);
        document.getElementById('faturaOutrosTitulo').textContent = `Marcar outro lançamento como assinatura (${outros.length})`;
        document.getElementById('faturaOutros').innerHTML = outros.map(([a, i]) => htmlItemAssinatura(a, i)).join('');
    }

    function abrirRevisao(conta, nomeArquivo, resultado, conhecidos, dadosAssinaturas) {
        const mes = estado.mes;
        const banco = FaturaPdf.BANCOS[conta.cartao];
        const itens = resultado.parcelas.map(p => {
            const inicio = Contas.somarMeses(mes, -(p.numero - 1));
            const conhecido = conhecidos.find(k => k.contaId === conta.id && k.descricao === p.descricao && k.total === p.total && k.mesInicio === inicio);
            return { ...p, incluir: true, apelido: conhecido?.apelido || '', conhecido: !!conhecido };
        });
        const daConta = x => x.contaId === conta.id;
        const casadas = AssinaturasConhecidas.casar(resultado.avista,
            dadosAssinaturas.assinaturas.filter(daConta), dadosAssinaturas.ignoradas.filter(daConta), mes);
        revisao = { conta, mes, itens, assinaturas: casadas.itens, sumiram: casadas.sumiram };

        const avisos = [];
        if (!resultado.temTexto) avisos.push(aviso('O PDF não tem texto (parece digitalizado). Preencha manualmente.', 'alerta'));
        if (resultado.detectado && resultado.detectado !== conta.cartao) {
            avisos.push(aviso(`Este PDF parece ser do ${FaturaPdf.BANCOS[resultado.detectado]}, não do ${banco}. Confira antes de aplicar.`, 'alerta'));
        }
        if (resultado.total === null && resultado.temTexto) avisos.push(aviso('Não encontrei o valor total na fatura. Preencha o valor.', 'alerta'));
        if (resultado.vencimento && resultado.vencimento.slice(0, 7) !== mes) {
            avisos.push(aviso(`A fatura vence em ${resultado.vencimento.split('-').reverse().join('/')}, mas você está em ${Contas.mesLabel(mes)}. O valor e as parcelas entram neste mês.`, 'alerta'));
        }
        if (conta.valor !== null || conta.parcelas.some(p => !p.prevista) || conta.assinaturas.length) {
            avisos.push(aviso('Aplicar substitui o valor, as parcelas e as assinaturas já lançados neste cartão neste mês.', 'info'));
        }

        document.getElementById('faturaTitulo').textContent = `Fatura ${banco} · ${Contas.mesLabel(mes)}`;
        document.getElementById('faturaArquivo').textContent = nomeArquivo;
        document.getElementById('faturaAvisos').innerHTML = avisos.join('');
        document.getElementById('faturaValor').value = Contas.valorCampo(resultado.total);
        document.getElementById('faturaValor').classList.remove('border-red-500');
        document.getElementById('faturaVencimento').textContent = resultado.vencimento
            ? `Vencimento: ${resultado.vencimento.split('-').reverse().join('/')}` : '';
        document.getElementById('faturaTituloParcelas').textContent = itens.length
            ? `Compras parceladas (${itens.length}) · ${Contas.moeda(itens.reduce((s, p) => s + p.valor, 0))}`
            : 'Nenhuma compra parcelada encontrada';
        document.getElementById('faturaParcelas').innerHTML = itens.map((p, i) => {
            const faltam = p.total - p.numero;
            const fim = Contas.somarMeses(mes, faltam);
            return `
                <div class="bg-gray-900 border border-gray-700 rounded-lg p-3 flex flex-col sm:flex-row sm:items-center gap-3">
                    <label class="flex items-start gap-3 min-w-0 flex-1 cursor-pointer">
                        <input type="checkbox" data-incluir="${i}" checked class="mt-1 h-4 w-4 accent-blue-500">
                        <span class="min-w-0">
                            <span class="block font-medium break-words">${Contas.esc(p.descricao)}</span>
                            <span class="block text-xs text-gray-400">Parcela ${p.numero} de ${p.total} · ${Contas.moeda(p.valor)} · ${faltam ? `faltam ${faltam}, última em ${Contas.mesCurto(fim)}` : 'última parcela'}</span>
                        </span>
                    </label>
                    <input type="text" data-apelido="${i}" maxlength="100" autocomplete="off" placeholder="Apelido (opcional)" value="${Contas.esc(p.apelido)}"
                           aria-label="Apelido de ${Contas.esc(p.descricao)}"
                           class="w-full sm:w-48 bg-gray-800 border border-gray-700 rounded-lg p-2 text-sm text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>`;
        }).join('');
        document.getElementById('faturaOutrosBloco').open = false;
        renderizarAssinaturasRevisao();

        const modal = document.getElementById('modalFatura');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.getElementById('faturaValor').focus();
    }

    function fecharRevisao() {
        const modal = document.getElementById('modalFatura');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        const conta = revisao?.conta;
        revisao = null;
        if (conta) document.querySelector(`[data-pdf="${conta.id}"]`)?.focus();
    }

    async function aplicarFatura() {
        const campoValor = document.getElementById('faturaValor');
        const valor = Contas.lerValor(campoValor.value);
        if (valor === undefined) {
            campoValor.classList.add('border-red-500');
            ML.toast('Valor inválido. Use um formato como 1.140,05.', 'erro');
            campoValor.focus();
            return;
        }
        const { conta, mes, itens, assinaturas } = revisao;
        const botao = document.getElementById('faturaAplicar');
        botao.disabled = true;
        try {
            await filaGravacao;
            await Contas.api('fatura', {
                conta_id: conta.id,
                mes,
                valor,
                parcelas: itens.filter(p => p.incluir).map(({ descricao, numero, total, valor, apelido }) => ({ descricao, numero, total, valor, apelido })),
                // Sugeridas e desmarcadas vão como "não é assinatura"; outros lançamentos só se marcados
                assinaturas: assinaturas.filter(a => a.incluir || a.sugerida)
                    .map(({ id, descricao, valor, apelido, incluir }) => ({ id: incluir ? id : null, descricao, valor, apelido, assinatura: incluir })),
            });
            const quantas = itens.filter(p => p.incluir).length;
            const quantasAssinaturas = assinaturas.filter(a => a.incluir).length;
            const partes = [quantas && `${quantas} parcela(s)`, quantasAssinaturas && `${quantasAssinaturas} assinatura(s)`].filter(Boolean);
            ML.toast(`${conta.nome}: fatura aplicada${partes.length ? ' com ' + partes.join(' e ') : ''}.`, 'sucesso');
            fecharRevisao();
            await carregarMes(mes, false);
        } catch (erro) {
            ML.toast(erro.message || 'Não foi possível salvar. Verifique a conexão.', 'erro');
        } finally {
            botao.disabled = false;
        }
    }

    async function desfazerFatura(conta) {
        const confirmou = await ML.confirmar(`Remover o valor, as parcelas e as assinaturas da fatura ${conta.nome} em ${Contas.mesLabel(estado.mes)}? Os apelidos das compras e assinaturas que aparecem em outros meses são mantidos.`, {
            titulo: 'Desfazer fatura',
            textoConfirmar: 'Remover',
            perigo: true,
        });
        if (!confirmou) return;
        const mes = estado.mes;
        try {
            await filaGravacao;
            await Contas.api('fatura', { conta_id: conta.id, mes, valor: null, parcelas: [] });
            await carregarMes(mes, false);
        } catch (erro) {
            ML.toast(erro.message || 'Não foi possível desfazer. Verifique a conexão.', 'erro');
        }
    }

    lista.addEventListener('click', e => {
        const botao = e.target.closest('[data-pdf]');
        if (!botao) return;
        contaDaFatura = estado.contas.find(c => c.id === Number(botao.dataset.pdf));
        document.getElementById('arquivoFatura').click();
    });
    document.getElementById('arquivoFatura').addEventListener('change', e => {
        const arquivo = e.target.files[0];
        e.target.value = '';
        if (arquivo && contaDaFatura) lerFatura(contaDaFatura, arquivo);
    });
    document.getElementById('faturaParcelas').addEventListener('input', e => {
        const i = e.target.dataset.incluir ?? e.target.dataset.apelido;
        if (i === undefined) return;
        if (e.target.dataset.incluir !== undefined) revisao.itens[i].incluir = e.target.checked;
        else revisao.itens[i].apelido = e.target.value;
    });
    document.getElementById('modalFatura').addEventListener('input', e => {
        const i = e.target.dataset.assinaturaIncluir ?? e.target.dataset.assinaturaApelido;
        if (i === undefined) return;
        const item = revisao.assinaturas[i];
        if (e.target.dataset.assinaturaIncluir !== undefined) {
            item.incluir = e.target.checked;
            // Atualiza só o título, sem redesenhar (o foco continua no lugar)
            const marcadas = revisao.assinaturas.filter(a => a.incluir);
            document.getElementById('faturaTituloAssinaturas').textContent = `Assinaturas (${marcadas.length}) · ${Contas.moeda(marcadas.reduce((s, a) => s + a.valor, 0))}`;
        } else {
            item.apelido = e.target.value;
        }
    });
    document.getElementById('faturaCancelar').addEventListener('click', fecharRevisao);
    document.getElementById('faturaAplicar').addEventListener('click', aplicarFatura);
    document.getElementById('modalFatura').addEventListener('keydown', e => {
        if (e.key === 'Escape') fecharRevisao();
        if (e.key === 'Enter' && e.target.matches('input[type="text"]')) {
            e.preventDefault();
            aplicarFatura();
        }
    });

    document.getElementById('botaoMes').addEventListener('click', salvarOuReabrir);
    document.getElementById('mesAnterior').addEventListener('click', () => carregarMes(Contas.somarMeses(estado.mes, -1)));
    document.getElementById('mesSeguinte').addEventListener('click', () => carregarMes(Contas.somarMeses(estado.mes, 1)));

    carregarMes(/^\d{4}-(0[1-9]|1[0-2])$/.test(parametroMes || '') ? parametroMes : Contas.mesAtual(), false);
    </script>
</body>
</html>
