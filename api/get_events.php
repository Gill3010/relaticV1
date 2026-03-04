<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Incluye el archivo de configuración de la base de datos
require_once "config.php";

// Manejo de la solicitud OPTIONS
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

$response = ["success" => false, "events" => []];

try {
    // Prepara y ejecuta la consulta SQL para obtener todos los eventos
    $sql = "SELECT id, name FROM events ORDER BY date DESC, name ASC";
    $stmt = $pdo->query($sql);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($events) {
        $response["success"] = true;
        $response["events"] = $events;
        http_response_code(200);
    } else {
        $response["message"] = "No se encontraron eventos.";
        http_response_code(404);
    }

} catch (PDOException $e) {
    // En caso de un error de base de datos
    error_log("Error de base de datos en get_events.php: " . $e->getMessage());
    $response["message"] = "Error de base de datos. Por favor, intente de nuevo más tarde.";
    http_response_code(500);
}

echo json_encode($response);
?>