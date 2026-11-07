<?php
$lifetime = 10800;
ini_set('session.gc_maxlifetime', $lifetime);
// Fica no volume "dados" para as sessões sobreviverem à recriação do container
session_save_path('/var/lib/ml-app/sessions');
session_set_cookie_params([
    'lifetime' => $lifetime,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
