-- Assinaturas reconhecidas nas faturas de cartão (aba Contas > Assinaturas)
CREATE TABLE IF NOT EXISTS contas_pessoais.assinaturas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_id INT UNSIGNED NOT NULL,
    descricao VARCHAR(150) NOT NULL,
    apelido VARCHAR(100) NULL,
    cancelada TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_assinaturas_conta (conta_id),
    CONSTRAINT fk_assinaturas_conta FOREIGN KEY (conta_id) REFERENCES contas_pessoais.contas (id)
);

CREATE TABLE IF NOT EXISTS contas_pessoais.assinatura_cobrancas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assinatura_id INT UNSIGNED NOT NULL,
    mes DATE NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    UNIQUE KEY uq_assinatura_cobrancas_mes (assinatura_id, mes),
    KEY idx_assinatura_cobrancas_mes (mes),
    CONSTRAINT fk_assinatura_cobrancas_assinatura FOREIGN KEY (assinatura_id) REFERENCES contas_pessoais.assinaturas (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contas_pessoais.assinaturas_ignoradas (
    conta_id INT UNSIGNED NOT NULL,
    descricao VARCHAR(150) NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (conta_id, descricao, valor),
    CONSTRAINT fk_assinaturas_ignoradas_conta FOREIGN KEY (conta_id) REFERENCES contas_pessoais.contas (id)
);
