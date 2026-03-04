<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

$response = ['success' => false, 'message' => 'Credenciales incorrectas.'];
$errors = [];

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($data)) {
    http_response_code(405);
    $response['message'] = 'Método de petición no permitido o formato de datos incorrecto.';
    echo json_encode($response);
    exit;
}

$email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
$password = $data['password'] ?? '';
$rememberMe = $data['rememberMe'] ?? false;
$honeypot = $data['honeypot'] ?? '';

if (!empty($honeypot)) {
    http_response_code(400);
    $response['message'] = 'Error en la validación de seguridad.';
    echo json_encode($response);
    exit;
}

if (empty($email)) {
    $errors['email'] = 'El email es obligatorio.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'El email no tiene un formato válido.';
}

if (empty($password)) {
    $errors['password'] = 'La contraseña es obligatoria.';
} else if (strlen($password) < 6) {
    $errors['password'] = 'La contraseña debe tener al menos 6 caracteres.';
}

if (!empty($errors)) {
    http_response_code(400);
    $response['message'] = 'Errores de validación en el formulario.';
    $response['errors'] = $errors;
    echo json_encode($response);
    exit;
}

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

// CORRECCIÓN: Se elimina 'full_name' de la consulta SQL porque la columna no existe en la base de datos
$sql = "SELECT id, email, password_hash, password_salt, role FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    $providedPasswordHash = hash('sha256', $password . $user['password_salt']);

    if ($providedPasswordHash === $user['password_hash']) {
        $_SESSION['user_id'] = $user['id'];
        
        if ($rememberMe) {
            setcookie('remember_token', 'un_token_seguro_y_largo', time() + (86400 * 30), "/");
        }
        
        $response['success'] = true;
        $response['message'] = '¡Inicio de sesión exitoso! Redirigiendo...';
        
        $response['user'] = [
            'id' => $user['id'],
            'role' => $user['role']
        ];
        
        http_response_code(200);
    } else {
        http_response_code(401);
    }
} else {
    http_response_code(401);
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>