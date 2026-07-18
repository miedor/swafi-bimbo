<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SecurityIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $roles = collect($this->session()->get('swafi_roles', []))
            ->filter(fn ($role) => is_scalar($role))
            ->map(fn ($role) => mb_strtolower(trim((string) $role)));

        $permissions = collect($this->session()->get('swafi_permissions', []))
            ->filter(fn ($permission) => is_scalar($permission))
            ->map(fn ($permission) => trim((string) $permission));

        if ($roles->contains('administrador swafi')) {
            return true;
        }

        return $this->input('tab', 'usuarios') === 'bitacora'
            ? $permissions->contains('bitacora.ver')
            : $permissions->contains('seguridad.administrar');
    }

    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in(['usuarios', 'roles', 'bitacora'])],
            'export' => ['nullable', Rule::in(['csv', 'xlsx', 'pdf'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],

            'buscar' => ['nullable', 'string', 'max:120'],
            'rol_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'estatus' => ['nullable', Rule::in(['activo', 'inactivo', 'bloqueado'])],
            'editar_usuario' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'editar_rol' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'editar_permiso' => ['nullable', 'integer', Rule::exists('permissions', 'id')],

            'buscar_bitacora' => ['nullable', 'string', 'max:160'],
            'usuario_bitacora_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'numero_activo' => ['nullable', 'string', 'max:30'],
            'modulo' => ['nullable', 'string', 'max:80'],
            'accion' => ['nullable', 'string', 'max:40'],
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d'],
            'detalle_bitacora' => [
                'nullable',
                'integer',
                Rule::exists('bitacora_auditoria', 'id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'tab.in' => 'La sección de seguridad seleccionada no es válida.',
            'export.in' => 'El formato de exportación solicitado no es válido.',
            'per_page.in' => 'Selecciona 10, 25 o 50 registros por página.',
            'rol_id.exists' => 'El rol seleccionado ya no existe.',
            'estatus.in' => 'El estatus seleccionado no es válido.',
            'usuario_bitacora_id.exists' => 'La persona usuaria seleccionada ya no existe.',
            'fecha_desde.date_format' => 'La fecha inicial debe utilizar el formato año-mes-día.',
            'fecha_hasta.date_format' => 'La fecha final debe utilizar el formato año-mes-día.',
            'detalle_bitacora.exists' => 'El evento de bitácora solicitado ya no existe.',
        ];
    }

    public function attributes(): array
    {
        return [
            'buscar' => 'búsqueda de usuarios',
            'buscar_bitacora' => 'búsqueda de bitácora',
            'usuario_bitacora_id' => 'usuario de bitácora',
            'numero_activo' => 'número de activo',
            'fecha_desde' => 'fecha inicial',
            'fecha_hasta' => 'fecha final',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $from = $this->input('fecha_desde');
                $to = $this->input('fecha_hasta');

                if (is_string($from) && is_string($to) && $from !== '' && $to !== '' && $from > $to) {
                    $validator->errors()->add(
                        'fecha_hasta',
                        'La fecha final debe ser igual o posterior a la fecha inicial.'
                    );
                }

                $tab = (string) $this->input('tab', 'usuarios');
                $export = $this->input('export');

                if ($export !== null && $export !== '' && $tab !== 'bitacora' && $export !== 'csv') {
                    $validator->errors()->add(
                        'export',
                        'En esta sección únicamente está disponible la exportación CSV.'
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach ([
            'tab',
            'export',
            'buscar',
            'estatus',
            'buscar_bitacora',
            'numero_activo',
            'modulo',
            'accion',
            'fecha_desde',
            'fecha_hasta',
        ] as $key) {
            $value = $this->input($key);

            if (is_string($value)) {
                $trimmed[$key] = trim($value);
            }
        }

        foreach ([
            'per_page',
            'rol_id',
            'editar_usuario',
            'editar_rol',
            'editar_permiso',
            'usuario_bitacora_id',
            'detalle_bitacora',
        ] as $key) {
            $value = $this->input($key);

            if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
                $trimmed[$key] = (int) trim($value);
            }
        }

        $this->merge($trimmed);
    }
}
