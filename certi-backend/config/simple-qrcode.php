<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default QR Code Backend
    |--------------------------------------------------------------------------
    |
    | This option controls the default backend used for generating QR codes.
    | You may set this to any of the backends supported by the package.
    |
    | Supported: "gd", "imagick"
    |
    */

    'default' => 'gd',

    /*
    |--------------------------------------------------------------------------
    | QR Code Backends
    |--------------------------------------------------------------------------
    |
    | Here you may configure the backends used for generating QR codes.
    | Each backend may have its own configuration options.
    |
    */

    'backends' => [
        'gd' => [
            'driver' => 'gd',
        ],
        'imagick' => [
            'driver' => 'imagick',
        ],
    ],
];