<?php
// Incluye el archivo de configuración de la base de datos y plantillas de certificados
require_once "api/config.php";
$certificateTemplates = require "api/certificate_templates.php";

// Función para formatear fecha (si la fecha es inválida o 0000-00-00, usa la fecha de hoy)
function formatearFechaEmision($fecha) {
    $fecha = trim((string)$fecha);
    if ($fecha === '' || $fecha === '0000-00-00') {
        $fecha = date('Y-m-d');
    }
    $timestamp = strtotime($fecha);
    if (!$timestamp || (int)date("Y", $timestamp) < 1) {
        $fecha = date('Y-m-d');
        $timestamp = strtotime($fecha);
    }
    if (!$timestamp) return htmlspecialchars($fecha);

    $dia = (int)date("j", $timestamp);
    $anio = date("Y", $timestamp);

    $meses = [
        1 => "enero", 2 => "febrero", 3 => "marzo",
        4 => "abril", 5 => "mayo", 6 => "junio",
        7 => "julio", 8 => "agosto", 9 => "septiembre",
        10 => "octubre", 11 => "noviembre", 12 => "diciembre"
    ];
    $mes = $meses[(int)date("n", $timestamp)];

    $diasEnPalabras = [
        1 => "un", 2 => "dos", 3 => "tres", 4 => "cuatro", 5 => "cinco",
        6 => "seis", 7 => "siete", 8 => "ocho", 9 => "nueve", 10 => "diez",
        11 => "once", 12 => "doce", 13 => "trece", 14 => "catorce", 15 => "quince",
        16 => "dieciséis", 17 => "diecisiete", 18 => "dieciocho", 19 => "diecinueve", 20 => "veinte",
        21 => "veintiún", 22 => "veintidós", 23 => "veintitrés", 24 => "veinticuatro", 25 => "veinticinco",
        26 => "veintiséis", 27 => "veintisiete", 28 => "veintiocho", 29 => "veintinueve", 30 => "treinta",
        31 => "treinta y un"
    ];
    $diaTexto = $diasEnPalabras[$dia] ?? $dia;

    return "Dado en la ciudad de Panamá a los {$diaTexto} días del mes de {$mes} del {$anio}";
}

// Función para formatear fecha con día y mes en palabras
function formatearFechaCompleta($fecha) {
    $fecha = trim((string)$fecha);
    if ($fecha === '' || $fecha === '0000-00-00') {
        $fecha = date('Y-m-d');
    }
    $timestamp = strtotime($fecha);
    if (!$timestamp || (int)date("Y", $timestamp) < 1) {
        return htmlspecialchars($fecha);
    }
    
    $dia = (int)date("j", $timestamp);
    $anio = date("Y", $timestamp);
    $meses = [
        1 => "enero", 2 => "febrero", 3 => "marzo",
        4 => "abril", 5 => "mayo", 6 => "junio",
        7 => "julio", 8 => "agosto", 9 => "septiembre",
        10 => "octubre", 11 => "noviembre", 12 => "diciembre"
    ];
    $mes = $meses[(int)date("n", $timestamp)];
    
    return "{$dia} de {$mes}";
}

// Función para obtener solo el año
function obtenerAnio($fecha) {
    $fecha = trim((string)$fecha);
    if ($fecha === '' || $fecha === '0000-00-00') {
        $fecha = date('Y-m-d');
    }
    $timestamp = strtotime($fecha);
    if (!$timestamp || (int)date("Y", $timestamp) < 1) {
        return date("Y");
    }
    return date("Y", $timestamp);
}

// Función para determinar el artículo definido según el género del nombre del evento
function obtenerArticuloDefinido($nombreEvento) {
    $nombreEvento = strtolower(trim($nombreEvento));
    
    // Lista de palabras que típicamente son femeninas
    $terminacionesFemeninas = [
        'a', 'ión', 'ad', 'ud', 'ez', 'ie', 'umbre', 'sis'
    ];
    
    // Lista de palabras específicas femeninas (sustantivos que terminan diferente pero son femeninos)
    $palabrasFemeninas = [
        'capacitación', 'formación', 'certificación', 'especialización',
        'diplomatura', 'maestría', 'licenciatura', 'ingeniería',
        'conferencia', 'jornada', 'feria', 'exposición', 'muestra',
        'clase', 'sesión', 'charla', 'ponencia', 'presentación',
        'actividad', 'práctica', 'experiencia', 'oportunidad',
        'carrera', 'profesión', 'disciplina', 'materia', 'asignatura'
    ];
    
    // Lista de palabras específicas masculinas
    $palabrasMasculinas = [
        'curso', 'taller', 'seminario', 'diplomado', 'programa',
        'entrenamiento', 'adiestramiento', 'aprendizaje',
        'congreso', 'simposio', 'foro', 'encuentro', 'evento',
        'workshop', 'bootcamp', 'masterclass', 'webinar',
        'proyecto', 'trabajo', 'estudio', 'análisis',
        'bachillerato', 'doctorado', 'posgrado', 'postgrado'
    ];
    
    // Extraer las palabras del nombre del evento
    $palabras = explode(' ', $nombreEvento);
    
    // Buscar en palabras específicas primero
    foreach ($palabras as $palabra) {
        if (in_array($palabra, $palabrasFemeninas)) {
            return 'la';
        }
        if (in_array($palabra, $palabrasMasculinas)) {
            return 'el';
        }
    }
    
    // Verificar terminaciones en la primera palabra sustantiva (generalmente la más importante)
    $palabraPrincipal = $palabras[0];
    
    // Verificar terminaciones femeninas
    foreach ($terminacionesFemeninas as $terminacion) {
        if (substr($palabraPrincipal, -strlen($terminacion)) === $terminacion) {
            return 'la';
        }
    }
    
    // Si no se encuentra ninguna regla específica, usar "el" como predeterminado
    return 'el';
}

// Devuelve el nombre del evento con artículo solo si no empieza ya por La/El/Los/Las (evita "la La Universidad...")
function formatearNombreEventoConArticulo($nombreEvento) {
    $nombreEvento = trim((string)$nombreEvento);
    if ($nombreEvento === '') return $nombreEvento;
    $inicio = mb_strtoupper(mb_substr($nombreEvento, 0, 4, 'UTF-8'), 'UTF-8');
    if (mb_substr($inicio, 0, 3) === 'LA ' || mb_substr($inicio, 0, 3) === 'EL ') {
        return $nombreEvento;
    }
    if (mb_strlen($nombreEvento) >= 4 && (mb_substr($inicio, 0, 4) === 'LOS ' || mb_substr($inicio, 0, 4) === 'LAS ')) {
        return $nombreEvento;
    }
    return obtenerArticuloDefinido($nombreEvento) . ' ' . $nombreEvento;
}

// Define un mensaje de error por defecto
$message = "Certificado no encontrado.";
$certificate = null;
$esV2 = false;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = $_GET['id'];
    
    // Nueva consulta: Busca el certificado y une la tabla 'events' para obtener los datos del evento
    $sql = "
    SELECT 
        c.*, 
        e.name AS event_name, 
        e.logo_url, 
        e.signature_url
    FROM 
        certificates c
    JOIN 
        events e ON c.event_id = e.id
    WHERE 
        c.id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($certificate) {
        $message = "Certificado verificado exitosamente.";
        $templateVersion = !empty($certificate['template_version']) ? strtolower(trim($certificate['template_version'])) : 'v1';
        // V2: template_version='v2' O tiene email/orcid (certificados con datos verificables). El QR de V2 apunta a verify_certificate_data.php
        $esV2 = ($templateVersion === 'v2') || !empty(trim($certificate['email'] ?? '')) || !empty(trim($certificate['orcid'] ?? ''));
        $templateConfig = $certificateTemplates[$templateVersion] ?? $certificateTemplates['v1'];
        $certificateTemplateUrl = $templateConfig['background'];
        $hasPlanEstudio = !empty($templateConfig['has_plan_estudio']);
        $articuloConcepto = obtenerArticuloDefinido($certificate['concepto']);
    } else {
        $message = "El certificado con ID " . htmlspecialchars($id) . " no existe.";
    }
}

// URL del QR: V2 → verify_certificate_data.php (datos verificados); V1 → verify_certificate.php
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
if ($path === '' || $path === '\\') $path = '';
$certId = (isset($id) && $certificate) ? (int) $id : '';
$currentUrl = $protocol . "://" . $host . $path . "/" . (!empty($esV2) ? "verify_certificate_data.php" : "verify_certificate.php") . "?id=" . $certId;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Certificado</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
        }
        .certificate-container {
            position: relative;
            width: 95%;
            max-width: 1000px;
            margin: 20px auto;
            background-image: url('<?php echo htmlspecialchars(isset($certificateTemplateUrl) ? $certificateTemplateUrl : 'assets/certificates/certificate.png'); ?>');
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
            aspect-ratio: 1.294 / 1;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .text-overlay {
            position: absolute;
            color: #000;
            font-weight: normal;
            text-align: center;
            line-height: 1.2;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            color: #4a5568;
            z-index: 10;
        }
        
        /* Posiciones y estilos específicos para cada elemento */

        .event-logo-overlay {
            position: absolute;
            top: 10.5%;
            left: 17%;
            width: 12%;
            height: auto;
            max-width: 150px;
        }

        .event-name-overlay {
            position: absolute;
            top: 21%;
            left: 50%;
            transform: translateX(-50%);
            width: 50%;
            font-size: 1.6vw;
            font-weight: bold;
            color: #00285a;
            text-align: center;
            text-transform: uppercase;
        }

        .texto-otorgado {
            top: 35%;
            font-size: 1.2vw;
            font-weight: 500;
            color: #4a5568;
        }
        
        .tipo-documento {
            top: 39%;
            font-size: 1.4vw;
            font-weight: bold;
            color: #00285a;
            text-transform: uppercase;
        }
        
        /* ========== AJUSTE DEL NOMBRE ESTUDIANTE (MODIFICADO) ========== */
        .nombre-estudiante {
            top: 44%;
            font-size: 2.2vw;
            font-weight: bold;
            color: #00285a;
            text-transform: uppercase;
            width: 70%; /* Área segura del 70% */
            white-space: normal; /* Permitir saltos de línea */
            word-wrap: break-word; /* Romper palabras largas si es necesario */
            hyphens: none; /* Sin guiones automáticos */
            -webkit-hyphens: none;
            -moz-hyphens: none;
            -ms-hyphens: none;
            line-height: 1.2;
            overflow-wrap: break-word; /* Alternativa moderna a word-wrap */
        }

        .id-estudiante {
            top: 49%;
            font-size: 1.2vw;
            color: #4a5568;
        }

        .convenio {
            top: 34%;
            font-size: 0.9vw;
            color: #4a5568;
            font-style: italic;
        }
        /* ========== FIN DEL AJUSTE DEL NOMBRE ========== */

        .texto-otorgado {
            top: 40%;
            font-size: 1.2vw;
            font-weight: 500;
            color: #4a5568;
        }

        .texto-culminado {
            top: 55%;
            font-size: 1.2vw;
            font-weight: 500;
            color: #4a5568;
        }

        .concepto-value {
            top: 61%;
            font-size: 1.4vw;
            font-weight: bold;
            color: #2d3748;
            text-transform: uppercase;
            white-space: normal;
            word-wrap: break-word;
            hyphens: none;
            -webkit-hyphens: none;
            -moz-hyphens: none;
            -ms-hyphens: none;
            text-align: center;
            line-height: 1.3;
            padding: 0 2%;
            width: 76%;
        }

        .detalles-curso {
            top: 67%;
            font-size: 1vw;
            color: #4a5568;
            line-height: 1.5;
        }

        .fechas-periodo {
            top: 72%;
            font-size: 1vw;
            color: #4a5568;
        }

        .fecha-emision {
            top: 75%;
            font-size: 0.9vw;
            color: #718096;
        }

        .event-signature-overlay {
            position: absolute;
            bottom: 8%;
            left: 50%;
            transform: translateX(-50%);
            width: 20%;
            max-width: 200px;
            height: auto;
        }

        .qr-code-container {
            position: absolute;
            bottom: 7%;
            right: 5.5%;
            background: white;
            padding: 8px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .qr-code-overlay {
            width: 80px;
            height: 80px;
        }

        .qr-label {
            font-size: 9px;
            color: #333;
            text-align: center;
            font-weight: 500;
            margin: 0;
            font-family: Arial, sans-serif;
        }

        /* Media query para ajustar el QR en vista móvil */
        @media screen and (max-width: 768px) {
            .qr-code-container {
                bottom: 6%;
                right: 4%;
                padding: 4px;
                border-radius: 4px;
            }

            .qr-code-overlay {
                width: 45px;
                height: 45px;
            }

            .qr-label {
                font-size: 6px;
            }
        }

        .message-box {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 20px auto;
            text-align: center;
        }
        .success-message { color: #28a745; }
        .error-message { color: #dc3545; }
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
        }
        .download-button:hover {
            background-color: #0056b3;
        }
        .download-button.secundary {
            background-color: #6c757d;
            margin-left: 10px;
        }
        .download-button.secundary:hover {
            background-color: #545b62;
        }
    </style>
</head>
<body>

    <?php if ($certificate): ?>
        <div class="message-box">
            <h2 class="success-message">Certificado Verificado</h2>
            <p>La siguiente información coincide con nuestros registros.</p>
            <a href="download_certificate.php?id=<?php echo htmlspecialchars($certificate['id']); ?>" class="download-button" target="_blank">
                Descargar Certificado
            </a>
            <?php if (!empty($hasPlanEstudio)): ?>
            <a href="download_certificate.php?id=<?php echo htmlspecialchars($certificate['id']); ?>&amp;document=plan_estudio" class="download-button secundary" target="_blank">
                Descargar Plan de Estudio
            </a>
            <?php endif; ?>
        </div>
        <div class="certificate-container">
            <?php if (!empty($certificate['logo_url'])): ?>
                <img src="<?php echo htmlspecialchars($certificate['logo_url']); ?>" alt="Logo del Evento" class="event-logo-overlay">
            <?php endif; ?>
            
            <div class="text-overlay event-name-overlay">
                <?php echo htmlspecialchars(formatearNombreEventoConArticulo($certificate['event_name'])); ?>
            </div>
            <div class="text-overlay convenio">Según convenio vigente No. 100-001 del 20 de octubre de 2025</div>

            <div class="text-overlay texto-otorgado">Otorgan a</div>

            <!--
<div class="text-overlay tipo-documento">
    <?php echo htmlspecialchars($certificate['tipo_documento']) . ' A'; ?>
</div>
-->

            <div class="text-overlay nombre-estudiante" id="nombre-estudiante">
                <?php echo htmlspecialchars($certificate['nombre_estudiante']); ?>
            </div>
            <div class="text-overlay id-estudiante" id="id-estudiante">ID: <?php echo htmlspecialchars($certificate['id_estudiante']); ?></div>
            
            <div class="text-overlay texto-culminado">
                <?php 
                // Usar la variable motivo si existe, sino mantener el texto original
                if (!empty($certificate['motivo'])) {
                    echo htmlspecialchars($certificate['motivo']);
                } else {
                    echo "por haber culminado satisfactoriamente los requisitos de " . $articuloConcepto;
                }
                ?>
            </div>
            
            <div class="text-overlay concepto-value" id="concepto-text">
                <?php echo htmlspecialchars($certificate['concepto']); ?>
            </div>
            
            <div class="text-overlay detalles-curso">
                Realizado del <?php echo formatearFechaCompleta($certificate['fecha_inicio']); ?> hasta el <?php echo formatearFechaCompleta($certificate['fecha_fin']) . ' de ' . obtenerAnio($certificate['fecha_fin']); ?> con una duración total de <?php echo htmlspecialchars($certificate['horas_academicas']); ?>. En mérito a lo expuesto, y con el fin de acreditar su formación, se expide el presente diploma.
            </div>

            <div class="text-overlay fecha-emision">
                <?php echo formatearFechaEmision($certificate['fecha_emision']); ?>
            </div>

            <?php if (!empty($certificate['signature_url'])): ?>
                <img src="<?php echo htmlspecialchars($certificate['signature_url']); ?>" alt="Firma del Evento" class="event-signature-overlay">
            <?php endif; ?>

            <!-- Contenedor para el código QR dinámico -->
            <div class="qr-code-container">
                <div id="qrcode" class="qr-code-overlay"></div>
                <p class="qr-label">Escanea para verificar</p>
            </div>
        </div>
    <?php else: ?>
        <div class="message-box">
            <h2 class="error-message">Error en la Verificación</h2>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($certificate): ?>
    <!-- Biblioteca QRCode.js desde CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Determinar el tamaño del QR según el ancho de la pantalla
        var isMobile = window.innerWidth <= 768;
        var qrSize = isMobile ? 45 : 80;
        
        // Generar el código QR con la URL actual de verificación
        var qrcode = new QRCode(document.getElementById("qrcode"), {
            text: "<?php echo $currentUrl; ?>",
            width: qrSize,
            height: qrSize,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });
        
        // ========== AJUSTE DINÁMICO DEL NOMBRE ESTUDIANTE (MODIFICADO) ==========
        var nombreElement = document.getElementById('nombre-estudiante');
        var idElement = document.getElementById('id-estudiante');
        
        if (nombreElement) {
            var textoNombre = nombreElement.innerText;
            var longitudNombre = textoNombre.length;
            
            // Ajustar el tamaño de fuente según la longitud del nombre
            if (longitudNombre > 40) {
                nombreElement.style.fontSize = '1.5vw'; // Nombres muy largos
            } else if (longitudNombre > 30) {
                nombreElement.style.fontSize = '1.8vw'; // Nombres largos
            } else if (longitudNombre > 25) {
                nombreElement.style.fontSize = '2.0vw'; // Nombres medios-largos
            }
            // Si es menor a 25 caracteres, mantiene el tamaño original de 2.2vw
            
            // Ajustar posición del ID si el nombre ocupa más de una línea
            setTimeout(function() {
                var nombreHeight = nombreElement.offsetHeight;
                var containerHeight = nombreElement.parentElement.offsetHeight;
                
                // Si el nombre tiene altura mayor a lo esperado (más de una línea)
                if (nombreHeight > (containerHeight * 0.05)) {
                    var currentTop = parseFloat(idElement.style.top || '49%');
                    // Ajustar el ID hacia abajo proporcionalmente
                    idElement.style.top = (currentTop + 2) + '%';
                }
            }, 100);
        }
        // ========== FIN DEL AJUSTE DEL NOMBRE ==========
        
        // Ajuste dinámico del tamaño de fuente del concepto
        var conceptoElement = document.getElementById('concepto-text');
        if (conceptoElement) {
            var textoConcepto = conceptoElement.innerText;
            var longitudTexto = textoConcepto.length;
            
            // Ajustar el tamaño de fuente según la longitud del texto
            if (longitudTexto > 150) {
                conceptoElement.style.fontSize = '0.9vw';
            } else if (longitudTexto > 100) {
                conceptoElement.style.fontSize = '1.1vw';
            } else if (longitudTexto > 60) {
                conceptoElement.style.fontSize = '1.2vw';
            }
            // Si es menor a 60 caracteres, mantiene el tamaño original de 1.4vw
        }
    });
    </script>
    <?php endif; ?>

</body>
</html>