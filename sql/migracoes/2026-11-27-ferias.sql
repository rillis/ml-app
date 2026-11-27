-- Módulo Férias: prazo de disponibilidade (MANUFACTURING_TIME) com contagem regressiva nos anúncios que não são Full
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
