<?php

namespace App\Console\Commands;

use App\Services\SecureAdministratorProvisioningService;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class BootstrapSwafiAdministratorCommand extends Command
{
    protected $signature = 'swafi:administrator:bootstrap
        {--name= : Nombre completo de la persona administradora}
        {--email= : Correo de la persona administradora}
        {--user= : Identificador de acceso de la persona administradora}
        {--rotate-password : Rota la contraseña del Administrador SWAFI existente}
        {--promote-existing : Autoriza promover una cuenta existente cuando todavía no existe otro administrador}
        {--confirm : Confirma expresamente la operación sensible}';

    protected $description = 'Crea, promueve o recupera de forma segura la cuenta Administrador SWAFI sin contraseñas predeterminadas.';

    public function handle(SecureAdministratorProvisioningService $service): int
    {
        try {
            $administrators = $service->administrators();
            $rotatePassword = (bool) $this->option('rotate-password');
            $promoteExisting = (bool) $this->option('promote-existing');

            $name = $this->optionOrConfig('name', 'swafi.administrador_inicial.nombre');
            $email = $this->optionOrConfig('email', 'swafi.administrador_inicial.email');
            $usuario = $this->optionOrConfig('user', 'swafi.administrador_inicial.usuario');

            if ($email === '' && $usuario === '' && $administrators->count() === 1) {
                $existing = $administrators->first();
                $name = (string) $existing->name;
                $email = (string) $existing->email;
                $usuario = (string) $existing->usuario;

                if (!$rotatePassword) {
                    $this->info('Ya existe un Administrador SWAFI. No se realizaron cambios.');

                    return self::SUCCESS;
                }
            }

            $email = $this->resolveRequiredValue(
                $email,
                'Correo de la persona administradora',
                'Falta SWAFI_BOOTSTRAP_ADMIN_EMAIL o la opción --email.'
            );
            $usuario = $this->resolveRequiredValue(
                $usuario,
                'Usuario de acceso de la persona administradora',
                'Falta SWAFI_BOOTSTRAP_ADMIN_USER o la opción --user.'
            );

            $inspection = $service->inspectTarget($email, $usuario);

            if ($inspection['identity_conflict']) {
                throw new DomainException(
                    'El correo y el usuario corresponden a cuentas distintas. No se realizó ninguna modificación.'
                );
            }

            if (
                $inspection['administrator_count'] > 0 &&
                !$inspection['target_is_administrator']
            ) {
                throw new DomainException(
                    'Ya existe un Administrador SWAFI y la identidad proporcionada no corresponde a esa cuenta.'
                );
            }

            if ($inspection['target_is_administrator'] && !$rotatePassword) {
                $this->info('La identidad indicada ya es Administrador SWAFI. No se realizaron cambios.');

                return self::SUCCESS;
            }

            $target = $inspection['target'];

            if ($target !== null) {
                $name = $name !== '' ? $name : (string) $target->name;
            }

            if ($inspection['administrator_count'] === 0) {
                $name = $this->resolveRequiredValue(
                    $name,
                    'Nombre completo de la persona administradora',
                    'Falta SWAFI_BOOTSTRAP_ADMIN_NAME o la opción --name.'
                );
            }

            if (
                $inspection['administrator_count'] === 0 &&
                $target !== null &&
                !$promoteExisting
            ) {
                throw new DomainException(
                    'La identidad ya pertenece a una cuenta sin rol administrador. Repite el comando con --promote-existing después de validar a la persona usuaria.'
                );
            }

            [$password, $passwordFromEnvironment] = $this->resolvePassword();

            if (!$this->operationConfirmed($rotatePassword, $target !== null)) {
                $this->warn('Operación cancelada. No se modificó ninguna cuenta.');

                return self::FAILURE;
            }

            $result = $service->provision(
                [
                    'name' => $name,
                    'email' => $email,
                    'usuario' => $usuario,
                    'password' => $password,
                ],
                $rotatePassword,
                $promoteExisting
            );

            $message = match ($result['status']) {
                'created' => 'Administrador SWAFI creado y protegido correctamente.',
                'promoted' => 'La cuenta existente fue promovida a Administrador SWAFI de forma segura.',
                'rotated' => 'La contraseña del Administrador SWAFI fue rotada y sus sesiones activas fueron revocadas.',
                default => 'La cuenta Administrador SWAFI ya estaba configurada. No se realizaron cambios.',
            };

            $this->info($message);

            if ((int) ($result['sessions_revoked'] ?? 0) > 0) {
                $this->line('Sesiones revocadas: '.(int) $result['sessions_revoked']);
            }

            if ($passwordFromEnvironment) {
                $this->warn(
                    'Elimina SWAFI_BOOTSTRAP_ADMIN_PASSWORD de las variables de entorno y vuelve a limpiar la caché de configuración.'
                );
            }

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            $this->error('No fue posible completar la operación por errores de validación:');

            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->line(' - '.$message);
                }
            }

            return self::FAILURE;
        } catch (DomainException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error(
                'Ocurrió un error inesperado. Revisa los registros técnicos de Laravel mediante la referencia del despliegue.'
            );

            return self::FAILURE;
        }
    }

    private function optionOrConfig(string $option, string $configKey): string
    {
        $value = trim((string) $this->option($option));

        return $value !== '' ? $value : trim((string) config($configKey, ''));
    }

    private function resolveRequiredValue(string $value, string $question, string $nonInteractiveError): string
    {
        if ($value !== '') {
            return $value;
        }

        if (!$this->input->isInteractive()) {
            throw new RuntimeException($nonInteractiveError);
        }

        $answer = trim((string) $this->ask($question));

        if ($answer === '') {
            throw new RuntimeException('El valor solicitado es obligatorio.');
        }

        return $answer;
    }

    private function resolvePassword(): array
    {
        $environmentPassword = Env::get('SWAFI_BOOTSTRAP_ADMIN_PASSWORD');
        $environmentPassword = is_string($environmentPassword)
            ? trim($environmentPassword)
            : '';

        if ($environmentPassword !== '') {
            return [$environmentPassword, true];
        }

        if (!$this->input->isInteractive()) {
            throw new RuntimeException(
                'Falta la variable temporal SWAFI_BOOTSTRAP_ADMIN_PASSWORD. No envíes la contraseña como argumento de consola.'
            );
        }

        $password = (string) $this->secret('Contraseña segura de la persona administradora');
        $confirmation = (string) $this->secret('Confirma la contraseña segura');

        if ($password === '' || !hash_equals($password, $confirmation)) {
            throw new RuntimeException('La contraseña y su confirmación no coinciden.');
        }

        return [$password, false];
    }

    private function operationConfirmed(bool $rotatePassword, bool $targetExists): bool
    {
        if ((bool) $this->option('confirm')) {
            return true;
        }

        if (!$this->input->isInteractive()) {
            throw new RuntimeException(
                'La operación sensible requiere la opción --confirm cuando se ejecuta sin interacción.'
            );
        }

        $operation = $rotatePassword
            ? 'rotar la contraseña y revocar las sesiones del Administrador SWAFI'
            : ($targetExists
                ? 'promover la cuenta existente a Administrador SWAFI'
                : 'crear la cuenta inicial Administrador SWAFI');

        return $this->confirm('¿Confirmas que deseas '.$operation.'?', false);
    }
}
