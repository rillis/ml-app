<?php
const LOGIN_MAX_TENTATIVAS = 5;
const LOGIN_COOLDOWN_SEGUNDOS = 300;
const LOGIN_JANELA_SEGUNDOS = 900; // Falhas mais antigas que isso deixam de contar
const LOGIN_DIR_TENTATIVAS = '/var/lib/ml-app/login-tentativas';

// Pelo túnel, a conexão vem do gateway do Docker e o IP real está no cabeçalho da Cloudflare.
// O cabeçalho só vale nesse caso: quem acessa direto pela rede local não consegue falsificá-lo.
function login_ip(): string
{
    $remoto = $_SERVER['REMOTE_ADDR'] ?? '';
    $cloudflare = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if (filter_var($cloudflare, FILTER_VALIDATE_IP) && preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $remoto)) {
        return $cloudflare;
    }
    return $remoto;
}

function login_arquivo(string $ip): string
{
    return LOGIN_DIR_TENTATIVAS . '/' . hash('sha256', $ip) . '.json';
}

// Segundos que faltam para este IP poder tentar de novo (0 = liberado).
function login_tempo_restante(string $ip): int
{
    $conteudo = @file_get_contents(login_arquivo($ip));
    $dados = $conteudo ? json_decode($conteudo, true) : null;
    return max(0, (int)($dados['bloqueado_ate'] ?? 0) - time());
}

// Registra uma senha errada e devolve o tempo de bloqueio, se ele começou agora.
function login_registrar_falha(string $ip): int
{
    $arquivo = login_arquivo($ip);
    $h = @fopen($arquivo, 'c+');
    if (!$h || !flock($h, LOCK_EX)) {
        // Falha ao gravar não pode impedir o acesso ao sistema
        error_log('ml-app: não foi possível registrar tentativa de login');
        return 0;
    }

    $agora = time();
    $dados = json_decode(stream_get_contents($h) ?: '', true) ?: [];
    $cooldownVencido = !empty($dados['bloqueado_ate']) && $dados['bloqueado_ate'] <= $agora;
    $falhaAntiga = ($dados['ultima'] ?? 0) < $agora - LOGIN_JANELA_SEGUNDOS;
    $falhas = ($cooldownVencido || $falhaAntiga) ? 1 : (int)($dados['falhas'] ?? 0) + 1;
    $bloqueadoAte = $falhas >= LOGIN_MAX_TENTATIVAS ? $agora + LOGIN_COOLDOWN_SEGUNDOS : 0;

    ftruncate($h, 0);
    rewind($h);
    fwrite($h, json_encode(['falhas' => $falhas, 'ultima' => $agora, 'bloqueado_ate' => $bloqueadoAte]));
    flock($h, LOCK_UN);
    fclose($h);

    if ($bloqueadoAte) {
        error_log('ml-app: login bloqueado por excesso de tentativas, IP ' . $ip);
    }
    if (mt_rand(1, 50) === 1) {
        foreach (glob(LOGIN_DIR_TENTATIVAS . '/*.json') ?: [] as $antigo) {
            if (filemtime($antigo) < $agora - 86400) {
                @unlink($antigo);
            }
        }
    }

    return $bloqueadoAte ? LOGIN_COOLDOWN_SEGUNDOS : 0;
}

function login_limpar(string $ip): void
{
    @unlink(login_arquivo($ip));
}

function login_mensagem_bloqueio(int $segundos): string
{
    $minutos = (int)ceil($segundos / 60);
    return 'Muitas tentativas. Tente novamente em ' . $minutos . ($minutos > 1 ? ' minutos.' : ' minuto.');
}
