// Lê faturas de cartão em PDF no navegador: valor total, vencimento e compras parceladas.
// O arquivo e a senha não saem do navegador; o resultado só pré-preenche a revisão.
(function (global) {
    'use strict';

    const VALOR = '(\\d{1,3}(?:\\.\\d{3})*,\\d{2})';
    // Descrição que não engole um valor seguido de outro lançamento (faturas em duas colunas)
    const DESCRICAO = '((?:(?!\\d,\\d{2}(?:\\s|$)).)+?)';
    const MESES = { JAN: 1, FEV: 2, MAR: 3, ABR: 4, MAI: 5, JUN: 6, JUL: 7, AGO: 8, SET: 9, OUT: 10, NOV: 11, DEZ: 12 };

    function numero(texto) {
        return Number(texto.replace(/\./g, '').replace(',', '.'));
    }

    function iso(dia, mes, ano) {
        return `${ano}-${String(mes).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
    }

    function limparDescricao(texto) {
        return texto.replace(/\s+/g, ' ').replace(/[\s\-–]+$/, '').trim().slice(0, 150);
    }

    function primeiro(texto, regexes) {
        for (const regex of regexes) {
            const achou = texto.match(regex);
            if (achou) return achou;
        }
        return null;
    }

    // Aplica a regex de parcela em cada linha. grupos: [descricao, numero, total, valor]
    function parcelasNasLinhas(linhas, regex, grupos = [1, 2, 3, 4]) {
        const parcelas = [];
        for (const linha of linhas) {
            for (const m of linha.matchAll(regex)) {
                const [d, n, t, v] = grupos.map(g => m[g]);
                const item = { descricao: limparDescricao(d), numero: Number(n), total: Number(t), valor: numero(v) };
                if (item.descricao && item.total >= 2 && item.numero >= 1 && item.numero <= item.total && item.valor > 0) {
                    parcelas.push(item);
                }
            }
        }
        return parcelas;
    }

    // Lançamentos à vista (sem parcela): base para reconhecer assinaturas.
    // Pagamentos e estornos têm valor negativo e ficam de fora pela própria regex.
    const NAO_E_COMPRA = /^(pagamento|iof|estorno|encargos?|juros|multa|anuidade|saldo|total)\b/i;
    function avistaNasLinhas(linhas, regex, eParcela) {
        const itens = [];
        for (const linha of linhas) {
            for (const m of linha.matchAll(regex)) {
                const item = { descricao: limparDescricao(m[1]), valor: numero(m[2]) };
                if (item.descricao && item.valor > 0 && !eParcela.test(item.descricao) && !NAO_E_COMPRA.test(item.descricao)) {
                    itens.push(item);
                }
            }
        }
        return itens;
    }
    // 30/05 PG *GAMERS CLUB LTDASOR 21,90 (PicPay, Original e Itaú)
    const AVISTA_DATA_BARRA = new RegExp('(?:^|\\s)\\d{2}/\\d{2}\\s+' + DESCRICAO + '\\s+' + VALOR + '(?=\\s|$)', 'g');

    function linhasItau(linhas) {
        const fim = linhas.findIndex(l => /Compras parceladas\s*-\s*pr[oó]ximas faturas/i.test(l));
        return fim < 0 ? linhas : linhas.slice(0, fim);
    }

    // Cada banco: como reconhecer o PDF, onde está o total, o vencimento e as parcelas.
    const BANCOS = {
        nubank: {
            nome: 'Nubank',
            reconhece: t => /Nu Pagamentos|Nubank/i.test(t),
            total: t => primeiro(t, [
                new RegExp('no valor de\\s+R\\$\\s*' + VALOR, 'i'),
                new RegExp('Total a pagar\\s+R\\$\\s*' + VALOR + '\\s+Pagamento m[ií]nimo', 'i'),
            ]),
            vencimento: t => {
                const m = t.match(/vencimento:?\s*(\d{2})\s+([A-Z]{3})\s+(\d{4})/i);
                return m && MESES[m[2].toUpperCase()] ? iso(m[1], MESES[m[2].toUpperCase()], m[3]) : null;
            },
            // 28 SET •••• 2055 Mercadolivre*Mercadol - Parcela 8/10 R$ 71,69
            parcelas: linhas => parcelasNasLinhas(linhas, new RegExp(
                '\\d{2}\\s+[A-Z]{3}\\s+(?:[•·*]+\\s*\\d{4}\\s+)?' + DESCRICAO + '\\s*-\\s*Parcela\\s+(\\d{1,2})\\s*/\\s*(\\d{1,2})\\s+R\\$\\s*' + VALOR, 'gi')),
            // 06 OUT •••• 5704 Amazonprimebr R$ 19,90 (o final do cartão separa compras de outras linhas com data)
            avista: linhas => avistaNasLinhas(linhas, new RegExp(
                '\\d{2}\\s+[A-Z]{3}\\s+[•·*]+\\s*\\d{4}\\s+' + DESCRICAO + '\\s+R\\$\\s*' + VALOR, 'gi'), /-\s*Parcela\s+\d/i),
        },
        picpay: {
            nome: 'PicPay',
            reconhece: t => /Picpay Card|PicPay Bank/i.test(t),
            total: t => primeiro(t, [new RegExp('Total da fatura\\s+(?:R\\$\\s*)?' + VALOR, 'i')]),
            vencimento: t => {
                const m = t.match(/Vencimento:?\s*(\d{2})[-/](\d{2})[-/](\d{4})/i);
                return m ? iso(m[1], m[2], m[3]) : null;
            },
            // 14/03 CEA TAM 330 ECPARC02/03 113,32
            parcelas: linhas => parcelasNasLinhas(linhas, new RegExp(
                '\\d{2}/\\d{2}\\s+' + DESCRICAO + '\\s*PARC\\s?(\\d{2})\\s*/\\s*(\\d{2})\\s+' + VALOR, 'g')),
            avista: linhas => avistaNasLinhas(linhas, AVISTA_DATA_BARRA, /PARC\s?\d{2}\s*\/\s*\d{2}/i),
        },
        original: {
            nome: 'Original',
            reconhece: t => /\bOriginal\b/.test(t),
            total: t => primeiro(t, [
                new RegExp('SALDO TOTAL\\s+(?:R\\$\\s*)?' + VALOR, 'i'),
                new RegExp('Valor Total da Fatura\\s+R\\$\\s*' + VALOR, 'i'),
            ]),
            vencimento: t => {
                const m = t.match(/Vencimento da fatura[\s\S]{0,80}?(\d{2})\/(\d{2})\/(\d{4})/i);
                return m ? iso(m[1], m[2], m[3]) : null;
            },
            // 28/10 TELHA NORTE PARC02/03 105,66
            parcelas: linhas => parcelasNasLinhas(linhas, new RegExp(
                '\\d{2}/\\d{2}\\s+' + DESCRICAO + '\\s*PARC\\s?(\\d{2})\\s*/\\s*(\\d{2})\\s+' + VALOR, 'g')),
            avista: linhas => avistaNasLinhas(linhas, AVISTA_DATA_BARRA, /PARC\s?\d{2}\s*\/\s*\d{2}/i),
        },
        itau: {
            nome: 'Itaú',
            reconhece: t => /Ita[uú] Unibanco|Banco Ita[uú]/i.test(t),
            total: t => primeiro(t, [
                new RegExp('Total desta fatura\\s+(?:R\\$\\s*)?' + VALOR, 'i'),
                new RegExp('O total da sua fatura [eé]:[\\s\\S]{0,80}?R\\$\\s*' + VALOR, 'i'),
            ]),
            vencimento: t => {
                const m = t.match(/Vencimento:?\s*(\d{2})\/(\d{2})\/(\d{4})/i);
                return m ? iso(m[1], m[2], m[3]) : null;
            },
            // 07/09 DECOLARBARUERI 10/12 199,25. O quadro "Compras parceladas - próximas faturas"
            // repete as mesmas compras com a parcela seguinte, então a leitura para nele.
            parcelas: linhas => parcelasNasLinhas(linhasItau(linhas), new RegExp(
                '\\d{2}/\\d{2}\\s+' + DESCRICAO + '\\s+(\\d{2})/(\\d{2})\\s+' + VALOR, 'g')),
            avista: linhas => avistaNasLinhas(linhasItau(linhas), AVISTA_DATA_BARRA, /\s\d{2}\/\d{2}$/),
        },
    };

    // Compras iguais na mesma fatura viram "Descrição #2" para não se misturarem
    function numerarRepetidas(parcelas) {
        const vistos = {};
        return parcelas.map(p => {
            const chave = `${p.descricao}|${p.total}|${p.numero}`;
            vistos[chave] = (vistos[chave] || 0) + 1;
            return vistos[chave] > 1 ? { ...p, descricao: `${p.descricao} #${vistos[chave]}`.slice(0, 150) } : p;
        });
    }

    function detectarBanco(texto) {
        // Original vem antes do PicPay: a fatura do Original também cita o PicPay
        return ['nubank', 'itau', 'original', 'picpay'].find(b => BANCOS[b].reconhece(texto)) || null;
    }

    // linhas: texto de cada linha do PDF, na ordem de leitura
    function analisarLinhas(linhas, banco) {
        const texto = linhas.join('\n');
        const detectado = detectarBanco(texto);
        const regras = BANCOS[banco];
        if (!regras) throw new Error('Banco sem leitor de fatura: ' + banco);
        const total = regras.total(texto);
        return {
            banco,
            detectado,
            total: total ? numero(total[1]) : null,
            vencimento: regras.vencimento(texto),
            parcelas: numerarRepetidas(regras.parcelas(linhas)),
            avista: regras.avista(linhas),
            temTexto: texto.trim().length > 0,
        };
    }

    // ---------- Leitura do PDF (mesmo pdf.js dos boletos) ----------

    let pdfjsPromessa = null;

    function carregarPdfJs() {
        if (!pdfjsPromessa) {
            pdfjsPromessa = import('/boletos/vendor/pdfjs/pdf.min.js').then(pdfjs => {
                pdfjs.GlobalWorkerOptions.workerSrc = '/boletos/vendor/pdfjs/pdf.worker.min.js';
                return pdfjs;
            }).catch(erro => {
                pdfjsPromessa = null;
                throw erro;
            });
        }
        return pdfjsPromessa;
    }

    function linhasDaPagina(itens) {
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
        return linhas.map(l => l.itens.sort((a, b) => a.x - b.x).map(it => it.str).join(' ').replace(/\s+/g, ' '));
    }

    // pedirSenha(erroAnterior: boolean) resolve com a senha ou null (cancelou).
    // Lança Error com name 'Cancelado' se a pessoa desistir da senha.
    async function lerArquivo(arquivo, banco, pedirSenha) {
        const pdfjs = await carregarPdfJs();
        const bytes = new Uint8Array(await arquivo.arrayBuffer());
        let senha;
        for (;;) {
            // O pdf.js transfere o buffer para o worker, então cada tentativa usa uma cópia
            const tarefa = pdfjs.getDocument({ data: bytes.slice(), password: senha, isEvalSupported: false });
            try {
                const doc = await tarefa.promise;
                const linhas = [];
                for (let n = 1; n <= doc.numPages; n++) {
                    const pagina = await doc.getPage(n);
                    linhas.push(...linhasDaPagina((await pagina.getTextContent()).items));
                }
                return analisarLinhas(linhas, banco);
            } catch (erro) {
                if (erro.name !== 'PasswordException') throw erro;
                senha = await pedirSenha(senha !== undefined);
                if (senha === null) {
                    const cancelado = new Error('Leitura cancelada.');
                    cancelado.name = 'Cancelado';
                    throw cancelado;
                }
            } finally {
                await tarefa.destroy();
            }
        }
    }

    const BANCOS_DISPONIVEIS = Object.fromEntries(Object.entries(BANCOS).map(([id, b]) => [id, b.nome]));
    global.FaturaPdf = { lerArquivo, analisarLinhas, detectarBanco, BANCOS: BANCOS_DISPONIVEIS };
})(typeof window !== 'undefined' ? window : globalThis);
