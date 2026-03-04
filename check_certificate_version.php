<?php
/**
 * Verificar template_version de un certificado (para depuración).
 * Uso: check_certificate_version.php?id=123
 * ELIMINAR este archivo en producción.
 */
require_once "api/config.php";

header('Content-Type: text/plain; charset=utf-8');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
    echo "Uso: check_certificate_version.php?id=ID_DEL_CERTIFICADO\n";
    exit;
}

$stmt = $pdo->prepare("SELECT id, nombre_estudiante, template_version FROM certificates WHERE id = ?");
$stmt->execute([$id]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$c) {
    echo "Certificado con ID $id no encontrado.\n";
    exit;
}

$tv = $c['template_version'];
$tvNorm = strtolower(trim($tv ?? ''));
$esV2 = ($tvNorm === 'v2');

echo "Certificado ID: " . $c['id'] . "\n";
echo "Nombre: " . ($c['nombre_estudiante'] ?? '-') . "\n";
echo "template_version (raw): " . var_export($tv, true) . "\n";
echo "¿Se considera V2?: " . ($esV2 ? 'SÍ' : 'NO') . "\n";
echo "\nSi es NO, el certificado mostrará la vista estándar en vez de la vista de datos verificados.\n";
