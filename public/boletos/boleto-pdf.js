// Lê PDFs no navegador e identifica os boletos contidos neles.
// Nada é salvo aqui: o resultado só pré-preenche o formulário para o usuário revisar.
(function (global) {
    'use strict';

    // ---------- Dígitos verificadores (FEBRABAN) ----------

    function mod10(digitos) {
        let soma = 0, peso = 2;
        for (let i = digitos.length - 1; i >= 0; i--) {
            const p = Number(digitos[i]) * peso;
            soma += p > 9 ? p - 9 : p;
            peso = peso === 2 ? 1 : 2;
        }
        return (10 - (soma % 10)) % 10;
    }

    function somaMod11(digitos) {
        let soma = 0, peso = 2;
        for (let i = digitos.length - 1; i >= 0; i--) {
            soma += Number(digitos[i]) * peso;
            peso = peso === 9 ? 2 : peso + 1;
        }
        return soma % 11;
    }

    // Boleto bancário: resultado 0, 10 ou 11 vira 1.
    function mod11Banco(digitos) {
        const dv = 11 - somaMod11(digitos);
        return dv === 0 || dv > 9 ? 1 : dv;
    }

    // Arrecadação (contas de consumo e tributos): resto 0 ou 1 vira 0.
    function mod11Arrecadacao(digitos) {
        const resto = somaMod11(digitos);
        return resto < 2 ? 0 : 11 - resto;
    }

    // ---------- Boleto bancário (47 dígitos) ----------

    function barrasDaLinhaBancaria(l) {
        return l.slice(0, 4) + l[32] + l.slice(33, 47) + l.slice(4, 9) + l.slice(10, 20) + l.slice(21, 31);
    }

    function barrasBancariaValida(b) {
        return b[3] === '9' && mod11Banco(b.slice(0, 4) + b.slice(5)) === Number(b[4]);
    }

    function linhaBancariaValida(l) {
        return mod10(l.slice(0, 9)) === Number(l[9])
            && mod10(l.slice(10, 20)) === Number(l[20])
            && mod10(l.slice(21, 31)) === Number(l[31])
            && barrasBancariaValida(barrasDaLinhaBancaria(l));
    }

    function linhaDasBarrasBancaria(b) {
        const c1 = b.slice(0, 4) + b.slice(19, 24);
        const c2 = b.slice(24, 34);
        const c3 = b.slice(34, 44);
        return c1 + mod10(c1) + c2 + mod10(c2) + c3 + mod10(c3) + b[4] + b.slice(5, 19);
    }

    // O fator de vencimento voltou a 1000 em 22/02/2025; a data mais próxima de hoje é a correta.
    function dataDoFator(fator, hoje) {
        if (fator < 1000) return null;
        const DIA = 86400000;
        const c1 = Date.UTC(1997, 9, 7) + fator * DIA;
        const c2 = Date.UTC(2025, 1, 22) + (fator - 1000) * DIA;
        const agora = hoje.getTime();
        const data = Math.abs(c1 - agora) < Math.abs(c2 - agora) ? c1 : c2;
        return new Date(data).toISOString().slice(0, 10);
    }

    // ---------- Arrecadação (48 dígitos, sempre começa com 8) ----------

    function dvArrecadacao(idValor) {
        return idValor === '6' || idValor === '7' ? mod10 : mod11Arrecadacao;
    }

    function barrasArrecadacaoValida(b) {
        if (b[0] !== '8' || !'6789'.includes(b[2])) return false;
        return dvArrecadacao(b[2])(b.slice(0, 3) + b.slice(4)) === Number(b[3]);
    }

    function linhaArrecadacaoValida(l) {
        if (l[0] !== '8' || !'6789'.includes(l[2])) return false;
        const dv = dvArrecadacao(l[2]);
        for (let i = 0; i < 48; i += 12) {
            if (dv(l.slice(i, i + 11)) !== Number(l[i + 11])) return false;
        }
        return barrasArrecadacaoValida(barrasDaLinhaArrecadacao(l));
    }

    function barrasDaLinhaArrecadacao(l) {
        return l.slice(0, 11) + l.slice(12, 23) + l.slice(24, 35) + l.slice(36, 47);
    }

    function linhaDasBarrasArrecadacao(b) {
        const dv = dvArrecadacao(b[2]);
        let linha = '';
        for (let i = 0; i < 44; i += 11) {
            const bloco = b.slice(i, i + 11);
            linha += bloco + dv(bloco);
        }
        return linha;
    }

    // Converte o código salvo (linha digitável de 47/48 dígitos ou barras de 44) nos 44 dígitos das barras.
    // Devolve null se os dígitos verificadores não baterem.
    function barrasDoCodigo(codigo) {
        const c = String(codigo || '').replace(/\D/g, '');
        if (c.length === 47) return linhaBancariaValida(c) ? barrasDaLinhaBancaria(c) : null;
        if (c.length === 48) return linhaArrecadacaoValida(c) ? barrasDaLinhaArrecadacao(c) : null;
        if (c.length === 44) return barrasBancariaValida(c) || barrasArrecadacaoValida(c) ? c : null;
        return null;
    }

    // Linha digitável (47/48 dígitos) do código salvo, que é o formato aceito ao colar no app do banco.
    function linhaDoCodigo(codigo) {
        const barras = barrasDoCodigo(codigo);
        if (!barras) return null;
        return barras[0] === '8' ? linhaDasBarrasArrecadacao(barras) : linhaDasBarrasBancaria(barras);
    }

    // ---------- Interpretação do código ----------

    function valorOuNulo(digitos) {
        const n = Number(digitos) / 100;
        return n > 0 ? n : null;
    }

    function interpretarCodigo(codigo, hoje) {
        if (codigo.length === 47) {
            return {
                codigo,
                tipo: 'bancario',
                vencimento: dataDoFator(Number(codigo.slice(33, 37)), hoje),
                valor: valorOuNulo(codigo.slice(37, 47)),
            };
        }
        // Nos identificadores 7 e 9 o campo é um valor de referência, não o valor em reais.
        const valorEmReais = codigo[2] === '6' || codigo[2] === '8';
        const barras = barrasDaLinhaArrecadacao(codigo);
        return {
            codigo,
            tipo: 'arrecadacao',
            vencimento: null,
            valor: valorEmReais ? valorOuNulo(barras.slice(4, 15)) : null,
        };
    }

    // Procura códigos em um texto. Cada um tem seus dígitos verificadores conferidos, então
    // sequências numéricas quaisquer (CNPJ, nosso número...) não viram boleto por engano.
    function encontrarCodigos(texto) {
        const achados = [];
        const trechos = /\d[\d\s.\-]{42,}\d/g;
        let m;
        while ((m = trechos.exec(texto))) {
            let digitos = '';
            const posicoes = [];
            for (let i = 0; i < m[0].length; i++) {
                if (m[0][i] >= '0' && m[0][i] <= '9') {
                    digitos += m[0][i];
                    posicoes.push(m.index + i);
                }
            }
            let i = 0;
            while (i + 44 <= digitos.length) {
                let codigo = null, tamanho = 0;
                if (i + 48 <= digitos.length && linhaArrecadacaoValida(digitos.slice(i, i + 48))) {
                    codigo = digitos.slice(i, i + 48);
                    tamanho = 48;
                } else if (i + 47 <= digitos.length && linhaBancariaValida(digitos.slice(i, i + 47))) {
                    codigo = digitos.slice(i, i + 47);
                    tamanho = 47;
                } else if (digitos.length === 44 && m[0].length === 44) {
                    // Código de barras (44 dígitos) só é aceito sem separadores, pois tem um único
                    // verificador. É convertido para a linha digitável, o formato usado no sistema.
                    if (barrasArrecadacaoValida(digitos)) codigo = linhaDasBarrasArrecadacao(digitos);
                    else if (barrasBancariaValida(digitos)) codigo = linhaDasBarrasBancaria(digitos);
                    tamanho = 44;
                }
                if (codigo) {
                    achados.push({ codigo, posicao: posicoes[i] });
                    i += tamanho;
                } else {
                    i++;
                }
            }
        }
        return achados;
    }

    // ---------- Campos procurados no texto ----------

    function normalizar(s) {
        return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function dataBrParaIso(dia, mes, ano) {
        const a = ano.length === 2 ? '20' + ano : ano;
        const d = new Date(Date.UTC(Number(a), Number(mes) - 1, Number(dia)));
        if (d.getUTCMonth() !== Number(mes) - 1 || d.getUTCDate() !== Number(dia)) return null;
        return d.toISOString().slice(0, 10);
    }

    function vencimentoNoTexto(texto) {
        const m = texto.match(/(?:vencimento|vence\s+em)[^\d]{0,80}(\d{2})\/(\d{2})\/(\d{4}|\d{2})\b/i);
        return m ? dataBrParaIso(m[1], m[2], m[3]) : null;
    }

    function valorNoTexto(texto) {
        const m = texto.match(/(?:valor(?:\s+(?:do\s+documento|cobrado|a\s+pagar|total))?|total\s+a\s+pagar)[^\d]{0,80}?(\d{1,3}(?:\.\d{3})*,\d{2}|\d+,\d{2})\b/i);
        if (!m) return null;
        const n = Number(m[1].replace(/\./g, '').replace(',', '.'));
        return n > 0 ? n : null;
    }

    const PARECE_ROTULO = /ag[eê]ncia|c[oó]digo|cnpj|cpf|vencimento|valor|data\b|nosso|documento|esp[eé]cie|quantidade|carteira|aceite|pagador|sacado/i;

    function limparNome(s) {
        const nome = s
            .replace(/\b(?:cnpj|cpf)\b.*$/i, '')
            .replace(/\d{2,3}\.?\d{3}\.?\d{3}[\/\-]?\d{0,4}-?\d{0,2}.*$/, '')
            .replace(/[\s\-–:,\/]+$/, '')
            .replace(/^[\s\-–:,\/]+/, '')
            .replace(/\s+/g, ' ')
            .slice(0, 120);
        return (nome.match(/\p{L}/gu) || []).length >= 3 && !PARECE_ROTULO.test(nome) ? nome : '';
    }

    function beneficiarioNasLinhas(linhas, inicio, fim, alvo) {
        const rotulo = /(?:benefici[aá]rio|cedente|favorecido|raz[aã]o\s+social)(?:\s+final)?\s*[:\-–]?\s*(.*)$/i;
        const ignorar = /recibo|endere[cç]o|cpf|cnpj|autentica/i;
        let melhor = '', menorDistancia = Infinity;

        for (let l = inicio; l <= fim; l++) {
            const itens = linhas[l].itens;
            for (let i = 0; i < itens.length; i++) {
                const m = itens[i].str.match(rotulo);
                // Só o que vem antes do rótulo descarta: "Endereço do Beneficiário", mas não "Beneficiário: X - CNPJ...".
                if (!m || ignorar.test(itens[i].str.slice(0, m.index))) continue;

                let nome = limparNome(m[1]);
                if (!nome && itens[i + 1]) nome = limparNome(itens[i + 1].str);
                if (!nome && linhas[l + 1]) {
                    // Em layouts de colunas, o nome fica logo abaixo do rótulo.
                    const abaixo = linhas[l + 1].itens.find(it => it.x >= itens[i].x - 15);
                    if (abaixo) nome = limparNome(abaixo.str);
                }
                const distancia = Math.abs(l - alvo);
                if (nome && distancia < menorDistancia) {
                    melhor = nome;
                    menorDistancia = distancia;
                }
            }
        }
        return melhor;
    }

    // Reaproveita uma descrição já usada no sistema quando ela aparece no boleto.
    function descricaoConhecida(texto, conhecidas) {
        const alvo = ' ' + normalizar(texto).replace(/[^\p{L}\p{N} ]/gu, ' ').replace(/\s+/g, ' ') + ' ';
        let melhor = '', tamanho = 0;
        for (const d of conhecidas) {
            const n = normalizar(d).replace(/[^\p{L}\p{N} ]/gu, ' ').replace(/\s+/g, ' ').trim();
            if (n.length >= 5 && n.length > tamanho && alvo.includes(' ' + n + ' ')) {
                melhor = d;
                tamanho = n.length;
            }
        }
        return melhor;
    }

    // ---------- Análise das páginas ----------

    // paginas: uma lista de linhas por página; cada linha é { itens: [{ x, str }] }.
    function analisarPaginas(paginas, opcoes = {}) {
        const hoje = opcoes.hoje || new Date();
        const conhecidas = opcoes.descricoesConhecidas || [];
        const porCodigo = new Map();
        const textoCompleto = [];

        paginas.forEach((linhas, indice) => {
            let texto = '';
            const inicios = linhas.map(linha => {
                const inicio = texto.length;
                texto += linha.itens.map(it => it.str).join(' ') + '\n';
                return inicio;
            });
            textoCompleto.push(texto);

            const linhaDaPosicao = pos => {
                let l = 0;
                while (l + 1 < inicios.length && inicios[l + 1] <= pos) l++;
                return l;
            };
            const ocorrencias = encontrarCodigos(texto).map(o => ({ ...o, linha: linhaDaPosicao(o.posicao) }));

            ocorrencias.forEach(oc => {
                // O trecho de cada boleto vai até a ocorrência de outro código na mesma página.
                const anteriores = ocorrencias.filter(o => o.codigo !== oc.codigo && o.posicao < oc.posicao);
                const posteriores = ocorrencias.filter(o => o.codigo !== oc.codigo && o.posicao > oc.posicao);
                const primeira = anteriores.length ? anteriores[anteriores.length - 1].linha : 0;
                const ultima = posteriores.length ? posteriores[0].linha : Math.max(linhas.length - 1, 0);
                const trecho = linhas.slice(primeira, ultima + 1).map(l => l.itens.map(it => it.str).join(' ')).join('\n');

                if (!porCodigo.has(oc.codigo)) {
                    porCodigo.set(oc.codigo, { ...interpretarCodigo(oc.codigo, hoje), descricao: '', paginas: [] });
                }
                const boleto = porCodigo.get(oc.codigo);
                if (!boleto.paginas.includes(indice + 1)) boleto.paginas.push(indice + 1);
                if (!boleto.vencimento) boleto.vencimento = vencimentoNoTexto(trecho);
                if (!boleto.valor) boleto.valor = valorNoTexto(trecho);
                if (!boleto.descricao) {
                    boleto.descricao = descricaoConhecida(trecho, conhecidas)
                        || beneficiarioNasLinhas(linhas, primeira, ultima, oc.linha);
                }
            });
        });

        const texto = textoCompleto.join('\n');
        return {
            boletos: [...porCodigo.values()],
            temTexto: /\p{L}{3}|\d{3}/u.test(texto),
        };
    }

    // ---------- Leitura do PDF ----------

    let pdfjsPromessa = null;

    function carregarPdfJs() {
        if (!pdfjsPromessa) {
            pdfjsPromessa = import('./vendor/pdfjs/pdf.min.js').then(pdfjs => {
                pdfjs.GlobalWorkerOptions.workerSrc = 'vendor/pdfjs/pdf.worker.min.js';
                return pdfjs;
            }).catch(erro => {
                pdfjsPromessa = null;
                throw erro;
            });
        }
        return pdfjsPromessa;
    }

    function agruparLinhas(itens) {
        const validos = itens
            .filter(it => it.str && it.str.trim())
            .map(it => ({ str: it.str.trim(), x: it.transform[4], y: it.transform[5] }))
            .sort((a, b) => b.y - a.y || a.x - b.x);

        const linhas = [];
        for (const it of validos) {
            const atual = linhas[linhas.length - 1];
            if (atual && Math.abs(atual.y - it.y) <= 3) atual.itens.push(it);
            else linhas.push({ y: it.y, itens: [it] });
        }
        linhas.forEach(l => l.itens.sort((a, b) => a.x - b.x));
        return linhas;
    }

    async function lerArquivo(arquivo, opcoes) {
        const pdfjs = await carregarPdfJs();
        const dados = await arquivo.arrayBuffer();
        const tarefa = pdfjs.getDocument({ data: dados, isEvalSupported: false });
        try {
            const doc = await tarefa.promise;
            const paginas = [];
            for (let n = 1; n <= doc.numPages; n++) {
                const pagina = await doc.getPage(n);
                const conteudo = await pagina.getTextContent();
                paginas.push(agruparLinhas(conteudo.items));
            }
            return analisarPaginas(paginas, opcoes);
        } finally {
            await tarefa.destroy();
        }
    }

    const api = { lerArquivo, barrasDoCodigo, linhaDoCodigo, analisarPaginas, normalizar, encontrarCodigos, interpretarCodigo, linhaBancariaValida, linhaArrecadacaoValida, mod10, mod11Banco, mod11Arrecadacao, dvArrecadacao };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    else global.BoletoPdf = api;
})(typeof window !== 'undefined' ? window : globalThis);
