<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// La ruta al autoloader de Composer es crucial. 
// Usando __DIR__ . '/../' para asegurar que siempre apunte al directorio padre.
require_once __DIR__ . '/../vendor/autoload.php';
require_once "config.php"; // Asume que config.php está en el mismo directorio

use PhpOffice\PhpSpreadsheet\IOFactory;

$response = ["success" => false, "message" => ""];

// Manejo de la solicitud OPTIONS (útil para peticiones preflight de CORS)
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $response["message"] = "Método no permitido.";
    http_response_code(405);
    echo json_encode($response);
    exit;
}

// Validar que se ha subido un archivo
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    $response["message"] = "Error al subir el archivo.";
    http_response_code(400);
    echo json_encode($response);
    exit;
}

$tempFilePath = $_FILES['excel_file']['tmp_name'];

try {
    // Cargar el archivo Excel
    $spreadsheet = IOFactory::load($tempFilePath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    // Eliminar la primera fila si son cabeceras
    if (!empty($rows) && count($rows[0]) > 0) {
        array_shift($rows);
    }
    
    // Preparar la consulta SQL para insertar los eventos
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO events (name) VALUES (?) ON DUPLICATE KEY UPDATE name=name");

    $count = 0;
    $errors = [];

    foreach ($rows as $row) {
        // Asume que la primera columna (índice 0) tiene el nombre del evento
        $eventName = $row[0] ?? null;

        // Sanitizar y validar el nombre del evento
        $sanitizedName = htmlspecialchars(trim($eventName), ENT_QUOTES, 'UTF-8');

        if (empty($sanitizedName)) {
            continue; // Ignorar filas vacías
        }

        try {
            if ($stmt->execute([$sanitizedName])) {
                // Si se insertó una nueva fila (no era un duplicado)
                if ($stmt->rowCount() > 0) {
                    $count++;
                }
            } else {
                $errors[] = "Error al insertar el evento: " . $sanitizedName;
            }
        } catch (PDOException $e) {
            error_log("Error de inserción para el evento '{$sanitizedName}': " . $e->getMessage());
            $errors[] = "Error al insertar el evento: " . $sanitizedName;
        }
    }

    $pdo->commit();

    if (empty($errors)) {
        $response["success"] = true;
        $response["message"] = "Se han insertado {$count} nuevos eventos correctamente.";
        http_response_code(201);
    } else {
        $response["success"] = false;
        $response["message"] = 'Se completó la carga con algunos errores. Detalles en el registro de errores.';
        $response["errors"] = $errors;
        http_response_code(500);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error general al procesar el archivo: " . $e->getMessage());
    $response["message"] = "Error interno del servidor al procesar el archivo. Verifique el formato.";
    http_response_code(500);
}

echo json_encode($response);