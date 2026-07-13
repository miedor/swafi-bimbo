<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Laravel Cloud puede inyectar FILESYSTEM_DISK cuando un bucket de Object
    | Storage se adjunta al ambiente. En desarrollo local puede permanecer
    | como "local".
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Disco documental de SWAFI
    |--------------------------------------------------------------------------
    |
    | Todos los avatares, PDF, XML y evidencias nuevas se guardan en este disco.
    | En producción se recomienda SWAFI_STORAGE_DISK=s3 y un bucket privado.
    | Los registros históricos sin metadata de disco se interpretan como local.
    |
    */

    'swafi_disk' => env('SWAFI_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),
    'swafi_legacy_disk' => env('SWAFI_LEGACY_STORAGE_DISK', 'local'),
    'swafi_allow_local_in_production' => (bool) env('SWAFI_ALLOW_LOCAL_IN_PRODUCTION', false),
    'swafi_audit_scheduled' => (bool) env('SWAFI_STORAGE_AUDIT_SCHEDULED', true),
    'swafi_audit_time' => env('SWAFI_STORAGE_AUDIT_TIME', '02:30'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true,
            'report' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
