# ml-app

Painel interno da loja do Mercado Livre, em `https://dash.rillis.com.br` (porta 51014). Junta num só container os antigos ml-dash, ml-boletos e ml-estoque, mais o módulo de contas pessoais. Tudo exige login.

PHP 8.2 + Apache, MySQL via PDO e Tailwind embutido (`public/assets/tailwind.js`).

## Módulos

| Caminho | O que faz | Banco |
|---|---|---|
| `/dash/` | Últimos eventos do ML (vendas, perguntas, mensagens, reclamações), boletos dos próximos 15 dias e logs do container `ml-jrf` | `ml_jrf`, `ml_boletos` |
| `/dash/fila/` | Fila do webhook do ml-jrf: pendentes, falhas, botões de reprocessar e descartar | `ml_jrf.webhook_queue` |
| `/boletos/` | Cadastro de boletos (com leitura de PDF via pdf.js), vencimento ajustado a feriados, insights | `ml_boletos` |
| `/estoque/` | Estoque local por SKU, com histórico (a lista de SKUs vem de `src/estoque/sku.csv`) | `ml_estoque` |
| `/contas/` | Contas pessoais mensais e insights | `contas_pessoais` (esquema em `sql/contas_pessoais.sql`) |

Login e usuários: `ml_login.usuarios` (senhas com `password_hash`).

## Estrutura

```
public/   raiz do site (só isto é servido pelo Apache)
src/      código incluído pelo PHP: auth, sessão, banco, parciais de cada módulo
sql/      esquemas para criar bancos novos
```

`src/` e o `.env` ficam fora da raiz do site, então o navegador não alcança esses arquivos.

## Configuração

```bash
cp .env.example .env   # e preencha DB_HOST, DB_PORT, DB_USER, DB_PASS
```

O `DOCKER_GID` do `docker-compose.yml` precisa ser o grupo dono do `/var/run/docker.sock` no host (`getent group docker`). É com ele que o Dash lê os logs do `ml-jrf`.

## Deploy

```bash
docker compose up -d --build
```

`public/` e `src/` são montados como somente leitura, então mudanças em PHP entram no ar na hora. Só é preciso rebuild ao mudar o `Dockerfile`. As sessões ficam no volume `dados` e sobrevivem à recriação do container.
