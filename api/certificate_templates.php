<?php
/**
 * Configuración de plantillas de certificados.
 * Cada versión define la imagen de fondo y si incluye documento adicional (plan de estudio).
 */
return [
    'v1' => [
        'background' => 'assets/certificates/certificate.png',
        'has_plan_estudio' => false,
    ],
    'v2' => [
        'background' => 'assets/certificates/certificate2.jpeg',
        'has_plan_estudio' => true,
        'plan_estudio_path' => 'assets/documents/plandeestudio.pdf',
    ],
];
