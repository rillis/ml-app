<?php
require __DIR__ . '/../src/session.php';

// Só aceita voltar para páginas do próprio app, nunca para outro site
$next = $_GET['next'] ?? '';
$posLogin = is_string($next) && preg_match('#^/(dash|boletos|estoque|contas)(/[^\\\\\s]*)?\z#', $next) ? $next : '/dash/';

if (isset($_SESSION['user']) && isset($_SESSION['expires']) && time() <= $_SESSION['expires']) {
    header('Location: ' . $posLogin);
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';
    require __DIR__ . '/../src/login_limite.php';
    $ip = login_ip();
    $espera = login_tempo_restante($ip);
    if ($espera > 0) {
        http_response_code(429);
        $erro = login_mensagem_bloqueio($espera);
    } elseif (!empty($username) && !empty($senha)) {
        require __DIR__ . '/../src/db.php';
        $pdo = ml_db('ml_login');
        try {
            $stmt = $pdo->prepare('SELECT id, username, password_hash FROM usuarios WHERE username = :username LIMIT 1');
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();
            if ($user && password_verify($senha, $user['password_hash'])) {
                login_limpar($ip);
                session_regenerate_id(true);
                $_SESSION['user'] = $user['username'];
                $_SESSION['expires'] = time() + $lifetime;
                header('Location: ' . $posLogin);
                exit;
            } else {
                $bloqueio = login_registrar_falha($ip);
                if ($bloqueio > 0) {
                    http_response_code(429);
                }
                $erro = $bloqueio > 0 ? login_mensagem_bloqueio($bloqueio) : 'Usuário ou senha inválidos';
            }
        } catch (PDOException $e) {
            error_log('ml-app login: ' . $e->getMessage());
            $erro = 'Não foi possível entrar agora. Tente novamente em instantes.';
        }
    } else {
        $erro = 'Preencha todos os campos';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ML</title>
    <script src="/assets/tailwind.js"></script>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen font-sans selection:bg-blue-500 selection:text-white flex justify-center items-center p-4">
    <div class="bg-gray-800 p-8 rounded-xl border border-gray-700 shadow-2xl w-full max-w-sm">
        <div class="mb-8 text-center">
            <h3 class="text-3xl font-bold">
                <span aria-hidden="true">🔐</span>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400">Acesso - ML</span>
            </h3>
        </div>
        <form method="post" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1" for="usuario">Usuário</label>
                <input type="text" name="usuario" id="usuario" placeholder="Digite seu usuário" required autocomplete="username"
                       class="w-full bg-gray-900 border border-gray-700 rounded-lg p-3 text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1" for="senha">Senha</label>
                <input type="password" name="senha" id="senha" placeholder="••••••••" required autocomplete="current-password"
                       class="w-full bg-gray-900 border border-gray-700 rounded-lg p-3 text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors">
            </div>
            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold py-3 px-6 rounded-lg transition-colors shadow-lg mt-2">
                Entrar
            </button>
        </form>
        <?php if ($erro): ?>
            <p class="mt-6 text-center text-sm font-medium text-red-400 bg-red-500/10 py-2 rounded-lg border border-red-500/20">
                <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
