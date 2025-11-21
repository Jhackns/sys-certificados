<?php

return [
    // Formato de salida por defecto del QR (png, svg, eps)
    'writer' => 'png',

    // Forzar backend de imagen a GD para evitar dependencia de Imagick
    // Valores soportados: 'gd', 'imagick'
    'image_backend' => 'gd',

    // Tamaño por defecto en píxeles
    'size' => 300,

    // Margen alrededor del QR
    'margin' => 2,

    // Codificación
    'encoding' => 'UTF-8',

    // Nivel de corrección de errores (L, M, Q, H)
    'error_correction' => 'M',
];
