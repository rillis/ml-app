// Avisos e diálogos do app, no lugar de alert(), confirm() e prompt().
// Textos entram sempre por textContent, nunca como HTML.
const ML = (() => {
    const CORES_TOAST = {
        sucesso: 'bg-emerald-900 border-emerald-500/50 text-emerald-100',
        erro: 'bg-red-900 border-red-500/50 text-red-100',
        aviso: 'bg-amber-900 border-amber-500/50 text-amber-100',
        info: 'bg-gray-800 border-gray-600 text-gray-100',
    };
    const CLASSE_BOTAO_SECUNDARIO = 'px-5 py-2.5 rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors';
    const CLASSE_BOTAO_PRIMARIO = 'px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-semibold transition-colors shadow-lg';
    const CLASSE_BOTAO_PERIGO = 'px-5 py-2.5 rounded-lg bg-red-600 hover:bg-red-500 text-white font-semibold transition-colors shadow-lg';

    function criar(tag, classe, texto) {
        const elemento = document.createElement(tag);
        if (classe) elemento.className = classe;
        if (texto !== undefined) elemento.textContent = texto;
        return elemento;
    }

    function areaToasts() {
        let area = document.getElementById('mlToasts');
        if (!area) {
            area = criar('div', 'fixed top-20 left-4 right-4 sm:left-auto sm:w-96 z-[80] flex flex-col gap-2 pointer-events-none');
            area.id = 'mlToasts';
            area.setAttribute('aria-live', 'polite');
            document.body.appendChild(area);
        }
        return area;
    }

    function toast(mensagem, tipo = 'info') {
        const aviso = criar('div', `pointer-events-auto border rounded-xl shadow-2xl px-4 py-3 text-sm font-medium transition-opacity duration-300 ${CORES_TOAST[tipo] || CORES_TOAST.info}`, mensagem);
        if (tipo === 'erro') aviso.setAttribute('role', 'alert');
        areaToasts().appendChild(aviso);

        const remover = () => {
            aviso.classList.add('opacity-0');
            setTimeout(() => aviso.remove(), 300);
        };
        aviso.addEventListener('click', remover);
        setTimeout(remover, tipo === 'erro' ? 7000 : 3500);
    }

    // Monta a janela e resolve a Promise com o valor passado a fechar().
    function abrirDialogo(titulo, montarConteudo, valorAoCancelar) {
        return new Promise(resolve => {
            const focoAnterior = document.activeElement;
            const fundo = criar('div', 'fixed inset-0 z-[70] bg-black/70 backdrop-blur-sm flex justify-center items-center p-4');
            const janela = criar('div', 'bg-gray-800 p-6 rounded-2xl border border-gray-700 shadow-2xl w-full max-w-md');
            janela.setAttribute('role', 'dialog');
            janela.setAttribute('aria-modal', 'true');
            const cabecalho = criar('h2', 'text-xl font-bold text-gray-100 mb-3', titulo);
            cabecalho.id = 'mlDialogoTitulo';
            janela.setAttribute('aria-labelledby', cabecalho.id);
            janela.appendChild(cabecalho);

            const fechar = valor => {
                fundo.remove();
                if (focoAnterior && focoAnterior.focus) focoAnterior.focus();
                resolve(valor);
            };

            const elementoInicial = montarConteudo(janela, fechar);

            fundo.addEventListener('mousedown', e => {
                if (e.target === fundo) fechar(valorAoCancelar);
            });
            fundo.addEventListener('keydown', e => {
                if (e.key === 'Escape') {
                    e.stopPropagation();
                    fechar(valorAoCancelar);
                } else if (e.key === 'Tab') {
                    // Mantém o Tab dentro da janela enquanto ela estiver aberta
                    const focaveis = [...janela.querySelectorAll('button, input')];
                    const primeiro = focaveis[0];
                    const ultimo = focaveis[focaveis.length - 1];
                    if (e.shiftKey && document.activeElement === primeiro) {
                        e.preventDefault();
                        ultimo.focus();
                    } else if (!e.shiftKey && document.activeElement === ultimo) {
                        e.preventDefault();
                        primeiro.focus();
                    }
                }
            });

            fundo.appendChild(janela);
            document.body.appendChild(fundo);
            elementoInicial.focus();
        });
    }

    // Resolve com true ou false. O foco começa em Cancelar para Enter não confirmar sem querer.
    function confirmar(mensagem, opcoes = {}) {
        const { titulo = 'Confirmar', textoConfirmar = 'Confirmar', perigo = false } = opcoes;
        return abrirDialogo(titulo, (janela, fechar) => {
            janela.appendChild(criar('p', 'text-gray-300 mb-6', mensagem));
            const acoes = criar('div', 'flex justify-end gap-3');
            const cancelar = criar('button', CLASSE_BOTAO_SECUNDARIO, 'Cancelar');
            const ok = criar('button', perigo ? CLASSE_BOTAO_PERIGO : CLASSE_BOTAO_PRIMARIO, textoConfirmar);
            cancelar.type = ok.type = 'button';
            cancelar.addEventListener('click', () => fechar(false));
            ok.addEventListener('click', () => fechar(true));
            acoes.append(cancelar, ok);
            janela.appendChild(acoes);
            return cancelar;
        }, false);
    }

    // Resolve com um inteiro maior que zero, ou null se a pessoa cancelar.
    function perguntarQuantidade(opcoes = {}) {
        const { titulo = 'Quantidade', dica = '', textoConfirmar = 'Confirmar', perigo = false } = opcoes;
        return abrirDialogo(titulo, (janela, fechar) => {
            const formulario = criar('form');
            formulario.noValidate = true;
            if (dica) formulario.appendChild(criar('p', 'text-sm text-gray-400 mb-3', dica));

            const campo = criar('input', 'w-full bg-gray-900 border border-gray-700 rounded-lg p-3 text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500');
            campo.type = 'number';
            campo.min = '1';
            campo.step = '1';
            campo.inputMode = 'numeric';
            campo.placeholder = 'Quantidade';
            campo.setAttribute('aria-label', 'Quantidade');

            const erro = criar('p', 'hidden text-sm text-red-400 font-semibold mt-2', 'Informe um número inteiro maior que zero.');
            const acoes = criar('div', 'flex justify-end gap-3 mt-6');
            const cancelar = criar('button', CLASSE_BOTAO_SECUNDARIO, 'Cancelar');
            const ok = criar('button', perigo ? CLASSE_BOTAO_PERIGO : CLASSE_BOTAO_PRIMARIO, textoConfirmar);
            cancelar.type = 'button';
            ok.type = 'submit';
            cancelar.addEventListener('click', () => fechar(null));
            acoes.append(cancelar, ok);

            formulario.addEventListener('submit', e => {
                e.preventDefault();
                const quantidade = Number(campo.value);
                if (!Number.isInteger(quantidade) || quantidade <= 0) {
                    erro.classList.remove('hidden');
                    campo.focus();
                    return;
                }
                fechar(quantidade);
            });

            formulario.append(campo, erro, acoes);
            janela.appendChild(formulario);
            return campo;
        }, null);
    }

    return { toast, confirmar, perguntarQuantidade };
})();
