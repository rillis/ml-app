<?php
require __DIR__ . '/../src/session.php';
session_unset();
session_destroy();
header('Location: /login.php');
exit;
