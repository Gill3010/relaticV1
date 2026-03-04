<?php
// Permite solicitudes desde el origen que envió la petición para solucionar el error de CORS.
// Esto es necesario para que funcione tanto en tu entorno de desarrollo como en producción.
header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Includes the database configuration file
require_once "config.php";

// Handles the OPTIONS request
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

$response = ["success" => false, "message" => ""];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validar que se ha proporcionado el ID del evento y los archivos
    if (!isset($_POST['event_id']) || !isset($_FILES['letter_logo']) || !isset($_FILES['letter_signature'])) {
        $response["message"] = "Solicitud inválida. Por favor, proporciona el ID del evento, logo y firma para cartas.";
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    $eventId = $_POST['event_id'];
    $logoFile = $_FILES['letter_logo'];
    $signatureFile = $_FILES['letter_signature'];

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    // --- SUBIDA DEL LOGO PARA CARTAS ---
    $logoTargetDir = "letter_logos/";
    if (!is_dir($logoTargetDir)) mkdir($logoTargetDir, 0755, true);

    $logoFileName = uniqid('letter_logo_', true) . '_' . basename($logoFile["name"]);
    $logoTargetPath = $logoTargetDir . $logoFileName;
    $logoPathForDB = 'api/' . $logoTargetPath;

    if (!in_array($logoFile['type'], $allowedTypes) || $logoFile['size'] > $maxSize) {
        $response["message"] = "Archivo de logo inválido. Debe ser una imagen (PNG, JPG, GIF) y pesar menos de 2MB.";
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    if (!move_uploaded_file($logoFile["tmp_name"], $logoTargetPath)) {
        $response["message"] = "Error al subir el archivo del logo para cartas.";
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    // --- SUBIDA DE LA FIRMA PARA CARTAS ---
    $signatureTargetDir = "letter_signatures/";
    if (!is_dir($signatureTargetDir)) mkdir($signatureTargetDir, 0755, true);

    $signatureFileName = uniqid('letter_sig_', true) . '_' . basename($signatureFile["name"]);
    $signatureTargetPath = $signatureTargetDir . $signatureFileName;
    $signaturePathForDB = 'api/' . $signatureTargetPath;

    if (!in_array($signatureFile['type'], $allowedTypes) || $signatureFile['size'] > $maxSize) {
        $response["message"] = "Archivo de firma inválido. Debe ser una imagen (PNG, JPG, GIF) y pesar menos de 2MB.";
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    if (!move_uploaded_file($signatureFile["tmp_name"], $signatureTargetPath)) {
        $response["message"] = "Error al subir el archivo de la firma para cartas.";
        http_response_code(500);
        echo json_encode($response);
        exit;
    }

    try {
        // Actualizar todas las cartas existentes del evento con los nuevos activos
        $sql = "UPDATE letters SET logo_url = ?, signature_url = ? WHERE event_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$logoPathForDB, $signaturePathForDB, $eventId]);

        // Verificar si existen cartas para ese evento
        $checkEventSql = "SELECT COUNT(*) FROM letters WHERE event_id = ?";
        $checkStmt = $pdo->prepare($checkEventSql);
        $checkStmt->execute([$eventId]);
        $letterCount = $checkStmt->fetchColumn();

        if ($letterCount > 0) {
            $response["success"] = true;
            $response["message"] = "Activos para cartas subidos exitosamente. Se actualizaron {$stmt->rowCount()} cartas del evento.";
            http_response_code(200);
        } else {
            $response["success"] = true;
            $response["message"] = "Los archivos se subieron correctamente, pero no hay cartas creadas aún para este evento.";
            http_response_code(200);
        }

    } catch (PDOException $e) {
        error_log("Database error in process_excel_upload_letter.php: " . $e->getMessage());
        $response["message"] = "Error de base de datos. Por favor, intenta de nuevo más tarde.";
        http_response_code(500);

        // Limpiar archivos subidos en caso de error de BD
        if (file_exists($logoTargetPath)) unlink($logoTargetPath);
        if (file_exists($signatureTargetPath)) unlink($signatureTargetPath);
    }
} else {
    $response["message"] = "Método no permitido.";
    http_response_code(405);
}

echo json_encode($response);
?>
