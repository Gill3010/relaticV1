<?php

// Incluye las bibliotecas y el archivo de configuración necesarios
require_once "api/config.php";
require_once "vendor/autoload.php";
$certificateTemplates = require "api/certificate_templates.php";

// Función para formatear fecha en estilo notarial (si la fecha es inválida o 0000-00-00, usa la fecha de hoy)
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

// Función para formatear fecha con día y mes en palabras (alineada con verify_certificate.php)
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
    $meses = [
        1 => "enero", 2 => "febrero", 3 => "marzo",
        4 => "abril", 5 => "mayo", 6 => "junio",
        7 => "julio", 8 => "agosto", 9 => "septiembre",
        10 => "octubre", 11 => "noviembre", 12 => "diciembre"
    ];
    $mes = $meses[(int)date("n", $timestamp)];
    return "{$dia} de {$mes}";
}

// Función para obtener solo el año (alineada con verify_certificate.php)
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

// Función para determinar el artículo definido según el género del texto
function obtenerArticuloDefinido($texto) {
    $texto = strtolower(trim($texto));
    
    $terminacionesFemeninas = ['a', 'ión', 'ad', 'ud', 'ez', 'ie', 'umbre', 'sis'];
    $palabrasFemeninas = [
        'capacitación','formación','certificación','especialización',
        'diplomatura','maestría','licenciatura','ingeniería',
        'conferencia','jornada','feria','exposición','muestra',
        'clase','sesión','charla','ponencia','presentación',
        'actividad','práctica','experiencia','oportunidad',
        'carrera','profesión','disciplina','materia','asignatura'
    ];
    $palabrasMasculinas = [
        'curso','taller','seminario','diplomado','programa',
        'entrenamiento','adiestramiento','aprendizaje',
        'congreso','simposio','foro','encuentro','evento',
        'workshop','bootcamp','masterclass','webinar',
        'proyecto','trabajo','estudio','análisis',
        'bachillerato','doctorado','posgrado','postgrado'
    ];

    $palabras = explode(' ', $texto);

    foreach ($palabras as $palabra) {
        if (in_array($palabra, $palabrasFemeninas)) return 'la';
        if (in_array($palabra, $palabrasMasculinas)) return 'el';
    }

    $palabraPrincipal = $palabras[0];
    foreach ($terminacionesFemeninas as $terminacion) {
        if (substr($palabraPrincipal, -strlen($terminacion)) === $terminacion) {
            return 'la';
        }
    }

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

// Validar entrada
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de certificado no válido.");
}

$id = $_GET['id'];
$requestDocument = isset($_GET['document']) && $_GET['document'] === 'plan_estudio';

try {
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

    if (!$certificate) {
        die("Certificado no encontrado.");
    }

    $templateVersion = !empty($certificate['template_version']) ? strtolower(trim($certificate['template_version'])) : 'v1';
    $templateConfig = $certificateTemplates[$templateVersion] ?? $certificateTemplates['v1'];
    $esV2 = ($templateVersion === 'v2') || !empty(trim($certificate['email'] ?? '')) || !empty(trim($certificate['orcid'] ?? ''));

    // Descarga del documento "Plan de estudio" (solo certificados v2)
    if ($requestDocument) {
        if (empty($templateConfig['has_plan_estudio']) || empty($templateConfig['plan_estudio_path'])) {
            http_response_code(403);
            die("Este certificado no incluye el documento solicitado.");
        }
        $docPath = $templateConfig['plan_estudio_path'];
        if (!is_file($docPath)) {
            http_response_code(404);
            die("Documento no encontrado.");
        }
        $nombreArchivo = basename($docPath);
        $ext = strtolower(pathinfo($docPath, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            // Incrustar el mismo código QR del certificado dentro de la plantilla del plan de estudios
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $path = rtrim(dirname($_SERVER['PHP_SELF']), '/');
            $qrScript = $esV2 ? 'verify_certificate_data.php' : 'verify_certificate.php';
            $verificationUrl = $protocol . "://" . $host . $path . "/" . $qrScript . "?id=" . $certificate['id'];

            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetAutoPageBreak(false, 0);

            $pageCount = $pdf->setSourceFile($docPath);

            for ($i = 1; $i <= $pageCount; $i++) {
                $tplIdx = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tplIdx);
                $orientation = isset($size['orientation']) ? $size['orientation'] : 'P';
                $format = [$size['width'], $size['height']];
                $pdf->AddPage($orientation, $format, false, false);
                $pdf->useTemplate($tplIdx);

                // Incrustar el QR (mismo que en certificado) solo en la primera página
                if ($i === 1) {
                    $pageW = $size['width'];
                    $pageH = $size['height'];
                    $qrSize = $pageW * 0.08;
                    // Posición: más arriba y ligeramente a la derecha, dentro del área de contenido
                    $qrX = $pageW * 0.98 - $qrSize - 25;
                    $qrY = $pageH * 0.98 - $qrSize - 30;

                    $pdf->SetFillColor(255, 255, 255);
                    $pdf->SetLineStyle(array('width' => 0.3, 'color' => array(220, 220, 220)));
                    $fondoX = $qrX - 2;
                    $fondoY = $qrY - 2;
                    $fondoWidth = $qrSize + 4;
                    $fondoHeight = $qrSize + 8;
                    $pdf->RoundedRect($fondoX, $fondoY, $fondoWidth, $fondoHeight, 2, '1111', 'DF');

                    $qrStyle = array(
                        'border' => 0,
                        'vpadding' => 'auto',
                        'hpadding' => 'auto',
                        'fgcolor' => array(0, 0, 0),
                        'bgcolor' => array(255, 255, 255),
                        'module_width' => 1,
                        'module_height' => 1
                    );
                    $pdf->write2DBarcode($verificationUrl, 'QRCODE,M', $qrX, $qrY, $qrSize, $qrSize, $qrStyle, 'N');

                    $pdf->SetFont('helvetica', '', 5);
                    $pdf->SetTextColor(51, 51, 51);
                    $textoQrY = $qrY + $qrSize + 1;
                    $pdf->SetXY($fondoX, $textoQrY);
                    $pdf->Cell($fondoWidth, 4, 'Escanea para verificar', 0, 0, 'C');
                }
            }

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
            $pdf->Output($nombreArchivo, 'D');
        } else {
            $mimeTypes = [
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];
            $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
            header('Content-Type: ' . $contentType);
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
            header('Content-Length: ' . filesize($docPath));
            readfile($docPath);
        }
        exit;
    }

    // Nombre del evento con artículo (sin duplicar si ya empieza por La/El/Los/Las)
    $nombreEventoCompleto = formatearNombreEventoConArticulo($certificate['event_name']);
    $articuloConcepto = obtenerArticuloDefinido($certificate['concepto']);

    $bg_image_path = $templateConfig['background'];

    // Generar URL de verificación para el QR: V2 → verify_certificate_data.php; V1 → verify_certificate.php
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $path = rtrim(dirname($_SERVER['PHP_SELF']), '/');
    $qrScript = $esV2 ? 'verify_certificate_data.php' : 'verify_certificate.php';
    $verificationUrl = $protocol . "://" . $host . $path . "/" . $qrScript . "?id=" . $certificate['id'];

    // Crear PDF (11 x 8.5 pulgadas)
    $pdf = new TCPDF('L', 'mm', [279.4, 215.9], true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false, 0);

    $pdf->AddPage();
    $pdf->Image($bg_image_path, 0, 0, 279.4, 215.9, '', '', '', false, 300, '', false, false, 0);

    // Logo - posición ajustada para coincidir con HTML (top: 10.5%, left: 17%)
    if (!empty($certificate['logo_url']) && file_exists($certificate['logo_url'])) {
        $logoX = 279.4 * 0.17; // 17% desde la izquierda
        $logoY = 215.9 * 0.105; // 10.5% desde arriba
        $logoWidth = 279.4 * 0.12; // 12% del ancho total
        $pdf->Image($certificate['logo_url'], $logoX, $logoY, $logoWidth, 0);
    }

    // Nombre del evento - posición ajustada (top: 21%, centrado)
    $pdf->SetFont('times', 'B', 22); // Times New Roman Bold, tamaño equivalente a 1.6vw
    $pdf->SetTextColor(0, 40, 90); // Color #00285a
    $eventoY = 215.9 * 0.21; // 21% desde arriba
    $pdf->SetXY(20, $eventoY);

    $textoEvento = mb_strtoupper($nombreEventoCompleto, 'UTF-8');
    
    // Ajustar texto largo con salto de línea automático (50% del ancho)
    $anchoEvento = 279.4 * 0.5; // 50% del ancho total
    $pdf->MultiCell(
        $anchoEvento,
        8, // Altura de línea
        $textoEvento,
        0,
        'C', // Centrado
        false,
        1,
        (279.4 - $anchoEvento) / 2, // Centrar horizontalmente
        $eventoY,
        true,
        0,
        false,
        true,
        0,
        'M'
    );

    // Convenio - posición ajustada (top: 34%, alineado con verify_certificate.php)
    $pdf->SetFont('times', 'I', 12); // Times Italic, tamaño equivalente a 0.9vw
    $pdf->SetTextColor(74, 85, 104); // Color #4a5568
    $convenioY = 215.9 * 0.34;
    $pdf->SetXY(20, $convenioY);
    $pdf->Cell(239.4, 8, 'Según convenio vigente No. 100-001 del 20 de octubre de 2025', 0, 1, 'C');

    // Texto "Otorgan a" - posición ajustada (top: 40%, alineado con verify_certificate.php)
    $pdf->SetFont('times', '', 16); // Times Normal, tamaño equivalente a 1.2vw
    $pdf->SetTextColor(74, 85, 104); // Color #4a5568
    $otorgadoY = 215.9 * 0.40;
    $pdf->SetXY(20, $otorgadoY);
    $pdf->Cell(239.4, 8, 'Otorgan a', 0, 1, 'C');

    // ========== AJUSTE DEL NOMBRE ESTUDIANTE (MODIFICADO) ==========
    // Nombre estudiante - posición ajustada (top: 44%) con manejo dinámico
    $nombreTexto = mb_strtoupper($certificate['nombre_estudiante'], 'UTF-8');
    $longitudNombre = mb_strlen($nombreTexto, 'UTF-8');

    // Calcular ancho del área segura (70% del ancho total)
    $anchoNombre = 279.4 * 0.70;
    $xNombre = (279.4 - $anchoNombre) / 2;

    // Ajustar tamaño de fuente según longitud
    if ($longitudNombre > 40) {
        $nombreFontSize = 20; // Nombres muy largos
    } elseif ($longitudNombre > 30) {
        $nombreFontSize = 24; // Nombres largos
    } elseif ($longitudNombre > 25) {
        $nombreFontSize = 27; // Nombres medios-largos
    } else {
        $nombreFontSize = 30; // Nombres cortos (tamaño original)
    }

    $pdf->SetFont('times', 'B', $nombreFontSize);
    $pdf->SetTextColor(0, 40, 90); // Color #00285a

    // Posición inicial
    $nombreY = 215.9 * 0.44;

    // Usar MultiCell para permitir salto de línea sin guiones
    $pdf->MultiCell(
        $anchoNombre,
        10, // Altura de línea
        $nombreTexto,
        0,
        'C', // Centrado
        false,
        1,
        $xNombre,
        $nombreY,
        true,
        0,
        false,
        true,
        0,
        'M'
    );

    // Capturar la posición Y actual después del nombre (para ajustar ID si es necesario)
    $nombreEndY = $pdf->GetY();

    // ID estudiante - ajustar posición según altura del nombre
    $idY = max($nombreEndY + 2, 215.9 * 0.49); // Mínimo 2mm de separación
    $pdf->SetFont('times', '', 16);
    $pdf->SetTextColor(74, 85, 104);
    $pdf->SetXY(20, $idY);
    $pdf->Cell(239.4, 8, 'ID: ' . $certificate['id_estudiante'], 0, 1, 'C');
    // ========== FIN DEL AJUSTE DEL NOMBRE ==========

    // Texto "por haber culminado..." con motivo personalizado - posición ajustada (top: 55%)
    $pdf->SetFont('times', '', 16); // Times Normal, tamaño equivalente a 1.2vw
    $pdf->SetTextColor(74, 85, 104); // Color #4a5568
    $culminadoY = 215.9 * 0.55;
    $pdf->SetXY(20, $culminadoY);
    
    // Usar la variable motivo si existe, sino mantener el texto original
    if (!empty($certificate['motivo'])) {
        $textoculminado = $certificate['motivo'];
    } else {
        $textoculminado = "por haber culminado satisfactoriamente los requisitos de " . $articuloConcepto;
    }
    
    $pdf->Cell(239.4, 8, $textoculminado, 0, 1, 'C');

    // Concepto - posición ajustada (top: 61%) con manejo multilínea y ajuste dinámico de tamaño (entre comillas como en la imagen)
    $conceptoTexto = mb_strtoupper($certificate['concepto'], 'UTF-8');
    $longitudTexto = mb_strlen($conceptoTexto, 'UTF-8');
    
    // Ajustar tamaño de fuente según longitud del texto (igual lógica que JavaScript)
    if ($longitudTexto > 150) {
        $fontSize = 12; // Equivalente a 0.9vw
    } elseif ($longitudTexto > 100) {
        $fontSize = 15; // Equivalente a 1.1vw
    } elseif ($longitudTexto > 60) {
        $fontSize = 16; // Equivalente a 1.2vw
    } else {
        $fontSize = 19; // Tamaño original equivalente a 1.4vw
    }
    
    $pdf->SetFont('times', 'B', $fontSize);
    $pdf->SetTextColor(45, 55, 72); // Color #2d3748
    $conceptoY = 215.9 * 0.61;
    
    $anchoConcepto = 279.4 * 0.76; // 76% del ancho (equivale al width: 76% del HTML)
    $xConcepto = (279.4 - $anchoConcepto) / 2; // Centrar horizontalmente
    
    $pdf->SetXY($xConcepto, $conceptoY);
    $pdf->MultiCell(
        $anchoConcepto,
        9, // Altura de línea (line-height: 1.3)
        $conceptoTexto,
        0,
        'C', // Centrado
        false,
        1,
        $xConcepto,
        $conceptoY,
        true,
        0,
        false,
        true,
        0,
        'M'
    );

    // Obtener posición Y actual después del concepto
    $currentY = $pdf->GetY();

    // Texto de duración y fechas - posición ajustada (top: 67%), formato alineado con verify_certificate.php
    $fechaInicioStr = formatearFechaCompleta($certificate['fecha_inicio']);
    $fechaFinStr = formatearFechaCompleta($certificate['fecha_fin']);
    $anioFin = obtenerAnio($certificate['fecha_fin']);
    $textoDetalles = "Realizado del {$fechaInicioStr} hasta el {$fechaFinStr} de {$anioFin} con una duración total de {$certificate['horas_academicas']}. En mérito a lo expuesto, y con el fin de acreditar su formación, se expide el presente diploma.";
    $pdf->SetFont('times', '', 12);
    $pdf->SetTextColor(74, 85, 104);
    $detallesY = max($currentY + 5, 215.9 * 0.67);
    
    // Usar MultiCell para manejar texto largo
    $anchoTexto = 239.4;
    $pdf->MultiCell(
        $anchoTexto,
        6,
        $textoDetalles,
        0,
        'C',
        false,
        1,
        20,
        $detallesY,
        true,
        0,
        false,
        true,
        0,
        'M'
    );
    $currentY = $pdf->GetY();

    // Fecha de emisión - posición fija para evitar superposición con firmas
    $pdf->SetFont('times', '', 12);
    $pdf->SetTextColor(113, 128, 150);
    $emisionY = 164; // Posición fija ajustada
    $pdf->SetXY(20, $emisionY);
    $pdf->Cell(239.4, 8, formatearFechaEmision($certificate['fecha_emision']), 0, 1, 'C');

    // Firma - posición ajustada (bottom: 8%, centrada)
    if (!empty($certificate['signature_url']) && file_exists($certificate['signature_url'])) {
        $firmaY = 215.9 * 0.83; // Posicionar las firmas en 83% desde arriba
        $firmaWidth = 279.4 * 0.20; // 20% del ancho total
        $firmaX = (279.4 - $firmaWidth) / 2; // Centrar horizontalmente
        $pdf->Image($certificate['signature_url'], $firmaX, $firmaY, $firmaWidth, 0);
    }

    // ========== CÓDIGO QR DINÁMICO CON TCPDF (AJUSTADO +10mm arriba, +12mm izquierda) ==========
    // Posición del QR: esquina inferior derecha - ajustado para mejor balance visual
    $qrSize = 279.4 * 0.08; // 8% del ancho total
    $qrX = 279.4 * 0.98 - $qrSize - 12; // right: 2% + 12mm hacia la izquierda (ajustado desde 18mm)
    $qrY = 215.9 * 0.98 - $qrSize - 18; // Ajustado +10mm (de -8 a -18)
    
    // Dibujar rectángulo blanco de fondo con bordes redondeados
    $pdf->SetFillColor(255, 255, 255); // Blanco
    $pdf->SetLineStyle(array('width' => 0.3, 'color' => array(220, 220, 220))); // Borde gris claro
    $fondoX = $qrX - 2;
    $fondoY = $qrY - 2;
    $fondoWidth = $qrSize + 4;
    $fondoHeight = $qrSize + 8; // Incluye espacio para el texto
    $pdf->RoundedRect($fondoX, $fondoY, $fondoWidth, $fondoHeight, 2, '1111', 'DF');
    
    // Configuración del estilo del QR
    $qrStyle = array(
        'border' => 0,
        'vpadding' => 'auto',
        'hpadding' => 'auto',
        'fgcolor' => array(0, 0, 0),
        'bgcolor' => array(255, 255, 255),
        'module_width' => 1,
        'module_height' => 1
    );
    
    // Generar el código QR con la URL de verificación
    $pdf->write2DBarcode($verificationUrl, 'QRCODE,M', $qrX, $qrY, $qrSize, $qrSize, $qrStyle, 'N');
    
    // Agregar texto "Escanea para verificar" debajo del QR
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(51, 51, 51); // Color #333
    $textoQrY = $qrY + $qrSize + 1; // Posición justo debajo del QR
    $pdf->SetXY($fondoX, $textoQrY);
    $pdf->Cell($fondoWidth, 4, 'Escanea para verificar', 0, 0, 'C');

    // Descargar PDF
    $pdf->Output('Certificado_' . $certificate['id'] . '.pdf', 'D');

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
} catch (Exception $e) {
    die("Error al generar el PDF: " . $e->getMessage());
}

?>