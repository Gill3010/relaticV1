<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responder a preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Incluir configuración de la base de datos
require_once 'config.php';

// Respuesta por defecto
$response = [
    'success' => false,
    'message' => '',
    'errors' => []
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

// Protección contra bots (honeypot)
if (!empty($input['honeypot'])) {
    $response['message'] = 'Registro bloqueado';
    http_response_code(403);
    echo json_encode($response);
    exit;
}

// Validar datos recibidos
$errors = validateRegistrationData($input);

if (!empty($errors)) {
    $response['message'] = 'Errores de validación';
    $response['errors'] = $errors;
    http_response_code(422);
    echo json_encode($response);
    exit;
}

// Verificar si el email ya existe
if (emailExists($input['email'])) {
    $response['message'] = 'El correo electrónico ya está registrado';
    $response['errors']['email'] = 'Este correo electrónico ya está registrado';
    http_response_code(409);
    echo json_encode($response);
    exit;
}

// Registrar intento de registro
logRegistrationAttempt($_SERVER['REMOTE_ADDR'], $input['email']);

// Crear usuario en la base de datos
try {
    $userId = createUser($input);
    
    if ($userId) {
        $response['success'] = true;
        $response['message'] = '¡Registro exitoso! Te hemos enviado un correo de verificación.';
        $response['userId'] = $userId;
        http_response_code(201);
    } else {
        $response['message'] = 'Error al crear el usuario';
        http_response_code(500);
    }
} catch (Exception $e) {
    $response['message'] = 'Error interno del servidor';
    error_log('Error en registro: ' . $e->getMessage());
    http_response_code(500);
}

// Este es el único punto de salida para la respuesta JSON
echo json_encode($response);
exit;

// -----------------------------------------------------------------------------------------------------
// Funciones de validación y procesamiento (sin cambios)
// -----------------------------------------------------------------------------------------------------

function validateRegistrationData($data) {
    $errors = [];
    
    // Validar email
    if (empty($data['email'])) {
        $errors['email'] = 'El email es obligatorio';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'El email no tiene un formato válido';
    } elseif (!isValidDomain($data['email'])) {
        $errors['email'] = 'El dominio de correo no está permitido';
    } elseif (strlen($data['email']) > 255) {
        $errors['email'] = 'El email es demasiado largo';
    }
    
    // Validar contraseña
    if (empty($data['password'])) {
        $errors['password'] = 'La contraseña es obligatoria';
    } elseif (strlen($data['password']) < 8) {
        $errors['password'] = 'La contraseña debe tener al menos 8 caracteres';
    } elseif (strlen($data['password']) > 12) {
        $errors['password'] = 'La contraseña no puede exceder los 12 caracteres';
    } elseif (!preg_match('/(?=.*[a-z])(?=.*[A-Z])/', $data['password'])) {
        $errors['password'] = 'La contraseña debe contener mayúsculas y minúsculas';
    } elseif (!preg_match('/(?=.*\d)/', $data['password'])) {
        $errors['password'] = 'La contraseña debe contener al menos un número';
    } elseif (!preg_match('/(?=.*[!@#$%^&*()_+\-=\[\]{};\'":\\|,.<>\/?])/', $data['password'])) {
        $errors['password'] = 'La contraseña debe contener al menos un carácter especial';
    }
    
    // Validar confirmación de contraseña
    if (empty($data['confirmPassword'])) {
        $errors['confirmPassword'] = 'Debes confirmar tu contraseña';
    } elseif ($data['password'] !== $data['confirmPassword']) {
        $errors['confirmPassword'] = 'Las contraseñas no coinciden';
    }
    
    // Validar nombre
    if (empty($data['firstName'])) {
        $errors['firstName'] = 'El nombre es obligatorio';
    } elseif (strlen($data['firstName']) > 50) {
        $errors['firstName'] = 'El nombre no puede exceder los 50 caracteres';
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $data['firstName'])) {
        $errors['firstName'] = 'El nombre no puede contener números ni caracteres especiales';
    }
    
    // Validar apellido
    if (empty($data['lastName'])) {
        $errors['lastName'] = 'El apellido es obligatorio';
    } elseif (strlen($data['lastName']) > 50) {
        $errors['lastName'] = 'El apellido no puede exceder los 50 caracteres';
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $data['lastName'])) {
        $errors['lastName'] = 'El apellido no puede contener números ni caracteres especiales';
    }
    
    // Validar rol
    $allowedRoles = ['member', 'gestor'];
    if (empty($data['role']) || !in_array($data['role'], $allowedRoles)) {
        $errors['role'] = 'Rol no válido';
    }
    
    // Validar términos y condiciones
    if (empty($data['acceptTerms']) || !$data['acceptTerms']) {
        $errors['acceptTerms'] = 'Debes aceptar los términos y condiciones';
    }
    
    return $errors;
}

function isValidDomain($email) {
    $blockedDomains = ['tempmail.com', 'mailinator.com', 'guerrillamail.com', '10minutemail.com'];
    $domain = explode('@', $email)[1];
    return !in_array($domain, $blockedDomains);
}

function emailExists($email) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log('Error checking email: ' . $e->getMessage());
        return false;
    }
}

function logRegistrationAttempt($ipAddress, $email) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO registration_attempts (ip_address, email, successful) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$ipAddress, $email, false]);
    } catch (PDOException $e) {
        error_log('Error logging registration attempt: ' . $e->getMessage());
    }
}

// -----------------------------------------------------------------------------------------------------
// Funciones de creación de usuario y perfil (modificadas)
// -----------------------------------------------------------------------------------------------------

/**
 * Crea un nuevo usuario en la base de datos y, si el rol es 'gestor' o 'member',
 * crea automáticamente un perfil asociado.
 */
function createUser($data) {
    global $pdo;
    
    try {
        $salt = bin2hex(random_bytes(16));
        $passwordHash = hash('sha256', $data['password'] . $salt);
        $verificationToken = bin2hex(random_bytes(32));
        $verificationTokenExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $pdo->beginTransaction();
        
        // 1. Insertar el nuevo usuario en la tabla `users`
        $stmt = $pdo->prepare("
            INSERT INTO users (
                first_name, last_name, email, password_hash, password_salt,
                role, verification_token, verification_token_expires,
                accepted_terms, terms_acceptance_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            ucwords(strtolower($data['firstName'])),
            ucwords(strtolower($data['lastName'])),
            $data['email'],
            $passwordHash,
            $salt,
            $data['role'],
            $verificationToken,
            $verificationTokenExpires,
            $data['acceptTerms'] ? 1 : 0
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // 2. Comprobar el rol y crear el perfil asociado
        if ($data['role'] === 'gestor') {
            createGestorProfile($userId);
        } elseif ($data['role'] === 'member') {
            // NUEVA LÓGICA: CREAR PERFIL DE MIEMBRO
            createMemberProfile($userId);
        }
        
        $stmtHistory = $pdo->prepare("
            INSERT INTO password_history (user_id, password_hash) 
            VALUES (?, ?)
        ");
        $stmtHistory->execute([$userId, $passwordHash]);
        
        $stmtAttempt = $pdo->prepare("
            UPDATE registration_attempts 
            SET successful = true 
            WHERE ip_address = ? AND email = ? 
            ORDER BY attempt_time DESC 
            LIMIT 1
        ");
        $stmtAttempt->execute([$_SERVER['REMOTE_ADDR'], $data['email']]);
        
        $pdo->commit();
        
        sendVerificationEmail($data['email'], $verificationToken);
        
        return $userId;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Error creating user: ' . $e->getMessage());
        return false;
    }
}

/**
 * Crea una nueva entrada en la tabla `gestor_profiles` para el usuario especificado.
 * @param int $userId El ID del usuario recién creado.
 */
function createGestorProfile($userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO gestor_profiles (user_id) 
            VALUES (?)
        ");
        $stmt->execute([$userId]);
        error_log("Perfil de gestor creado para el usuario ID: $userId");
    } catch (PDOException $e) {
        error_log('Error creating gestor profile: ' . $e->getMessage());
    }
}

/**
 * Crea una nueva entrada en la tabla `member_profiles` para el usuario especificado.
 * @param int $userId El ID del usuario recién creado.
 */
function createMemberProfile($userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO member_profiles (user_id) 
            VALUES (?)
        ");
        $stmt->execute([$userId]);
        error_log("Perfil de miembro creado para el usuario ID: $userId");
    } catch (PDOException $e) {
        error_log('Error creating member profile: ' . $e->getMessage());
    }
}

function sendVerificationEmail($email, $token) {
    // Implementa envío real de email según tu sistema
    error_log("Email de verificación para $email con token: $token");
}
?>