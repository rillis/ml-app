<?php
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';
$pdo = ml_db('ml_jrf');

include __DIR__ . '/../../src/dash/posts.php';
include __DIR__ . '/../../src/dash/boletos.php';
include __DIR__ . '/../../src/dash/logs.php';
require __DIR__ . '/../../src/dash/parciais.php';

$filtros = [
    'todos'       => 'Todos',
    'vendas'      => 'Vendas',
    'perguntas'   => 'Perguntas',
    'mensagens'   => 'Mensagens',
    'reclamacoes' => 'Reclamações',
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard ML</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <script src="/assets/tailwind.js"></script>
    <style>
        /* darkmode das scrollbars **/
        .scrollbar-dark::-webkit-scrollbar {
            width: 8px;
        }
        .scrollbar-dark::-webkit-scrollbar-track {
            background: #1f2937; /* gray-800 */
            border-radius: 4px;
        }
        .scrollbar-dark::-webkit-scrollbar-thumb {
            background: #374151; /* gray-700 */
            border-radius: 4px;
        }
        .scrollbar-dark::-webkit-scrollbar-thumb:hover {
            background: #4b5563; /* gray-600 */
        }
    </style>
</head>
<!-- No computador (md+), h-screen e overflow-hidden travam a altura na janela; no celular a página rola normalmente -->
<body class="bg-gray-900 text-gray-100 p-4 sm:p-8 font-sans min-h-screen md:h-screen flex flex-col md:overflow-hidden">
    <?php require __DIR__ . '/../../src/nav.php'; ?>

    <!-- flex-shrink-0 e mb-6 movidos para uma div pai para agrupar o título e o subtítulo -->
    <div class="mb-6 flex-shrink-0">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-100 flex flex-wrap items-center gap-x-3 gap-y-2">Painel de Operações
            <a href="fila/" class="px-3 py-2 sm:py-1 rounded-lg text-sm font-semibold bg-gray-800 border border-gray-700 text-gray-300 hover:border-blue-500 hover:text-white transition-colors">🔔 Fila do webhook</a>
        </h1>
        <p class="text-sm text-gray-400 mt-1">
            <span id="ultimoEvento"><?= dash_h($lastEventText) ?></span>
            <span id="statusAtualizacao" class="ml-2 text-gray-500"></span>
        </p>
    </div>

    <!-- flex-1 e min-h-0 fazem o grid ocupar todo o espaço restante e respeitar o limite -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 flex-1 min-h-0">

        <!-- Coluna 1: Mercado Livre -->
        <div class="bg-gray-800 p-4 sm:p-6 rounded-lg shadow-lg flex flex-col min-h-0 h-[75vh] md:h-auto">
            <h2 class="text-xl font-semibold mb-4 border-b border-gray-700 pb-2 text-blue-400 flex-shrink-0">Últimos Eventos</h2>

            <div id="filtrosEventos" class="flex flex-wrap gap-2 mb-3 flex-shrink-0" role="group" aria-label="Filtrar eventos por tipo">
                <?php foreach ($filtros as $chave => $nome): ?>
                    <button type="button" data-filtro="<?= $chave ?>" aria-pressed="false" class="px-3 py-2 md:py-1 rounded-full text-xs font-semibold transition-colors bg-gray-700 text-gray-300 hover:bg-gray-600">
                        <?= $nome ?> (<span data-contagem="<?= $chave ?>"><?= (int)$contagemEventos[$chave] ?></span>)
                    </button>
                <?php endforeach; ?>
            </div>

            <ul id="listaEventos" class="space-y-3 flex-1 overflow-y-auto pr-2 scrollbar-dark"><?= dash_html_eventos($posts) ?></ul>
            <p id="eventosVazio" class="hidden flex-1 text-sm text-gray-500 text-center pt-6">Nenhum evento deste tipo nos últimos 2 dias.</p>
        </div>

        <!-- Coluna Direita (Dividida: Boletos e Logs) -->
        <div class="flex flex-col gap-6 min-h-0">

            <!-- Metade Superior: Boletos -->
            <div class="bg-gray-800 p-4 sm:p-6 rounded-lg shadow-lg flex flex-col flex-1 min-h-0 max-h-[60vh] md:max-h-none">
                <h2 class="text-xl font-semibold mb-4 border-b border-gray-700 pb-2 text-red-400 flex-shrink-0">Boletos (Próximos 15 dias)</h2>

                <ul id="listaBoletos" class="space-y-3 flex-1 overflow-y-auto pr-2 scrollbar-dark"><?= dash_html_boletos($boletos) ?></ul>
            </div>

            <!-- Metade Inferior: Docker Logs -->
            <!-- No celular os logs começam recolhidos: o título vira botão para abrir -->
            <div class="bg-gray-800 p-4 sm:p-6 rounded-lg shadow-lg flex flex-col md:flex-1 min-h-0">
                <h2 class="text-xl font-semibold border-b border-gray-700 pb-2 text-green-400 flex-shrink-0">
                    <button type="button" id="alternarLogs" aria-expanded="false" aria-controls="painelLogs" class="w-full flex items-center justify-between gap-3 text-left md:cursor-default py-1 md:py-0">
                        Logs: ml-jrf
                        <span class="md:hidden text-sm font-normal text-gray-400" data-rotulo>Mostrar ▾</span>
                    </button>
                </h2>

                <div id="painelLogs" class="hidden md:flex mt-4 h-[60vh] md:h-auto flex-1 overflow-y-auto pr-2 scrollbar-dark bg-gray-900 rounded p-3 text-xs text-gray-300 font-mono flex-col-reverse">
                    <!-- flex-col-reverse faz o scroll ficar no final automaticamente -->
                    <pre id="logsJrf" class="whitespace-pre-wrap font-mono"><?= getLogsContent(); ?></pre>
                </div>
            </div>

        </div>

    </div>

    <script>
    const INTERVALO_MS = 60000;
    const CHAVE_FILTRO = 'dash.filtroEventos';
    const CLASSES_FILTRO_ATIVO = ['bg-blue-600', 'text-white', 'hover:bg-blue-500'];
    const CLASSES_FILTRO_INATIVO = ['bg-gray-700', 'text-gray-300', 'hover:bg-gray-600'];
    const botoesFiltro = [...document.querySelectorAll('[data-filtro]')];
    const statusAtualizacao = document.getElementById('statusAtualizacao');
    let filtroAtual = 'todos';
    let atualizando = false;

    function lerFiltroSalvo() {
        try {
            return localStorage.getItem(CHAVE_FILTRO);
        } catch (e) {
            return null;
        }
    }

    function salvarFiltro(filtro) {
        try {
            localStorage.setItem(CHAVE_FILTRO, filtro);
        } catch (e) {
            // Sem armazenamento, o filtro só não é lembrado ao reabrir a página
        }
    }

    function aplicarFiltro() {
        const eventos = [...document.querySelectorAll('#listaEventos [data-categoria]')];
        let visiveis = 0;
        eventos.forEach(evento => {
            const mostrar = filtroAtual === 'todos' || evento.dataset.categoria === filtroAtual;
            evento.classList.toggle('hidden', !mostrar);
            if (mostrar) visiveis++;
        });
        document.getElementById('listaEventos').classList.toggle('hidden', visiveis === 0);
        document.getElementById('eventosVazio').classList.toggle('hidden', visiveis > 0);

        botoesFiltro.forEach(botao => {
            const ativo = botao.dataset.filtro === filtroAtual;
            botao.setAttribute('aria-pressed', String(ativo));
            CLASSES_FILTRO_ATIVO.forEach(c => botao.classList.toggle(c, ativo));
            CLASSES_FILTRO_INATIVO.forEach(c => botao.classList.toggle(c, !ativo));
        });
    }

    function escolherFiltro(filtro) {
        filtroAtual = filtro;
        salvarFiltro(filtro);
        aplicarFiltro();
    }

    function mostrarStatus(texto, falhou = false) {
        statusAtualizacao.textContent = texto;
        statusAtualizacao.classList.toggle('text-amber-400', falhou);
        statusAtualizacao.classList.toggle('text-gray-500', !falhou);
    }

    // Troca só o conteúdo dos painéis, então a rolagem de cada um é mantida.
    async function atualizarPainel() {
        if (atualizando) return;
        atualizando = true;
        try {
            const resposta = await fetch('api.php', { cache: 'no-store' });
            if (!resposta.ok) throw new Error('HTTP ' + resposta.status);
            const dados = await resposta.json();

            document.getElementById('ultimoEvento').textContent = dados.ultimoEvento;
            document.getElementById('listaEventos').innerHTML = dados.eventos;
            document.getElementById('listaBoletos').innerHTML = dados.boletos;
            document.getElementById('logsJrf').innerHTML = dados.logs;
            Object.entries(dados.contagens).forEach(([chave, total]) => {
                const contagem = document.querySelector(`[data-contagem="${chave}"]`);
                if (contagem) contagem.textContent = total;
            });
            aplicarFiltro();
            mostrarStatus('· atualizado às ' + new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }));
        } catch (erro) {
            console.error(erro);
            mostrarStatus('· falha ao atualizar, tentando de novo em 1 minuto', true);
        } finally {
            atualizando = false;
        }
    }

    botoesFiltro.forEach(botao => botao.addEventListener('click', () => escolherFiltro(botao.dataset.filtro)));

    // Logs recolhíveis no celular (no computador o painel fica sempre aberto pelo md:flex)
    document.getElementById('alternarLogs').addEventListener('click', evento => {
        if (window.matchMedia('(min-width: 768px)').matches) return;
        const botao = evento.currentTarget;
        const aberto = botao.getAttribute('aria-expanded') !== 'true';
        botao.setAttribute('aria-expanded', String(aberto));
        document.getElementById('painelLogs').classList.toggle('hidden', !aberto);
        document.getElementById('painelLogs').classList.toggle('flex', aberto);
        botao.querySelector('[data-rotulo]').textContent = aberto ? 'Ocultar ▴' : 'Mostrar ▾';
    });
    const filtroSalvo = lerFiltroSalvo();
    if (botoesFiltro.some(b => b.dataset.filtro === filtroSalvo)) filtroAtual = filtroSalvo;
    aplicarFiltro();
    setInterval(atualizarPainel, INTERVALO_MS);
    </script>
</body>
</html>
