<?php
header('Content-Type: application/json');

// Verifica la clave de seguridad
if (!isset($_GET['key']) || $_GET['key'] !== 'relatic2025json') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

// Conexión con PDO
require_once 'config.php'; // Este archivo ya define $pdo

try {
    $stmt = $pdo->query("SELECT * FROM suscriptions");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener datos: ' . $e->getMessage()]);
}
?>