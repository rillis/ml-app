<?php
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/boletos/nivel.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boletos</title>
    <script src="/assets/tailwind.js"></script>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen p-4 sm:p-8 font-sans selection:bg-blue-500 selection:text-white">
    <?php require __DIR__ . '/../../src/nav.php'; ?>
    <div class="max-w-7xl mx-auto">
        
        <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-end border-b border-gray-700 pb-4">
            <div>
                <h1 class="text-3xl sm:text-4xl font-bold">
                    <span aria-hidden="true">📄</span>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400">Contas a Pagar</span>
                </h1>
            </div>
            
            <div class="grid grid-cols-2 gap-3 sm:flex sm:flex-wrap sm:justify-end">
                <a href="insights.php" class="justify-center whitespace-nowrap bg-gray-800 hover:bg-gray-700 border border-gray-700 hover:border-emerald-500 text-gray-300 hover:text-emerald-400 font-semibold py-2.5 px-4 sm:px-6 rounded-xl transition-all shadow-lg flex items-center gap-2">
                    📊 Insights
                </a>
                <button onclick="abrirModal()" class="justify-center whitespace-nowrap bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2.5 px-4 sm:px-6 rounded-xl transition-colors shadow-lg flex items-center gap-2">
                    <span class="text-xl leading-none">+</span> Novo Boleto
                </button>
            </div>
        </header>

        <div class="mb-6 flex flex-col sm:flex-row gap-4 items-center justify-between">
            <input 
                id="busca" 
                placeholder="Buscar descrição ou código..." 
                oninput="renderizar()"
                class="w-full sm:w-1/2 bg-gray-800 border border-gray-700 rounded-xl p-3 text-base text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-lg transition-colors"
            >
            
            <div class="grid grid-cols-2 w-full gap-3 sm:flex sm:w-auto sm:flex-wrap sm:items-center sm:justify-end">
                <button id="btnCopiarSelecionados" onclick="copiarSelecionados()" disabled class="text-sm sm:text-base whitespace-nowrap bg-gray-800 border border-gray-700 px-3 sm:px-4 py-3 rounded-xl shadow-lg text-gray-300 enabled:hover:text-gray-100 enabled:hover:border-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">📋 Copiar código</button>
                <label class="flex items-center gap-3 cursor-pointer text-sm sm:text-base whitespace-nowrap text-gray-300 hover:text-gray-100 select-none bg-gray-800 border border-gray-700 px-3 sm:px-4 py-3 rounded-xl shadow-lg transition-colors">
                    <input type="checkbox" id="ocultarPagos" checked onchange="renderizar()" class="w-5 h-5 rounded border-gray-700 bg-gray-900 text-blue-500 focus:ring-blue-500 focus:ring-offset-gray-800">
                    Ocultar pagos
                </label>
            </div>
        </div>

        <!-- Resumo por nível de alerta; clicar filtra a lista por aquele nível -->
        <div id="resumoNiveis" class="mb-6 flex flex-wrap gap-2"></div>

        <!-- Celular: lista de cards. A tabela (abaixo) só aparece a partir de md. -->
        <div class="md:hidden">
            <label class="flex items-center gap-3 mb-3 px-1 text-sm text-gray-400 select-none">
                <input type="checkbox" data-selecionar-todos onchange="selecionarTodosVisiveis(this.checked)" class="w-6 h-6 rounded border-gray-700 bg-gray-900 text-blue-500 focus:ring-blue-500 focus:ring-offset-gray-800">
                Selecionar todos
            </label>
            <div id="listaMobile" class="space-y-3"></div>
        </div>

        <div class="hidden md:block bg-gray-800 rounded-xl border border-gray-700 shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-gray-900/80 text-gray-400 border-b border-gray-700">
                        <tr>
                            <th class="p-4 w-10">
                                <input type="checkbox" id="selecionarTodos" data-selecionar-todos onchange="selecionarTodosVisiveis(this.checked)" title="Selecionar todos" class="w-5 h-5 align-middle rounded border-gray-700 bg-gray-900 text-blue-500 focus:ring-blue-500 focus:ring-offset-gray-800">
                            </th>
                            <th class="p-4 font-semibold">Vencimento (Real)</th>
                            <th class="p-4 font-semibold">Descrição</th>
                            <th class="p-4 font-semibold">Valor</th>
                            <th class="p-4 font-semibold">Status</th>
                            <th class="p-4 font-semibold text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="lista" class="divide-y divide-gray-700/50">
                        </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="modal" class="hidden fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex justify-center items-center p-4 transition-opacity">
        <div class="bg-gray-800 p-5 sm:p-8 rounded-2xl border border-gray-700 shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col relative">
            <button onclick="fecharModal()" aria-label="Fechar" class="absolute top-2 right-2 sm:top-3 sm:right-3 w-11 h-11 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-100 hover:bg-gray-700 text-2xl leading-none">&times;</button>
            <h2 id="modalTitle" class="text-2xl font-bold mb-4 text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400">Novo Boleto</h2>

            <label id="areaImportar" class="block mb-4 border-2 border-dashed border-gray-600 hover:border-blue-500 rounded-xl p-3 text-center text-sm text-gray-400 cursor-pointer transition-colors">
                📎 Arraste PDFs aqui ou <span class="text-blue-400 underline">selecione os arquivos</span>
                <input type="file" id="arquivoPdf" accept="application/pdf,.pdf" multiple class="hidden" onchange="importarSelecionados(this)">
            </label>
            <div id="avisosImportacao" class="space-y-2 mb-4 text-sm empty:hidden"></div>

            <form id="formBoleto" onsubmit="salvarBoleto(event)" class="flex flex-col min-h-0">
                <input type="hidden" id="boletoId">
                <datalist id="lista-descricoes"></datalist>

                <div id="listaCards" class="space-y-4 overflow-y-auto min-h-0 pr-1"></div>

                <button type="button" id="btnAdicionarCard" onclick="adicionarCard()" class="mt-4 py-2 self-start text-sm text-blue-400 hover:text-blue-300 font-semibold transition-colors">+ Adicionar outro boleto</button>

                <div class="flex justify-end gap-3 mt-4 pt-4 border-t border-gray-700">
                    <button type="button" onclick="fecharModal()" class="px-5 py-2.5 rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">Cancelar</button>
                    <button type="submit" id="btnSalvar" class="px-6 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-wait text-white font-semibold transition-colors shadow-lg">Salvar Boleto</button>
                </div>
            </form>
        </div>
    </div>

    <template id="tplCard">
        <div data-card class="space-y-4">
            <div data-cabecalho class="hidden flex justify-between items-center gap-3">
                <span data-titulo class="text-sm font-semibold text-gray-300 truncate"></span>
                <button type="button" data-remover class="shrink-0 py-2 px-1 text-xs text-red-400 hover:text-red-300 font-semibold transition-colors">Remover</button>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Descrição</label>
                <input type="text" data-campo="descricao" list="lista-descricoes" required placeholder="Ex: Mensalidade Contabilizei" autocomplete="off"
                       class="w-full bg-gray-900 border border-gray-700 rounded-lg p-3 text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div data-area-vinculo class="hidden bg-blue-500/10 border border-blue-500/30 rounded-lg p-3">
                <label class="block text-sm font-medium text-blue-300 mb-1">Já existe boleto com este nome, sem valor e sem código. Ao salvar:</label>
                <select data-vinculo class="w-full bg-gray-900 border border-gray-700 rounded-lg p-2.5 text-gray-100 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></select>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Vencimento Original</label>
                    <input type="date" data-campo="vencimento" required
                           class="w-full bg-gray-900 border border-gray-700 rounded-lg p-3 text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 [color-scheme:dark]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Valor (R$)</label>
                    <input type="text" data-campo="valor" required placeholder="0,00" inputmode="decimal"
                           class="w-full bg-gray-900 border border-gray-700 rounded-lg p-3 text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Código de Barras</label>
                <input type="text" data-campo="codigo" placeholder="Cole o código do boleto (opcional)"
                       class="w-full bg-gray-900 border border-gray-700 rounded-lg p-3 text-gray-100 font-mono text-base sm:text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <p data-erro class="hidden text-sm text-red-400 font-semibold"></p>
        </div>
    </template>

    <div id="sobreposicaoArrastar" class="hidden fixed inset-0 z-[60] pointer-events-none bg-blue-900/60 backdrop-blur-sm flex items-center justify-center">
        <div class="border-4 border-dashed border-blue-300 rounded-2xl px-12 py-10 text-2xl font-bold text-white">
            📄 Solte o PDF para ler os boletos
        </div>
    </div>

    <script src="/assets/ui.js?v=<?= filemtime(__DIR__ . '/../assets/ui.js') ?>"></script>
    <!-- A versão na URL evita que navegador ou Cloudflare sirvam uma cópia antiga do script. -->
    <script src="boleto-pdf.js?v=<?= filemtime(__DIR__ . '/boleto-pdf.js') ?>"></script>
    <script>
    let cacheBoletos = [];
    // Níveis de alerta: a regra fica em src/boletos/nivel.php; a API já manda b.nivel e b.dias.
    const NIVEIS = <?= json_encode(BOLETO_NIVEIS, JSON_UNESCAPED_UNICODE) ?>;
    const NIVEIS_RESUMO = ['critico', 'urgente', 'atencao'];
    const EMOJI_NIVEL = { critico: '🔴', urgente: '🟠', atencao: '🟡' };
    let filtroNivel = null;
    let leiturasAtivas = 0;
    let sessaoModal = 0; // Invalida leituras de PDF ainda em andamento quando o modal fecha.
    const selecionados = new Set();

    function formatarMoeda(valor) {
        return parseFloat(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function formatarDataBR(dataString) {
        if (!dataString) return '-';
        const [ano, mes, dia] = dataString.split('-');
        return `${dia}/${mes}/${ano}`;
    }

    function tratarValorMonetario(valorStr) {
        let limpo = valorStr.replace(/[R$\s]/g, '');
        if (limpo.includes('.') && limpo.includes(',')) {
            limpo = limpo.replace(/\./g, '').replace(',', '.');
        } else if (limpo.includes(',')) {
            limpo = limpo.replace(',', '.');
        }
        return parseFloat(limpo) || 0;
    }

    function carregar() {
        fetch('api.php?action=listar')
            .then(r => r.json())
            .then(dados => {
                cacheBoletos = dados;
                renderizar();
                atualizarAutocomplete();
            }).catch(e => console.log('Aguardando api.php...'));
    }

    function atualizarAutocomplete() {
        const datalist = document.getElementById('lista-descricoes');
        if (!datalist) return;
        
        const descricoesUnicas = [...new Set(cacheBoletos.map(b => b.descricao.trim()))];
        datalist.innerHTML = descricoesUnicas.map(d => `<option value="${htmlspecialchars_js(d)}">`).join('');
    }

    function htmlspecialchars_js(string) {
        return string
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // A API de área de transferência só existe em HTTPS; o acesso pelo IP local usa o método antigo.
    function copiarTexto(texto) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(texto);
        }
        const area = document.createElement('textarea');
        area.value = texto;
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        const copiou = document.execCommand('copy');
        area.remove();
        return copiou ? Promise.resolve() : Promise.reject(new Error('Falha ao copiar'));
    }

    function copiarCodigo(codigo) {
        copiarTexto(codigo)
            .then(() => ML.toast('Código copiado para a área de transferência!', 'sucesso'))
            .catch(() => ML.toast('Não foi possível copiar o código.', 'erro'));
    }

    function boletosVisiveis() {
        const termo = document.getElementById('busca').value.toLowerCase().trim();
        const ocultarPagos = document.getElementById('ocultarPagos').checked;
        return cacheBoletos
            .filter(b => ocultarPagos ? b.pago == 0 : true)
            .filter(b => filtroNivel ? b.nivel === filtroNivel : true)
            .filter(b => b.descricao.toLowerCase().includes(termo) || (b.codigo_boleto && b.codigo_boleto.includes(termo)))
            .sort((a, b) => a.data_vencimento_real.localeCompare(b.data_vencimento_real));
    }

    // Boletos marcados que a busca ou o filtro esconderam não entram na cópia.
    function selecionadosVisiveis() {
        return boletosVisiveis().filter(b => selecionados.has(Number(b.id)));
    }

    function alternarSelecao(id, marcado) {
        if (marcado) selecionados.add(id);
        else selecionados.delete(id);
        atualizarSelecao();
    }

    function selecionarTodosVisiveis(marcado) {
        boletosVisiveis().forEach(b => {
            if (marcado) selecionados.add(Number(b.id));
            else selecionados.delete(Number(b.id));
        });
        renderizar();
    }

    function atualizarSelecao() {
        const total = boletosVisiveis().length;
        const marcados = selecionadosVisiveis().length;
        // Há um "selecionar todos" na tabela (computador) e outro na lista de cards (celular)
        document.querySelectorAll('[data-selecionar-todos]').forEach(todos => {
            todos.checked = total > 0 && marcados === total;
            todos.indeterminate = marcados > 0 && marcados < total;
        });

        const botao = document.getElementById('btnCopiarSelecionados');
        botao.disabled = marcados === 0;
        botao.innerText = marcados > 0 ? `📋 Copiar código (${marcados})` : '📋 Copiar código';
    }

    function copiarSelecionados() {
        // Troca o espaço especial que o formato de moeda usa após "R$" por um espaço comum.
        const texto = selecionadosVisiveis().map(b => [
            formatarDataBR(b.data_vencimento_real),
            formatarMoeda(b.valor).replace(/ /g, ' '),
            b.codigo_boleto || 'Sem código'
        ].join('\n')).join('\n--------------------\n');

        const botao = document.getElementById('btnCopiarSelecionados');
        copiarTexto(texto).then(() => {
            botao.innerText = '✓ Copiado!';
            setTimeout(atualizarSelecao, 1500);
        }).catch(() => ML.toast('Não foi possível copiar os códigos.', 'erro'));
    }

    function formatarValorCampo(valor) {
        return parseFloat(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function abrirModal(b = null) {
        sessaoModal++;
        leiturasAtivas = 0;
        const editando = !!b;
        document.getElementById('modal').classList.remove('hidden');
        document.getElementById('boletoId').value = editando ? b.id : '';
        document.getElementById('listaCards').innerHTML = '';
        document.getElementById('avisosImportacao').innerHTML = '';
        document.getElementById('areaImportar').classList.toggle('hidden', editando);
        document.getElementById('btnAdicionarCard').classList.toggle('hidden', editando);

        if (editando) {
            adicionarCard({
                descricao: b.descricao,
                vencimento: b.data_vencimento,
                valor: formatarValorCampo(b.valor),
                codigo: b.codigo_boleto || ''
            });
        } else {
            adicionarCard();
        }
    }

    function camposDoCard(card) {
        const campo = nome => card.querySelector(`[data-campo="${nome}"]`);
        return { descricao: campo('descricao'), vencimento: campo('vencimento'), valor: campo('valor'), codigo: campo('codigo') };
    }

    function adicionarCard(dados = {}, opcoes = {}) {
        const card = document.getElementById('tplCard').content.firstElementChild.cloneNode(true);
        const campos = camposDoCard(card);

        Object.entries(campos).forEach(([nome, input]) => {
            input.value = dados[nome] || '';
            // Destaca o que a leitura do PDF não encontrou, até o usuário preencher.
            if (opcoes.importado && !input.value) {
                input.classList.add('border-amber-500');
                input.title = 'Não identificado no PDF';
            }
            input.addEventListener('input', () => input.classList.remove('border-amber-500'));
        });

        campos.descricao.addEventListener('input', () => {
            delete card.dataset.vinculoManual;
            atualizarVinculos();
        });
        campos.vencimento.addEventListener('input', () => {
            delete card.dataset.dataDoVinculo;
            atualizarVinculos();
        });
        card.querySelector('[data-vinculo]').addEventListener('change', () => {
            card.dataset.vinculoManual = '1';
            atualizarVinculos();
        });

        card.dataset.origem = opcoes.origem || '';
        card.querySelector('[data-remover]').addEventListener('click', () => {
            card.remove();
            if (!document.querySelector('#listaCards [data-card]')) adicionarCard();
            atualizarCards();
        });
        if (opcoes.erro) mostrarErroCard(card, opcoes.erro);

        document.getElementById('listaCards').appendChild(card);
        atualizarCards();
        return card;
    }

    function cardVazio(card) {
        return Object.values(camposDoCard(card)).every(input => !input.value.trim());
    }

    function mostrarErroCard(card, mensagem) {
        const erro = card.querySelector('[data-erro]');
        erro.textContent = mensagem;
        erro.classList.remove('hidden');
    }

    function ePlaceholder(b) {
        return !b.codigo_boleto && parseFloat(b.valor) === 0 && b.pago == 0;
    }

    function placeholderMaisProximo(candidatos, data) {
        if (!candidatos.length) return '';
        if (!data) return String(candidatos[0].id);
        const distancia = b => Math.abs(Date.parse(b.data_vencimento) - Date.parse(data));
        return String(candidatos.reduce((melhor, b) => distancia(b) < distancia(melhor) ? b : melhor).id);
    }

    // Liga cada card ao placeholder de mesmo nome, com a data mais próxima. Dois cards nunca
    // usam o mesmo placeholder, e o usuário pode trocar a escolha ou optar por criar um novo.
    function atualizarVinculos() {
        const cards = [...document.querySelectorAll('#listaCards [data-card]')];
        const editando = !!document.getElementById('boletoId').value;
        const ocupados = new Set();

        cards.forEach(card => {
            const valor = card.querySelector('[data-vinculo]').value;
            if (card.dataset.vinculoManual && valor) ocupados.add(valor);
        });

        cards.forEach(card => {
            const select = card.querySelector('[data-vinculo]');
            const campos = camposDoCard(card);
            const nome = BoletoPdf.normalizar(campos.descricao.value);
            const manual = card.dataset.vinculoManual ? select.value : null;

            const candidatos = editando || !nome ? [] : cacheBoletos
                .filter(b => ePlaceholder(b) && BoletoPdf.normalizar(b.descricao) === nome)
                .filter(b => String(b.id) === manual || !ocupados.has(String(b.id)))
                .sort((a, b) => a.data_vencimento.localeCompare(b.data_vencimento));

            let escolhido;
            if (manual !== null && (manual === '' || candidatos.some(b => String(b.id) === manual))) {
                escolhido = manual;
            } else {
                delete card.dataset.vinculoManual;
                escolhido = placeholderMaisProximo(candidatos, card.dataset.dataDoVinculo ? '' : campos.vencimento.value);
            }
            if (escolhido) ocupados.add(escolhido);

            select.innerHTML = '';
            if (candidatos.length) {
                candidatos.forEach(b => select.add(new Option(`Atualizar o boleto de ${formatarDataBR(b.data_vencimento)}`, b.id)));
                select.add(new Option('Criar um boleto novo', ''));
                select.value = escolhido;
            }
            card.querySelector('[data-area-vinculo]').classList.toggle('hidden', !candidatos.length);

            // Sem data informada, usa a do placeholder escolhido.
            const placeholder = candidatos.find(b => String(b.id) === escolhido);
            if (placeholder && (!campos.vencimento.value || card.dataset.dataDoVinculo)) {
                campos.vencimento.value = placeholder.data_vencimento;
                campos.vencimento.classList.remove('border-amber-500');
                card.dataset.dataDoVinculo = '1';
            } else if (!placeholder && card.dataset.dataDoVinculo) {
                campos.vencimento.value = '';
                delete card.dataset.dataDoVinculo;
            }
        });
    }

    function atualizarCards() {
        atualizarVinculos();
        const cards = [...document.querySelectorAll('#listaCards [data-card]')];
        const editando = !!document.getElementById('boletoId').value;
        const varios = cards.length > 1;

        cards.forEach((card, i) => {
            card.classList.toggle('border-t', i > 0);
            card.classList.toggle('border-gray-700', i > 0);
            card.classList.toggle('pt-4', i > 0);
            card.querySelector('[data-cabecalho]').classList.toggle('hidden', !varios && !card.dataset.origem);
            const origem = card.dataset.origem ? ` · ${card.dataset.origem}` : '';
            card.querySelector('[data-titulo]').textContent = `Boleto ${i + 1}${origem}`;
        });

        document.getElementById('modalTitle').innerText = editando ? 'Editar Boleto' : (varios ? `Novos Boletos (${cards.length})` : 'Novo Boleto');
        const btnSalvar = document.getElementById('btnSalvar');
        btnSalvar.innerText = varios ? `Salvar ${cards.length} boletos` : 'Salvar Boleto';
        btnSalvar.disabled = leiturasAtivas > 0;
    }

    function adicionarAviso(mensagem, tipo = 'info') {
        const cores = {
            info: 'bg-blue-500/10 border-blue-500/30 text-blue-300',
            alerta: 'bg-amber-500/10 border-amber-500/30 text-amber-300',
        };
        const aviso = document.createElement('div');
        aviso.className = `border rounded-lg px-3 py-2 ${cores[tipo]}`;
        aviso.textContent = mensagem;
        document.getElementById('avisosImportacao').appendChild(aviso);
        return aviso;
    }

    function ePdf(arquivo) {
        return arquivo.type === 'application/pdf' || /\.pdf$/i.test(arquivo.name);
    }

    function importarSelecionados(input) {
        importarArquivos([...input.files]);
        input.value = '';
    }

    // Só preenche o formulário: nada é gravado até o usuário clicar em Salvar.
    async function importarArquivos(arquivos) {
        const modalAberto = !document.getElementById('modal').classList.contains('hidden');
        if (modalAberto && document.getElementById('boletoId').value) {
            ML.toast('Conclua ou cancele a edição antes de importar um PDF.', 'aviso');
            return;
        }
        if (!modalAberto) abrirModal();

        const sessao = sessaoModal;
        const descricoesConhecidas = [...new Set(cacheBoletos.map(b => b.descricao.trim()))];

        for (const arquivo of arquivos) {
            if (!ePdf(arquivo)) {
                adicionarAviso(`${arquivo.name}: apenas arquivos PDF são aceitos.`, 'alerta');
                continue;
            }
            const progresso = adicionarAviso(`Lendo ${arquivo.name}...`);
            leiturasAtivas++;
            atualizarCards();
            try {
                const resultado = await BoletoPdf.lerArquivo(arquivo, { descricoesConhecidas });
                if (sessao !== sessaoModal) return;
                progresso.remove();
                preencherComResultado(arquivo.name, resultado);
            } catch (erro) {
                if (sessao !== sessaoModal) return;
                progresso.remove();
                console.error(erro);
                adicionarAviso(erro.name === 'PasswordException'
                    ? `${arquivo.name}: o PDF é protegido por senha. Remova a senha ou preencha manualmente.`
                    : `${arquivo.name}: não foi possível ler o arquivo. Preencha manualmente.`, 'alerta');
            }
            leiturasAtivas--;
            atualizarCards();
        }
    }

    function preencherComResultado(nomeArquivo, resultado) {
        const { boletos, temTexto } = resultado;
        if (!boletos.length) {
            adicionarAviso(temTexto
                ? `${nomeArquivo}: nenhum código de boleto válido encontrado. Preencha manualmente.`
                : `${nomeArquivo}: o PDF não tem texto (parece digitalizado). Preencha manualmente.`, 'alerta');
            return;
        }

        const cards = [...document.querySelectorAll('#listaCards [data-card]')];
        const codigosNaLista = new Set(cards.map(c => camposDoCard(c).codigo.value.replace(/\D/g, '')).filter(Boolean));
        cards.filter(cardVazio).forEach(c => c.remove());

        let adicionados = 0, repetidos = 0;
        boletos.forEach(b => {
            if (codigosNaLista.has(b.codigo)) {
                repetidos++;
                return;
            }
            codigosNaLista.add(b.codigo);

            const existente = cacheBoletos.find(x => x.codigo_boleto === b.codigo);
            const paginas = b.paginas.length > 1 ? 'págs.' : 'pág.';
            adicionarCard({
                descricao: b.descricao,
                vencimento: b.vencimento,
                valor: b.valor ? formatarValorCampo(b.valor) : '',
                codigo: b.codigo
            }, {
                importado: true,
                origem: `${nomeArquivo}, ${paginas} ${b.paginas.join(', ')}`,
                erro: existente ? `Já cadastrado: ${existente.descricao} (venc. ${formatarDataBR(existente.data_vencimento)}). Remova para continuar.` : ''
            });
            adicionados++;
        });

        if (!document.querySelector('#listaCards [data-card]')) adicionarCard();
        if (repetidos) adicionarAviso(`${nomeArquivo}: ${repetidos} boleto(s) já estavam nesta lista e foram ignorados.`);
        if (adicionados) adicionarAviso(`${nomeArquivo}: ${adicionados} boleto(s) encontrado(s). Confira os dados e clique em Salvar.`);
    }

    function configurarArrastar() {
        const sobreposicao = document.getElementById('sobreposicaoArrastar');
        const temArquivo = e => e.dataTransfer && [...e.dataTransfer.types].includes('Files');
        let profundidade = 0;

        document.addEventListener('dragenter', e => {
            if (!temArquivo(e)) return;
            e.preventDefault();
            profundidade++;
            sobreposicao.classList.remove('hidden');
        });
        document.addEventListener('dragover', e => {
            if (temArquivo(e)) e.preventDefault();
        });
        document.addEventListener('dragleave', e => {
            if (!temArquivo(e)) return;
            profundidade = Math.max(0, profundidade - 1);
            if (profundidade === 0) sobreposicao.classList.add('hidden');
        });
        document.addEventListener('drop', e => {
            if (!temArquivo(e)) return;
            e.preventDefault(); // Evita que o navegador abra o PDF e saia da página.
            profundidade = 0;
            sobreposicao.classList.add('hidden');
            importarArquivos([...e.dataTransfer.files]);
        });
    }

    function enviarAcao(action, corpo) {
        return fetch('api.php?action=' + action, {
            method: 'POST',
            body: JSON.stringify(corpo)
        }).then(r => r.json()).then(res => {
            if (res.erro) ML.toast(res.erro, 'erro');
        }).catch(() => {
            ML.toast('Não foi possível concluir. Verifique a conexão e tente novamente.', 'erro');
        }).finally(carregar);
    }

    function alterarStatus(id, campo, valorAtual) {
        const novoValor = valorAtual == 1 ? 0 : 1;
        enviarAcao('status', { id: id, campo: campo, valor: novoValor });
    }

    async function apagar(id) {
        const confirmou = await ML.confirmar('Mover este boleto para o histórico (Excluir)?', {
            titulo: 'Excluir boleto',
            textoConfirmar: 'Excluir',
            perigo: true
        });
        if (confirmou) enviarAcao('apagar', { id: id });
    }

    // Botões AGENDADO/PAGO: a mesma lógica para a tabela (compacto) e para os cards do celular (grande)
    function botaoStatus(b, campo, ativo, grande) {
        const rotulo = campo === 'pago' ? 'PAGO' : 'AGENDADO';
        const cor = campo === 'pago'
            ? (ativo ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500 hover:text-white' : 'bg-gray-700 text-gray-300 border border-transparent hover:bg-emerald-600')
            : (ativo ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20 hover:bg-blue-500 hover:text-white' : 'bg-gray-700 text-gray-300 border border-transparent hover:bg-blue-600');
        const tamanho = grande ? 'h-11 px-2 rounded-lg text-sm w-full' : 'px-3 py-1 rounded-md text-xs';
        return `<button onclick="alterarStatus(${b.id}, '${campo}', ${ativo ? 1 : 0})" aria-pressed="${ativo}" class="${cor} ${tamanho} font-bold transition-colors">${ativo ? '✓ ' : ''}${rotulo}</button>`;
    }

    // "venceu há 2d", "hoje", "amanhã", "em 5d" (igual a boletoPrazo() no PHP)
    function textoPrazo(dias) {
        if (dias < 0) return `venceu há ${Math.abs(dias)}d`;
        if (dias === 0) return 'hoje';
        if (dias === 1) return 'amanhã';
        return `em ${dias}d`;
    }

    function seloPrazo(b) {
        if (b.nivel === 'pago') return '';
        const n = NIVEIS[b.nivel] || NIVEIS.ok;
        return `<span class="${n.selo} text-xs font-bold px-2 py-0.5 rounded-full whitespace-nowrap" title="${n.rotulo}">${textoPrazo(Number(b.dias))}</span>`;
    }

    function filtrarNivel(nivel) {
        filtroNivel = filtroNivel === nivel ? null : nivel;
        renderizar();
    }

    // Chips com contagem e total por nível (sobre todos os boletos não pagos, sem considerar a busca)
    function renderizarResumo() {
        const chips = NIVEIS_RESUMO.map(nivel => {
            const doNivel = cacheBoletos.filter(b => b.nivel === nivel);
            const total = doNivel.reduce((soma, b) => soma + (parseFloat(b.valor) || 0), 0);
            const n = NIVEIS[nivel];
            const ativo = filtroNivel === nivel;
            const vazio = doNivel.length === 0;
            const estilo = ativo
                ? `${n.selo.replace('animate-pulse', '')} ring-2 ring-white/70`
                : `bg-gray-800 text-gray-200 ${vazio ? 'opacity-40 cursor-default' : 'hover:bg-gray-700'}`;
            return `<button type="button" onclick="filtrarNivel('${nivel}')" aria-pressed="${ativo}" ${vazio && !ativo ? 'disabled' : ''}
                        class="flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-700 border-l-4 ${n.borda} text-sm font-semibold shadow-lg transition-colors ${estilo}">
                        <span>${EMOJI_NIVEL[nivel]} ${n.rotulo}</span>
                        <span class="font-bold">${doNivel.length}</span>
                        <span class="opacity-80 font-normal">· ${formatarMoeda(total)}</span>
                    </button>`;
        });
        if (filtroNivel) {
            chips.push(`<button type="button" onclick="filtrarNivel(null)" class="px-3 py-2 text-sm text-gray-400 hover:text-gray-100">✕ Limpar filtro</button>`);
        }
        document.getElementById('resumoNiveis').innerHTML = chips.join('');
    }

    function renderizar() {
        const lista = document.getElementById('lista');
        const listaMobile = document.getElementById('listaMobile');
        renderizarResumo();

        const linhas = [];
        const cards = [];
        boletosVisiveis().forEach(b => {
            const pago = b.pago == 1;
            const agendado = b.agendado == 1;

            let dataExibicao = formatarDataBR(b.data_vencimento_real);
            if (b.data_vencimento !== b.data_vencimento_real) {
                dataExibicao += ` <span class="text-xs text-gray-500">(${formatarDataBR(b.data_vencimento)})</span>`;
            }
            const selo = seloPrazo(b);

            // Destaque pelo nível de alerta (src/boletos/nivel.php); pago fica apagado
            const n = NIVEIS[b.nivel] || NIVEIS.ok;
            const destaque = pago
                ? 'opacity-40 grayscale'
                : `border-l-4 ${n.borda} ${n.fundo}`;

            const marcado = selecionados.has(Number(b.id)) ? 'checked' : '';
            const descricao = htmlspecialchars_js(b.descricao);
            const valor = formatarMoeda(b.valor);
            const editar = `onclick='abrirModal(${JSON.stringify(b).replace(/'/g, "&#39;")})'`;
            const btnAgendado = (grande) => botaoStatus(b, 'agendado', agendado, grande);
            const btnPago = (grande) => botaoStatus(b, 'pago', pago, grande);

            linhas.push(`
                <tr class="transition-all duration-200 hover:bg-gray-700/40 ${destaque}">
                    <td class="p-4">
                        <input type="checkbox" ${marcado} onchange="alternarSelecao(${Number(b.id)}, this.checked)" class="w-5 h-5 align-middle rounded border-gray-700 bg-gray-900 text-blue-500 focus:ring-blue-500 focus:ring-offset-gray-800">
                    </td>
                    <td class="p-4 font-medium text-gray-300"><div class="flex items-center gap-2">${dataExibicao} ${selo}</div></td>
                    <td class="p-4">
                        <div class="text-gray-100 font-semibold text-base">${descricao}</div>
                        ${b.codigo_boleto ? `<div class="text-xs text-gray-500 font-mono mt-1 cursor-pointer hover:text-blue-400 inline-block" onclick="copiarCodigo('${b.codigo_boleto}')" title="Clique para copiar">${b.codigo_boleto}</div>` : '<div class="text-xs text-gray-600 mt-1 italic">Sem código</div>'}
                    </td>
                    <td class="p-4 font-bold text-emerald-400 text-base">${valor}</td>
                    <td class="p-4 space-x-1">${btnAgendado(false)} ${btnPago(false)}</td>
                    <td class="p-4 text-center">
                        <button ${editar} class="bg-blue-500/10 text-blue-400 hover:bg-blue-500 hover:text-white h-8 w-8 rounded font-bold text-sm transition-colors border border-blue-500/20 mx-1" title="Editar">✏️</button>
                        <button onclick="apagar(${b.id})" class="bg-red-500/10 text-red-400 hover:bg-red-500 hover:text-white h-8 w-8 rounded font-bold text-sm transition-colors border border-red-500/20 mx-1" title="Excluir">🗑️</button>
                    </td>
                </tr>
            `);

            cards.push(`
                <div class="rounded-xl border border-gray-700 p-4 shadow-lg ${pago ? 'bg-gray-800' : ''} ${destaque}">
                    <div class="flex items-start gap-3">
                        <input type="checkbox" ${marcado} onchange="alternarSelecao(${Number(b.id)}, this.checked)" aria-label="Selecionar ${descricao}" class="w-6 h-6 mt-0.5 shrink-0 rounded border-gray-700 bg-gray-900 text-blue-500 focus:ring-blue-500 focus:ring-offset-gray-800">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <span class="text-gray-100 font-semibold break-words">${descricao}</span>
                                <span class="font-bold text-emerald-400 whitespace-nowrap">${valor}</span>
                            </div>
                            <div class="text-sm text-gray-400 mt-1 flex flex-wrap items-center gap-2"><span>Vence ${dataExibicao}</span> ${selo}</div>
                            ${b.codigo_boleto
                                ? `<button type="button" onclick="copiarCodigo('${b.codigo_boleto}')" class="mt-2 w-full text-left py-1.5 text-xs text-gray-500 font-mono truncate active:text-blue-400">📋 ${b.codigo_boleto}</button>`
                                : '<div class="mt-2 text-xs text-gray-600 italic">Sem código</div>'}
                        </div>
                    </div>
                    <div class="grid grid-cols-[1fr_1fr_2.75rem_2.75rem] gap-2 mt-3">
                        ${btnAgendado(true)}
                        ${btnPago(true)}
                        <button ${editar} aria-label="Editar" class="h-11 rounded-lg bg-blue-500/10 border border-blue-500/20 text-blue-400 active:bg-blue-500">✏️</button>
                        <button onclick="apagar(${b.id})" aria-label="Excluir" class="h-11 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 active:bg-red-500">🗑️</button>
                    </div>
                </div>
            `);
        });

        lista.innerHTML = linhas.join('');
        listaMobile.innerHTML = cards.length
            ? cards.join('')
            : '<p class="text-center text-sm text-gray-500 py-8">Nenhum boleto para mostrar.</p>';

        atualizarSelecao();
    }

    function fecharModal() {
        sessaoModal++;
        leiturasAtivas = 0;
        document.getElementById('modal').classList.add('hidden');
    }

    function salvarBoleto(e) {
        e.preventDefault();
        const id = document.getElementById('boletoId').value;
        const cards = [...document.querySelectorAll('#listaCards [data-card]')];

        cards.forEach(card => card.querySelector('[data-erro]').classList.add('hidden'));

        const boletos = cards.map(card => {
            const campos = camposDoCard(card);
            return {
                descricao: campos.descricao.value.trim(),
                data_vencimento: campos.vencimento.value,
                valor: tratarValorMonetario(campos.valor.value),
                codigo_boleto: campos.codigo.value.replace(/\D/g, ''),
                placeholder_id: card.querySelector('[data-vinculo]').value || null
            };
        });

        const action = id ? 'editar' : 'adicionar';
        const payload = id ? { ...boletos[0], id: id } : { boletos: boletos };
        const btnSalvar = document.getElementById('btnSalvar');
        btnSalvar.disabled = true;

        fetch('api.php?action=' + action, {
            method: 'POST',
            body: JSON.stringify(payload)
        }).then(r => r.json()).then(res => {
            if (res.erros) {
                res.erros.forEach(({ indice, erro }) => mostrarErroCard(cards[indice], erro));
                cards[res.erros[0].indice].scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else if (res.erro) {
                ML.toast(res.erro, 'erro');
            } else {
                fecharModal();
                carregar();
            }
        }).catch(() => {
            ML.toast('Não foi possível salvar. Verifique a conexão e tente novamente.', 'erro');
        }).finally(() => {
            btnSalvar.disabled = false;
        });
    }

    //setInterval(carregar, 1000);
    configurarArrastar();
    carregar();
    </script>
</body>
</html>