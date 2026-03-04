<?php
// Incluye el archivo de configuración de la base de datos
require_once "api/config.php";

// Define un mensaje de error por defecto
$message = "Carnet no encontrado.";
$carnet = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = $_GET['id'];
    
    // Consulta para buscar el carnet por ID
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

    if ($carnet) {
        $message = "Carnet verificado exitosamente.";
    } else {
        $message = "El carnet con ID " . htmlspecialchars($id) . " no existe.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Carnet</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 20px;
        background-color: #f0f0f0;
    }
    .carnet-container {
        position: relative;
        width: 700px;
        height: 450px;
        margin: auto;
        background-image: url('assets/carnets/carnet.png');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        box-shadow: 0 0 20px rgba(0,0,0,0.2);
    }
    .text-overlay {
        position: absolute;
        color: #1a365d;
        font-weight: normal;
        text-align: left;
        line-height: 1.3;
        font-family: Arial, sans-serif;
    }
    
    /* === FOTO DE PERFIL === */
    .foto-carnet-overlay {
        position: absolute;
        top: 20px;
        right: 70px;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        object-fit: cover;
        border: 5px solid #fff;
        box-shadow: 0 0 20px rgba(0,0,0,0.3);
    }
    
    /* === NIVEL 1: INFORMACIÓN PRINCIPAL (Mayor jerarquía) === */
    
    /* Nombre completo - máxima jerarquía */
    .nombre-completo-overlay {
        top: 255px;
        right: 70px;
        font-size: 28px;
        font-weight: bold;
        color: #1a365d;
        text-align: right;
        max-width: 280px;
        line-height: 1.15;
    }
    
    /* Título académico - segundo nivel de jerarquía */
    .titulo-academico-overlay {
        top: 310px;
        right: 70px;
        font-size: 18px;
        font-weight: 600;
        color: #1a365d;
        text-align: right;
        max-width: 280px;
    }
    
    /* Cargo/Rol - tercer nivel de jerarquía */
    .cargo-rol-overlay {
        top: 338px;
        right: 70px;
        font-size: 16px;
        font-weight: 600;
        color: #1a365d;
        text-align: right;
        max-width: 280px;
    }
    
    /* === NIVEL 2: INFORMACIÓN INSTITUCIONAL (Media jerarquía) === */
    
    /* Afiliación - zona izquierda superior */
    .afiliacion-overlay {
        top: 185px;
        left: 40px;
        font-size: 15px;
        font-weight: 500;
        color: #1a365d;
        text-align: left;
        max-width: 280px;
        line-height: 1.3;
    }
    
    /* Departamento */
    .departamento-overlay {
        top: 215px;
        left: 40px;
        font-size: 14px;
        color: #1a365d;
        text-align: left;
        max-width: 280px;
    }
    
    /* ORCID */
    .orcid-overlay {
        top: 240px;
        left: 40px;
        font-size: 14px;
        color: #1a365d;
        text-align: left;
        max-width: 280px;
    }
    
    /* === NIVEL 3: DATOS ADMINISTRATIVOS (Menor jerarquía - zona inferior) === */
    
    /* Columna izquierda inferior */
    .cedula-dni-overlay {
        bottom: 100px;
        left: 40px;
        font-size: 13px;
        color: #1a365d;
        text-align: left;
        max-width: 210px;
    }
    
    .tipo-membresia-overlay {
        bottom: 80px;
        left: 40px;
        font-size: 13px;
        color: #1a365d;
        text-align: left;
        max-width: 210px;
    }
    
    .fecha-ingreso-overlay {
        bottom: 60px;
        left: 40px;
        font-size: 13px;
        color: #1a365d;
        text-align: left;
        max-width: 210px;
    }
    
    /* Columna central inferior */
    .numero-expediente-overlay {
        bottom: 100px;
        left: 270px;
        font-size: 13px;
        color: #1a365d;
        text-align: left;
        max-width: 210px;
    }
    
    .fecha-admision-overlay {
        bottom: 80px;
        left: 270px;
        font-size: 13px;
        color: #1a365d;
        text-align: left;
        max-width: 210px;
    }
    
    .fecha-vencimiento-overlay {
        bottom: 60px;
        left: 270px;
        font-size: 13px;
        color: #1a365d;
        text-align: left;
        max-width: 210px;
    }
    
    /* === ELEMENTOS AUXILIARES === */
    
    /* ID del carnet - esquina inferior derecha */
    .id-carnet-overlay {
        bottom: 15px;
        right: 15px;
        font-size: 11px;
        color: #888;
        font-weight: 500;
    }
    
    /* QR Code - esquina inferior izquierda */
    .qr-code-overlay {
        position: absolute;
        bottom: 15px;
        left: 15px;
        width: 65px;
        height: 65px;
    }
    
    .message-box {
        background-color: #fff;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        max-width: 700px;
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
    
    /* === MEDIA QUERIES PARA RESPONSIVIDAD === */
    @media screen and (max-width: 768px) {
        body {
            padding: 10px;
        }
        
        .carnet-container {
            width: 100%;
            max-width: 100vw;
            height: auto;
            aspect-ratio: 700/450;
        }
        
        .message-box {
            margin: 10px auto;
            max-width: 100%;
        }
        
        /* Foto de perfil */
        .foto-carnet-overlay {
            top: 4.44%;
            right: 10%;
            width: 31.43%;
            height: 48.89%;
        }
        
        /* Nivel 1: Información principal */
        .nombre-completo-overlay {
            top: 56.67%;
            right: 10%;
            font-size: 3.8vw;
            max-width: 40%;
        }
        
        .titulo-academico-overlay {
            top: 68.89%;
            right: 10%;
            font-size: 2.4vw;
            max-width: 40%;
        }
        
        .cargo-rol-overlay {
            top: 75.11%;
            right: 10%;
            font-size: 2.2vw;
            max-width: 40%;
        }
        
        /* Nivel 2: Información institucional */
        .afiliacion-overlay {
            top: 41.11%;
            left: 5.71%;
            font-size: 2.1vw;
            max-width: 40%;
        }
        
        .departamento-overlay {
            top: 47.78%;
            left: 5.71%;
            font-size: 2vw;
            max-width: 40%;
        }
        
        .orcid-overlay {
            top: 53.33%;
            left: 5.71%;
            font-size: 2vw;
            max-width: 40%;
        }
        
        /* Nivel 3: Datos administrativos */
        .cedula-dni-overlay {
            bottom: 22.22%;
            left: 5.71%;
            font-size: 1.85vw;
            max-width: 30%;
        }
        
        .tipo-membresia-overlay {
            bottom: 17.78%;
            left: 5.71%;
            font-size: 1.85vw;
            max-width: 30%;
        }
        
        .fecha-ingreso-overlay {
            bottom: 13.33%;
            left: 5.71%;
            font-size: 1.85vw;
            max-width: 30%;
        }
        
        .numero-expediente-overlay {
            bottom: 22.22%;
            left: 38.57%;
            font-size: 1.85vw;
            max-width: 30%;
        }
        
        .fecha-admision-overlay {
            bottom: 17.78%;
            left: 38.57%;
            font-size: 1.85vw;
            max-width: 30%;
        }
        
        .fecha-vencimiento-overlay {
            bottom: 13.33%;
            left: 38.57%;
            font-size: 1.85vw;
            max-width: 30%;
        }
        
        /* Elementos auxiliares */
        .id-carnet-overlay {
            bottom: 3.33%;
            right: 2.14%;
            font-size: 1.4vw;
        }
        
        .qr-code-overlay {
            bottom: 3.33%;
            left: 2.14%;
            width: 9.29%;
            height: 14.44%;
        }
    }
    
    @media screen and (max-width: 480px) {
        body {
            padding: 5px;
        }
        
        .message-box {
            padding: 10px;
            margin: 5px auto;
        }
        
        .download-button {
            padding: 8px 16px;
            font-size: 14px;
        }
        
        /* Ajustar fuentes para móviles */
        .nombre-completo-overlay {
            font-size: 5vw;
            line-height: 1.1;
        }
        
        .titulo-academico-overlay {
            font-size: 3.2vw;
        }
        
        .cargo-rol-overlay {
            font-size: 2.8vw;
        }
        
        .afiliacion-overlay {
            font-size: 2.5vw;
        }
        
        .departamento-overlay,
        .orcid-overlay {
            font-size: 2.3vw;
        }
        
        .cedula-dni-overlay,
        .tipo-membresia-overlay,
        .fecha-ingreso-overlay,
        .numero-expediente-overlay,
        .fecha-admision-overlay,
        .fecha-vencimiento-overlay {
            font-size: 2.2vw;
        }
        
        .id-carnet-overlay {
            font-size: 1.7vw;
        }
        
        .foto-carnet-overlay {
            border: 3px solid #fff;
        }
    }
    
    @media screen and (max-width: 320px) {
        .nombre-completo-overlay {
            font-size: 5.5vw;
        }
        
        .titulo-academico-overlay {
            font-size: 3.5vw;
        }
        
        .cargo-rol-overlay {
            font-size: 3.2vw;
        }
        
        .afiliacion-overlay {
            font-size: 2.9vw;
        }
        
        .departamento-overlay,
        .orcid-overlay {
            font-size: 2.6vw;
        }
        
        .cedula-dni-overlay,
        .tipo-membresia-overlay,
        .fecha-ingreso-overlay,
        .numero-expediente-overlay,
        .fecha-admision-overlay,
        .fecha-vencimiento-overlay {
            font-size: 2.5vw;
        }
        
        .id-carnet-overlay {
            font-size: 2vw;
        }
    }
    
    @media print {
        body {
            background-color: white;
            padding: 0;
        }
        .message-box {
            display: none;
        }
        .carnet-container {
            box-shadow: none;
            margin: 0;
            page-break-inside: avoid;
        }
    }
    </style>
</head>
<body>

    <?php if ($carnet): ?>
        <div class="message-box">
            <h2 class="success-message">Carnet Verificado</h2>
            <p>La siguiente información coincide con nuestros registros.</p>
            <a href="download_carnet.php?id=<?php echo htmlspecialchars($carnet['id']); ?>" class="download-button" target="_blank">
                Descargar Carnet
            </a>
        </div>
        <div class="carnet-container">
            <?php if (!empty($carnet['foto_ruta'])): ?>
                <img src="<?php echo htmlspecialchars($carnet['foto_ruta']); ?>" alt="Foto de Perfil" class="foto-carnet-overlay">
            <?php endif; ?>
            
            <!-- NIVEL 1: Información Principal -->
            <div class="text-overlay nombre-completo-overlay"><?php echo htmlspecialchars($carnet['nombre_completo']); ?></div>
            <div class="text-overlay titulo-academico-overlay"><?php echo htmlspecialchars($carnet['titulo_academico']); ?></div>
            <div class="text-overlay cargo-rol-overlay"><?php echo htmlspecialchars($carnet['cargo_rol']); ?></div>
            
            <!-- NIVEL 2: Información Institucional -->
            <div class="text-overlay afiliacion-overlay"><?php echo htmlspecialchars($carnet['afiliacion']); ?></div>
            <div class="text-overlay departamento-overlay">Departamento: <?php echo htmlspecialchars($carnet['departamento']); ?></div>
            <div class="text-overlay orcid-overlay">ORCID: <?php echo htmlspecialchars($carnet['orcid']); ?></div>
            
            <!-- NIVEL 3: Datos Administrativos -->
            <div class="text-overlay cedula-dni-overlay">Cédula: <?php echo htmlspecialchars($carnet['cedula_dni']); ?></div>
            <div class="text-overlay tipo-membresia-overlay">Membresía: <?php echo htmlspecialchars($carnet['tipo_membresia']); ?></div>
            <div class="text-overlay fecha-ingreso-overlay">Ingreso: <?php echo htmlspecialchars($carnet['fecha_ingreso']); ?></div>
            
            <div class="text-overlay numero-expediente-overlay">Exp: <?php echo htmlspecialchars($carnet['numero_expediente']); ?></div>
            <div class="text-overlay fecha-admision-overlay">Admisión: <?php echo htmlspecialchars($carnet['fecha_admision']); ?></div>
            <div class="text-overlay fecha-vencimiento-overlay">Vence: <?php echo htmlspecialchars($carnet['fecha_vencimiento']); ?></div>
            
            <!-- Elementos Auxiliares -->
            <div class="id-carnet-overlay">ID: <?php echo htmlspecialchars($carnet['id']); ?></div>
        </div>
    <?php else: ?>
        <div class="message-box">
            <h2 class="error-message">Error en la Verificación</h2>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

</body>
</html>