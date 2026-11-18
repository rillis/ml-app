// Serviços de assinatura reconhecidos nas faturas. Cada padrão é procurado dentro da
// descrição sem acentos, espaços e pontuação ("Ec *Mp*Melimais" vira "ecmpmelimais"),
// então prefixos de intermediadoras (EBN*, DL*, PG *, MP*) não atrapalham.
// Padrões curtos ou genéricos (ex.: "max", "tim", "google") ficam de fora para não pegar compras comuns.
// A ordem importa: o primeiro que bater dá o nome sugerido.
(function (global) {
    'use strict';

    const SERVICOS = [
        // Vídeo
        ['Netflix', ['netflix']],
        ['Amazon Prime', ['amazonprime', 'primevideo']],
        ['Disney+', ['disneyplus', 'disney']],
        ['Max (HBO)', ['hbomax']],
        ['Globoplay', ['globoplay']],
        ['Paramount+', ['paramountplus', 'paramount']],
        ['Star+', ['starplus']],
        ['Crunchyroll', ['crunchyroll']],
        ['Telecine', ['telecine']],
        ['DAZN', ['dazn']],
        ['Apple TV+', ['appletv']],
        // Música
        ['Spotify', ['spotify']],
        ['Deezer', ['deezer']],
        ['Tidal', ['tidal']],
        ['Apple Music', ['applemusic']],
        ['Amazon Music', ['amazonmusic']],
        ['YouTube Music', ['youtubemusic']],
        ['YouTube Premium', ['youtube']],
        // Apple, Google e Microsoft
        ['Apple (App Store / iCloud)', ['applecombill', 'icloud']],
        ['Google One', ['googleone']],
        ['Google Workspace', ['googleworkspace', 'gsuite']],
        ['Microsoft 365', ['microsoft365', 'office365']],
        // Jogos
        ['Xbox Game Pass', ['xboxgamepass', 'gamepass']],
        ['PlayStation Plus', ['playstationplus', 'psplus']],
        ['EA Play', ['eaplay']],
        ['Ubisoft+', ['ubisoftplus']],
        ['Gamers Club', ['gamersclub']],
        ['Discord Nitro', ['discord']],
        ['Twitch', ['twitch']],
        ['Chess.com', ['chesscom']],
        // IA e software
        ['ChatGPT', ['openai', 'chatgpt']],
        ['Claude', ['claudeai', 'anthropic']],
        ['Midjourney', ['midjourney']],
        ['Perplexity', ['perplexity']],
        ['GitHub', ['github']],
        ['Canva', ['canva']],
        ['Adobe', ['adobe']],
        ['Dropbox', ['dropbox']],
        ['Notion', ['notionso', 'notionlabs']],
        ['Figma', ['figma']],
        ['JetBrains', ['jetbrains']],
        ['Grammarly', ['grammarly']],
        ['1Password', ['1password']],
        ['Bitwarden', ['bitwarden']],
        ['NordVPN', ['nordvpn']],
        ['ExpressVPN', ['expressvpn']],
        ['Surfshark', ['surfshark']],
        ['LinkedIn Premium', ['linkedin']],
        ['Patreon', ['patreon']],
        ['Substack', ['substack']],
        // Hospedagem e nuvem
        ['AWS', ['amazonwebservices']],
        ['DigitalOcean', ['digitalocean']],
        ['Cloudflare', ['cloudflare']],
        ['Hostinger', ['hostinger']],
        ['Vercel', ['vercel']],
        // Leitura e estudo
        ['Kindle Unlimited', ['kindleunlimited', 'kindleunltd']],
        ['Audible', ['audible']],
        ['Skeelo', ['skeelo']],
        ['Ubook', ['ubook']],
        ['Duolingo', ['duolingo']],
        ['Babbel', ['babbel']],
        ['Cambly', ['cambly']],
        ['Coursera', ['coursera']],
        ['Alura', ['alura']],
        ['Headspace', ['headspace']],
        ['Strava', ['strava']],
        // Clubes e serviços
        ['Meli+ (Mercado Livre)', ['melimais']],
        ['Uber One', ['uberone']],
        ['Clube iFood', ['ifoodclub']],
        ['Rappi Prime', ['rappiprime']],
        // Academia e bem-estar
        ['Smart Fit', ['smartfit']],
        ['Bluefit', ['bluefit']],
        ['TotalPass', ['totalpass']],
        ['Wellhub (Gympass)', ['wellhub', 'gympass']],
        // Relacionamento
        ['Tinder', ['tinder']],
        ['Bumble', ['bumble']],
        // Pedágio
        ['Sem Parar', ['semparar']],
        ['ConectCar', ['conectcar']],
        ['Veloe', ['veloe']],
    ];

    function chave(descricao) {
        return String(descricao).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]/g, '');
    }

    // Nome do serviço conhecido ou null
    function servico(descricao) {
        const c = chave(descricao);
        const achou = SERVICOS.find(([, padroes]) => padroes.some(p => c.includes(p)));
        return achou ? achou[0] : null;
    }

    // Liga os lançamentos à vista de uma fatura às assinaturas já acompanhadas do cartão.
    // Ordem: mesma descrição e valor; mesma descrição (valor mudou); mesmo serviço da lista.
    // Devolve { itens, sumiram }. Cada item: { descricao, valor, id, apelido, incluir, sugerida, motivo, valorAnterior, servico }
    // motivo: 'acompanhada' | 'valor' (mudou) | 'reativada' | 'nova' (da lista) | 'ignorada' | 'outro'
    function casar(avista, assinaturas, ignoradas, mes) {
        const itens = avista.map(l => ({ ...l, chave: chave(l.descricao), servico: servico(l.descricao), assinatura: null }));
        const livres = new Set(assinaturas);
        const ligar = (item, a) => { item.assinatura = a; livres.delete(a); };
        const passo = teste => itens.filter(i => !i.assinatura).forEach(item => {
            const a = [...livres].find(a => teste(item, a));
            if (a) ligar(item, a);
        });
        passo((i, a) => chave(a.descricao) === i.chave && a.valor === i.valor);
        passo((i, a) => chave(a.descricao) === i.chave);
        passo((i, a) => i.servico && servico(a.descricao) === i.servico && a.valor === i.valor);
        passo((i, a) => i.servico && servico(a.descricao) === i.servico);

        const resultado = itens.map(i => {
            const a = i.assinatura;
            const base = { descricao: i.descricao, valor: i.valor, servico: i.servico, id: null, apelido: '', valorAnterior: null };
            if (a) {
                const motivo = a.cancelada ? 'reativada' : a.valor !== i.valor ? 'valor' : 'acompanhada';
                return { ...base, id: a.id, apelido: a.apelido || '', valorAnterior: a.valor, motivo, incluir: true, sugerida: true };
            }
            if (ignoradas.some(g => chave(g.descricao) === i.chave && g.valor === i.valor)) {
                return { ...base, motivo: 'ignorada', incluir: false, sugerida: false };
            }
            if (i.servico) return { ...base, apelido: i.servico, motivo: 'nova', incluir: true, sugerida: true };
            return { ...base, motivo: 'outro', incluir: false, sugerida: false };
        });
        // Acompanhadas que não vieram nesta fatura (só as que já existiam neste mês)
        const sumiram = [...livres].filter(a => !a.cancelada && a.primeiroMes <= mes);
        return { itens: resultado, sumiram };
    }

    global.AssinaturasConhecidas = { chave, servico, casar, SERVICOS };
})(typeof window !== 'undefined' ? window : globalThis);
