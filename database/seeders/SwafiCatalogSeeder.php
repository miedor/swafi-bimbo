<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SwafiCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

        $roles = [
            ['nombre' => 'Administrador SWAFI', 'descripcion' => 'Administración general, seguridad, catálogos y bitácora.'],
            ['nombre' => 'Usuario Captura', 'descripcion' => 'Registro individual y masivo de expedientes de activo fijo.'],
            ['nombre' => 'Usuario Consulta / Auditoría', 'descripcion' => 'Consulta, reportes, exportación y revisión de trazabilidad.'],
            ['nombre' => 'Usuario Planta / Inventarios', 'descripcion' => 'Consulta y seguimiento de ubicación física e inventarios.'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['nombre' => $role['nombre']],
                [
                    'descripcion' => $role['descripcion'],
                    'activo' => 1,
                    'es_sistema' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Permisos
        |--------------------------------------------------------------------------
        */

        $catalogReadPermissions = [
            ['clave' => 'catalogos.proveedores.ver', 'descripcion' => 'Consultar el catálogo de proveedores.'],
            ['clave' => 'catalogos.plantas.ver', 'descripcion' => 'Consultar el catálogo de plantas.'],
            ['clave' => 'catalogos.centros_costo.ver', 'descripcion' => 'Consultar el catálogo de centros de costo.'],
            ['clave' => 'catalogos.categorias_activo.ver', 'descripcion' => 'Consultar el catálogo de categorías de activo.'],
            ['clave' => 'catalogos.tipos_activo.ver', 'descripcion' => 'Consultar el catálogo de tipos de activo.'],
            ['clave' => 'catalogos.estatus_documentales.ver', 'descripcion' => 'Consultar el catálogo de estatus documentales.'],
            ['clave' => 'catalogos.estatus_operativos.ver', 'descripcion' => 'Consultar el catálogo de estatus operativos.'],
            ['clave' => 'catalogos.areas.ver', 'descripcion' => 'Consultar el catálogo de áreas.'],
            ['clave' => 'catalogos.ubicaciones.ver', 'descripcion' => 'Consultar el catálogo de ubicaciones.'],
            ['clave' => 'catalogos.responsables.ver', 'descripcion' => 'Consultar el catálogo de responsables.'],
        ];

        $permissions = array_merge([
            ['clave' => 'dashboard.ver', 'descripcion' => 'Visualizar dashboard principal.'],
            ['clave' => 'expedientes.ver', 'descripcion' => 'Consultar expedientes.'],
            ['clave' => 'expedientes.crear', 'descripcion' => 'Crear expedientes.'],
            ['clave' => 'expedientes.editar', 'descripcion' => 'Editar expedientes.'],
            ['clave' => 'expedientes.eliminar', 'descripcion' => 'Eliminar expedientes.'],
            ['clave' => 'documentos.cargar', 'descripcion' => 'Registrar documentos PDF/XML.'],
            ['clave' => 'documentos.eliminar', 'descripcion' => 'Dar de baja lógicamente documentos del expediente. Permiso exclusivo del Administrador SWAFI.'],
            ['clave' => 'valores.administrar', 'descripcion' => 'Administrar valores fiscales y financieros.'],
            ['clave' => 'ubicaciones.administrar', 'descripcion' => 'Administrar ubicación física e inventarios.'],
            ['clave' => 'reportes.exportar', 'descripcion' => 'Exportar consultas y reportes.'],
            ['clave' => 'catalogos.ver', 'descripcion' => 'Consultar catálogos base.'],
            ['clave' => 'catalogos.administrar', 'descripcion' => 'Administrar catálogos base.'],
            ['clave' => 'seguridad.administrar', 'descripcion' => 'Administrar usuarios, roles y permisos.'],
            ['clave' => 'bitacora.ver', 'descripcion' => 'Consultar bitácora de auditoría.'],
        ], $catalogReadPermissions);

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['clave' => $permission['clave']],
                [
                    'descripcion' => $permission['descripcion'],
                    'activo' => 1,
                    'es_sistema' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Relación rol - permiso
        |--------------------------------------------------------------------------
        */

        $adminRoleId = DB::table('roles')->where('nombre', 'Administrador SWAFI')->value('id');
        $capturaRoleId = DB::table('roles')->where('nombre', 'Usuario Captura')->value('id');
        $consultaRoleId = DB::table('roles')->where('nombre', 'Usuario Consulta / Auditoría')->value('id');
        $plantaRoleId = DB::table('roles')->where('nombre', 'Usuario Planta / Inventarios')->value('id');

        $allPermissionIds = DB::table('permissions')->where('activo', 1)->pluck('id');

        foreach ($allPermissionIds as $permissionId) {
            DB::table('permission_role')->updateOrInsert([
                'role_id' => $adminRoleId,
                'permission_id' => $permissionId,
            ]);
        }

        $catalogReadPermissionKeys = array_column($catalogReadPermissions, 'clave');

        $capturaPermisos = array_merge([
            'dashboard.ver',
            'expedientes.ver',
            'expedientes.crear',
            'expedientes.editar',
            'documentos.cargar',
            'valores.administrar',
            'catalogos.ver',
        ], $catalogReadPermissionKeys);

        $consultaPermisos = array_merge([
            'dashboard.ver',
            'expedientes.ver',
            'reportes.exportar',
            'bitacora.ver',
            'catalogos.ver',
        ], $catalogReadPermissionKeys);

        $plantaPermisos = array_merge([
            'dashboard.ver',
            'expedientes.ver',
            'ubicaciones.administrar',
            'catalogos.ver',
        ], $catalogReadPermissionKeys);

        $this->attachPermissions($capturaRoleId, $capturaPermisos);
        $this->attachPermissions($consultaRoleId, $consultaPermisos);
        $this->attachPermissions($plantaRoleId, $plantaPermisos);

        /*
        |--------------------------------------------------------------------------
        | Proveedores
        |--------------------------------------------------------------------------
        */

        $proveedores = [
            ['rfc' => 'ACM010101ABC', 'nombre' => 'ACME Industrial SA de CV', 'correo' => 'contacto@acmeindustrial.local', 'telefono' => '5550001001'],
            ['rfc' => 'EDC020202DEF', 'nombre' => 'Equipos del Centro SA de CV', 'correo' => 'ventas@equiposcentro.local', 'telefono' => '5550001002'],
            ['rfc' => 'RDT030303GHI', 'nombre' => 'Refacciones Delta SA de CV', 'correo' => 'servicio@refaccionesdelta.local', 'telefono' => '5550001003'],
            ['rfc' => 'SIM040404JKL', 'nombre' => 'Suministros Industriales MX SA de CV', 'correo' => 'atencion@suministrosmx.local', 'telefono' => '5550001004'],
        ];

        foreach ($proveedores as $proveedor) {
            DB::table('proveedores')->updateOrInsert(
                ['rfc' => $proveedor['rfc']],
                [
                    'nombre' => $proveedor['nombre'],
                    'correo' => $proveedor['correo'],
                    'telefono' => $proveedor['telefono'],
                    'estatus' => 'activo',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Centros de costo
        |--------------------------------------------------------------------------
        */

        $centrosCosto = [
            ['clave' => 'CC-ADM-100', 'descripcion' => 'Administración y Control'],
            ['clave' => 'CC-PLA-200', 'descripcion' => 'Planta Santa María'],
            ['clave' => 'CC-MAN-300', 'descripcion' => 'Mantenimiento e Inventarios'],
            ['clave' => 'CC-PRO-400', 'descripcion' => 'Producción'],
        ];

        foreach ($centrosCosto as $centro) {
            DB::table('centros_costo')->updateOrInsert(
                ['clave' => $centro['clave']],
                [
                    'descripcion' => $centro['descripcion'],
                    'estatus' => 'activo',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Plantas
        |--------------------------------------------------------------------------
        */

        $plantas = [
            ['clave' => 'PLT-SM', 'nombre' => 'Planta Santa María', 'estado' => 'Ciudad de México'],
            ['clave' => 'PLT-TR', 'nombre' => 'Planta Tía Rosa', 'estado' => 'Ciudad de México'],
            ['clave' => 'PLT-BA', 'nombre' => 'Planta Barcel', 'estado' => 'Estado de México'],
        ];

        foreach ($plantas as $planta) {
            DB::table('plantas')->updateOrInsert(
                ['clave' => $planta['clave']],
                [
                    'nombre' => $planta['nombre'],
                    'estado' => $planta['estado'],
                    'pais' => 'México',
                    'estatus' => 'activo',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Áreas
        |--------------------------------------------------------------------------
        */

        $plantaSantaMariaId = DB::table('plantas')->where('clave', 'PLT-SM')->value('id');
        $plantaTiaRosaId = DB::table('plantas')->where('clave', 'PLT-TR')->value('id');
        $plantaBarcelId = DB::table('plantas')->where('clave', 'PLT-BA')->value('id');

        $areas = [
            ['planta_id' => $plantaSantaMariaId, 'nombre' => 'Producción'],
            ['planta_id' => $plantaSantaMariaId, 'nombre' => 'Mantenimiento'],
            ['planta_id' => $plantaSantaMariaId, 'nombre' => 'Almacén'],
            ['planta_id' => $plantaTiaRosaId, 'nombre' => 'Línea de empaque'],
            ['planta_id' => $plantaBarcelId, 'nombre' => 'Servicios generales'],
        ];

        foreach ($areas as $area) {
            DB::table('areas')->updateOrInsert(
                [
                    'planta_id' => $area['planta_id'],
                    'nombre' => $area['nombre'],
                ],
                [
                    'estatus' => 'activo',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Categorías y tipos de activo
        |--------------------------------------------------------------------------
        */

        $categoriasActivo = [
            [
                'clave' => 'ME',
                'nombre' => 'Maquinaria y equipo',
                'descripcion' => 'Bienes productivos, equipos industriales y herramientas especializadas.',
            ],
            [
                'clave' => 'VEH',
                'nombre' => 'Vehículos',
                'descripcion' => 'Unidades de transporte y vehículos utilitarios.',
            ],
            [
                'clave' => 'MOB',
                'nombre' => 'Mobiliario',
                'descripcion' => 'Muebles y bienes de apoyo administrativo u operativo.',
            ],
            [
                'clave' => 'TEC',
                'nombre' => 'Tecnología',
                'descripcion' => 'Equipos de cómputo y otros bienes tecnológicos.',
            ],
        ];

        foreach ($categoriasActivo as $categoria) {
            DB::table('categorias_activo')->updateOrInsert(
                ['clave' => $categoria['clave']],
                [
                    'nombre' => $categoria['nombre'],
                    'descripcion' => $categoria['descripcion'],
                    'estatus' => 'activo',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $categoriaIds = DB::table('categorias_activo')
            ->whereIn('clave', collect($categoriasActivo)->pluck('clave')->all())
            ->pluck('id', 'clave');

        $tiposActivo = [
            ['categoria_clave' => 'ME', 'clave' => 'EQP', 'descripcion' => 'Equipo industrial', 'vida_util_meses' => 120],
            ['categoria_clave' => 'VEH', 'clave' => 'VEH', 'descripcion' => 'Vehículo utilitario', 'vida_util_meses' => 60],
            ['categoria_clave' => 'MOB', 'clave' => 'MOB', 'descripcion' => 'Mobiliario', 'vida_util_meses' => 120],
            ['categoria_clave' => 'TEC', 'clave' => 'CMP', 'descripcion' => 'Equipo de cómputo', 'vida_util_meses' => 36],
            ['categoria_clave' => 'ME', 'clave' => 'HER', 'descripcion' => 'Herramienta especializada', 'vida_util_meses' => 48],
        ];

        foreach ($tiposActivo as $tipo) {
            DB::table('tipos_activo')->updateOrInsert(
                ['clave' => $tipo['clave']],
                [
                    'categoria_activo_id' => $categoriaIds[$tipo['categoria_clave']] ?? null,
                    'descripcion' => $tipo['descripcion'],
                    'vida_util_meses' => $tipo['vida_util_meses'],
                    'estatus' => 'activo',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Estatus documentales y operativos
        |--------------------------------------------------------------------------
        */

        $estatusDocumentales = [
            ['clave' => 'completo', 'nombre' => 'Completo', 'descripcion' => 'El expediente cuenta con la documentación base requerida.', 'orden' => 10],
            ['clave' => 'incompleto', 'nombre' => 'Incompleto', 'descripcion' => 'El expediente aún no cuenta con todos los documentos requeridos.', 'orden' => 20],
            ['clave' => 'observado', 'nombre' => 'Observado', 'descripcion' => 'El expediente presenta una inconsistencia o requiere seguimiento.', 'orden' => 30],
        ];

        foreach ($estatusDocumentales as $estatusDocumental) {
            DB::table('estatus_documentales')->updateOrInsert(
                ['clave' => $estatusDocumental['clave']],
                [
                    'nombre' => $estatusDocumental['nombre'],
                    'descripcion' => $estatusDocumental['descripcion'],
                    'orden' => $estatusDocumental['orden'],
                    'es_sistema' => true,
                    'estatus' => 'activo',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $estatusOperativos = [
            ['clave' => 'en_operacion', 'nombre' => 'En operación', 'descripcion' => 'El activo se encuentra disponible en su ubicación operativa.', 'orden' => 10],
            ['clave' => 'traslado', 'nombre' => 'Traslado', 'descripcion' => 'El activo se encuentra en proceso de traslado o reubicación controlada.', 'orden' => 20],
            ['clave' => 'baja', 'nombre' => 'Baja', 'descripcion' => 'El activo ya no se encuentra disponible para la operación ordinaria.', 'orden' => 30],
        ];

        foreach ($estatusOperativos as $estatusOperativo) {
            DB::table('estatus_operativos')->updateOrInsert(
                ['clave' => $estatusOperativo['clave']],
                [
                    'nombre' => $estatusOperativo['nombre'],
                    'descripcion' => $estatusOperativo['descripcion'],
                    'orden' => $estatusOperativo['orden'],
                    'es_sistema' => true,
                    'estatus' => 'activo',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Responsables
        |--------------------------------------------------------------------------
        */

        $responsables = [
            ['nombre' => 'Jorge Méndez', 'correo' => 'jorge.mendez@bimbo.local', 'telefono' => '5550002001'],
            ['nombre' => 'María Ponce', 'correo' => 'maria.ponce@bimbo.local', 'telefono' => '5550002002'],
            ['nombre' => 'Carlos Hernández', 'correo' => 'carlos.hernandez@bimbo.local', 'telefono' => '5550002003'],
            ['nombre' => 'Laura Torres', 'correo' => 'laura.torres@bimbo.local', 'telefono' => '5550002004'],
        ];

        foreach ($responsables as $responsable) {
            DB::table('responsables')->updateOrInsert(
                ['correo' => $responsable['correo']],
                [
                    'nombre' => $responsable['nombre'],
                    'telefono' => $responsable['telefono'],
                    'estatus' => 'activo',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Ubicaciones
        |--------------------------------------------------------------------------
        */

        $areaProduccionId = DB::table('areas')->where('planta_id', $plantaSantaMariaId)->where('nombre', 'Producción')->value('id');
        $areaMantenimientoId = DB::table('areas')->where('planta_id', $plantaSantaMariaId)->where('nombre', 'Mantenimiento')->value('id');
        $areaAlmacenId = DB::table('areas')->where('planta_id', $plantaSantaMariaId)->where('nombre', 'Almacén')->value('id');
        $areaEmpaqueId = DB::table('areas')->where('planta_id', $plantaTiaRosaId)->where('nombre', 'Línea de empaque')->value('id');

        $ubicaciones = [
            [
                'codigo_interno' => 'UBI-SM-PRO-L3-PB',
                'planta_id' => $plantaSantaMariaId,
                'area_id' => $areaProduccionId,
                'edificio' => 'Edificio B',
                'piso' => 'Piso 1',
                'pasillo' => 'Pasillo B',
                'descripcion' => 'Línea 3 / Pasillo B',
            ],
            [
                'codigo_interno' => 'UBI-SM-MAN-TALLER',
                'planta_id' => $plantaSantaMariaId,
                'area_id' => $areaMantenimientoId,
                'edificio' => 'Taller de mantenimiento',
                'piso' => 'Planta baja',
                'pasillo' => 'Zona técnica',
                'descripcion' => 'Taller de mantenimiento planta',
            ],
            [
                'codigo_interno' => 'UBI-SM-ALM-TEMP',
                'planta_id' => $plantaSantaMariaId,
                'area_id' => $areaAlmacenId,
                'edificio' => 'Almacén',
                'piso' => 'Planta baja',
                'pasillo' => 'Temporal',
                'descripcion' => 'Almacén temporal',
            ],
            [
                'codigo_interno' => 'UBI-TR-EMP-L1',
                'planta_id' => $plantaTiaRosaId,
                'area_id' => $areaEmpaqueId,
                'edificio' => 'Nave empaque',
                'piso' => 'Piso 1',
                'pasillo' => 'Línea 1',
                'descripcion' => 'Empaque línea 1',
            ],
        ];

        foreach ($ubicaciones as $ubicacion) {
            DB::table('ubicaciones')->updateOrInsert(
                ['codigo_interno' => $ubicacion['codigo_interno']],
                [
                    'planta_id' => $ubicacion['planta_id'],
                    'area_id' => $ubicacion['area_id'],
                    'edificio' => $ubicacion['edificio'],
                    'piso' => $ubicacion['piso'],
                    'pasillo' => $ubicacion['pasillo'],
                    'descripcion' => $ubicacion['descripcion'],
                    'estatus' => 'activo',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function attachPermissions(?int $roleId, array $permissionKeys): void
    {
        if (!$roleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('clave', $permissionKeys)
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }
}
