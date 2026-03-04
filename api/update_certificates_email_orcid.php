<?php
/**
 * Actualiza las columnas email y orcid en certificados V2 por orden de fila.
 * El archivo Excel/CSV debe tener dos columnas: email (o correo) y orcid.
 * La fila 1 del archivo se empareja con el 1er certificado V2, fila 2 con el 2do, etc.
 */
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../vendor/autoload.php';
require_once "config.php";

use PhpOffice\PhpSpreadsheet\IOFactory;

$response = ["success" => false, "message" => "", "updated" => 0, "total" => 0];

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $response["message"] = "Método no permitido";
    http_response_code(405);
    echo json_encode($response);
    exit;
}

if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    $response["message"] = "Error al subir el archivo. Debe enviar un Excel o CSV con columnas 'email' y 'orcid'.";
    http_response_code(400);
    echo json_encode($response);
    exit;
}

$eventId = isset($_POST['event_id']) && $_POST['event_id'] !== '' ? (int) $_POST['event_id'] : null;

try {
    $uploadedFile = $_FILES['excel_file']['tmp_name'];
    $spreadsheet = IOFactory::load($uploadedFile);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray(null, true, true, true);

    if (empty($data) || count($data) < 2) {
        $response["message"] = "El archivo está vacío o no tiene filas de datos.";
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Fila 1 = encabezados
    $headers = array_map('strtolower', array_map('trim', $data[1]));
    $emailCol = array_search('email', $headers);
    if ($emailCol === false) {
        $emailCol = array_search('correo', $headers);
    }
    $orcidCol = array_search('orcid', $headers);

    if ($emailCol === false || $orcidCol === false) {
        $response["message"] = "El archivo debe tener columnas 'email' (o 'correo') y 'orcid'.";
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    $rows = [];
    for ($i = 2; $i <= count($data); $i++) {
        $row = $data[$i];
        $email = isset($row[$emailCol]) ? trim((string) $row[$emailCol]) : '';
        $orcid = isset($row[$orcidCol]) ? trim((string) $row[$orcidCol]) : '';
        $rows[] = [
            'email' => $email !== '' ? $email : null,
            'orcid' => $orcid !== '' ? $orcid : null,
        ];
    }

    if (empty($rows)) {
        $response["message"] = "No hay filas de datos en el archivo.";
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Obtener certificados V2 ordenados por id
    $sql = "SELECT id FROM certificates WHERE template_version = 'v2'";
    $params = [];
    if ($eventId !== null) {
        $sql .= " AND event_id = ?";
        $params[] = $eventId;
    }
    $sql .= " ORDER BY id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $certificates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $totalCerts = count($certificates);
    $totalRows = count($rows);
    $toUpdate = min($totalCerts, $totalRows);

    if ($toUpdate === 0) {
        $response["message"] = "No hay certificados V2 para actualizar" . ($eventId !== null ? " para el evento $eventId" : "") . ".";
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    $updateStmt = $pdo->prepare("UPDATE certificates SET email = ?, orcid = ? WHERE id = ?");
    $updated = 0;

    for ($i = 0; $i < $toUpdate; $i++) {
        $updateStmt->execute([
            $rows[$i]['email'],
            $rows[$i]['orcid'],
            $certificates[$i],
        ]);
        $updated += $updateStmt->rowCount();
    }

    $response["success"] = true;
    $response["updated"] = $updated;
    $response["total"] = $totalCerts;
    $response["message"] = "Se actualizaron $updated certificados V2 con email y ORCID.";
    if ($totalRows > $totalCerts) {
        $response["message"] .= " (El archivo tenía más filas que certificados; se usaron las primeras $totalCerts.)";
    } elseif ($totalCerts > $totalRows) {
        $response["message"] .= " (Hay más certificados V2 que filas en el archivo; solo se actualizaron los primeros $totalRows.)";
    }
    http_response_code(200);

} catch (Exception $e) {
    error_log("update_certificates_email_orcid: " . $e->getMessage());
    $response["message"] = "Error al procesar el archivo: " . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
