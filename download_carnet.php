<?php
// Incluye las bibliotecas y el archivo de configuración necesarios
require_once "api/config.php";
require_once "vendor/autoload.php";

// Valida la entrada del usuario
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de carnet no válido.");
}

$id = $_GET['id'];

try {
    // Obtiene los datos del carnet en una sola consulta
    $sql = "
    SELECT 
        id,
        nombre_completo,
        cedula_dni,
        cargo_rol,
        departamento,
        fecha_ingreso,
        fecha_vencimiento,
        titulo_academico,
        afiliacion,
        numero_expediente,
        fecha_admision,
        orcid,
        tipo_membresia,
        foto_ruta
    FROM 
        carnets
    WHERE 
        id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $carnet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$carnet) {
        die("Carnet no encontrado.");
    }
    
    // --- Configuración de TCPDF para las dimensiones del carnet (700x450 px) ---
    // 700 px = 185.21 mm a 96 DPI
    // 450 px = 119.06 mm a 96 DPI
    $pdf = new TCPDF('L', 'mm', array(185.21, 119.06), true, 'UTF-8', false);

    // Configurar el PDF (metadatos y estilos)
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('RELATIC');
    $pdf->SetTitle('Carnet ' . $carnet['nombre_completo']);
    $pdf->SetSubject('Carnet de Identificación');
    $pdf->SetKeywords('Carnet, Identificación, PHP, PDF, RELATIC');

    // Eliminar cabecera y pie de página por defecto
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Configurar márgenes a 0
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false, 0);

    // Añadir la imagen de fondo como plantilla (ajustada a 700x450px)
    $bg_image_path = 'assets/carnets/carnet.png';
    $pdf->AddPage();
    $pdf->Image($bg_image_path, 0, 0, 185.21, 119.06, '', '', '', false, 300, '', false, false, 0);
    
    // --- Añadir los datos del carnet sobre la imagen (posiciones exactas del CSS) ---
    
    // FOTO DE PERFIL (círculo en la parte superior derecha)
    // CSS: top: 15px, right: 75px, width: 240px, height: 240px, border-radius: 50%
    // Convertido: top=4mm, right=20mm, width=63.5mm, height=63.5mm
    $photo_path = $carnet['foto_ruta'];
    if (!empty($photo_path) && file_exists($photo_path)) {
        // Posición: x = 185.21 - 20 - 63.5 = 101.71mm, y = 4mm
        $center_x = 101.71 + 31.75; // Centro X del círculo
        $center_y = 4 + 31.75; // Centro Y del círculo
        $radius = 31.75; // Radio del círculo (63.5/2)
        
        // Crear máscara circular
        $pdf->StartTransform();
        
        // Dibujar círculo como máscara de recorte
        $pdf->Circle($center_x, $center_y, $radius, 0, 360, 'CNZ');
        
        // Aplicar la imagen dentro del área circular
        $pdf->Image($photo_path, 101.71, 4, 63.5, 63.5, '', '', '', false, 300, '', false, false, 0);
        
        $pdf->StopTransform();
        
        // Opcional: Agregar borde circular blanco como en el CSS
        $pdf->SetDrawColor(255, 255, 255); // Borde blanco
        $pdf->SetLineWidth(1.32); // 5px convertido a mm (5 * 0.264583)
        $pdf->Circle($center_x, $center_y, $radius, 0, 360, 'D');
    }
    
    // NOMBRE COMPLETO (parte inferior derecha, grande)
    // CSS: bottom: 150px, right: 125px, font-size: 28px, font-weight: bold, color: #1a365d
    // Posición: y = 119.06 - 39.69 = 79.37mm, x = 185.21 - 33.07 = 152.14mm (ancho máximo 79.38mm)
    $pdf->SetFont('helvetica', 'B', 24); // 28px ≈ 24pt en PDF
    $pdf->SetTextColor(26, 54, 93); // #1a365d
    $pdf->SetXY(72.76, 79.37); // Ajustado para alineación derecha
    $pdf->Cell(79.38, 10, htmlspecialchars($carnet['nombre_completo']), 0, 1, 'R');

    // TÍTULO ACADÉMICO (debajo del nombre)
    // CSS: bottom: 115px, right: 105px, font-size: 18px, font-weight: 600, color: #1a365d
    // Posición: y = 119.06 - 30.43 = 88.63mm, x = 185.21 - 27.78 = 157.43mm (ancho máximo 79.38mm)
    $pdf->SetFont('helvetica', 'B', 16); // 18px ≈ 16pt, weight 600 = bold
    $pdf->SetTextColor(26, 54, 93); // #1a365d
    $pdf->SetXY(78.05, 88.63);
    $pdf->Cell(79.38, 8, htmlspecialchars($carnet['titulo_academico']), 0, 1, 'R');
    
    // CARGO/ROL (parte superior izquierda, debajo del título del carnet)
    // CSS: top: 200px, left: 135px, font-size: 22px, font-weight: bold, color: #1a365d
    // Posición: y = 52.92mm, x = 35.72mm (ancho máximo 79.38mm)
    $pdf->SetFont('helvetica', 'B', 20); // 22px ≈ 20pt
    $pdf->SetTextColor(26, 54, 93); // #1a365d
    $pdf->SetXY(35.72, 52.92);
    $pdf->Cell(79.38, 8, htmlspecialchars($carnet['cargo_rol']), 0, 1, 'L');

    // FECHA DE VENCIMIENTO (debajo del cargo)
    // CSS: top: 225px, left: 145px, font-size: 16px, color: #1a365d
    // Posición: y = 59.53mm, x = 38.37mm
    $pdf->SetFont('helvetica', '', 14); // 16px ≈ 14pt
    $pdf->SetTextColor(26, 54, 93); // #1a365d
    $pdf->SetXY(38.37, 59.53);
    $pdf->Cell(60, 6, htmlspecialchars($carnet['fecha_vencimiento']), 0, 1, 'L');
    
    // AFILIACIÓN (lado izquierdo, posición media)
    // CSS: top: 250px, left: 50px, font-size: 14px, color: #1a365d
    // Posición: y = 66.15mm, x = 13.23mm (ancho máximo 52.92mm)
    $pdf->SetFont('helvetica', '', 12); // 14px ≈ 12pt
    $pdf->SetTextColor(26, 54, 93); // #1a365d
    $pdf->SetXY(13.23, 66.15);
    $pdf->Cell(52.92, 5, htmlspecialchars($carnet['afiliacion']), 0, 1, 'L');

    // ORCID (debajo de afiliación)
    // CSS: top: 280px, left: 50px, font-size: 14px, color: #1a365d
    // Posición: y = 74.08mm, x = 13.23mm (ancho máximo 52.92mm)
    $pdf->SetFont('helvetica', '', 12); // 14px ≈ 12pt
    $pdf->SetTextColor(26, 54, 93); // #1a365d
    $pdf->SetXY(13.23, 74.08);
    $pdf->Cell(52.92, 5, htmlspecialchars($carnet['orcid']), 0, 1, 'L');

    // QR CODE (esquina inferior izquierda)
    // CSS: bottom: 20px, left: 20px, width: 60px, height: 60px
    // Posición: y = 119.06 - 5.29 - 15.88 = 97.89mm, x = 5.29mm, tamaño = 15.88x15.88mm
    $qr_code_path = 'api/qrcodes/carnets/' . $carnet['id'] . '.png';
    if (file_exists($qr_code_path)) {
        $pdf->Image($qr_code_path, 5.29, 97.89, 15.88, 15.88);
    }

    // ID DEL CARNET (esquina inferior derecha, pequeño)
    // CSS: bottom: 20px, right: 20px, font-size: 10px, color: #666
    // Posición: y = 119.06 - 5.29 = 113.77mm, x = 185.21 - 5.29 = 179.92mm (ancho 30mm hacia la izquierda)
    $pdf->SetFont('helvetica', '', 8); // 10px ≈ 8pt
    $pdf->SetTextColor(102, 102, 102); // #666
    $pdf->SetXY(149.92, 113.77);
    $pdf->Cell(30, 5, 'ID: ' . htmlspecialchars($carnet['id']), 0, 1, 'R');
    
    // Enviar el PDF al navegador con la cabecera 'D' para forzar la descarga
    $pdf->Output('Carnet_' . $carnet['nombre_completo'] . '_' . $carnet['id'] . '.pdf', 'D');

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
} catch (Exception $e) {
    die("Error al generar el PDF: " . $e->getMessage());
}
?>