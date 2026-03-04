<?php
// member_search.php
session_start();
header('Content-Type: application/json');

// --- CÓDIGO CORS SEGURO ---
$allowed_origins = [
    'http://localhost:4173',
    'http://localhost:4174',
    'http://localhost:3000',
    'https://relaticpanama.org',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *"); // Para desarrollo
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Conexión a la base de datos
require_once "config.php";

$response = [
    "success" => false,
    "message" => "",
    "carnets" => [],
    "certificates" => [],
    "letters" => []
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $input = file_get_contents("php://input");
    if (empty($input)) {
        $response["message"] = "Datos no proporcionados.";
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $response["message"] = "JSON inválido.";
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    $searchTerm = trim($data['cedula_dni'] ?? '');
    if (empty($searchTerm)) {
        $response["message"] = "Término de búsqueda no proporcionado.";
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Validación de cédula: números, letras, guiones y espacios
    if (!preg_match('/^[0-9A-Z\- ]+$/i', $searchTerm)) {
        $response["message"] = "Cédula/DNI inválida.";
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    try {
        // Función para simplificar consultas
        function fetchData($pdo, $table, $column, $value) {
            $sql = "SELECT * FROM `$table` WHERE `$column` = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$value]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Consultas usando los nombres correctos de columnas
        $carnets = fetchData($pdo, 'carnets', 'cedula_dni', $searchTerm);
        $certificates = fetchData($pdo, 'certificates', 'id_estudiante', $searchTerm);
        $letters = fetchData($pdo, 'letters', 'dni_cedula', $searchTerm);

        $response["success"] = true;
        $response["carnets"] = $carnets;
        $response["certificates"] = $certificates;
        $response["letters"] = $letters;
        $response["message"] = "Búsqueda completada exitosamente.";
        http_response_code(200);

    } catch (PDOException $e) {
        error_log("Error de base de datos: " . $e->getMessage());
        $response["message"] = "Error de base de datos. Inténtelo de nuevo más tarde.";
        http_response_code(500);
    }

} else {
    $response["message"] = "Método no permitido.";
    http_response_code(405);
}

echo json_encode($response);
?>
