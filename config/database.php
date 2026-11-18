<?php
declare(strict_types=1);
// Local (XAMPP): localhost / bistro_devops / root / senha vazia.
// InfinityFree: troque pelos dados exatos do painel (o host NÃO é localhost).
$dbHost = 'localhost';
$dbName = 'bistro_devops';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET time_zone = '-03:00'");   // NOW() em horário de Brasília
} catch (PDOException $e) {
    error_log('Erro de conexão: ' . $e->getMessage());
    http_response_code(500);
    exit('Não foi possível conectar ao banco. Confira config/database.php e importe database.sql.');
}
