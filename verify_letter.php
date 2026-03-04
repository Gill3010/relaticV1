<?php
// Forzar UTF-8 - MEJORADO
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
header("Content-Type: text/html; charset=UTF-8");

// Asegurarse de que la conexión PDO use UTF-8
require_once "api/config.php";

// Verificar que la conexión PDO tenga UTF-8 configurado
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

$id = $_GET['id'] ?? null;
$letter = null;
$message = "Carta no encontrada.";

// Ruta de la imagen de fondo de la carta
$imagePath = 'assets/cartas/carta.jpg';
$imageFullPath = __DIR__ . '/' . $imagePath;
$imageExists = file_exists($imageFullPath);
$imageMessage = $imageExists ? "" : "Imagen NO encontrada en {$imagePath}.";

if ($id && is_numeric($id)) {
    // Nueva consulta: traemos la carta y el nombre del evento
    $sql = "
    SELECT 
        l.*, 
        e.name AS event_name
    FROM 
        letters l
    LEFT JOIN 
        events e ON l.event_id = e.id
    WHERE 
        l.id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $letter = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($letter) {
        $message = "Carta verificada.";
        
        // Asegurar UTF-8 en todos los campos de texto
        foreach ($letter as $key => $value) {
            if (is_string($value)) {
                $letter[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
        }
        
        // Generar el número de constancia dinámico
        $numeroConstanciaGenerado = generarNumeroConstancia($letter['tipo_constancia'], $letter['id']);
    }
}

// Función helper para salida segura de texto UTF-8
function safeOutput($text, $fallback = '') {
    if (empty($text)) return htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8');
    
    // Asegurar que el texto esté en UTF-8
    $cleanText = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    return htmlspecialchars($cleanText, ENT_QUOTES, 'UTF-8');
}

// Función para generar el número de constancia dinámico
function generarNumeroConstancia($tipoConstancia, $id) {
    $anioActual = date('Y'); // Obtiene el año actual
    $numeroConstancia = $tipoConstancia . '-00' . $id . '-' . $anioActual;
    return $numeroConstancia;
}

// Función para convertir el día a palabras y agregar el número entre paréntesis
function agregarDias($fecha) {
    $numerosEnPalabras = [
        1 => 'uno', 2 => 'dos', 3 => 'tres', 4 => 'cuatro', 5 => 'cinco',
        6 => 'seis', 7 => 'siete', 8 => 'ocho', 9 => 'nueve', 10 => 'diez',
        11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce', 15 => 'quince',
        16 => 'dieciséis', 17 => 'diecisiete', 18 => 'dieciocho', 19 => 'diecinueve', 20 => 'veinte',
        21 => 'veintiuno', 22 => 'veintidós', 23 => 'veintitrés', 24 => 'veinticuatro', 25 => 'veinticinco',
        26 => 'veintiséis', 27 => 'veintisiete', 28 => 'veintiocho', 29 => 'veintinueve', 30 => 'treinta',
        31 => 'treinta y uno'
    ];
    
    // Extraer el número del día
    preg_match('/^(\d{1,2})/', $fecha, $matches);
    $dia = (int)$matches[1];
    
    // Obtener el día en palabras
    $diaEnPalabras = isset($numerosEnPalabras[$dia]) ? $numerosEnPalabras[$dia] : $dia;
    
    // Reemplazar el número por palabras seguido del número entre paréntesis y la palabra "días"
    return preg_replace('/^(\d{1,2})/', $diaEnPalabras . ' (' . $dia . ') días', $fecha);
}

// Función para convertir fecha YYYY-MM-DD a formato legible en español
function formatearFechaEspanol($fecha) {
    $meses = [
        '01' => 'enero',
        '02' => 'febrero',
        '03' => 'marzo',
        '04' => 'abril',
        '05' => 'mayo',
        '06' => 'junio',
        '07' => 'julio',
        '08' => 'agosto',
        '09' => 'septiembre',
        '10' => 'octubre',
        '11' => 'noviembre',
        '12' => 'diciembre'
    ];
    
    // Separar la fecha en partes
    $partes = explode('-', $fecha);
    $ano = $partes[0];
    $mes = $partes[1];
    $dia = ltrim($partes[2], '0'); // Eliminar ceros a la izquierda del día
    
    // Construir fecha en español
    return $dia . ' de ' . $meses[$mes] . ' de ' . $ano;
}

// Procesar lógica de firmantes
$firmanteFijo = "LICENCIADA TANIA KENNEDY";
$cargoFijo = "PRESIDENTE RELATIC PANAMÁ";
$firmanteVariable = !empty($letter['firmante']) ? trim($letter['firmante']) : '';
$cargoVariable = !empty($letter['cargo']) ? trim($letter['cargo']) : '';

// Construir lista de firmantes
$listaFirmantes = [];
$listaFirmantes[] = $firmanteFijo;
if (!empty($firmanteVariable)) {
    $listaFirmantes[] = $firmanteVariable;
}

$cantidadFirmantes = count($listaFirmantes);

// Determinar artículo y verbo según cantidad de firmantes
if ($cantidadFirmantes === 1) {
    $articulo = "LA SUSCRITA";
    $verboCertifica = "CERTIFICA";
} else {
    $articulo = "LOS SUSCRITOS";
    $verboCertifica = "CERTIFICAN";
}

// Construir texto de firmantes con cargos
if ($cantidadFirmantes === 1) {
    $textoFirmantes = $firmanteFijo . ", " . $cargoFijo;
} else {
    $textoFirmantes = $firmanteFijo . ", " . $cargoFijo . " Y " . $firmanteVariable . ", " . $cargoVariable;
}

// Generar URL de verificación para el QR
$verificationUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                   "://" . $_SERVER['HTTP_HOST'] . 
                   dirname($_SERVER['PHP_SELF']) . 
                   "/verify_letter.php?id=" . ($letter['id'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verificación de Carta</title>
<style>
body { 
    font-family: Arial, sans-serif; 
    background: #f5f5f5; 
    margin: 0; 
    padding: 0;
    line-height: 1.6;
    letter-spacing: 0.3px;
}

.carta-container {
    position: relative;
    width: 95%;
    max-width: 1000px;
    margin: 20px auto;
    background-image: url('<?php echo $imagePath; ?>');
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    aspect-ratio: 0.773;
    min-height: 600px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.text-overlay {
    position: absolute;
    color: #000;
    font-family: Arial, sans-serif;
    font-weight: normal;
    line-height: 1.5;
    padding: 0;
    margin: 0;
    letter-spacing: 0.4px;
}

/* Logos e imágenes con mejor espaciado */
.event-logo-overlay {
    position: absolute;
    top: 3%;
    left: 5%;
    max-width: 120px;
    max-height: 80px;
    object-fit: contain;
}

.event-signature-overlay {
    position: absolute;
    bottom: 24%;
    left: 70%;
    max-width: 200px;
    max-height: 80px;
    object-fit: contain;
}

/* Contenedor del código QR */
.qr-code-container {
    position: absolute;
    bottom: 12%;
    right: 3%;
    background: white;
    padding: 8px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

#qrcode {
    width: 100px;
    height: 100px;
}

.qr-label {
    font-size: 9px;
    color: #333;
    text-align: center;
    font-weight: 500;
    margin: 0;
    font-family: Arial, sans-serif;
}

/* Estilo de mensaje consistente con certificados */
.message-box {
    background-color: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    max-width: 600px;
    margin: 20px auto;
    text-align: center;
    border: 1px solid #e0e0e0;
    font-family: Arial, sans-serif;
}

.success-message { 
    color: #28a745; 
    margin: 0 0 15px 0; 
    font-size: 1.3em;
    font-weight: 600;
    font-family: Arial, sans-serif;
}

.error-message { 
    color: #dc3545; 
    margin: 0 0 15px 0; 
    font-size: 1.3em;
    font-weight: 600;
    font-family: Arial, sans-serif;
}

.download-button {
    display: inline-block;
    margin-top: 15px;
    padding: 10px 20px;
    font-size: 16px;
    font-weight: bold;
    color: #fff;
    background-color: #007bff;
    border: none;
    border-radius: 5px;
    text-decoration: none;
    cursor: pointer;
    transition: background-color 0.3s ease;
    font-family: Arial, sans-serif;
}
.download-button:hover { background-color: #0056b3; }

/* ========== RESPONSIVE MÓVIL - OPTIMIZADO ========== */
@media (max-width: 768px) {
    .carta-container { 
        width: 98%; 
        margin: 10px auto; 
    }
    
    /* LOGOS - Ajuste de posición */
    .event-logo-overlay {
        max-width: 80px;
        max-height: 50px;
        top: 2.8%;
        left: 3%;
    }
    
    /* FECHA Y LUGAR - Reposicionado debajo de los logos */
    .carta-container > div[style*="top: 16%"] {
        font-size: 8px !important;
        top: 11% !important;
        right: 3% !important;
        left: auto !important;
        line-height: 1.5 !important;
        max-width: 55%;
        white-space: normal !important;
        text-align: right !important;
    }
    
    /* CONSTANCIA - Número de constancia */
    .carta-container > div[style*="top: 22%"] {
        font-size: 13px !important;
        top: 18% !important;
        letter-spacing: 0.6px !important;
        word-spacing: 0.8px !important;
        padding: 0 8px;
    }
    
    /* FIRMANTES - Línea completa con mejor espaciado */
    .carta-container > div[style*="top: 35%"] {
        font-size: 8px !important;
        top: 29% !important;
        left: 1.1cm !important;
        right: 1.1cm !important;
        line-height: 2.1 !important;
        padding: 0 8px;
        white-space: normal !important;
        word-wrap: break-word !important;
    }
    
    /* CERTIFICA QUE - Separado del texto anterior */
    .carta-container > div[style*="top: 40%"] {
        font-size: 8.5px !important;
        top: 36% !important;
        line-height: 1.8 !important;
    }
    
    /* PÁRRAFO PRINCIPAL - Más espacio vertical y legibilidad */
    .carta-container > div[style*="top: 45%"] {
        font-size: 8px !important;
        top: 40% !important;
        left: 1.1cm !important;
        right: 1.1cm !important;
        line-height: 2.2 !important;
        padding: 0 8px;
        text-align: justify !important;
        margin-bottom: 0.8em !important;
    }
    
    /* CORREO - Separado del párrafo anterior */
    .carta-container > div[style*="top: 50%"] {
        font-size: 8px !important;
        top: 49% !important;
        left: 1.1cm !important;
        right: 1.1cm !important;
        line-height: 2.2 !important;
        padding: 0 8px;
        text-align: justify !important;
        margin-bottom: 0.8em !important;
    }
    
    /* TEXTO DE EXPEDICIÓN - Separado */
    .carta-container > div[style*="top: 53%"] {
        font-size: 8px !important;
        top: 55% !important;
        left: 1.1cm !important;
        right: 1.1cm !important;
        line-height: 2.2 !important;
        padding: 0 8px;
        text-align: justify !important;
        margin-bottom: 1.5em !important;
    }
    
    /* ATENTAMENTE - Reposicionado */
    .carta-container > div[style*="top: 57%"] {
        font-size: 8px !important;
        top: 62% !important;
        left: 1.1cm !important;
        line-height: 1.8 !important;
    }
    
    /* FIRMA - Ajustada para no interferir con QR */
    .event-signature-overlay {
        bottom: 28%;
        left: 58%;
        max-width: 115px;
        max-height: 52px;
    }
    
    /* QR CODE - Reposicionado más arriba para evitar franja azul */
    .qr-code-container {
        bottom: 18% !important;
        right: 3%;
        padding: 5px;
        border-radius: 5px;
        gap: 3px;
    }
    
    #qrcode {
        width: 58px !important;
        height: 58px !important;
    }
    
    #qrcode canvas,
    #qrcode img {
        width: 58px !important;
        height: 58px !important;
    }
    
    .qr-label {
        font-size: 6.5px;
        line-height: 1.2;
        white-space: nowrap;
    }
}

/* ========== PANTALLAS MUY PEQUEÑAS (<480px) ========== */
@media (max-width: 480px) {
    .carta-container { 
        width: 98%; 
        margin: 8px auto; 
    }
    
    /* LOGOS */
    .event-logo-overlay {
        max-width: 68px;
        max-height: 45px;
        top: 2.6%;
        left: 2.5%;
    }
    
    /* FECHA Y LUGAR */
    .carta-container > div[style*="top: 16%"] {
        font-size: 7px !important;
        top: 10% !important;
        right: 2.5% !important;
        left: auto !important;
        line-height: 1.4 !important;
        max-width: 58%;
        white-space: normal !important;
        text-align: right !important;
    }
    
    /* CONSTANCIA */
    .carta-container > div[style*="top: 22%"] {
        font-size: 11px !important;
        top: 17% !important;
        letter-spacing: 0.5px !important;
        padding: 0 6px;
    }
    
    /* FIRMANTES */
    .carta-container > div[style*="top: 35%"] {
        font-size: 7px !important;
        top: 28% !important;
        left: 0.9cm !important;
        right: 0.9cm !important;
        line-height: 2.2 !important;
        padding: 0 6px;
    }
    
    /* CERTIFICA */
    .carta-container > div[style*="top: 40%"] {
        font-size: 7.5px !important;
        top: 35% !important;
        line-height: 1.9 !important;
    }
    
    /* PÁRRAFOS */
    .carta-container > div[style*="top: 45%"] {
        font-size: 7px !important;
        top: 39% !important;
        left: 0.9cm !important;
        right: 0.9cm !important;
        line-height: 2.3 !important;
        padding: 0 6px;
        margin-bottom: 0.9em !important;
    }
    
    .carta-container > div[style*="top: 50%"] {
        font-size: 7px !important;
        top: 48% !important;
        left: 0.9cm !important;
        right: 0.9cm !important;
        line-height: 2.3 !important;
        padding: 0 6px;
        margin-bottom: 0.9em !important;
    }
    
    .carta-container > div[style*="top: 53%"] {
        font-size: 7px !important;
        top: 54% !important;
        left: 0.9cm !important;
        right: 0.9cm !important;
        line-height: 2.3 !important;
        padding: 0 6px;
        margin-bottom: 1.5em !important;
    }
    
    /* ATENTAMENTE */
    .carta-container > div[style*="top: 57%"] {
        font-size: 7px !important;
        top: 61% !important;
        left: 0.9cm !important;
        line-height: 1.9 !important;
    }
    
    /* FIRMA */
    .event-signature-overlay {
        bottom: 27%;
        left: 56%;
        max-width: 100px;
        max-height: 48px;
    }
    
    /* QR CODE - Más arriba para evitar franja azul */
    .qr-code-container {
        bottom: 19% !important;
        right: 2.5%;
        padding: 4px;
        border-radius: 4px;
        gap: 2px;
    }
    
    #qrcode {
        width: 50px !important;
        height: 50px !important;
    }
    
    #qrcode canvas,
    #qrcode img {
        width: 50px !important;
        height: 50px !important;
    }
    
    .qr-label {
        font-size: 6px;
        line-height: 1.1;
    }
}
</style>
</head>
<body>

<div class="message-box">
    <?php if ($letter): ?>
        <h2 class="success-message"><?php echo safeOutput($message); ?></h2>
        <p>La siguiente información coincide con nuestros registros.</p>
        <a href="download_letter.php?id=<?php echo safeOutput($letter['id']); ?>" class="download-button" target="_blank">
            Descargar Carta
        </a>
    <?php else: ?>
        <h2 class="error-message"><?php echo safeOutput($message); ?></h2>
        <?php if (!$imageExists): ?>
            <p><?php echo safeOutput($imageMessage); ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($letter && $imageExists): ?>
<div class="carta-container">
    <?php if (!empty($letter['logo_url'])): ?>
        <img src="<?php echo safeOutput($letter['logo_url']); ?>" alt="Logo de la Carta" class="event-logo-overlay">
    <?php endif; ?>

    <!-- Encabezado con fecha (esquina superior derecha) -->
    <div style="position: absolute; top: 16%; right: 1.5cm; font-size: 12px; color: #000; font-weight: 500; text-align: right; white-space: nowrap; letter-spacing: 0.3px; font-family: Arial, sans-serif;">
        <?php echo safeOutput($letter['lugar']); ?>, <?php echo safeOutput($letter['fecha_expedicion']); ?>
    </div>

    <!-- 1. TIPO DE CONSTANCIA (Ej: CONSTANCIA-00-123456-2025) -->
    <div style="position: absolute; top: 22%; left: 50%; transform: translateX(-50%); font-size: 20px; color: #000; font-weight: bold; text-align: center; text-transform: uppercase; letter-spacing: 1.2px; word-spacing: 2px; font-family: Arial, sans-serif;">
        <?php echo safeOutput($numeroConstanciaGenerado); ?>
    </div>

    <!-- 2. LÍNEA DE FIRMANTES (LA SUSCRITA / LOS SUSCRITOS + nombres y cargos + institución) -->
    <div style="position: absolute; top: 35%; left: 1.5cm; right: 1.5cm; font-size: 12px; color: #000; font-weight: 600; text-align: center; letter-spacing: 0.3px; word-spacing: 0.5px; font-family: Arial, sans-serif; line-height: 1.65; white-space: normal; word-wrap: break-word; overflow-wrap: break-word; hyphens: none;">
        <?php echo $articulo; ?>, <?php echo safeOutput($textoFirmantes); ?> <?php echo safeOutput($letter['institucion']); ?>
    </div>
    
    <!-- 3. CERTIFICA(N) QUE: -->
    <div style="position: absolute; top: 40%; left: 50%; transform: translateX(-50%); font-size: 12px; color: #000; font-weight: bold; text-align: center; letter-spacing: 1px; word-spacing: 2px; font-family: Arial, sans-serif; line-height: 1.5;">
        <?php echo $verboCertifica; ?> QUE:
    </div>
    
    <!-- PÁRRAFO PRINCIPAL DESPUÉS DE "CERTIFICAN QUE:" -->
    <?php
    // Determinar texto dinámico según el estado del evento
    $hoy = date('Y-m-d');
    $fechaInicio = $letter['fecha_inicio'];
    $fechaFinal = $letter['fecha_final'];

    // Convertir las fechas a formato español
    $fechaInicioFormateada = formatearFechaEspanol($fechaInicio);
    $fechaFinalFormateada = formatearFechaEspanol($fechaFinal);

    if ($fechaInicio > $hoy) {
        // Evento futuro
        $texto_evento = "el cual comenzará el " . $fechaInicioFormateada . " y finalizará el " . $fechaFinalFormateada;
    } elseif ($fechaFinal < $hoy) {
        // Evento ya finalizado
        $texto_evento = "el cual se desarrolló desde el " . $fechaInicioFormateada . " hasta el " . $fechaFinalFormateada;
    } else {
        // Evento en curso
        $texto_evento = "el cual se está desarrollando del " . $fechaInicioFormateada . " hasta el " . $fechaFinalFormateada;
    }
    ?>
    
    <div style="position: absolute; top: 45%; left: 1.5cm; right: 1.5cm; font-size: 12px; color: #000; text-align: justify; line-height: 1.6; letter-spacing: 0.2px; word-spacing: 0.5px; font-family: Arial, sans-serif; hyphens: none;">
        <span style="font-weight: bold; text-transform: uppercase;"><?php echo safeOutput($letter['participante']); ?></span>, con documento de identidad No. <span style="font-weight: bold;"><?php echo safeOutput($letter['dni_cedula']); ?></span> <?php echo safeOutput($letter['inscripcion_texto']); ?> <span style="font-weight: bold; text-transform: uppercase;"><?php echo safeOutput($letter['event_name']); ?></span>, <?php echo $texto_evento; ?>.
    </div>
    
    <!-- Información del correo -->
    <div style="position: absolute; top: 50%; left: 1.5cm; right: 1.5cm; font-size: 12px; color: #000; text-align: justify; line-height: 1.6; letter-spacing: 0.2px; word-spacing: 0.5px; font-family: Arial, sans-serif; hyphens: none;">
        Para acreditar la veracidad de este documento, realice su solicitud al correo: <span style="font-style: italic; color: #2c5aa0;"><?php echo safeOutput($letter['correo']); ?></span>
    </div>
    
    <!-- Texto de expedición -->
    <div style="position: absolute; top: 53%; left: 1.5cm; right: 1.5cm; font-size: 12px; color: #000; text-align: justify; line-height: 1.6; letter-spacing: 0.2px; word-spacing: 0.5px; font-family: Arial, sans-serif; margin-bottom: 1em; hyphens: none;">
        Se expide el presente documento a los <?php echo agregarDias($letter['fecha_expedicion']); ?>, para los fines que estime conveniente.
    </div>
    
    <div style="position: absolute; top: 57%; left: 1.5cm; font-size: 12px; color: #000; font-weight: 500; letter-spacing: 0.3px; font-family: Arial, sans-serif; line-height: 1.5;">Atentamente,</div>

    <?php if (!empty($letter['signature_url'])): ?>
        <img src="<?php echo safeOutput($letter['signature_url']); ?>" alt="Firma de la Carta" class="event-signature-overlay">
    <?php endif; ?>

    <!-- Código QR para verificación -->
    <div class="qr-code-container">
        <div id="qrcode"></div>
        <p class="qr-label">Escanea para verificar</p>
    </div>
</div>

<!-- Biblioteca QRCode.js desde CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Generar el código QR con la URL de verificación
    var qrcode = new QRCode(document.getElementById("qrcode"), {
        text: "<?php echo $verificationUrl; ?>",
        width: 100,
        height: 100,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.M
    });
});
</script>

<?php elseif ($letter && !$imageExists): ?>
<div class="message-box error-message">La carta existe en la base de datos, pero la imagen de fondo no se encuentra.</div>
<?php endif; ?>

</body>
</html>