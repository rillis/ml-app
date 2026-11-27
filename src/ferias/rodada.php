<?php
// Férias: prazo de disponibilidade (MANUFACTURING_TIME) com contagem regressiva nos anúncios que não são Full.
// No dia de início o prazo é `dias`; a cada dia cai 1; no dia inicio + dias o prazo sai e as férias terminam.
// O prazo vale para o user product: um PUT por produto muda todos os anúncios ligados a ele.
//
// Roda pelo cron do host de hora em hora (só trabalha quando o prazo do dia ainda não foi gravado)
// e pela aba Férias, em segundo plano:
//   docker exec ml-app php /var/www/html/src/ferias/rodada.php [--forcar] [--so=MLB1,MLB2] [--hoje=AAAA-MM-DD] [--sem-discord]
// --so      limita aos anúncios informados (teste): não grava rodada, não muda o status das férias e não avisa no Discord
// --hoje    finge outra data (teste da contagem)
// --forcar  roda mesmo que a rodada do dia já tenha dado certo ou passado do limite de tentativas

require_once __DIR__ . '/../vendas/full.php';

const FERIAS_FUSO = 'America/Sao_Paulo';
const FERIAS_MAX_DIAS = 60; // máximo do ML
const FERIAS_TENTATIVAS_DIA = 4; // rodadas automáticas por dia (a 1ª e até 3 novas tentativas se houver falha)
// Férias alteradas ou encerradas pela tela no meio de uma rodada: ela para e a próxima recomeça com o prazo novo
class FeriasInterrompida extends RuntimeException
{
}

const FERIAS_LINK = 'https://dash.rillis.com.br/ferias/';

function ferias_agora(): string
{
    return (new DateTime('now', new DateTimeZone(FERIAS_FUSO)))->format('Y-m-d H:i:s');
}

function ferias_hoje(): string
{
    return (new DateTime('now', new DateTimeZone(FERIAS_FUSO)))->format('Y-m-d');
}

function ferias_soma_dias(string $dia, int $n): string
{
    return (new DateTime($dia))->modify(($n >= 0 ? '+' : '') . $n . ' days')->format('Y-m-d');
}

// Férias em andamento (agendada, ativa ou encerrando); só existe uma por vez
function ferias_atual(PDO $pdo): ?array
{
    $f = $pdo->query("SELECT * FROM ferias WHERE status IN ('agendada', 'ativa', 'encerrando') ORDER BY id DESC LIMIT 1")->fetch();
    return $f ?: null;
}

// Prazo do dia: null = ainda não começou; 0 = tirar o prazo
function ferias_alvo(array $f, string $hoje): ?int
{
    if ($f['status'] === 'encerrando') {
        return 0;
    }
    if ($hoje < $f['inicio']) {
        return null;
    }
    $passados = (int) (new DateTime($f['inicio']))->diff(new DateTime($hoje))->format('%a');
    return max(0, (int) $f['dias'] - $passados);
}

function ferias_prazo_item(array $item): ?int
{
    foreach ($item['sale_terms'] ?? [] as $s) {
        if (($s['id'] ?? '') === 'MANUFACTURING_TIME') {
            $n = $s['value_struct']['number'] ?? null;
            if ($n === null && preg_match('/\d+/', (string) ($s['value_name'] ?? ''), $m)) {
                $n = $m[0];
            }
            return $n === null ? null : (int) $n;
        }
    }
    return null;
}

// Grava o prazo no anúncio (null = tirar). Devolve null se deu certo, ou a mensagem de erro.
function ferias_put(string $anuncio, ?int $prazo): ?string
{
    $termo = $prazo === null
        ? ['id' => 'MANUFACTURING_TIME', 'value_id' => null, 'value_name' => null]
        : ['id' => 'MANUFACTURING_TIME', 'value_name' => $prazo . ' dias']; // no MLB a unidade é "dias", sem acento
    $corpo = json_encode(['sale_terms' => [$termo]]);
    $pingFeito = false;
    for ($tentativa = 1; ; $tentativa++) {
        $ch = curl_init(VENDAS_API . '/items/' . $anuncio);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $corpo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . vendas_token(), 'Content-Type: application/json'],
        ]);
        $resposta = curl_exec($ch);
        $codigo = curl_errno($ch) ? 0 : curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);

        if ($codigo === 200) {
            // Confere no próprio retorno do PUT se o prazo ficou como pedido
            $item = json_decode((string) $resposta, true) ?: [];
            $ficou = ferias_prazo_item($item);
            return $ficou === $prazo ? null : 'O ML aceitou, mas o prazo ficou ' . ($ficou === null ? 'vazio' : "$ficou dias");
        }
        if ($codigo === 401 && !$pingFeito) {
            vendas_ping_token(); // token recusado de vez: a exceção para a rodada inteira
            $pingFeito = true;
            continue;
        }
        if (($codigo === 0 || $codigo === 429 || $codigo >= 500) && $tentativa < 4) {
            sleep($tentativa * 2);
            continue;
        }
        if ($codigo === 0) {
            return 'Sem resposta do ML: ' . $erroCurl;
        }
        $json = json_decode((string) $resposta, true);
        $causas = implode('; ', array_filter(array_map(fn($c) => $c['message'] ?? null, $json['cause'] ?? [])));
        return mb_substr("HTTP $codigo: " . ($json['message'] ?? substr((string) $resposta, 0, 200)) . ($causas ? " ($causas)" : ''), 0, 500);
    }
}

// Anúncios ativos e pausados que não são Full, agrupados por user product
function ferias_produtos_ml(string $vendedor, ?array $so): array
{
    if ($so) {
        $ids = $so;
    } else {
        $ids = [];
        foreach (['active', 'paused'] as $status) {
            $parametros = ['search_type' => 'scan', 'limit' => 100, 'status' => $status];
            do {
                $r = vendas_get("/users/$vendedor/items/search", $parametros);
                $ids = array_merge($ids, $r['results'] ?? []);
                $parametros['scroll_id'] = $r['scroll_id'] ?? null;
            } while (!empty($r['results']) && $parametros['scroll_id']);
        }
        if (!$ids) {
            throw new RuntimeException('A busca de anúncios do ML voltou vazia');
        }
    }
    $ids = array_values(array_unique($ids));
    $campos = 'id,title,status,shipping,user_product_id,sale_terms';
    $itens = vendas_get_varios(array_combine($ids, array_map(fn($id) => "/items/$id?attributes=$campos", $ids)));

    $produtos = [];
    foreach ($itens as $it) {
        if (empty($it['id'])) {
            continue;
        }
        $up = $it['user_product_id'] ?? $it['id'];
        $p = &$produtos[$up];
        $p['anuncios'][] = $it['id'];
        $p['titulo'] ??= mb_substr((string) ($it['title'] ?? ''), 0, 255);
        $p['prazo'] ??= ferias_prazo_item($it);
        // Full e Flex não aceitam prazo: o produto inteiro fica de fora
        $full = ($it['shipping']['logistic_type'] ?? '') === 'fulfillment';
        $flex = in_array('self_service_in', $it['shipping']['tags'] ?? [], true);
        $p['pular'] = ($p['pular'] ?? false) || $full || $flex;
        unset($p);
    }
    return $produtos;
}

function ferias_discord(array $f, string $hoje, int $alvo, array $r, ?string $erro): void
{
    $webhook = getenv('FERIAS_DISCORD_WEBHOOK');
    if (!$webhook) {
        return;
    }
    $mencao = getenv('FERIAS_DISCORD_MENCAO');
    $agir = $erro !== null || ($r['falhas'] ?? 0) > 0;
    $volta = ferias_soma_dias($f['inicio'], (int) $f['dias']);
    $dataCurta = fn($d) => substr($d, 8, 2) . '/' . substr($d, 5, 2);

    if ($erro !== null) {
        $titulo = '🏖️ Férias: a rodada falhou';
        $texto = $erro;
    } elseif ($alvo === 0) {
        $titulo = $r['falhas'] ? '🏖️ Férias: prazo não saiu de todos os anúncios' : '🏖️ Férias encerradas';
        $texto = $r['falhas']
            ? 'Faltam ' . $r['falhas'] . ' produto(s) para tirar o prazo. Tento de novo na próxima hora (até ' . FERIAS_TENTATIVAS_DIA . ' rodadas por dia).'
            : 'Prazo tirado de ' . $r['atualizados'] . ' produto(s). Os anúncios voltaram ao normal.';
    } else {
        $titulo = "🏖️ Férias: prazo de hoje $alvo " . ($alvo === 1 ? 'dia' : 'dias');
        $texto = 'Volta em ' . $dataCurta($volta) . " (o prazo sai nesse dia).\n"
            . "Atualizados: {$r['atualizados']} · já estavam certos: {$r['iguais']} · fora (Full/Flex): {$r['pulados']} · falhas: {$r['falhas']}";
        if ($r['falhas']) {
            $texto .= "\nTento de novo na próxima hora (até " . FERIAS_TENTATIVAS_DIA . ' rodadas por dia).';
        }
    }
    $payload = [
        'content' => $agir && $mencao ? "<@&$mencao>" : '',
        'allowed_mentions' => ['roles' => $agir && $mencao ? [$mencao] : []],
        'embeds' => [[
            'title' => $titulo, 'description' => $texto, 'url' => FERIAS_LINK,
            'color' => $agir ? 0xEF4444 : ($alvo === 0 ? 0x10B981 : 0x3B82F6),
            'footer' => ['text' => 'Dia ' . $dataCurta($hoje)],
        ]],
    ];
    $ch = curl_init($webhook);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($codigo < 200 || $codigo >= 300) {
        fwrite(STDERR, "Discord respondeu $codigo\n");
    }
}

function ferias_rodar(PDO $pdo, array $opcoes = []): array
{
    $so = $opcoes['so'] ?? null;
    $teste = $so !== null;
    $hoje = $opcoes['hoje'] ?? ferias_hoje();
    $forcar = $opcoes['forcar'] ?? false;
    $discord = !$teste && !($opcoes['semDiscord'] ?? false);

    // Pela tela (--forcar) espera a rodada que estiver em andamento terminar ou ser interrompida
    if (!(int) $pdo->query("SELECT GET_LOCK('ml_ferias', " . ($forcar ? 1200 : 0) . ')')->fetchColumn()) {
        return ['pulado' => 'Já existe uma rodada em andamento'];
    }
    try {
        $f = ferias_atual($pdo);
        if (!$f) {
            return ['pulado' => 'Sem férias em andamento'];
        }
        $alvo = ferias_alvo($f, $hoje);
        if ($alvo === null) {
            return ['pulado' => 'Férias agendadas para ' . $f['inicio']];
        }

        if (!$teste && !$forcar) {
            $hist = $pdo->prepare('SELECT COUNT(*) AS rodadas, SUM(fim IS NOT NULL AND falhas = 0 AND erro IS NULL) AS ok
                FROM ferias_rodadas WHERE ferias_id = ? AND dia = ? AND prazo = ?');
            $hist->execute([$f['id'], $hoje, $alvo]);
            $h = $hist->fetch();
            if ((int) $h['ok'] > 0) {
                return ['pulado' => 'Rodada de hoje já feita'];
            }
            if ((int) $h['rodadas'] >= FERIAS_TENTATIVAS_DIA) {
                return ['pulado' => 'Limite de tentativas de hoje atingido'];
            }
        }

        if (!$teste && $f['status'] === 'agendada') {
            $pdo->prepare("UPDATE ferias SET status = 'ativa' WHERE id = ?")->execute([$f['id']]);
            $f['status'] = 'ativa';
        }

        $rodadaId = null;
        if (!$teste) {
            $pdo->prepare('INSERT INTO ferias_rodadas (ferias_id, dia, prazo, inicio) VALUES (?, ?, ?, ?)')
                ->execute([$f['id'], $hoje, $alvo, ferias_agora()]);
            $rodadaId = (int) $pdo->lastInsertId();
        }
        $r = ['total' => 0, 'feitos' => 0, 'atualizados' => 0, 'iguais' => 0, 'pulados' => 0, 'falhas' => 0];
        $progresso = function (bool $final = false) use ($pdo, $rodadaId, &$r) {
            if ($rodadaId && ($final || $r['feitos'] % 10 === 0)) {
                $pdo->prepare('UPDATE ferias_rodadas SET total = ?, feitos = ?, atualizados = ?, iguais = ?, pulados = ?, falhas = ? WHERE id = ?')
                    ->execute([$r['total'], $r['feitos'], $r['atualizados'], $r['iguais'], $r['pulados'], $r['falhas'], $rodadaId]);
            }
        };
        $grava = $pdo->prepare('INSERT INTO ferias_produtos (ferias_id, user_product, anuncios, titulo, original, atual, atualizado_em, erro)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE anuncios = VALUES(anuncios), titulo = VALUES(titulo), atual = VALUES(atual),
                atualizado_em = VALUES(atualizado_em), erro = VALUES(erro)');
        $marcaErro = $pdo->prepare('UPDATE ferias_produtos SET erro = ? WHERE ferias_id = ? AND user_product = ?');

        try {
            if ($alvo === 0) {
                // Fim: volta cada produto ao prazo que tinha antes (ou sem prazo)
                $pendentes = $pdo->prepare('SELECT * FROM ferias_produtos WHERE ferias_id = ? AND NOT (atual <=> original)');
                $pendentes->execute([$f['id']]);
                $lista = $pendentes->fetchAll();
                if ($teste) {
                    $lista = array_filter($lista, fn($p) => array_intersect(json_decode($p['anuncios'], true), $so));
                }
                $r['total'] = count($lista);
                $progresso(true);
                foreach ($lista as $p) {
                    $anuncios = json_decode($p['anuncios'], true);
                    $original = $p['original'] === null ? null : (int) $p['original'];
                    $erro = ferias_put($anuncios[0], $original);
                    if ($erro === null) {
                        $grava->execute([$f['id'], $p['user_product'], $p['anuncios'], $p['titulo'], $p['original'], $original, ferias_agora(), null]);
                        $r['atualizados']++;
                    } else {
                        $marcaErro->execute([$erro, $f['id'], $p['user_product']]);
                        $r['falhas']++;
                    }
                    $r['feitos']++;
                    $progresso();
                }
            } else {
                $vendedor = (string) vendas_get('/users/me')['id'];
                $produtos = ferias_produtos_ml($vendedor, $so);
                $conhecidos = $pdo->prepare('SELECT user_product, original FROM ferias_produtos WHERE ferias_id = ?');
                $conhecidos->execute([$f['id']]);
                $originais = $conhecidos->fetchAll(PDO::FETCH_KEY_PAIR);

                $r['total'] = count($produtos);
                $progresso(true);
                $confere = $pdo->prepare('SELECT status, dias FROM ferias WHERE id = ?');
                foreach ($produtos as $up => $p) {
                    if (!$teste && $r['feitos'] % 10 === 0) {
                        $confere->execute([$f['id']]);
                        $agora = $confere->fetch();
                        if ($agora['status'] !== $f['status'] || (int) $agora['dias'] !== (int) $f['dias']) {
                            throw new FeriasInterrompida('Interrompida: as férias foram alteradas pela tela');
                        }
                    }
                    $r['feitos']++;
                    if ($p['pular']) {
                        $r['pulados']++;
                        $progresso();
                        continue;
                    }
                    // O prazo que o produto já tinha antes das férias é guardado na 1ª vez que ele aparece
                    $novo = !array_key_exists($up, $originais);
                    $original = $novo ? $p['prazo'] : ($originais[$up] === null ? null : (int) $originais[$up]);
                    $desejado = max($alvo, (int) $original); // nunca baixa um prazo maior que já existia
                    $json = json_encode($p['anuncios']);
                    if ($p['prazo'] === $desejado) {
                        $grava->execute([$f['id'], $up, $json, $p['titulo'], $original, $desejado, ferias_agora(), null]);
                        $r['iguais']++;
                    } else {
                        $erro = ferias_put($p['anuncios'][0], $desejado);
                        if ($erro === null) {
                            $grava->execute([$f['id'], $up, $json, $p['titulo'], $original, $desejado, ferias_agora(), null]);
                            $r['atualizados']++;
                        } else {
                            // `atual` fica como está no ML, para o fim das férias saber se precisa voltar
                            $grava->execute([$f['id'], $up, $json, $p['titulo'], $original, $p['prazo'], ferias_agora(), $erro]);
                            $r['falhas']++;
                        }
                    }
                    $progresso();
                }
            }
        } catch (Throwable $e) {
            if ($rodadaId) {
                $progresso(true);
                $pdo->prepare('UPDATE ferias_rodadas SET fim = ?, erro = ? WHERE id = ?')
                    ->execute([ferias_agora(), mb_substr($e->getMessage(), 0, 500), $rodadaId]);
            }
            if ($e instanceof FeriasInterrompida) {
                return ['interrompida' => true] + $r;
            }
            if ($discord) {
                ferias_discord($f, $hoje, $alvo, $r, $e->getMessage());
            }
            throw $e;
        }

        if ($rodadaId) {
            $progresso(true);
            $pdo->prepare('UPDATE ferias_rodadas SET fim = ? WHERE id = ?')->execute([ferias_agora(), $rodadaId]);
            if ($alvo === 0 && $r['falhas'] === 0) {
                $pdo->prepare("UPDATE ferias SET status = 'encerrada', encerrada_em = ? WHERE id = ?")->execute([ferias_agora(), $f['id']]);
            }
        }
        if ($discord) {
            ferias_discord($f, $hoje, $alvo, $r, null);
        }
        unset($r['feitos']);
        return ['ferias' => (int) $f['id'], 'dia' => $hoje, 'prazo' => $alvo, 'teste' => $teste] + $r;
    } finally {
        $pdo->query("DO RELEASE_LOCK('ml_ferias')");
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['argv'][0] ?? '') === __FILE__) {
    $opcoes = ['forcar' => false];
    foreach (array_slice($_SERVER['argv'], 1) as $arg) {
        if ($arg === '--forcar') {
            $opcoes['forcar'] = true;
        } elseif ($arg === '--sem-discord') {
            $opcoes['semDiscord'] = true;
        } elseif (str_starts_with($arg, '--so=')) {
            $opcoes['so'] = array_values(array_filter(array_map('trim', explode(',', substr($arg, 5)))));
        } elseif (preg_match('/^--hoje=(\d{4}-\d{2}-\d{2})$/', $arg, $m)) {
            $opcoes['hoje'] = $m[1];
        } else {
            fwrite(STDERR, "Opção desconhecida: $arg\n");
            exit(2);
        }
    }
    try {
        $resultado = ferias_rodar(ml_db('ml_vendas'), $opcoes);
        // O cron roda de hora em hora: sem férias, não enche o log
        if (($resultado['pulado'] ?? '') === 'Sem férias em andamento' && !posix_isatty(STDOUT)) {
            exit(0);
        }
        echo '[' . ferias_agora() . '] ' . json_encode($resultado, JSON_UNESCAPED_UNICODE), "\n";
    } catch (Throwable $e) {
        fwrite(STDERR, '[' . ferias_agora() . '] Erro: ' . $e->getMessage() . "\n");
        exit(1);
    }
}
