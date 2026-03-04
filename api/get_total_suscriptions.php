<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once 'config.php';

try {
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM suscriptions");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['total' => (int)$row['total']]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en consulta: ' . $e->getMessage()]);
}