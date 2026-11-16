// Funções compartilhadas pelas telas de Contas Pessoais.
const Contas = (() => {
    const MESES = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

    function esc(texto) {
        return String(texto ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
    }

    function moeda(valor) {
        return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function valorCampo(valor) {
        return valor === null || valor === undefined ? '' : Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Aceita "1.234,56", "1234,56", "1234.56" e "1.234". Vazio é null; inválido é undefined.
    function lerValor(texto) {
        let limpo = String(texto).replace(/[R$\s]/g, '');
        if (limpo === '') return null;
        if (limpo.includes(',')) {
            limpo = limpo.replace(/\./g, '').replace(',', '.');
        } else if (/^\d{1,3}(\.\d{3})+$/.test(limpo)) {
            limpo = limpo.replace(/\./g, '');
        }
        return /^\d+(\.\d{1,2})?$/.test(limpo) ? Number(limpo) : undefined;
    }

    function mesAtual(hoje = new Date()) {
        return `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}`;
    }

    function somarMeses(mes, quantidade) {
        const [ano, numero] = mes.split('-').map(Number);
        return mesAtual(new Date(ano, numero - 1 + quantidade, 1));
    }

    function mesLabel(mes) {
        const [ano, numero] = mes.split('-').map(Number);
        return `${MESES[numero - 1]} de ${ano}`;
    }

    function mesCurto(mes) {
        const [ano, numero] = mes.split('-').map(Number);
        return `${MESES[numero - 1].slice(0, 3).toLowerCase()}/${String(ano).slice(2)}`;
    }

    async function api(action, corpo, parametros = {}) {
        const consulta = new URLSearchParams({ action, ...parametros });
        const opcoes = corpo === undefined ? { cache: 'no-store' } : {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(corpo),
        };
        const resposta = await fetch('api.php?' + consulta, opcoes);
        const dados = await resposta.json();
        if (dados.erro) throw new Error(dados.erro);
        return dados;
    }

    return { esc, moeda, valorCampo, lerValor, mesAtual, somarMeses, mesLabel, mesCurto, api };
})();
