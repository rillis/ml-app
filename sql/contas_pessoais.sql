-- Banco do módulo Contas Pessoais. Valor preenchido = conta paga naquele mês.
CREATE DATABASE IF NOT EXISTS contas_pessoais CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contas_pessoais.contas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    nome VARCHAR(100) NOT NULL,
    categoria VARCHAR(60) NULL,
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
