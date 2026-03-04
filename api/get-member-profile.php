<?php
// get-member-profile.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Considera usar un origen específico si es posible
require_once 'config.php';

$response = ['success' => false, 'message' => '', 'profile' => null];

if (!isset($_GET['userId']) || !is_numeric($_GET['userId'])) {
    $response['message'] = 'ID de usuario no válido.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

$userId = $_GET['userId'];

try {
    // CAMBIO CLAVE: Agregando 'mp.cedula_dni' a la consulta SQL
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            mp.created_at,
            mp.cedula_dni  -- ¡NUEVO!
        FROM
            users u
        JOIN
            member_profiles mp ON u.id = mp.user_id
        WHERE
            u.id = ?
    ");
    
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($profile) {
        $profile['full_name'] = $profile['first_name'] . ' ' . $profile['last_name'];
        unset($profile['first_name']);
        unset($profile['last_name']);
        
        // Manejar el caso si la cédula es NULL o vacía en la base de datos
        $profile['cedula_dni'] = $profile['cedula_dni'] ?? 'N/A';
        
        // Formatear la fecha de creación para mostrar de manera amigable (igual que el gestor)
        if ($profile['created_at']) {
            $date = new DateTime($profile['created_at']);
            $profile['created_at'] = $date->format('d/m/Y');
        }
        
        $response['success'] = true;
        $response['message'] = 'Perfil de miembro encontrado.';
        $response['profile'] = $profile;
        http_response_code(200);
    } else {
        $response['message'] = 'Perfil de miembro no encontrado para este usuario.';
        http_response_code(404);
    }
    
} catch (PDOException $e) {
    $response['message'] = 'Error interno del servidor.';
    error_log('Error fetching member profile: ' . $e->getMessage());
    http_response_code(500);
}

echo json_encode($response);
?>