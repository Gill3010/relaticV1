<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Incluir configuración de la base de datos
require_once 'config.php';

// Respuesta por defecto
$response = [
    'success' => false,
    'message' => '',
    'documents' => [
        'carnets' => [],
        'certificates' => []
    ],
    'user' => null
];

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido';
    http_response_code(405);
    echo json_encode($response);
    exit;
}

// Obtener y decodificar datos JSON
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'JSON inválido';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

// Validar que se recibió el userId
if (empty($input['userId'])) {
    $response['message'] = 'ID de usuario requerido';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

$userId = filter_var($input['userId'], FILTER_VALIDATE_INT);

if (!$userId || $userId <= 0) {
    $response['message'] = 'ID de usuario inválido';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

try {
    // Verificar que el usuario existe y es miembro
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            first_name, 
            last_name, 
            email, 
            role, 
            cedula_dni, 
            member_id, 
            join_date, 
            status 
        FROM users 
        WHERE id = ? AND role = 'member' AND status = 'active'
    ");
    
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $response['message'] = 'Usuario no encontrado o no tiene permisos para acceder a documentos';
        http_response_code(404);
        echo json_encode($response);
        exit;
    }
    
    // Obtener documentos del usuario
    $documents = getUserDocuments($userId);
    
    $response['success'] = true;
    $response['message'] = 'Documentos obtenidos exitosamente';
    $response['user'] = [
        'id' => (int)$user['id'],
        'firstName' => $user['first_name'],
        'lastName' => $user['last_name'],
        'email' => $user['email'],
        'cedulaDni' => $user['cedula_dni'],
        'memberId' => $user['member_id'],
        'joinDate' => $user['join_date'],
        'status' => $user['status']
    ];
    $response['documents'] = $documents;
    
    http_response_code(200);
    
} catch (PDOException $e) {
    error_log('Error en get-user-documents: ' . $e->getMessage());
    $response['message'] = 'Error interno del servidor al obtener documentos';
    http_response_code(500);
} catch (Exception $e) {
    error_log('Error general en get-user-documents: ' . $e->getMessage());
    $response['message'] = 'Error procesando la solicitud';
    http_response_code(500);
}

echo json_encode($response);

/**
 * Función para obtener documentos del usuario usando user_id
 */
function getUserDocuments($userId) {
    global $pdo;
    
    $documents = [
        'carnets' => [],
        'certificates' => []
    ];
    
    // Obtener carnets del usuario
    $stmt = $pdo->prepare("
        SELECT 
            id,
            nombre_completo as document_name,
            'carnet' as document_type,
            cedula_dni,
            cargo_rol as position,
            departamento as department,
            fecha_ingreso as start_date,
            fecha_vencimiento as expiration_date,
            foto_ruta as file_path,
            fecha_creacion as created_at,
            TIMESTAMPDIFF(DAY, CURDATE(), fecha_vencimiento) as days_until_expiration
        FROM carnets 
        WHERE user_id = ?
        ORDER BY fecha_creacion DESC
    ");
    
    $stmt->execute([$userId]);
    $carnets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear fechas y agregar estado de expiración
    foreach ($carnets as &$carnet) {
        $carnet['id'] = (int)$carnet['id'];
        $carnet['is_expired'] = $carnet['days_until_expiration'] < 0;
        $carnet['is_expiring_soon'] = $carnet['days_until_expiration'] >= 0 && $carnet['days_until_expiration'] <= 30;
        unset($carnet['days_until_expiration']);
    }
    
    $documents['carnets'] = $carnets;
    
    // Obtener certificados del usuario
    $stmt = $pdo->prepare("
        SELECT 
            id,
            nombre_curso as document_name,
            'certificate' as document_type,
            nombre_estudiante as student_name,
            id_estudiante as student_id,
            horas_academicas as academic_hours,
            creditos as credits,
            fecha_inicio as start_date,
            fecha_fin as end_date,
            fecha_emision as issue_date,
            created_at,
            DATEDIFF(CURDATE(), fecha_emision) as days_since_issue
        FROM certificates 
        WHERE user_id = ?
        ORDER BY fecha_emision DESC
    ");
    
    $stmt->execute([$userId]);
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear datos numéricos
    foreach ($certificates as &$certificate) {
        $certificate['id'] = (int)$certificate['id'];
        $certificate['academic_hours'] = (int)$certificate['academic_hours'];
        $certificate['credits'] = (int)$certificate['credits'];
        $certificate['is_recent'] = $certificate['days_since_issue'] <= 30;
        unset($certificate['days_since_issue']);
    }
    
    $documents['certificates'] = $certificates;
    
    return $documents;
}

// Cerrar conexión
$pdo = null;
?>
