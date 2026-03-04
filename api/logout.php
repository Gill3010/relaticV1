<?php
// Define los orígenes permitidos
$allowed_origins = [
    'https://www.relaticpanama.org', // ← AÑADIDO (con www)
    'https://relaticpanama.org',
    'http://localhost:4173',
    'http://localhost:5173' // ← AÑADIDO (por si usas Vite)
];

// Obtiene el origen de la solicitud
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Si el origen está en la lista de permitidos, establece la cabecera
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Si no está en la lista, puedes usar un origen por defecto o rechazar
    header("Access-Control-Allow-Origin: https://www.relaticpanama.org");
}

// Establece las cabeceras CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS'); // ← AÑADIDO GET
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With'); // ← AÑADIDO
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 3600'); // ← AÑADIDO para cachear preflight

// Manejo de la solicitud "preflight"
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo procesar POST para logout
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Use POST.'
    ]);
    exit;
}

// Configuración de sesión más segura
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Solo si usas HTTPS
ini_set('session.cookie_samesite', 'Lax');

// Inicia la sesión para poder destruirla
session_start();

// Regenerar ID de sesión para prevenir fixation attacks
session_regenerate_id(true);

// Destruye todos los datos de sesión registrados
$_SESSION = array();

// Elimina la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruye la sesión
session_destroy();

// Responde al cliente
echo json_encode([
    'success' => true,
    'message' => 'Sesión cerrada con éxito.',
    'redirect' => '/login' // ← OPCIONAL: para redirección en frontend
]);

exit;
?>