<?php
require_once __DIR__ . '/session.php';

if (!isset($_SESSION['user']) || !isset($_SESSION['expires']) || time() > $_SESSION['expires']) {
    session_unset();
    session_destroy();
    $destino = '/login.php';
    // Chamadas à API não são páginas: não faz sentido voltar para elas após o login
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && basename($_SERVER['SCRIPT_NAME']) !== 'api.php') {
        $destino .= '?next=' . rawurlencode($_SERVER['REQUEST_URI']);
    }
    header('Location: ' . $destino);
    exit;
}

$_SESSION['expires'] = time() + $lifetime;
