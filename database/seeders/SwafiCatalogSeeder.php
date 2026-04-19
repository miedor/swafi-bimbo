<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SwafiCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->insert([
            ['nombre' => 'Administrador', 'descripcion' => 'Control total del sistema', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Capturista', 'descripcion' => 'Alta y edición de expedientes', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Auditor', 'descripcion' => 'Consulta y revisión', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Consultor', 'descripcion' => 'Solo consulta', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Supervisor', 'descripcion' => 'Validación operativa y seguimiento', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('permissions')->insert([
            ['clave' => 'expedientes.ver', 'descripcion' => 'Consultar expedientes', 'created_at' => now(), 'updated_at' => now()],
            ['clave' => 'expedientes.crear', 'descripcion' => 'Crear expedientes', 'created_at' => now(), 'updated_at' => now()],
            ['clave' => 'expedientes.editar', 'descripcion' => 'Editar expedientes', 'created_at' => now(), 'updated_at' => now()],
            ['clave' => 'expedientes.eliminar', 'descripcion' => 'Eliminar expedientes', 'created_at' => now(), 'updated_at' => now()],
            ['clave' => 'reportes.exportar', 'descripcion' => 'Exportar reportes', 'created_at' => now(), 'updated_at' => now()],
            ['clave' => 'catalogos.administrar', 'descripcion' => 'Administrar catálogos', 'created_at' => now(), 'updated_at' => now()],
            ['clave' => 'seguridad.administrar', 'descripcion' => 'Administrar seguridad y accesos', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('tipos_activo')->insert([
            ['clave' => 'EQP', 'descripcion' => 'Equipo industrial', 'vida_util_meses' => 120, 'estatus' => 'activo', 'created_at' => now(), 'updated_at' => now()],
            ['clave' => 'VEH', 'descripcion' => 'Vehículo', 'vida_util_meses' => 60, 'estatus' => 'activo', 'created_at' => now(), 'updated_at' => now()],
            ['clave' => 'MOB', 'descripcion' => 'Mobiliario', 'vida_util_meses' => 120, 'estatus' => 'activo', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
