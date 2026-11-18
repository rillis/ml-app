-- Aba Full: anúncios no Full e foto diária do estoque
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
