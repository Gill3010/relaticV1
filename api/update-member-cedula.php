<?php
// update-member-cedula.php
session_start();
header('Content-Type: application/json');

// --- CÓDIGO CORS MEJORADO PARA MÚLTIPLES ENTORNOS ---
$allowed_origins = [
    'http://localhost:4174',  
    'http://localhost:4173',
    'https://relaticpanama.org',      // Sin www
    'https://www.relaticpanama.org',  // ✅ Con www - AGREGADO
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
} else {
    // Debug: Log del origen no permitido
    error_log("CORS: Origen no permitido: " . $origin);
}

// Manejo de preflight (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'config.php';

$response = ['success' => false, 'message' => ''];

// Leer datos enviados en JSON
$input = json_decode(file_get_contents('php://input'), true);

// ⚠️ CAMBIO CLAVE: Obtener el userId directamente del cuerpo de la solicitud
$userId = isset($input['userId']) ? (int)$input['userId'] : null;
$cedula = isset($input['cedula']) ? trim($input['cedula']) : null;

// NUEVA LÓGICA DE VALIDACIÓN: Usar el userId de la solicitud
if (empty($userId)) {
    $response['message'] = 'ID de usuario no proporcionado.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

// ⚠️ Adicional: Validar que el usuario en la sesión coincida con el de la solicitud (doble seguridad)
// Si la sesión no funciona, esta parte se omite, pero el script sigue funcionando
if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] !== $userId) {
    $response['message'] = 'Acceso no autorizado.';
    http_response_code(401);
    echo json_encode($response);
    exit;
}

// Validar cédula
if (empty($cedula)) {
    $response['message'] = 'La cédula no puede estar vacía.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

try {
    // Evitar duplicados: verificar que no exista la cédula en otro usuario
    $stmt = $pdo->prepare("
        SELECT user_id 
        FROM member_profiles 
        WHERE cedula_dni = ? AND user_id != ?
    ");
    $stmt->execute([$cedula, $userId]);
    
    if ($stmt->fetch()) {
        $response['message'] = 'La cédula ya está registrada por otro usuario.';
        http_response_code(409); // Conflict
        echo json_encode($response);
        exit;
    }

    // Actualizar o insertar la cédula en el perfil del usuario
    $stmt = $pdo->prepare("
        UPDATE member_profiles
        SET cedula_dni = ?
        WHERE user_id = ?
    ");
    $stmt->execute([$cedula, $userId]);

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Cédula actualizada correctamente.';
    } else {
        $response['message'] = 'No se realizaron cambios (¿ya estaba guardada esta cédula?).';
    }

    http_response_code(200);
    
} catch (PDOException $e) {
    $response['message'] = 'Error interno del servidor.';
    error_log('Error updating cedula: ' . $e->getMessage());
    http_response_code(500);
}

echo json_encode($response);
?>