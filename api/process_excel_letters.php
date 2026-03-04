<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../vendor/autoload.php';
require_once "config.php";

use PhpOffice\PhpSpreadsheet\IOFactory;

$response = ["success" => false, "message" => "", "letters" => []];

// Manejo de la solicitud OPTIONS (CORS preflight)
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

// Validar archivo subido
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    $response["message"] = "Error al subir el archivo.";
    http_response_code(400);
    echo json_encode($response);
    exit;
}

// Validar que se ha recibido el ID del evento
if (!isset($_POST['event_id']) || empty($_POST['event_id'])) {
    $response["message"] = "ID de evento no proporcionado.";
    http_response_code(400);
    echo json_encode($response);
    exit;
}

$tempFilePath = $_FILES['excel_file']['tmp_name'];
$eventId = $_POST['event_id'];

try {
    $spreadsheet = IOFactory::load($tempFilePath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    // Eliminar cabecera
    if (!empty($rows) && count($rows[0]) > 0) {
        array_shift($rows);
    }

    $pdo->beginTransaction();

    // Actualizar la consulta SQL con la nueva variable al final
    $stmt = $pdo->prepare("
        INSERT INTO letters (
            participante, dni_cedula, tipo_constancia, fecha_inicio, fecha_final, 
            fecha_generacion, fecha_expedicion, event_id, lugar, firmante, cargo, 
            institucion, correo, inscripcion_texto
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $count = 0;
    $errors = [];

    // Fechas automáticas
    $fechaGeneracion = date('Y-m-d H:i:s');
    $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
    ];
    $dia = date('j');
    $mes = $meses[date('n')];
    $anio = date('Y');
    $fechaExpedicionFormateada = "{$dia} de {$mes} de {$anio}";

    foreach ($rows as $index => $row) {
        $nombreCompleto = trim($row[0] ?? '');
        $dniCedula = trim($row[1] ?? '');
        $tipoConstancia = trim($row[2] ?? '');
        $fechaInicio = trim($row[3] ?? '');
        $fechaFinal = trim($row[4] ?? '');
        $lugar = trim($row[5] ?? '');
        $firmante = trim($row[6] ?? '');
        $cargo = trim($row[7] ?? '');
        $institucion = trim($row[8] ?? '');
        $correo = trim($row[9] ?? '');
        $inscripcionTexto = trim($row[10] ?? '');

        if (empty($nombreCompleto) || empty($dniCedula) || empty($tipoConstancia) || empty($fechaInicio) || empty($fechaFinal) || empty($lugar) || empty($firmante) || empty($cargo) || empty($institucion) || empty($correo)) {
            $errors[] = "Fila " . ($index + 2) . ": Datos incompletos.";
            continue;
        }

        $nombreCompleto = htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8');
        $dniCedula = htmlspecialchars($dniCedula, ENT_QUOTES, 'UTF-8');
        $tipoConstancia = htmlspecialchars($tipoConstancia, ENT_QUOTES, 'UTF-8');
        $lugar = htmlspecialchars($lugar, ENT_QUOTES, 'UTF-8');
        $firmante = htmlspecialchars($firmante, ENT_QUOTES, 'UTF-8');
        $cargo = htmlspecialchars($cargo, ENT_QUOTES, 'UTF-8');
        $institucion = htmlspecialchars($institucion, ENT_QUOTES, 'UTF-8');
        $correo = htmlspecialchars($correo, ENT_QUOTES, 'UTF-8');
        $inscripcionTexto = htmlspecialchars($inscripcionTexto, ENT_QUOTES, 'UTF-8');

        try {
            $fechaInicioFormatted = date('Y-m-d', strtotime($fechaInicio));
            $fechaFinalFormatted = date('Y-m-d', strtotime($fechaFinal));
            if ($fechaInicioFormatted === false || $fechaFinalFormatted === false) {
                throw new Exception("Formato de fecha inválido");
            }
        } catch (Exception $e) {
            $errors[] = "Fila " . ($index + 2) . ": Formato de fecha inválido.";
            continue;
        }

        try {
            if ($stmt->execute([
                $nombreCompleto,
                $dniCedula,
                $tipoConstancia,
                $fechaInicioFormatted,
                $fechaFinalFormatted,
                $fechaGeneracion,
                $fechaExpedicionFormateada,
                $eventId,
                $lugar,
                $firmante,
                $cargo,
                $institucion,
                $correo,
                $inscripcionTexto
            ])) {
                $count++;
                $lastId = $pdo->lastInsertId();
                $verificationUrl = "https://relaticpanama.org/verify_letter.php?id={$lastId}";

                $response['letters'][] = [
                    'id' => $lastId,
                    'participante' => $nombreCompleto,
                    'verification_url' => $verificationUrl
                ];
            } else {
                $errors[] = "Fila " . ($index + 2) . ": Error al insertar la carta para {$nombreCompleto}.";
            }
        } catch (PDOException $e) {
            error_log("Error de inserción para '{$nombreCompleto}': " . $e->getMessage());
            $errors[] = "Fila " . ($index + 2) . ": Error de base de datos.";
        }
    }

    $pdo->commit();

    if (empty($errors)) {
        $response["success"] = true;
        $response["message"] = "Se han generado {$count} cartas correctamente.";
        $response["fecha_expedicion"] = $fechaExpedicionFormateada;
        http_response_code(201);
    } else {
        $response["success"] = false;
        $response["message"] = "Se completó la carga con algunos errores. Se procesaron {$count} cartas exitosamente.";
        $response["errors"] = $errors;
        $response["fecha_expedicion"] = $fechaExpedicionFormateada;
        http_response_code(207);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error general al procesar cartas: " . $e->getMessage());
    $response["message"] = "Error interno del servidor. Verifique el formato del archivo.";
    http_response_code(500);
}

echo json_encode($response);
?>