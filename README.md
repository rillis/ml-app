# ml-app

Painel interno da loja do Mercado Livre, em `https://dash.rillis.com.br` (porta 51014). Junta num só container os antigos ml-dash, ml-boletos e ml-estoque, mais o módulo de contas pessoais. As abas Etiquetas e Margem são interfaces para serviços que rodam em containers próprios.

PHP 8.2 + Apache, MySQL via PDO e Tailwind embutido (`public/assets/tailwind.js`).

## Módulos

| Caminho | O que faz | Banco |
|---|---|---|
| `/dash/` | Painel ao vivo numa tela só: vendas do dia (contra ontem até a mesma hora), etiquetas aguardando NF ou impressão, perguntas sem resposta (responde num modal, direto no ML), reclamações abertas (as que pedem ação sua em destaque), eventos do webhook de ontem e hoje, boletos dos próximos 15 dias e saúde do ml-jrf. A fila do webhook (reprocessar e descartar) e os logs do `ml-jrf` abrem em modal | `ml_jrf`, `ml_boletos` |
| `/boletos/` | Cadastro de boletos (com leitura de PDF via pdf.js), vencimento ajustado a feriados, insights. No PC, cada boleto tem um botão que mostra a linha digitável em QR code (qrcode-generator, em `public/boletos/vendor/qrcode`) para ler com o celular e colar no app do banco | `ml_boletos` |
| `/vendas/` | Vendas do ML copiadas para o banco: comparativo entre períodos (datas livres, padrão mês até hoje; contra o período anterior, o período anterior em dias úteis ou o mesmo período do ano passado), faturamento por mês (Full × normal), acumulado do período, Premium × Clássico, faturamento por dia da semana, cancelamentos por mês, tabela por SKU e anúncios que não existem mais (com os equivalentes de hoje). Canceladas ficam fora das outras visões | `ml_vendas` (esquema em `sql/ml_vendas.sql`) |
| `/full/` | Estoque dos anúncios no Full e sugestão de envio: quando criar o próximo envio e quanto mandar de cada anúncio, pelo ciclo escolhido | `ml_vendas` (tabelas `full_*`), `ml_estoque` |
| `/ferias/` | Modo férias sem pausar anúncios: prazo de disponibilidade com contagem regressiva em todos os anúncios que não são Full, mudando sozinho a cada meia-noite até sair | `ml_vendas` (tabelas `ferias*`) |
| `/margem/` | Custo, margem e preço sugerido de cada anúncio, comparador de SKUs da tabela com os anúncios, somador do PDF de envio do Full e as tabelas de preço e apelidos. Tudo roda no ml-tools-web, em container próprio; o `api.php` repassa as chamadas para ele (`MARGEM_URL`, padrão `http://192.168.15.11:51016`) | — |
| `/estoque/` | Estoque local por SKU, com histórico (a lista de SKUs vem de `src/estoque/sku.csv`) | `ml_estoque` |
| `/etiquetas/` | Vendas aguardando NF e prontas para imprimir; processa em lote (NF-e, DANFEs, etiquetas e mensagem) pelo serviço ml-etiquetas-web, que roda em container próprio. O `api.php` repassa as chamadas para ele (`ETIQUETAS_URL`, padrão `http://192.168.15.11:51015`) | — |
| `/contas/` | Contas pessoais mensais, leitura de fatura de cartão em PDF, parcelas e insights | `contas_pessoais` (esquema em `sql/contas_pessoais.sql`) |
| `/home/resumo.php` | JSON para os cards da home (home.rillis.com.br): boletos em aberto por nível e se o mês atual das contas foi salvo | `ml_boletos`, `contas_pessoais` |

## Dash (`/dash/`)

A tela pede ao `api.php` dois blocos separados:

- **Banco** (`?r=banco`, a cada 30 s): eventos, vendas do dia, boletos e a fila do webhook.
- **ML** (`?r=ml`, a cada 2 min): perguntas sem resposta, reclamações abertas e o resumo das etiquetas (do ml-etiquetas-web). Quando chega um evento novo pelo webhook, a tela pede esse bloco na hora. O resultado fica em cache por 100 s em `/var/lib/ml-app/dash-ml.json`, para várias abas abertas não repetirem as consultas.
- O card de etiquetas só fica vermelho em dia útil até as 18h; em fim de semana, feriado (tabela `ml_boletos.feriados`) ou depois das 18h fica verde, com o aviso "fora do expediente".

Como o `tokens.json` do `ml-jrf` só é lido pelo root, o bloco do ML e a resposta de perguntas rodam pela linha de comando (`src/dash/ml.php`), como o Atualizar de Vendas:

```bash
docker exec ml-app php /var/www/html/src/dash/ml.php dados --forcar
```

## Vendas (`/vendas/`)

A API do ML só devolve os pedidos dos últimos 12 meses, então o módulo guarda uma cópia no banco `ml_vendas`: um registro por pedido (com o JSON completo do ML na coluna `bruto`) e um por item.

O botão **Atualizar** busca no ML só o que mudou desde a última cópia (`order.date_last_updated`, com 1 h de folga) e grava por cima, pelo id do pedido. Assim entram as vendas novas e as antigas que foram canceladas ou mudaram de status. Com o banco vazio, ele faz a carga a partir de `VENDAS_INICIO` (padrão `2025-12-01`).

Na comparação por dias úteis, a comparação começa no mesmo dia do período anterior e vai até juntar a mesma quantidade de dias úteis do período atual (até hoje). Fins de semana e os feriados da tabela `ml_boletos.feriados` não contam.

**Projeção do mês atual:** média de três métodos, todos com os dias completos até ontem: perfil da semana (peso de cada dia nas 8 semanas antes do mês), ritmo dos últimos 28 dias e ritmo dos últimos 7 dias, os dois aplicados aos dias que faltam. Feriado conta como domingo. O que falta é dividido entre Full e normal pelo mix do mês até agora. A faixa (±) é o erro médio da média dos três métodos em fev–out/2026, em cada ponto do mês (`ERRO_PROJECAO` em `public/vendas/index.php`). Ela aparece na barra do mês em "Faturamento por mês" e como linha roxa no acumulado.

O card **Evolução da projeção do mês** não segue os filtros: mostra o acumulado do mês atual (Full e normal), o mês anterior inteiro e uma linha de projeção por dia, refeita como se fosse a manhã daquele dia (só com os dias completos até a véspera). Abaixo, o fechamento projetado em cada dia.

No gráfico de acumulado, a comparação usa sempre o calendário, mesmo com dias úteis selecionados. Período que começa no dia 1 mostra o mês inteiro, e a comparação também.

Full ou normal vem de `order_items[].stock.node_id`: armazém do ML (`BRSP02`, `BRSP04`...) é Full. Pedidos de antes de 2026-07 não trazem esse campo; para eles a cópia consulta o envio (`/shipments`).

**Anúncios que não existem mais:** uma vez por dia, o Atualizar lista todos os anúncios do vendedor (`/users/{id}/items/search`, que só devolve os que existem). Anúncio que vendeu e não está na lista foi encerrado ou apagado. Para cada um, busca os anúncios de hoje com o mesmo SKU (`seller_sku`) e guarda tipo (Clássico/Premium), Full ou normal e status em `anuncios_extintos`. A tabela no fim da página usa o histórico todo, sem os filtros, e esconde quem tem substituto: anúncio ativo com o mesmo SKU e o mesmo tipo (Full ou normal não importa). O botão **Conferir agora** da seção refaz só essa parte na hora e avisa o que mudou (anúncios encerrados a mais, equivalentes diferentes, anúncios que voltaram). Pela linha de comando: `docker exec ml-app php /var/www/html/src/vendas/extintos.php`.

Carga completa pela linha de comando (refaz tudo desde `VENDAS_INICIO`, sem apagar nada):

```bash
docker exec ml-app php /var/www/html/src/vendas/sync.php --carga
```

Sem `--carga`, o comando faz o mesmo que o botão. O token vem do `tokens.json` do `ml-jrf`, montado em `/ml-jrf` só para leitura; em 401 a cópia chama o ping de token do `ml-jrf`.

Criar o banco:

```bash
mysql < sql/ml_vendas.sql
```

## Full (`/full/`)

O Atualizar (o mesmo de Vendas) também busca todos os anúncios Full, ativos e pausados, e o estoque de cada um no ML (`/inventories/{id}/stock/fulfillment`). A tela tem uma linha por anúncio (com o ID), porque o que vai para o Full fica preso ao anúncio e cada anúncio vende diferente. Anúncios que o ML junta no mesmo estoque (mesmo `inventory_id`) são calculados juntos: a quantidade aparece na linha do que mais vende. Cada atualização também grava uma foto do estoque do dia em `full_estoque_dia`.

Sugestão por anúncio, com os parâmetros da tela (ciclo, prazo e segurança, guardados no navegador):

- **Venda por dia:** a maior média entre os últimos 30 e 60 dias completos, só das vendas pelo Full daquele anúncio.
- **Enviar até:** o dia em que o estoque entra na margem (prazo + segurança).
- **Sem venda nos últimos 60 dias:** com estoque no Full, "Parar de mandar"; sem estoque, "Mudar para normal".
- **Entra no envio de hoje:** quem não aguenta até o próximo envio (um ciclo). A quantidade cobre prazo + ciclo + segurança.
- **Local:** estoque do módulo Estoque pelo SKU, só para consulta.
- **Copiar envio:** por anúncio (`MLB... - 2x`) ou por SKU, abrindo os kits e somando (`KF-133 - 6x`).

Unidades já enviadas que o ML ainda não recebeu não aparecem na API, então não entram na conta.

Criar as tabelas em um banco que já existe:

```bash
mysql < sql/migracoes/2026-11-17-full-estoque.sql
mysql < sql/migracoes/2026-11-18-anuncios-extintos.sql
```

## Férias (`/ferias/`)

O modo férias do ML pausa os anúncios, e isso derruba a relevância. Esta aba faz outra coisa: coloca prazo de disponibilidade (`MANUFACTURING_TIME`) em todos os anúncios ativos e pausados que não são Full, e o prazo cai 1 por dia. Na tela você escolhe o dia inicial e o dia final (o último dia fora). Exemplo: do dia 1 ao dia 10 → prazo 10 no dia 1, 6 no dia 5, 1 no dia 10; no dia 11 o prazo sai e as férias terminam.

- Início hoje (grava na hora) ou agendado para um dia futuro (começa na rodada da meia-noite). Máximo de 60 dias contando o início (limite do ML).
- Dá para alterar o dia final (a contagem continua do início), encerrar antes (tira o prazo na hora) ou cancelar as agendadas.
- O prazo vale para o user product: um `PUT` por produto muda juntos os anúncios ligados (Clássico e Premium). Produtos ligados a anúncio Full ou com Flex ficam de fora.
- Produto que já tinha prazo antes das férias fica com o maior dos dois e volta ao prazo original no fim.
- Anúncios novos entram na rodada do dia seguinte.

A rodada fica em `src/ferias/rodada.php` e roda como root no container (o `tokens.json` do `ml-jrf` só é lido pelo dono). O cron do host chama de hora em hora; ela só trabalha quando o prazo do dia ainda não foi gravado. Se algum produto falhar, tenta de novo nas horas seguintes, até 4 rodadas por dia. Os botões da tela disparam a rodada em segundo plano e a tela mostra o progresso.

```
5 * * * * docker exec ml-app php /var/www/html/src/ferias/rodada.php >> /home/rillis/docker/ml-app/logs/ferias.log 2>&1
```

Cada rodada manda um resumo no Discord (`FERIAS_DISCORD_WEBHOOK`); o cargo `FERIAS_DISCORD_MENCAO` só é marcado quando há falha ou a rodada não conseguiu rodar.

Teste num produto só, fingindo a data (não grava rodada, não muda o status das férias e não avisa no Discord):

```bash
docker exec ml-app php /var/www/html/src/ferias/rodada.php --so=MLB123,MLB456 --hoje=2026-12-01
```

Criar as tabelas:

```bash
mysql < sql/migracoes/2026-11-27-ferias.sql
```

## Níveis de alerta dos boletos

A regra fica em `src/boletos/nivel.php` (tabela de `/boletos/` e widget do Dash) e é repetida em `alerta/main.py` (Discord). Mudou num, mude no outro.

| Nível | Quando | Cor |
|---|---|---|
| 🔴 Crítico | vencido (agendado ou não) | `#EF4444` |
| 🟠 Urgente | não agendado, vence em 0 a 3 dias | `#F97316` |
| 🟡 Atenção | não agendado e vence em 4 a 7 dias, ou agendado e vence hoje | `#EAB308` |
| 🔵 Ok | o resto (só na tabela) | `#3B82F6` |

Os dias são contados a partir do vencimento real (ajustado a fim de semana e feriado), no fuso America/Sao_Paulo.

## Faturas de cartão em PDF (`/contas/`)

Contas marcadas como cartão em **Gerenciar contas** ganham um botão 📄 ao lado do valor. O PDF é lido no navegador (pdf.js dos boletos, em `public/contas/fatura-pdf.js`): preenche o valor da fatura e lista as compras parceladas para revisão, com apelido opcional. Se o PDF tiver senha, o app pede num modal.

Bancos com leitor: Nubank, PicPay, Original e Itaú. Banco novo = uma entrada em `BANCOS` no `fatura-pdf.js` e o id em `CONTAS_CARTOES` no `api.php`.

Uma compra é reconhecida de um mês para o outro por cartão + descrição + número de parcelas + mês da 1ª parcela, então o apelido acompanha as próximas faturas. A aba **🧾 Parcelas** mostra o que falta pagar e a soma prevista dos próximos meses.

Bancos criados antes desse recurso precisam da migração:

```bash
mysql contas_pessoais < sql/migracoes/2026-11-16-faturas-pdf.sql
```

### Assinaturas

A mesma leitura separa os lançamentos à vista e marca como assinatura os que batem com a lista de serviços em `public/contas/assinaturas-conhecidas.js` (Netflix, Spotify, Amazon Prime, Apple.com/bill etc.). Na revisão dá para desmarcar ("não é assinatura", não volta a ser sugerida), dar um apelido ou marcar qualquer outro lançamento.

Nas faturas seguintes a cobrança é ligada à assinatura pela descrição e pelo valor; se só a descrição bater, o valor novo é registrado e aparece como mudança de preço. Assinatura que não vem na última fatura lida do cartão aparece como "cancelada?" na aba **🔁 Assinaturas**, onde dá para confirmar o cancelamento. Serviço novo na lista = uma linha em `SERVICOS`.

Migração:

```bash
mysql contas_pessoais < sql/migracoes/2026-11-18-assinaturas.sql
```

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
