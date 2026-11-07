<?php require __DIR__ . '/../../src/auth.php'; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insights - Boletos</title>
    <script src="/assets/tailwind.js"></script>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen p-4 sm:p-8 font-sans selection:bg-blue-500 selection:text-white">
    <?php require __DIR__ . '/../../src/nav.php'; ?>
    <div class="max-w-7xl mx-auto">
        
        <header class="mb-8 flex justify-between items-end border-b border-gray-700 pb-4">
            <div>
                <h1 class="text-4xl font-bold">
                    <span aria-hidden="true">📊</span>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-blue-400">Insights</span>
                </h1>
            </div>
            <a href="index.php" class="bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-300 font-semibold py-2.5 px-6 rounded-xl transition-all shadow-lg flex items-center gap-2">
                ⬅️ Voltar aos Boletos
            </a>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            
            <div class="bg-gray-800 rounded-xl border border-gray-700 shadow-lg p-6 lg:col-span-2">
                <h2 class="text-xl font-bold text-gray-300 mb-4">Próximos 8 Dias</h2>
                <div id="visao-8-dias" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    </div>
            </div>

            <div class="bg-gray-800 rounded-xl border border-gray-700 shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-300 mb-4">Provisão de Caixa</h2>
                <p class="text-sm text-gray-400 mb-4">Soma de boletos no período com base nos filtros abaixo.</p>
                
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <input type="date" id="data_inicio" onchange="calcularProvisao()" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-2 text-gray-100 [color-scheme:dark]">
                        <input type="date" id="data_fim" onchange="calcularProvisao()" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-2 text-gray-100 [color-scheme:dark]">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-400 mb-1 block">Pago</label>
                            <select id="filtro_pago" onchange="calcularProvisao()" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-2 text-gray-100">
                                <option value="">- (Indiferente)</option>
                                <option value="1">Sim</option>
                                <option value="0" selected>Não</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-400 mb-1 block">Agendado</label>
                            <select id="filtro_agendado" onchange="calcularProvisao()" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-2 text-gray-100">
                                <option value="" selected>- (Indiferente)</option>
                                <option value="1">Sim</option>
                                <option value="0">Não</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4 p-4 bg-gray-900 rounded-lg border border-gray-700 text-center">
                        <div class="text-sm text-gray-400">Total a Provisionar</div>
                        <div id="valor_provisao" class="text-2xl font-bold text-emerald-400 mt-1">R$ 0,00</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 rounded-xl border border-gray-700 shadow-lg overflow-hidden">
            <div class="p-6 border-b border-gray-700 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-300">Resumo Mensal</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full table-fixed text-left text-sm whitespace-nowrap">
                    <thead class="bg-gray-900/80 text-gray-400 border-b border-gray-700">
                        <tr>
                            <th class="p-4 font-semibold">Mês/Ano</th>
                            <th class="p-4 font-semibold">Total Geral</th>
                            <th class="p-4 font-semibold text-emerald-400">Pago</th>
                            <th class="p-4 font-semibold text-blue-400">Agendado (Ñ Pago)</th>
                            <th class="p-4 font-semibold text-red-400">Pendente</th>
                            <th class="p-4 font-semibold text-center">Progresso</th>
                        </tr>
                    </thead>
                    <tbody id="lista-mensal" class="divide-y divide-gray-700/50">
                        </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
    let boletos = [];

    function formatarMoeda(valor) {
        return parseFloat(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    // Inicializa datas da provisão (primeiro ao último dia do mês atual)
    window.onload = () => {
        const hoje = new Date();
        const ano = hoje.getFullYear();
        const mes = hoje.getMonth();

        const primeiroDia = new Date(ano, mes, 1);
        const ultimoDia = new Date(ano, mes + 1, 0);

        // Função auxiliar para formatar em YYYY-MM-DD evitando furos de fuso horário
        const formatarInput = (d) => d.getFullYear() + '-' + 
                                String(d.getMonth() + 1).padStart(2, '0') + '-' + 
                                String(d.getDate()).padStart(2, '0');

        document.getElementById('data_inicio').value = formatarInput(primeiroDia);
        document.getElementById('data_fim').value = formatarInput(ultimoDia);
        
        carregarDados();
    };

    function carregarDados() {
        fetch('api.php?action=listar')
            .then(r => r.json())
            .then(dados => {
                boletos = dados;
                renderizar8Dias();
                calcularProvisao();
                renderizarMensal();
            });
    }

    function renderizar8Dias() {
        const container = document.getElementById('visao-8-dias');
        const cards = [];
        
        const hoje = new Date();
        hoje.setHours(0,0,0,0);

        // Gera array dos próximos 7 dias
        for(let i=0; i<8; i++) {
            const dataAlvo = new Date(hoje);
            dataAlvo.setDate(hoje.getDate() + i);
            const dataStr = dataAlvo.toISOString().split('T')[0];
            
            // Soma os boletos daquele dia
            const totalDia = boletos
                .filter(b => b.data_vencimento_real === dataStr)
                .reduce((acc, b) => acc + parseFloat(b.valor), 0);

            // Se for hoje, exibe "Hoje", amanhã "Amanhã", senão DD/MM
            let label = dataAlvo.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
            if(i === 0) label = "Hoje";
            if(i === 1) label = "Amanhã";

            let corValor = totalDia > 0 ? "text-orange-400" : "text-gray-500";
            let bgCard = totalDia > 0 ? "bg-orange-900/10 border-orange-500/30" : "bg-gray-900 border-gray-700";

            cards.push(`
                <div class="p-4 rounded-xl border ${bgCard} flex flex-col items-center justify-center text-center">
                    <span class="text-sm text-gray-400 font-medium mb-1">${label}</span>
                    <span class="text-lg font-bold ${corValor}">${formatarMoeda(totalDia)}</span>
                </div>
            `);
        }
        container.innerHTML = cards.join('');
    }

    function calcularProvisao() {
    const inicio = document.getElementById('data_inicio').value;
    const fim = document.getElementById('data_fim').value;
    const filtroPago = document.getElementById('filtro_pago').value;
    const filtroAgendado = document.getElementById('filtro_agendado').value;

    if(!inicio || !fim) return;

    const totalProvisao = boletos
        // Filtra pelo range de datas usando data_vencimento_real
        .filter(b => b.data_vencimento_real >= inicio && b.data_vencimento_real <= fim)
        // Aplica os filtros dinâmicos
        .filter(b => {
            let condicaoPago = filtroPago === "" ? true : b.pago == filtroPago;
            let condicaoAgendado = filtroAgendado === "" ? true : b.agendado == filtroAgendado;
            return condicaoPago && condicaoAgendado;
        })
        // Soma os valores
        .reduce((acc, b) => acc + parseFloat(b.valor), 0);

    document.getElementById('valor_provisao').innerText = formatarMoeda(totalProvisao);
}

    function renderizarMensal() {
        const tbody = document.getElementById('lista-mensal');
        // Agrupamento
        const grupos = {};
        
        boletos.forEach(b => {
            const mesAno = b.data_vencimento_real.substring(0, 7); // YYYY-MM
            if(!grupos[mesAno]) {
                grupos[mesAno] = { total: 0, pago: 0, agendado: 0, pendente: 0 };
            }
            
            const v = parseFloat(b.valor);
            grupos[mesAno].total += v;
            
            if(b.pago == 1) {
                grupos[mesAno].pago += v;
            } else if (b.agendado == 1) {
                grupos[mesAno].agendado += v;
            } else {
                grupos[mesAno].pendente += v;
            }
        });

        // INÍCIO DA ALTERAÇÃO
        const hoje = new Date();
        const fmtAnoMes = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
        
        const dataInicio = new Date(hoje.getFullYear(), hoje.getMonth() - 2, 1);
        const dataFim = new Date(hoje.getFullYear(), hoje.getMonth() + 2, 1);
        
        const mesInicioStr = fmtAnoMes(dataInicio);
        const mesFimStr = fmtAnoMes(dataFim);

        // Filtrar a janela de meses e ordenar crescente
        const mesesOrdenados = Object.keys(grupos)
            .filter(mes => mes >= mesInicioStr && mes <= mesFimStr)
            .sort((a,b) => a.localeCompare(b));
        // FIM DA ALTERAÇÃO

        tbody.innerHTML = mesesOrdenados.map(mes => {
            const g = grupos[mes];
            const partes = mes.split('-');
            const mesLabel = `${partes[1]}/${partes[0]}`;
            
            const pctAgend = g.total > 0 ? (g.agendado / g.total) * 100 : 0;
            const pctPago = g.total > 0 ? (g.pago / g.total) * 100 : 0;

            return `
                <tr class="hover:bg-gray-700/40 transition-colors">
                    <td class="p-4 font-bold text-gray-200">${mesLabel}</td>
                    <td class="p-4 font-medium text-gray-300">${formatarMoeda(g.total)}</td>
                    <td class="p-4 font-medium text-emerald-400">${formatarMoeda(g.pago)}</td>
                    <td class="p-4 font-medium text-blue-400">${formatarMoeda(g.agendado)}</td>
                    <td class="p-4 font-medium text-red-400">${formatarMoeda(g.pendente)}</td>
                    <td class="p-4">
                        <div class="w-full bg-gray-900 rounded-full h-2.5 border border-gray-700 flex overflow-hidden">
                            <div class="bg-blue-500 h-full" style="width: ${pctAgend}%"></div>
                            <div class="bg-emerald-500 h-full" style="width: ${pctPago}%"></div>
                        </div>
                        <div class="w-full flex justify-center gap-4">
                            <span class="text-xs text-blue-400">
                                AG: ${pctAgend.toFixed(1)}%
                            </span>

                            <span class="text-xs text-emerald-400">
                                PG: ${pctPago.toFixed(1)}%
                            </span>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }
    </script>
</body>
</html>