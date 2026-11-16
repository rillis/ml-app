<?php
require __DIR__ . '/../../../src/auth.php';
require __DIR__ . '/../../../src/db.php';
require __DIR__ . '/../../../src/dash/fila.php';
$pdo = ml_db('ml_jrf');
$dados = fila_dados($pdo);

$cards = [
    'pendentes'    => ['Pendentes', 'text-blue-400'],
    'processando'  => ['Processando', 'text-purple-400'],
    'falhas'       => ['Falhas', 'text-red-400'],
    'enviados_24h' => ['Enviados (24h)', 'text-emerald-400'],
    'pulados_24h'  => ['Pulados (24h)', 'text-gray-300'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fila do Webhook</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <script src="/assets/tailwind.js"></script>
</head>
<body class="bg-gray-900 text-gray-100 p-4 sm:p-8 font-sans min-h-screen">
    <?php require __DIR__ . '/../../../src/nav.php'; ?>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold text-gray-100">Fila do Webhook <span class="text-base font-normal text-gray-400">(ml-jrf)</span></h1>
            <p class="text-sm mt-1">
                <span id="saude" class="font-semibold <?= $dados['saude']['ok'] ? 'text-emerald-400' : 'text-red-400' ?>">● <?= dash_h($dados['saude']['texto']) ?></span>
                <span id="statusAtualizacao" class="ml-2 text-gray-500"></span>
            </p>
        </div>
        <a href="/dash/" class="py-2 text-sm text-blue-400 hover:underline">← Voltar ao painel</a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
        <?php foreach ($cards as $chave => [$rotulo, $cor]): ?>
            <div class="bg-gray-800 p-4 rounded-lg shadow-lg">
                <span class="text-xs uppercase tracking-wider text-gray-400"><?= $rotulo ?></span>
                <span class="block text-2xl font-bold <?= $cor ?>" data-resumo="<?= $chave ?>"><?= (int)$dados['resumo'][$chave] ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="bg-gray-800 p-6 rounded-lg shadow-lg mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 border-b border-gray-700 pb-2">
            <h2 class="text-xl font-semibold text-red-400">Precisam de atenção</h2>
            <button type="button" id="reprocessarTodas" class="px-3 py-2 md:py-1 rounded text-xs font-semibold bg-blue-600 hover:bg-blue-500 text-white">Reprocessar todas as falhas</button>
        </div>
        <p class="text-xs text-gray-500 mb-3">Falhas (desistiu após 6 tentativas) e pendentes que já falharam. "Reprocessar" zera as tentativas; "Descartar" marca como pulado sem enviar.</p>
        <div id="atencao" class="overflow-x-auto"><?= $dados['atencao'] ?></div>
    </div>

    <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-semibold mb-4 border-b border-gray-700 pb-2 text-blue-400">Últimas 50 notificações</h2>
        <div id="recentes" class="overflow-x-auto"><?= $dados['recentes'] ?></div>
    </div>

    <script>
    const INTERVALO_MS = 30000;
    const statusAtualizacao = document.getElementById('statusAtualizacao');
    let atualizando = false;

    function mostrarStatus(texto, falhou = false) {
        statusAtualizacao.textContent = texto;
        statusAtualizacao.classList.toggle('text-amber-400', falhou);
        statusAtualizacao.classList.toggle('text-gray-500', !falhou);
    }

    async function chamar(acao, corpo) {
        const opcoes = { cache: 'no-store' };
        if (corpo) {
            opcoes.method = 'POST';
            opcoes.headers = { 'Content-Type': 'application/json' };
            opcoes.body = JSON.stringify(corpo);
        }
        const resposta = await fetch('api.php?action=' + acao, opcoes);
        const dados = await resposta.json();
        if (!resposta.ok) throw new Error(dados.erro || 'HTTP ' + resposta.status);
        return dados;
    }

    async function atualizar() {
        if (atualizando) return;
        atualizando = true;
        try {
            const d = await chamar('listar');
            const saude = document.getElementById('saude');
            saude.textContent = '● ' + d.saude.texto;
            saude.classList.toggle('text-emerald-400', d.saude.ok);
            saude.classList.toggle('text-red-400', !d.saude.ok);
            Object.entries(d.resumo).forEach(([chave, valor]) => {
                const el = document.querySelector(`[data-resumo="${chave}"]`);
                if (el) el.textContent = valor;
            });
            document.getElementById('atencao').innerHTML = d.atencao;
            document.getElementById('recentes').innerHTML = d.recentes;
            mostrarStatus('· atualizado às ' + new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
        } catch (erro) {
            console.error(erro);
            mostrarStatus('· falha ao atualizar: ' + erro.message, true);
        } finally {
            atualizando = false;
        }
    }

    async function agir(acao, corpo, confirmacao) {
        if (confirmacao && !confirm(confirmacao)) return;
        try {
            const r = await chamar(acao, corpo);
            mostrarStatus(r.alterados ? `· ${r.alterados} item(ns) atualizado(s)` : '· nada mudou (o item já saiu desse estado)');
        } catch (erro) {
            mostrarStatus('· ' + erro.message, true);
        }
        await atualizar();
    }

    document.getElementById('atencao').addEventListener('click', evento => {
        const botao = evento.target.closest('[data-acao]');
        if (!botao) return;
        const id = Number(botao.dataset.id);
        if (botao.dataset.acao === 'reprocessar') {
            agir('reprocessar', { id });
        } else {
            agir('descartar', { id }, `Descartar a notificação #${id}? O alerta não será enviado.`);
        }
    });
    document.getElementById('reprocessarTodas').addEventListener('click', () =>
        agir('reprocessar', { todas: true }, 'Reprocessar todas as falhas?'));

    setInterval(atualizar, INTERVALO_MS);
    </script>
</body>
</html>
