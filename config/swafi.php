<?php

return [

    'reportes_programados' => [
        /*
         * Configuración operativa para HU-082. La zona horaria se utiliza para
         * interpretar la hora capturada y las fechas se almacenan en UTC.
         */
        'habilitados' => (bool) env('SWAFI_SCHEDULED_REPORTS_ENABLED', true),
        'zona_horaria' => env('SWAFI_SCHEDULED_REPORTS_TIMEZONE', 'America/Mexico_City'),
        'cola' => env('SWAFI_SCHEDULED_REPORTS_QUEUE', 'reports'),
        'dominios_destinatarios_permitidos' => array_values(array_filter(array_map(
            static fn (string $domain): string => mb_strtolower(trim($domain)),
            explode(',', (string) env(
                'SWAFI_SCHEDULED_REPORTS_ALLOWED_DOMAINS',
                'bimbo.local,grupobimbo.com'
            ))
        ))),
        'limite_lote' => min(
            100,
            max(
                1,
                (int) env('SWAFI_SCHEDULED_REPORTS_BATCH_LIMIT', 50)
            )
        ),
    ],

    'observaciones_recordatorios' => [
        /*
         * HU-014: las observaciones nuevas requieren fecha compromiso. El
         * scheduler envía como máximo un intento diario cuando la fecha está
         * próxima o vencida y registra únicamente referencias técnicas seguras.
         */
        'habilitados' => (bool) env('SWAFI_OBSERVATION_REMINDERS_ENABLED', true),
        'zona_horaria' => env(
            'SWAFI_OBSERVATION_REMINDERS_TIMEZONE',
            'America/Mexico_City'
        ),
        'hora' => env('SWAFI_OBSERVATION_REMINDERS_TIME', '08:00'),
        'dias_anticipacion' => min(
            30,
            max(
                0,
                (int) env('SWAFI_OBSERVATION_DUE_SOON_DAYS', 2)
            )
        ),
        'limite_lote' => min(
            100,
            max(
                1,
                (int) env('SWAFI_OBSERVATION_REMINDERS_BATCH_LIMIT', 50)
            )
        ),
    ],

    'depreciacion' => [
        /*
         * HU-036/HU-037: los métodos disponibles se mantienen en configuración
         * porque cada opción requiere una fórmula implementada y probada. Los
         * resultados son referenciales y no sustituyen los cálculos oficiales
         * del ERP corporativo.
         */
        'metodos' => [
            'linea_recta' => [
                'label' => 'Línea recta',
                'description' => 'Distribuye la base depreciable de forma uniforme durante la vida útil.',
            ],
        ],
    ],

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



    'catalogos' => [
        /*
         * La carga inicial de catálogos se previsualiza antes de aplicar cambios.
         * Estos límites evitan lotes excesivos y previsualizaciones abandonadas
         * que permanezcan disponibles indefinidamente.
         */
        'importacion_max_filas' => min(
            20000,
            max(
                1,
                (int) env('SWAFI_CATALOG_IMPORT_MAX_ROWS', 5000)
            )
        ),
        'previsualizacion_horas' => min(
            72,
            max(
                1,
                (int) env('SWAFI_CATALOG_IMPORT_PREVIEW_HOURS', 24)
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

    'security_headers' => [
        /*
         * La política CSP utiliza nonces por solicitud. El modo report-only
         * permite observar incompatibilidades antes de hacerla obligatoria.
         */
        'csp_enabled' => env('SWAFI_CSP_ENABLED', true),
        'csp_report_only' => env('SWAFI_CSP_REPORT_ONLY', false),
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
