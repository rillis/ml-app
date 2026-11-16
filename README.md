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

## Níveis de alerta dos boletos

A regra fica em `src/boletos/nivel.php` (tabela de `/boletos/` e widget do Dash) e é repetida em `alerta/main.py` (Discord). Mudou num, mude no outro.

| Nível | Quando | Cor |
|---|---|---|
| 🔴 Crítico | vencido (agendado ou não) | `#EF4444` |
| 🟠 Urgente | não agendado, vence em 0 a 3 dias | `#F97316` |
| 🟡 Atenção | não agendado e vence em 4 a 7 dias, ou agendado e vence hoje | `#EAB308` |
| 🔵 Ok | o resto (só na tabela) | `#3B82F6` |

Os dias são contados a partir do vencimento real (ajustado a fim de semana e feriado), no fuso America/Sao_Paulo.

## Alerta no Discord (`alerta/`)

Script Python que roda pelo cron do host, fora do container. Manda um embed por nível, na cor dele, e menciona em cascata: o pior nível do dia marca o cargo dele e os cargos dos níveis abaixo. Assim, quem tem só o cargo Urgente é avisado em dias de Urgente ou Crítico.

```bash
cd alerta
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt
cp .env.example .env   # banco, webhook e IDs dos cargos (vazio = nível sem menção)
```

```bash
.venv/bin/python main.py --dry-run   # mostra o payload sem enviar
```

Cron do host:

```
0 8 * * * /home/rillis/docker/ml-app/alerta/.venv/bin/python /home/rillis/docker/ml-app/alerta/main.py >> /home/rillis/docker/ml-app/alerta/logs/cron.log 2>&1
0 14 * * * /home/rillis/docker/ml-app/alerta/.venv/bin/python /home/rillis/docker/ml-app/alerta/main.py --lembrete >> /home/rillis/docker/ml-app/alerta/logs/cron.log 2>&1
```

O envio das 14:00 (`--lembrete`) só sai se ainda houver boleto Crítico e repete a mensagem inteira. `--limite N` usa só os N primeiros boletos, para testes.

## Estrutura

```
public/   raiz do site (só isto é servido pelo Apache)
src/      código incluído pelo PHP: auth, sessão, banco, parciais de cada módulo
sql/      esquemas para criar bancos novos
alerta/   aviso dos boletos no Discord (Python, roda pelo cron do host)
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
