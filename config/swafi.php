<?php

return [
    'importaciones' => [
        /*
         * Ventana máxima para solicitar la reversión controlada de un lote
         * aplicado. El servicio vuelve a validar dependencias y estado actual,
         * por lo que encontrarse dentro del plazo no garantiza por sí solo la
         * reversión.
         */
        'reversion_horas' => min(
            168,
            max(
                1,
                (int) env('SWAFI_IMPORT_ROLLBACK_HOURS', 24)
            )
        ),
    ],


    'bitacora' => [
        /*
         * Límite máximo de filas por archivo para evitar exportaciones que
         * consuman memoria de forma excesiva en Laravel Cloud. Los filtros de
         * la consulta se aplican antes de validar este límite.
         */
        'limite_exportacion' => min(
            50000,
            max(
                100,
                (int) env('SWAFI_AUDIT_EXPORT_LIMIT', 10000)
            )
        ),
    ],

    'administrador_inicial' => [
        /*
         * Identidad no secreta utilizada únicamente por el comando
         * swafi:administrator:bootstrap. La contraseña nunca se incorpora a
         * config para evitar que quede persistida en la caché de configuración.
         */
        'nombre' => env('SWAFI_BOOTSTRAP_ADMIN_NAME', ''),
        'email' => env('SWAFI_BOOTSTRAP_ADMIN_EMAIL', ''),
        'usuario' => env('SWAFI_BOOTSTRAP_ADMIN_USER', ''),
    ],
];
