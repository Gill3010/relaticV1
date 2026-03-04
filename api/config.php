<?php
/**
 * Configuraci?n principal. Las credenciales se cargan desde config.local.php (gitignored).
 * En primer despliegue: copia config.local.php.example a config.local.php y completa los valores.
 */
$configLocalPath = __DIR__ . '/config.local.php';
if (!file_exists($configLocalPath)) {
    http_response_code(500);
    error_log('Error: No existe config.local.php. Copia config.local.php.example a config.local.php y configura las credenciales.');
    echo json_encode(['error' => 'Error de configuraci?n del servidor.']);
    exit;
}

$local = require $configLocalPath;
$host    = $local['db_host'] ?? 'localhost';
$db      = $local['db_name'] ?? '';
$user    = $local['db_user'] ?? '';
$pass    = $local['db_pass'] ?? '';
$charset = $local['db_charset'] ?? 'utf8mb4';

// Variables expuestas para scripts que usan mysqli (login, reset-password)
$db_host   = $host;
$db_name   = $db;
$db_user   = $user;
$db_pass   = $pass;

// Credenciales SMTP para sendmailPHP
$smtp_host = $local['smtp_host'] ?? 'smtp.office365.com';
$smtp_port = $local['smtp_port'] ?? 587;
$smtp_user = $local['smtp_user'] ?? '';
$smtp_pass = $local['smtp_pass'] ?? '';

// API key para suscripciones
$api_key_suscriptions = $local['api_key_suscriptions'] ?? '';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log("Error de conexi?n a la base de datos: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.']);
    exit;
}
