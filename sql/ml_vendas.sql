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
    pedidos INT UNSIGNED NOT NULL DEFAULT 0,
    erro VARCHAR(500) NULL
);
