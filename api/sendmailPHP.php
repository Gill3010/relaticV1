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

// Cargar Composer autoload
require __DIR__ . '/../vendor/autoload.php'; // Ajusta si tu sendmailPHP.php está en otra carpeta

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
    // Configuración del servidor SMTP de Outlook
    $mail->isSMTP();
    $mail->Host       = 'smtp.office365.com';  // Servidor SMTP de Outlook
    $mail->SMTPAuth   = true;
    $mail->Username   = 'desarrolloyoperaciones@relaticpanama.org'; // Tu correo Outlook
    $mail->Password   = 'Mmmacarr3010*';          // Tu contraseña o App Password si 2FA activado
    $mail->SMTPSecure = 'STARTTLS';
    $mail->Port       = 587;

    // Remitente y destinatario
    $mail->setFrom('desarrolloyoperaciones@relaticpanama.org', 'Relatic Panamá');
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
