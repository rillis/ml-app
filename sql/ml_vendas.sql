-- Banco do módulo Vendas: cópia dos pedidos do Mercado Livre (a API só guarda 12 meses).
CREATE DATABASE IF NOT EXISTS ml_vendas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Um pedido do ML. Vendas no mesmo carrinho dividem o pack_id.
CREATE TABLE IF NOT EXISTS ml_vendas.pedidos (
    id BIGINT UNSIGNED PRIMARY KEY,
    pack_id BIGINT UNSIGNED NULL,
    -- date_created convertido para America/Sao_Paulo
    data_venda DATETIME NOT NULL,
    status VARCHAR(30) NOT NULL,
    cancelada TINYINT(1) AS (status = 'cancelled') STORED,
    total DECIMAL(12,2) NOT NULL,
    comprador_id BIGINT UNSIGNED NULL,
    -- full = algum item saiu de armazém do ML (stock.node_id sem o id do vendedor)
    logistica ENUM('full', 'normal') NOT NULL,
    -- date_last_updated do ML, em UTC: base do botão Atualizar
    ml_atualizado DATETIME NOT NULL,
    bruto JSON NOT NULL,
    sincronizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_pedidos_data (data_venda),
    KEY idx_pedidos_atualizado (ml_atualizado)
);

CREATE TABLE IF NOT EXISTS ml_vendas.itens (
    pedido_id BIGINT UNSIGNED NOT NULL,
    seq TINYINT UNSIGNED NOT NULL,
    sku VARCHAR(100) NOT NULL DEFAULT '',
    anuncio VARCHAR(30) NOT NULL,
    variacao BIGINT UNSIGNED NULL,
    titulo VARCHAR(255) NOT NULL DEFAULT '',
    qtd INT UNSIGNED NOT NULL,
    preco DECIMAL(12,2) NOT NULL,
    comissao DECIMAL(12,2) NULL,
    tipo_anuncio VARCHAR(30) NULL,
    node_id VARCHAR(40) NULL,
    do_full TINYINT(1) NOT NULL,
    PRIMARY KEY (pedido_id, seq),
    KEY idx_itens_sku (sku),
    CONSTRAINT fk_itens_pedido FOREIGN KEY (pedido_id) REFERENCES ml_vendas.pedidos (id) ON DELETE CASCADE
);

-- Cada execução do Atualizar
CREATE TABLE IF NOT EXISTS ml_vendas.sincronizacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inicio TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fim TIMESTAMP NULL,
    modo ENUM('carga', 'atualizacao') NOT NULL,
    desde VARCHAR(40) NOT NULL,
    -- pedidos = lidos do ML (a folga de 1 h relê alguns); novos e alterados = o que mudou de fato
    pedidos INT UNSIGNED NOT NULL DEFAULT 0,
    novos INT UNSIGNED NOT NULL DEFAULT 0,
    alterados INT UNSIGNED NOT NULL DEFAULT 0,
    erro VARCHAR(500) NULL
);

-- Anúncios no Full e o estoque deles no ML, atualizados junto com as vendas
CREATE TABLE IF NOT EXISTS ml_vendas.full_anuncios (
    inventory_id VARCHAR(30) PRIMARY KEY,
    anuncio VARCHAR(30) NOT NULL,
    variacao BIGINT UNSIGNED NULL,
    -- todos os anúncios que vendem deste estoque: [{"id", "variacao", "sku", "titulo", "status", "sub_status"}]
    anuncios JSON NOT NULL,
    sku VARCHAR(100) NOT NULL DEFAULT '',
    titulo VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL,
    sub_status VARCHAR(100) NULL,
    disponivel INT NOT NULL DEFAULT 0,
    indisponivel INT NOT NULL DEFAULT 0,
    indisponivel_detalhe JSON NULL,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_full_anuncio (anuncio)
);

-- Foto diária do estoque Full (a última atualização do dia vale): base para saber os dias sem estoque
CREATE TABLE IF NOT EXISTS ml_vendas.full_estoque_dia (
    dia DATE NOT NULL,
    inventory_id VARCHAR(30) NOT NULL,
    disponivel INT NOT NULL,
    indisponivel INT NOT NULL,
    PRIMARY KEY (dia, inventory_id)
);

-- Anúncios que vendiam e não existem mais no ML (fim da aba Vendas), com os anúncios de hoje do mesmo SKU
CREATE TABLE IF NOT EXISTS ml_vendas.anuncios_extintos (
    anuncio VARCHAR(30) NOT NULL,
    sku VARCHAR(100) NOT NULL DEFAULT '',
    -- anúncios que existem hoje com o mesmo SKU: [{"id", "tipo", "full", "status", "titulo"}]
    equivalentes JSON NOT NULL,
    verificado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (anuncio, sku)
);

CREATE TABLE IF NOT EXISTS ml_vendas.ferias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inicio DATE NOT NULL,           -- dia em que o prazo começa com `dias`
    dias TINYINT UNSIGNED NOT NULL, -- 1 a 60; no dia inicio + dias o prazo chega a 0 e sai
    status ENUM('agendada', 'ativa', 'encerrando', 'encerrada', 'cancelada') NOT NULL DEFAULT 'agendada',
    criada_em DATETIME NOT NULL,
    encerrada_em DATETIME NULL
);

-- Um registro por user product (o prazo vale para todos os anúncios ligados a ele)
CREATE TABLE IF NOT EXISTS ml_vendas.ferias_produtos (
    ferias_id INT NOT NULL,
    user_product VARCHAR(30) NOT NULL,
    anuncios JSON NOT NULL,          -- ["MLB...", ...]
    titulo VARCHAR(255) NOT NULL DEFAULT '',
    original SMALLINT UNSIGNED NULL, -- prazo que já existia antes das férias (volta no fim)
    atual SMALLINT UNSIGNED NULL,    -- último prazo gravado pelas férias (NULL = sem prazo)
    atualizado_em DATETIME NULL,
    erro VARCHAR(500) NULL,
    PRIMARY KEY (ferias_id, user_product)
);

-- Cada rodada (a da meia-noite, as novas tentativas e as feitas pela tela)
CREATE TABLE IF NOT EXISTS ml_vendas.ferias_rodadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ferias_id INT NOT NULL,
    dia DATE NOT NULL,
    prazo SMALLINT UNSIGNED NOT NULL, -- prazo do dia (0 = tirar)
    inicio DATETIME NOT NULL,
    fim DATETIME NULL,
    total INT NOT NULL DEFAULT 0,       -- produtos a conferir
    feitos INT NOT NULL DEFAULT 0,      -- produtos já conferidos (progresso)
    atualizados INT NOT NULL DEFAULT 0,
    iguais INT NOT NULL DEFAULT 0,
    pulados INT NOT NULL DEFAULT 0,     -- ligados a um anúncio Full ou com Flex
    falhas INT NOT NULL DEFAULT 0,
    erro VARCHAR(500) NULL,
    KEY (ferias_id, dia)
);
