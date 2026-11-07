<?php
require __DIR__ . '/../../src/auth.php';
$paginaContas = 'cadastro';
$classeCampo = 'w-full bg-gray-900 border border-gray-700 rounded-lg p-3 text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Contas</title>
    <script src="/assets/tailwind.js"></script>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen p-8 font-sans selection:bg-blue-500 selection:text-white">
    <?php require __DIR__ . '/../../src/nav.php'; ?>
    <div class="max-w-3xl mx-auto">
        <?php require __DIR__ . '/../../src/contas/cabecalho.php'; ?>

        <form id="formConta" class="bg-gray-800 border border-gray-700 rounded-xl p-6 mb-8">
            <h2 id="tituloForm" class="text-xl font-semibold text-gray-200 mb-4">Nova conta</h2>
            <input type="hidden" id="contaId">
            <div class="grid grid-cols-1 sm:grid-cols-6 gap-4">
                <div class="sm:col-span-3">
                    <label for="nome" class="block text-sm font-medium text-gray-400 mb-1">Nome</label>
                    <input id="nome" required maxlength="100" autocomplete="off" placeholder="Ex.: Luz" class="<?= $classeCampo ?>">
                </div>
                <div class="sm:col-span-2">
                    <label for="categoria" class="block text-sm font-medium text-gray-400 mb-1">Categoria (opcional)</label>
                    <input id="categoria" list="listaCategorias" maxlength="60" autocomplete="off" placeholder="Ex.: Casa" class="<?= $classeCampo ?>">
                    <datalist id="listaCategorias"></datalist>
                </div>
                <div>
                    <label for="dia" class="block text-sm font-medium text-gray-400 mb-1">Dia (opcional)</label>
                    <input id="dia" type="number" min="1" max="31" step="1" inputmode="numeric" placeholder="10" class="<?= $classeCampo ?>">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" id="cancelarEdicao" class="hidden px-5 py-2.5 rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">Cancelar</button>
                <button type="submit" id="botaoSalvar" class="px-6 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-semibold transition-colors shadow-lg">Adicionar conta</button>
            </div>
        </form>

        <p id="semContas" class="hidden text-center text-gray-400 py-6">Nenhuma conta cadastrada ainda.</p>
        <div id="lista" class="space-y-3"></div>
    </div>

    <script src="/assets/ui.js?v=<?= filemtime(__DIR__ . '/../assets/ui.js') ?>"></script>
    <script src="comum.js?v=<?= filemtime(__DIR__ . '/comum.js') ?>"></script>
    <script>
    let contas = [];
    const campo = id => document.getElementById(id);

    function limparFormulario() {
        campo('formConta').reset();
        campo('contaId').value = '';
        campo('tituloForm').textContent = 'Nova conta';
        campo('botaoSalvar').textContent = 'Adicionar conta';
        campo('cancelarEdicao').classList.add('hidden');
    }

    function renderizar() {
        campo('semContas').classList.toggle('hidden', contas.length > 0);
        const categorias = [...new Set(contas.map(c => c.categoria).filter(Boolean))].sort();
        campo('listaCategorias').innerHTML = categorias.map(c => `<option value="${Contas.esc(c)}">`).join('');
        campo('lista').innerHTML = contas.map(c => {
            const detalhes = [
                c.categoria ? Contas.esc(c.categoria) : 'Sem categoria',
                c.dia ? `dia ${c.dia}` : 'sem vencimento',
                c.ultimoValor !== null ? `último: ${Contas.moeda(c.ultimoValor)} (${Contas.mesCurto(c.ultimoMes)})` : 'sem valores'
            ].join(' · ');
            return `
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 flex flex-wrap items-center justify-between gap-3 ${c.ativa ? '' : 'opacity-60'}">
                    <div class="min-w-0">
                        <div class="font-semibold break-words">${Contas.esc(c.nome)}${c.ativa ? '' : ' <span class="text-xs text-gray-400 font-normal">(desativada)</span>'}</div>
                        <div class="text-xs text-gray-400 mt-1">${detalhes}</div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" data-editar="${c.id}" class="px-3 py-1.5 rounded-lg border border-gray-600 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">Editar</button>
                        <button type="button" data-alternar="${c.id}" class="px-3 py-1.5 rounded-lg border border-gray-600 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">${c.ativa ? 'Desativar' : 'Reativar'}</button>
                    </div>
                </div>`;
        }).join('');
    }

    async function carregar() {
        try {
            contas = (await Contas.api('contas')).contas;
            renderizar();
        } catch (erro) {
            ML.toast(erro.message || 'Não foi possível carregar as contas.', 'erro');
        }
    }

    campo('formConta').addEventListener('submit', async e => {
        e.preventDefault();
        const editando = !!campo('contaId').value;
        try {
            await Contas.api('conta_salvar', {
                id: campo('contaId').value || null,
                nome: campo('nome').value,
                categoria: campo('categoria').value,
                dia: campo('dia').value
            });
            ML.toast(editando ? 'Conta atualizada.' : 'Conta adicionada.', 'sucesso');
            limparFormulario();
            campo('nome').focus();
            carregar();
        } catch (erro) {
            ML.toast(erro.message || 'Não foi possível salvar. Verifique a conexão.', 'erro');
        }
    });

    campo('cancelarEdicao').addEventListener('click', limparFormulario);

    campo('lista').addEventListener('click', async e => {
        const editar = e.target.closest('[data-editar]');
        const alternar = e.target.closest('[data-alternar]');
        if (editar) {
            const conta = contas.find(c => c.id === Number(editar.dataset.editar));
            campo('contaId').value = conta.id;
            campo('nome').value = conta.nome;
            campo('categoria').value = conta.categoria || '';
            campo('dia').value = conta.dia || '';
            campo('tituloForm').textContent = 'Editar conta';
            campo('botaoSalvar').textContent = 'Salvar alterações';
            campo('cancelarEdicao').classList.remove('hidden');
            campo('formConta').scrollIntoView({ behavior: 'smooth', block: 'start' });
            campo('nome').focus();
        } else if (alternar) {
            const conta = contas.find(c => c.id === Number(alternar.dataset.alternar));
            if (conta.ativa) {
                const confirmou = await ML.confirmar(`Desativar ${conta.nome}? Ela deixa de aparecer nos próximos meses, e o histórico é mantido.`, {
                    titulo: 'Desativar conta',
                    textoConfirmar: 'Desativar',
                    perigo: true
                });
                if (!confirmou) return;
            }
            try {
                await Contas.api('conta_ativar', { id: conta.id, ativa: !conta.ativa });
                carregar();
            } catch (erro) {
                ML.toast(erro.message || 'Não foi possível alterar a conta.', 'erro');
            }
        }
    });

    carregar();
    </script>
</body>
</html>
