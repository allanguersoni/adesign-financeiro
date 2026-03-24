<?php
// app/config/conexao.php
// Arquivo de conexão com o banco de dados via PDO

require_once __DIR__ . '/env.php';

$host    = env('DB_HOST', 'db');
$dbname  = env('DB_NAME', 'saas_financeiro');
$user    = env('DB_USER', 'root');
$pass    = env('DB_PASS', 'root');
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Erro de conexão com o banco de dados: ' . $e->getMessage()]));
}
