<?php
// Forzar UTF-8 - MEJORADO
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

require_once "api/config.php";
require_once "vendor/autoload.php";

// Verificar que la conexión PDO tenga UTF-8 configurado
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de carta no válido.");
}

$id = $_GET['id'];

// Función helper para salida segura de texto UTF-8
function safeOutputPDF($text, $fallback = '') {
    if (empty($text)) return $fallback;
    return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
}

// Función para generar el número de constancia dinámico (IGUAL QUE VERIFY_LETTER.PHP)
function generarNumeroConstancia($tipoConstancia, $id) {
    $anioActual = date('Y');
    $numeroConstancia = $tipoConstancia . '-00' . $id . '-' . $anioActual;
    return $numeroConstancia;
}

// Función para convertir el día a palabras y agregar el número entre paréntesis (IGUAL QUE VERIFY_LETTER.PHP)
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

// Función para convertir fecha YYYY-MM-DD a formato legible en español (IGUAL QUE VERIFY_LETTER.PHP)
function formatearFechaEspanol($fecha) {
    $meses = [
        '01' => 'enero', '02' => 'febrero', '03' => 'marzo',
        '04' => 'abril', '05' => 'mayo', '06' => 'junio',
        '07' => 'julio', '08' => 'agosto', '09' => 'septiembre',
        '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'
    ];
    
    $partes = explode('-', $fecha);
    $ano = $partes[0];
    $mes = $partes[1];
    $dia = ltrim($partes[2], '0');
    
    return $dia . ' de ' . $meses[$mes] . ' de ' . $ano;
}

try {
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
    
    if (!$letter) die("Carta no encontrada.");
    
    // Asegurar UTF-8 en todos los campos
    foreach ($letter as $key => $value) {
        if (is_string($value)) $letter[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    // Generar el número de constancia dinámico
    $numeroConstanciaGenerado = generarNumeroConstancia($letter['tipo_constancia'], $letter['id']);

    // Procesar lógica de firmantes (IGUAL QUE VERIFY_LETTER.PHP)
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

    $backgroundPath = 'assets/cartas/carta.jpg';
    if (!file_exists($backgroundPath)) die("Plantilla de carta no encontrada.");

    // Generar URL de verificación para el QR
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']);
    $verificationUrl = $protocol . "://" . $host . $path . "/verify_letter.php?id=" . $letter['id'];

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Relatic Panamá');
    $pdf->SetTitle('Carta ' . safeOutputPDF($letter['participante']));
    $pdf->SetSubject('Carta de Constancia o Aceptación');

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->AddPage();

    // Imagen de fondo
    $pdf->Image($backgroundPath, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);

    // Logo (si existe) - Posición superior izquierda
    if (!empty($letter['logo_url']) && file_exists($letter['logo_url'])) {
        $pdf->Image($letter['logo_url'], 10.5, 9, 31.5, 21);
    }

    // Encabezado: lugar y fecha (superior derecha) - Ajustado a 1.5cm del borde derecho
    $pdf->SetFont('times', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $fechaTexto = safeOutputPDF($letter['lugar']) . ', ' . safeOutputPDF($letter['fecha_expedicion']);
    $pdf->SetXY(120, 47.5);
    $pdf->Cell(75, 6, $fechaTexto, 0, 1, 'R');

    // TIPO DE CONSTANCIA (Ej: CONSTANCIA-001-2025)
    $pdf->SetFont('times', 'B', 15);
    $pdf->SetXY(15, 65);
    $pdf->Cell(180, 6, mb_strtoupper($numeroConstanciaGenerado, 'UTF-8'), 0, 1, 'C');

    // LÍNEA DE FIRMANTES (LA SUSCRITA / LOS SUSCRITOS + nombres y cargos + institución) - Márgenes 1.5cm
    $firmanteTexto = $articulo . ', ' . safeOutputPDF($textoFirmantes) . ' ' . safeOutputPDF($letter['institucion']);
    $pdf->SetFont('times', 'B', 9);
    $pdf->SetXY(15, 103.5);
    $pdf->MultiCell(180, 4.5, $firmanteTexto, 0, 'C', false, 1);

    // CERTIFICA(N) QUE:
    $pdf->SetFont('times', 'B', 10.8);
    $pdf->SetXY(15, 118.5);
    $pdf->Cell(180, 6, $verboCertifica . ' QUE:', 0, 1, 'C');

    // Determinar texto dinámico según el estado del evento
    $hoy = date('Y-m-d');
    $fechaInicio = $letter['fecha_inicio'];
    $fechaFinal = $letter['fecha_final'];

    // Convertir las fechas a formato español
    $fechaInicioFormateada = formatearFechaEspanol($fechaInicio);
    $fechaFinalFormateada = formatearFechaEspanol($fechaFinal);

    if ($fechaInicio > $hoy) {
        $texto_evento = "el cual comenzará el " . $fechaInicioFormateada . " y finalizará el " . $fechaFinalFormateada;
    } elseif ($fechaFinal < $hoy) {
        $texto_evento = "el cual se desarrolló desde el " . $fechaInicioFormateada . " hasta el " . $fechaFinalFormateada;
    } else {
        $texto_evento = "el cual se está desarrollando del " . $fechaInicioFormateada . " hasta el " . $fechaFinalFormateada;
    }

    // PÁRRAFO PRINCIPAL (replicando exactamente el formato de verify_letter.php) - Márgenes 1.5cm
    $pdf->SetFont('times', '', 9);
    $pdf->SetXY(15, 133);
    
    $participanteUpper = mb_strtoupper(safeOutputPDF($letter['participante']), 'UTF-8');
    $eventNameUpper = !empty($letter['event_name']) ? mb_strtoupper(safeOutputPDF($letter['event_name']), 'UTF-8') : '';
    
    $parrafo = $participanteUpper . ', con documento de identidad No. ' . 
               safeOutputPDF($letter['dni_cedula']) . ' ' . 
               safeOutputPDF($letter['inscripcion_texto']) . ' ' . 
               $eventNameUpper . ', ' . 
               $texto_evento . '.';
    
    $pdf->MultiCell(180, 4.5, $parrafo, 0, 'J', false, 1);

    // Información del correo - Márgenes 1.5cm
    $pdf->SetFont('times', '', 9);
    $pdf->SetXY(15, 148);
    $parrafoCorreo = 'Para acreditar la veracidad de este documento, realice su solicitud al correo: ' . 
                     safeOutputPDF($letter['correo']);
    $pdf->MultiCell(180, 4.5, $parrafoCorreo, 0, 'J', false, 1);

    // Texto de expedición - Márgenes 1.5cm
    $pdf->SetFont('times', '', 9);
    $pdf->SetXY(15, 157);
    $parrafoExpedicion = 'Se expide el presente documento a los ' . 
                         agregarDias($letter['fecha_expedicion']) . 
                         ', para los fines que estime conveniente.';
    $pdf->MultiCell(180, 4.5, $parrafoExpedicion, 0, 'J', false, 1);

    // "Atentamente," - Margen izquierdo 1.5cm
    $pdf->SetFont('times', '', 9);
    $pdf->SetXY(15, 169);
    $pdf->Cell(50, 6, 'Atentamente,', 0, 1, 'L');

    // Firma (si existe) - Alineada horizontalmente con la firma izquierda
    if (!empty($letter['signature_url']) && file_exists($letter['signature_url'])) {
        $pdf->Image($letter['signature_url'], 145, 205, 52.5, 21);
    }

    // ========== CÓDIGO QR ==========
    // Posición del QR: esquina inferior derecha
    // X: 210mm (ancho A4) - 6mm (margen derecho) - 25mm (ancho QR) = 179mm
    // Y: 297mm (alto A4) - 15mm (margen inferior) - 25mm (alto QR) - 8mm (espacio texto) = 249mm
    $qrSize = 25;
    $qrX = 179;
    $qrY = 235;
    
    // Dibujar rectángulo blanco de fondo con bordes redondeados
    $pdf->SetFillColor(255, 255, 255); // Blanco
    $pdf->SetLineStyle(array('width' => 0.3, 'color' => array(220, 220, 220))); // Borde gris claro
    $fondoX = $qrX - 2;
    $fondoY = $qrY - 2;
    $fondoWidth = $qrSize + 4;
    $fondoHeight = $qrSize + 10; // Incluye espacio para el texto
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
    
    // Texto debajo del QR
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(51, 51, 51); // Color #333
    $textoQrY = $qrY + $qrSize + 1;
    $pdf->SetXY($fondoX, $textoQrY);
    $pdf->Cell($fondoWidth, 4, 'Escanea para verificar', 0, 0, 'C');

    // Descargar PDF
    $filename = 'Carta_' . safeOutputPDF($letter['participante']) . '_' . $letter['id'] . '.pdf';
    $pdf->Output($filename, 'D');

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
} catch (Exception $e) {
    die("Error al generar el PDF: " . $e->getMessage());
}
?>