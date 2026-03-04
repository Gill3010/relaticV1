<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$data = $_POST;
$foto = $_FILES['foto'] ?? null;
$comprobante = $_FILES['comprobantePago'] ?? null;

try {
    // Guardar archivos
    $uploadDir = '../api/suscriptions/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    // Foto carnet (obligatoria)
    $fotoName = uniqid() . "_" . basename($foto['name']);
    $fotoPath = $uploadDir . $fotoName;
    move_uploaded_file($foto['tmp_name'], $fotoPath);

    // Comprobante de pago (opcional)
    $comprobanteName = null;
    if ($comprobante && $comprobante['error'] === UPLOAD_ERR_OK) {
        $comprobanteName = uniqid() . "_" . basename($comprobante['name']);
        $comprobantePath = $uploadDir . $comprobanteName;
        move_uploaded_file($comprobante['tmp_name'], $comprobantePath);
    }

    // Insertar en BD (debes agregar la columna `comprobantePago` en la tabla)
    $stmt = $pdo->prepare("INSERT INTO suscriptions (
        email, pais, cedula, pasaporte, afiliacion, orcid,
        primerNombre, segundoNombre, primerApellido, segundoApellido,
        edad, genero, grado, actividad, area, palabrasClave,
        foto, comprobantePago
    ) VALUES (
        :email, :pais, :cedula, :pasaporte, :afiliacion, :orcid,
        :primerNombre, :segundoNombre, :primerApellido, :segundoApellido,
        :edad, :genero, :grado, :actividad, :area, :palabrasClave,
        :foto, :comprobantePago
    )");

    $stmt->execute([
        ':email' => $data['email'],
        ':pais' => $data['pais'],
        ':cedula' => $data['cedula'],
        ':pasaporte' => $data['pasaporte'] ?? null,
        ':afiliacion' => $data['afiliacion'],
        ':orcid' => $data['orcid'],
        ':primerNombre' => $data['primerNombre'],
        ':segundoNombre' => $data['segundoNombre'] ?? null,
        ':primerApellido' => $data['primerApellido'],
        ':segundoApellido' => $data['segundoApellido'] ?? null,
        ':edad' => $data['edad'],
        ':genero' => $data['genero'],
        ':grado' => $data['grado'],
        ':actividad' => $data['actividad'],
        ':area' => $data['area'],
        ':palabrasClave' => $data['palabrasClave'] ?? null,
        ':foto' => $fotoName,
        ':comprobantePago' => $comprobanteName
    ]);

    echo json_encode(['success' => true, 'message' => 'Formulario recibido']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar: ' . $e->getMessage()]);
}
?>