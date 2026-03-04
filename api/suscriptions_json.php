<?php
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
// Verifica la clave de seguridad desde config.local.php
if (!isset($_GET['key']) || $_GET['key'] !== $api_key_suscriptions || empty($api_key_suscriptions)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

try {
    $stmt = $pdo->query("SELECT * FROM suscriptions");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener datos: ' . $e->getMessage()]);
}
?>