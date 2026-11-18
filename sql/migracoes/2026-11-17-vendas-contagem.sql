-- Separa pedidos relidos dos que mudaram de fato em cada atualização de vendas
ALTER TABLE ml_vendas.sincronizacoes
    ADD COLUMN novos INT UNSIGNED NOT NULL DEFAULT 0 AFTER pedidos,
    ADD COLUMN alterados INT UNSIGNED NOT NULL DEFAULT 0 AFTER novos;
