<?php
/**
 * Vista independiente de datos verificados para certificados V2.
 * Muestra nombre_estudiante, id_estudiante, email y ORCID.
 * Solo aplica a certificados con template_version = 'v2'.
 * Si es V1 o no existe, redirige a la verificación estándar.
 */
require_once "api/config.php";

$certificate = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int) $_GET['id'];
    $sql = "SELECT id, nombre_estudiante, id_estudiante, email, orcid, template_version 
            FROM certificates WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Si no existe o es V1, redirigir a la vista estándar
// V2 = template_version 'v2' O tiene email/orcid (datos verificables)
$tv = $certificate ? strtolower(trim($certificate['template_version'] ?? '')) : '';
$esV2 = $certificate && (($tv === 'v2') || !empty(trim($certificate['email'] ?? '')) || !empty(trim($certificate['orcid'] ?? '')));
if (!$certificate || !$esV2) {
    header('Location: verify_certificate.php?id=' . (isset($_GET['id']) ? (int) $_GET['id'] : ''));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos Verificados</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 24px;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 420px;
            width: 100%;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 32px;
            text-align: center;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            background: #10b981;
            border-radius: 50%;
            margin-bottom: 20px;
        }
        .badge svg {
            width: 36px;
            height: 36px;
            fill: #fff;
        }
        .message {
            font-size: 1.25rem;
            font-weight: 600;
            color: #065f46;
            margin-bottom: 28px;
            line-height: 1.4;
        }
        .data-list {
            text-align: left;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }
        .data-row {
            display: flex;
            flex-direction: column;
            margin-bottom: 16px;
        }
        .data-row:last-child { margin-bottom: 0; }
        .data-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        .data-value {
            font-size: 1rem;
            color: #111827;
            font-weight: 500;
        }
        .data-value.empty { color: #9ca3af; font-style: italic; font-weight: normal; }
        .link-verify {
            display: inline-block;
            margin-top: 24px;
            font-size: 0.9rem;
            color: #2563eb;
            text-decoration: none;
        }
        .link-verify:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="badge">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        </div>
        <p class="message">Sus datos han sido verificados en nuestra base de datos</p>
        <div class="data-list">
            <div class="data-row">
                <span class="data-label">Nombre completo</span>
                <span class="data-value"><?php echo htmlspecialchars($certificate['nombre_estudiante'] ?? ''); ?></span>
            </div>
            <div class="data-row">
                <span class="data-label">DNI / ID</span>
                <span class="data-value"><?php echo htmlspecialchars($certificate['id_estudiante'] ?? ''); ?></span>
            </div>
            <div class="data-row">
                <span class="data-label">Correo electrónico</span>
                <span class="data-value <?php echo empty($certificate['email']) ? 'empty' : ''; ?>">
                    <?php echo htmlspecialchars($certificate['email'] ?? '') ?: 'No registrado'; ?>
                </span>
            </div>
            <div class="data-row">
                <span class="data-label">ORCID</span>
                <span class="data-value <?php echo empty($certificate['orcid']) ? 'empty' : ''; ?>">
                    <?php echo htmlspecialchars($certificate['orcid'] ?? '') ?: 'No registrado'; ?>
                </span>
            </div>
        </div>
        <a href="verify_certificate.php?id=<?php echo (int) $certificate['id']; ?>" class="link-verify">
            Ver certificado completo →
        </a>
    </div>
</body>
</html>
