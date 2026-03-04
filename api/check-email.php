<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

$response = ['available' => true];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode($response);
    exit;
}

if (!isset($_GET['email'])) {
    http_response_code(400);
    echo json_encode($response);
    exit;
}

$email = filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['available'] = false;
    echo json_encode($response);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $response['available'] = $stmt->fetch() === false;
} catch (PDOException $e) {
    error_log('Error checking email availability: ' . $e->getMessage());
}

echo json_encode($response);
?>