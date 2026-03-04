<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responde a la petición "preflight" del navegador (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = ['success' => false, 'message' => 'Error al enviar el enlace de recuperación.'];
$errors = [];

// Obtener y decodificar el cuerpo de la petición
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($data)) {
    http_response_code(405);
    $response['message'] = 'Método de petición no permitido o formato de datos incorrecto.';
    echo json_encode($response);
    exit;
}

// Limpiar y validar el email
$email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
$honeypot = $data['honeypot'] ?? '';

// Protección Honeypot
if (!empty($honeypot)) {
    http_response_code(400);
    $response['message'] = 'Error en la validación de seguridad.';
    echo json_encode($response);
    exit;
}

// Validación del email
if (empty($email)) {
    $errors['resetEmail'] = 'El email es obligatorio.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['resetEmail'] = 'El email no tiene un formato válido.';
}

if (!empty($errors)) {
    http_response_code(400);
    $response['message'] = 'Errores de validación en el formulario.';
    $response['errors'] = $errors;
    echo json_encode($response);
    exit;
}

// Lógica de Conexión y Búsqueda de Usuario en la Base de Datos
$servername = "localhost";
$dbusername = "Forms25";
$dbpassword = "Forms.2025";
$dbname = "Forms";

$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    $response['message'] = 'Error de conexión a la base de datos.';
    echo json_encode($response);
    exit;
}

// Se busca el email en la base de datos
$sql = "SELECT id FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    // Usuario existe, aquí iría la lógica para enviar el email de recuperación
    // Por ejemplo, generando un token y guardándolo en la base de datos
    // y luego enviándolo por correo electrónico.
    
    $response['success'] = true;
    $response['message'] = 'Se ha enviado un enlace de recuperación a tu correo electrónico.';
    http_response_code(200);
} else {
    // Por seguridad, se da una respuesta exitosa genérica
    $response['success'] = true;
    $response['message'] = 'Si la dirección de correo electrónico está registrada, recibirás un enlace de recuperación.';
    http_response_code(200);
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>