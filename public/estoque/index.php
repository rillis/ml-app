<?php require __DIR__ . '/../../src/auth.php'; 

function ler_file($file2) {
    $dados = [];
    if (($h = fopen($file2, 'r')) !== false) {
        fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            $dados[] = [
                'sku' => strtoupper(trim($row[0]))
            ];
        }
        fclose($h);
    }
    return $dados;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estoque</title>
    <script src="/assets/tailwind.js"></script>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>

<body class="bg-gray-900 text-gray-100 min-h-screen p-8 font-sans selection:bg-blue-500 selection:text-white">
    <?php require __DIR__ . '/../../src/nav.php'; ?>

    <div class="max-w-5xl mx-auto">
        
        <header class="mb-10 flex justify-between items-end border-b border-gray-700 pb-4">
            <div>
                <h1 class="text-4xl font-bold">
                    <span aria-hidden="true">📦</span>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400">Estoque</span>
                </h1>
            </div>
        </header>

        <div class="mb-8 flex gap-3">
            <input
              id="busca"
              placeholder="Buscar SKU..."
              oninput="renderizar()"
              class="flex-1 bg-gray-800 border border-gray-700 rounded-xl p-4 text-lg text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-lg transition-colors"
            >
            <button onclick="copiarEstoque()" id="btn-copy" title="Copiar Estoque"
                    class="bg-gray-800 hover:bg-gray-700 border border-gray-700 hover:border-blue-500 text-gray-300 hover:text-blue-400 rounded-xl px-6 flex items-center justify-center transition-all shadow-lg text-2xl">
                📋
            </button>
        </div>

        <div id="lista" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-10"></div>

        <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 shadow-lg mb-10">
            <h2 class="text-xl font-semibold mb-4 text-gray-300">Adicionar Item</h2>
            <div class="flex flex-col sm:flex-row gap-4">
                <input id="sku" list="lista-skus" placeholder="SKU" autocomplete="off" 
                       class="flex-1 bg-gray-900 border border-gray-700 rounded-lg p-3 text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                
                <datalist id="lista-skus">
                  <?php foreach (ler_file(__DIR__ . '/../../src/estoque/sku.csv') as $item): ?>
                    <option value="<?= htmlspecialchars($item['sku'], ENT_QUOTES, 'UTF-8') ?>">
                  <?php endforeach; ?>
                </datalist>
                
                <input id="qtd" type="number" placeholder="Quantidade" pattern="[0-9]*" inputmode="numeric" 
                       class="w-full sm:w-40 bg-gray-900 border border-gray-700 rounded-lg p-3 text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                
                <button onclick="adicionar()" 
                        class="bg-blue-600 hover:bg-blue-500 text-white font-semibold py-3 px-6 rounded-lg transition-colors shadow-lg">
                    Adicionar
                </button>
            </div>
        </div>

        <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 shadow-lg">
            <h2 class="text-xl font-semibold mb-4 text-gray-300">🕒 Últimas alterações</h2>
            <div id="hist" class="space-y-2"></div>
        </div>

    </div>

<script src="/assets/ui.js?v=<?= filemtime(__DIR__ . '/../assets/ui.js') ?>"></script>
<script>
let cache = [];

function normalizar(txt) {
  return txt.toUpperCase().trim();
}

function carregar() {
  fetch('api.php?action=listar')
    .then(r => r.json())
    .then(dados => {
      cache = dados;
      renderizar();
      carregarHistorico();
    });
}

function renderizar() {
  const lista = document.getElementById('lista');
  const termo = normalizar(document.getElementById('busca').value);

  lista.innerHTML = '';

  cache
    .filter(item => item.qtd > 0)
    .filter(item => normalizar(item.sku).includes(termo))
    .sort((a, b) => a.sku.localeCompare(b.sku, 'pt-BR'))
    .forEach(item => {
      lista.innerHTML += `
        <div class="bg-gray-800 p-5 rounded-xl border border-gray-700 shadow-lg flex justify-between items-center transition-all duration-200 hover:-translate-y-1 hover:border-blue-500 hover:shadow-blue-500/20">
          <div>
            <span class="block text-lg font-bold text-gray-100">${item.sku}</span>
            <span class="text-sm text-gray-400">Qtd: <span class="text-emerald-400 font-semibold">${item.qtd}</span></span>
          </div>
          <div class="flex gap-2">
            <button class="bg-red-500/10 text-red-400 hover:bg-red-500 hover:text-white h-10 w-10 rounded-lg font-bold text-lg transition-colors flex items-center justify-center border border-red-500/20 hover:border-red-500" onclick="remover('${item.sku}')">−</button>
            <button class="bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500 hover:text-white h-10 w-10 rounded-lg font-bold text-lg transition-colors flex items-center justify-center border border-emerald-500/20 hover:border-emerald-500" onclick="adicionarSku('${item.sku}')">+</button>
          </div>
        </div>
      `;
    });
}

// Devolve true se a alteração foi gravada. Sempre recarrega a lista no final.
function movimentar(action, sku, qtd) {
  return fetch('api.php?action=' + action, {
    method: 'POST',
    body: JSON.stringify({ sku, qtd })
  }).then(r => r.json()).then(res => {
    if (res.error) {
      ML.toast(res.error, 'erro');
      return false;
    }
    if (res.removido !== undefined && res.removido < res.pedido) {
      ML.toast(`Só havia ${res.removido} em estoque: ${res.removido} removido(s), não ${res.pedido}.`, 'aviso');
    }
    return true;
  }).catch(() => {
    ML.toast('Não foi possível salvar. Verifique a conexão e tente novamente.', 'erro');
    return false;
  }).finally(carregar);
}

async function remover(sku) {
  const item = cache.find(i => i.sku === sku);
  const qtd = await ML.perguntarQuantidade({
    titulo: `Remover ${sku}`,
    dica: item ? `Em estoque: ${item.qtd}` : '',
    textoConfirmar: 'Remover',
    perigo: true
  });
  if (qtd) movimentar('remover', sku.toUpperCase(), qtd);
}

async function adicionarSku(sku) {
  const item = cache.find(i => i.sku === sku);
  const qtd = await ML.perguntarQuantidade({
    titulo: `Adicionar ${sku}`,
    dica: item ? `Em estoque: ${item.qtd}` : '',
    textoConfirmar: 'Adicionar'
  });
  if (qtd) movimentar('adicionar', sku.toUpperCase(), qtd);
}

async function adicionar() {
  const sku = document.getElementById('sku').value.toUpperCase().trim();
  const qtd = document.getElementById('qtd').value;

  if (!sku || !qtd || qtd <= 0) return;

  if (await movimentar('adicionar', sku, qtd)) {
    document.getElementById('sku').value = '';
    document.getElementById('qtd').value = '';
  }
}

function copiarEstoque() {
  const itensEmEstoque = cache
    .filter(item => item.qtd > 0)
    .sort((a, b) => a.sku.localeCompare(b.sku, 'pt-BR'))
    .map(item => `${item.qtd}x ${item.sku}`)
    .join('\n');

  if (!itensEmEstoque) return;

  const btn = document.getElementById('btn-copy');
  const original = btn.innerHTML;
  
  const sucesso = () => {
    btn.innerHTML = '✅';
    setTimeout(() => { btn.innerHTML = original; }, 2000);
  };

  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(itensEmEstoque).then(sucesso);
  } else {
    const textArea = document.createElement("textarea");
    textArea.value = itensEmEstoque;
    
    textArea.style.position = "fixed";
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.width = "2em";
    textArea.style.height = "2em";
    textArea.style.padding = "0";
    textArea.style.border = "none";
    textArea.style.outline = "none";
    textArea.style.boxShadow = "none";
    textArea.style.background = "transparent";
    
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
      document.execCommand('copy');
      sucesso();
    } catch (err) {
      console.error('Falha ao copiar', err);
    }

    document.body.removeChild(textArea);
  }
}

function carregarHistorico() {
  fetch('api.php?action=historico')
    .then(r => r.json())
    .then(dados => {
      const h = document.getElementById('hist');
      h.innerHTML = '';
      dados.forEach(item => {
        h.innerHTML += `
          <div class="text-sm bg-gray-900/50 p-3 rounded-lg border border-gray-700/50 text-gray-300 flex flex-wrap gap-2 items-center">
            <span class="text-gray-500 min-w-[50px]">${item.hora}</span> 
            <span class="text-blue-400">👤 ${item.user}</span> 
            <span class="text-gray-600">|</span>
            <span class="font-medium text-gray-200">${item.tipo} ${item.qtd}</span> 
            <span class="text-gray-500">(${item.sku})</span>
          </div>
        `;
      });
    });
}

carregar();
setInterval(carregar, 1000);
</script>

</body>
</html>