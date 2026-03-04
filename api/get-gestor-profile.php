<?php
// get-gestor-profile.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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
    // Consulta SQL que ahora selecciona first_name, last_name, y email
    $stmt = $pdo->prepare("
        SELECT 
            u.id, 
            u.first_name, 
            u.last_name, 
            u.email, 
            gp.created_at
        FROM 
            users u
        JOIN 
            gestor_profiles gp ON u.id = gp.user_id
        WHERE 
            u.id = ?
    ");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($profile) {
        $profile['full_name'] = $profile['first_name'] . ' ' . $profile['last_name'];
        unset($profile['first_name']);
        unset($profile['last_name']);

        $response['success'] = true;
        $response['message'] = 'Perfil encontrado.';
        $response['profile'] = $profile;
        http_response_code(200);
    } else {
        $response['message'] = 'Perfil de gestor no encontrado para este usuario.';
        http_response_code(404);
    }
} catch (PDOException $e) {
    $response['message'] = 'Error interno del servidor.';
    error_log('Error fetching gestor profile: ' . $e->getMessage());
    http_response_code(500);
}

echo json_encode($response);
?>