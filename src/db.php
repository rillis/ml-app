<?php
function ml_db(string $database): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST'),
        getenv('DB_PORT'),
        $database
    );
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'), $options);
    } catch (PDOException $e) {
        error_log('ml-app db: ' . $e->getMessage());
        die('Erro de conexão com o banco de dados. Tente novamente em instantes.');
    }
}
