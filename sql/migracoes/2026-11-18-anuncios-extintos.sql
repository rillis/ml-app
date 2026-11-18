-- Anúncios que vendiam e não existem mais no ML (fim da aba Vendas), com os anúncios de hoje do mesmo SKU
CREATE TABLE IF NOT EXISTS ml_vendas.anuncios_extintos (
    anuncio VARCHAR(30) NOT NULL,
    sku VARCHAR(100) NOT NULL DEFAULT '',
    -- anúncios que existem hoje com o mesmo SKU: [{"id", "tipo", "full", "status", "titulo"}]
    equivalentes JSON NOT NULL,
    verificado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (anuncio, sku)
);
