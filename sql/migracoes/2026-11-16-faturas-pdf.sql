-- Leitura de faturas em PDF e controle de parcelas (aplicar uma vez em bancos criados antes)
ALTER TABLE contas_pessoais.contas ADD COLUMN cartao VARCHAR(20) NULL AFTER categoria;
CREATE TABLE IF NOT EXISTS contas_pessoais.parcelamentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_id INT UNSIGNED NOT NULL,
    descricao VARCHAR(150) NOT NULL,
    total_parcelas TINYINT UNSIGNED NOT NULL,
    mes_inicio DATE NOT NULL,
    apelido VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_parcelamentos_compra (conta_id, descricao, total_parcelas, mes_inicio),
    CONSTRAINT fk_parcelamentos_conta FOREIGN KEY (conta_id) REFERENCES contas_pessoais.contas (id)
);

-- Parcela paga em cada mês (mes = dia 1 do mês da fatura, como em lancamentos)
CREATE TABLE IF NOT EXISTS contas_pessoais.parcelas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parcelamento_id INT UNSIGNED NOT NULL,
    mes DATE NOT NULL,
    numero TINYINT UNSIGNED NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    UNIQUE KEY uq_parcelas_mes (parcelamento_id, mes),
    KEY idx_parcelas_mes (mes),
    CONSTRAINT fk_parcelas_parcelamento FOREIGN KEY (parcelamento_id) REFERENCES contas_pessoais.parcelamentos (id) ON DELETE CASCADE
);
