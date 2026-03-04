<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Responde a la petición "preflight"
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = ['success' => false, 'message' => 'Error al enviar el correo.'];

// Cargar Composer autoload y configuración (credenciales SMTP en config.local.php)
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/config.php';

// Obtener datos POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
$honeypot = $data['honeypot'] ?? '';

if (!empty($honeypot)) {
    $response['message'] = 'Error en la validación de seguridad.';
    echo json_encode($response);
    exit;
}

// Validación del email
if (empty($email)) {
    $response['message'] = 'El email es obligatorio.';
    echo json_encode($response);
    exit;
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'El email no tiene un formato válido.';
    echo json_encode($response);
    exit;
}

// Configuración de PHPMailer
$mail = new PHPMailer(true);

try {
    // Configuración SMTP desde config.local.php
    $mail->isSMTP();
    $mail->Host       = $smtp_host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp_user;
    $mail->Password   = $smtp_pass;
    $mail->SMTPSecure = 'STARTTLS';
    $mail->Port       = $smtp_port;

    // Remitente y destinatario
    $mail->setFrom($smtp_user, 'Relatic Panamá');
    $mail->addAddress($email);

    // Contenido del correo
    $mail->isHTML(true);
    $mail->Subject = $data['subject'] ?? 'Recuperación de contraseña';
    $mail->Body    = $data['body'] ?? 'Aquí va tu enlace de recuperación de contraseña';

    $mail->send();
    $response['success'] = true;
    $response['message'] = 'Correo enviado correctamente.';
} catch (Exception $e) {
    $response['message'] = "No se pudo enviar el correo. Error: {$mail->ErrorInfo}";
}

echo json_encode($response);
