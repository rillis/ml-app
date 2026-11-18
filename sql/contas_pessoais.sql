-- Banco do módulo Contas Pessoais. Valor preenchido = conta paga naquele mês.
CREATE DATABASE IF NOT EXISTS contas_pessoais CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contas_pessoais.contas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    nome VARCHAR(100) NOT NULL,
    categoria VARCHAR(60) NULL,
    -- Banco da fatura em PDF (nubank, picpay, original, itau). NULL = conta comum.
    cartao VARCHAR(20) NULL,
    dia_vencimento TINYINT UNSIGNED NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_contas_usuario_nome (username, nome)
);

-- mes guarda sempre o dia 1 do mês em que a conta foi paga
CREATE TABLE IF NOT EXISTS contas_pessoais.lancamentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_id INT UNSIGNED NOT NULL,
    mes DATE NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lancamentos_conta_mes (conta_id, mes),
    KEY idx_lancamentos_mes (mes),
    CONSTRAINT fk_lancamentos_conta FOREIGN KEY (conta_id) REFERENCES contas_pessoais.contas (id)
);

-- Meses fechados pelo botão "Salvar mês": só eles entram nos Insights
CREATE TABLE IF NOT EXISTS contas_pessoais.meses_salvos (
    username VARCHAR(100) NOT NULL,
    mes DATE NOT NULL,
    salvo_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (username, mes)
);

-- Compras parceladas lidas das faturas. Uma compra é a mesma de um mês para o outro
-- quando bate cartão, descrição, número de parcelas e mês da 1ª parcela.
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

-- Assinaturas lidas das faturas. Uma cobrança é ligada à assinatura pela descrição e pelo
-- valor; se o valor mudar, pela descrição. cancelada = a pessoa confirmou o cancelamento.
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

-- Cobrança de cada mês (mes = dia 1 do mês da fatura)
CREATE TABLE IF NOT EXISTS contas_pessoais.assinatura_cobrancas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assinatura_id INT UNSIGNED NOT NULL,
    mes DATE NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    UNIQUE KEY uq_assinatura_cobrancas_mes (assinatura_id, mes),
    KEY idx_assinatura_cobrancas_mes (mes),
    CONSTRAINT fk_assinatura_cobrancas_assinatura FOREIGN KEY (assinatura_id) REFERENCES contas_pessoais.assinaturas (id) ON DELETE CASCADE
);

-- Lançamentos marcados como "não é assinatura": não são mais sugeridos
CREATE TABLE IF NOT EXISTS contas_pessoais.assinaturas_ignoradas (
    conta_id INT UNSIGNED NOT NULL,
    descricao VARCHAR(150) NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (conta_id, descricao, valor),
    CONSTRAINT fk_assinaturas_ignoradas_conta FOREIGN KEY (conta_id) REFERENCES contas_pessoais.contas (id)
);
