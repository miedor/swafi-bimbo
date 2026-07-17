<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdministratorBootstrapSecurityConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_login_does_not_create_or_repair_an_administrator_account(): void
    {
        $controller = $this->read('app/Http/Controllers/AuthController.php');

        self::assertStringNotContainsString('ensureDefaultAdmin', $controller);
        self::assertStringNotContainsString('ensureBaseRolesPermissions', $controller);
        self::assertStringNotContainsString('Admin@12345678', $controller);
        self::assertStringContainsString('Hash::check($password, $user->password)', $controller);
        self::assertStringContainsString('$request->session()->regenerate();', $controller);
    }

    public function test_catalog_seeder_never_creates_users_or_resets_passwords(): void
    {
        $seeder = $this->read('database/seeders/SwafiCatalogSeeder.php');

        self::assertStringNotContainsString("DB::table('users')->updateOrInsert", $seeder);
        self::assertStringNotContainsString("DB::table('role_user')->updateOrInsert", $seeder);
        self::assertStringNotContainsString('Hash::make(', $seeder);
        self::assertStringNotContainsString("'password' =>", $seeder);
        self::assertStringContainsString("DB::table('roles')->updateOrInsert", $seeder);
        self::assertStringContainsString("DB::table('permissions')->updateOrInsert", $seeder);
    }

    public function test_bootstrap_command_never_accepts_the_password_as_a_console_argument(): void
    {
        $command = $this->read('app/Console/Commands/BootstrapSwafiAdministratorCommand.php');

        self::assertStringContainsString('swafi:administrator:bootstrap', $command);
        self::assertStringNotContainsString('{--password=', $command);
        self::assertStringContainsString("Env::get('SWAFI_BOOTSTRAP_ADMIN_PASSWORD')", $command);
        self::assertStringContainsString("\$this->secret('Contraseña segura", $command);
        self::assertStringContainsString("option('confirm')", $command);
        self::assertStringContainsString('No envíes la contraseña como argumento de consola.', $command);
    }

    public function test_provisioning_is_transactional_locked_audited_and_revokes_sessions(): void
    {
        $service = $this->read('app/Services/SecureAdministratorProvisioningService.php');

        foreach ([
            'DB::transaction(',
            '->lockForUpdate()',
            'Hash::make(',
            "DB::table('sessions')",
            "DB::table('permission_role')->updateOrInsert",
            "DB::table('role_user')->updateOrInsert",
            "'ADMIN_BOOTSTRAP_SEGURO'",
            "'ADMIN_PROMOCION_SEGURA'",
            "'ADMIN_PASSWORD_ROTACION'",
            "'user_id' => null",
            "'origen' => 'comando_consola_seguro'",
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }

        self::assertLessThanOrEqual(40, strlen('ADMIN_BOOTSTRAP_SEGURO'));
        self::assertLessThanOrEqual(40, strlen('ADMIN_PROMOCION_SEGURA'));
        self::assertLessThanOrEqual(40, strlen('ADMIN_PASSWORD_ROTACION'));
    }

    public function test_provisioning_prevents_silent_second_administrator_and_implicit_promotion(): void
    {
        $service = $this->read('app/Services/SecureAdministratorProvisioningService.php');
        $command = $this->read('app/Console/Commands/BootstrapSwafiAdministratorCommand.php');

        self::assertStringContainsString(
            'No se creó ni promovió una segunda cuenta administrativa.',
            $service
        );
        self::assertStringContainsString('!$promoteExisting', $service);
        self::assertStringContainsString('{--promote-existing', $command);
        self::assertStringContainsString('{--rotate-password', $command);
    }

    public function test_bootstrap_identity_is_configurable_and_password_is_not_cached_in_config(): void
    {
        $config = $this->read('config/swafi.php');
        $environment = $this->read('.env.example');

        foreach ([
            'SWAFI_BOOTSTRAP_ADMIN_NAME',
            'SWAFI_BOOTSTRAP_ADMIN_EMAIL',
            'SWAFI_BOOTSTRAP_ADMIN_USER',
        ] as $variable) {
            self::assertStringContainsString($variable, $config);
            self::assertStringContainsString($variable.'=', $environment);
        }

        self::assertStringNotContainsString('SWAFI_BOOTSTRAP_ADMIN_PASSWORD', $config);
        self::assertStringContainsString('SWAFI_BOOTSTRAP_ADMIN_PASSWORD=', $environment);
    }

    public function test_no_production_file_contains_the_removed_default_credentials(): void
    {
        $directories = ['app', 'config', 'database/seeders', 'routes'];
        $forbidden = [
            'Admin@12345678',
            "Hash::make('12345678')",
            'ensureDefaultAdmin',
        ];

        foreach ($directories as $directory) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $this->root.'/'.$directory,
                    \FilesystemIterator::SKIP_DOTS
                )
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                self::assertIsString($contents);

                foreach ($forbidden as $value) {
                    self::assertStringNotContainsString(
                        $value,
                        $contents,
                        $file->getPathname().' contiene un valor de aprovisionamiento inseguro.'
                    );
                }
            }
        }
    }

    public function test_existing_authentication_captcha_reset_and_banking_session_controls_remain_present(): void
    {
        $auth = $this->read('app/Http/Controllers/AuthController.php');
        $reset = $this->read('app/Http/Controllers/PasswordResetController.php');
        $session = $this->read('public/assets/swafi/js/swafi-session.js');
        $sessionConfig = $this->read('config/session.php');

        self::assertStringContainsString("new RecaptchaV3('login')", $auth);
        self::assertStringContainsString('Auth::login($user, false);', $auth);
        self::assertStringContainsString('$request->session()->invalidate();', $auth);
        self::assertStringContainsString('$request->session()->regenerateToken();', $auth);
        self::assertStringContainsString('new SwafiPasswordPolicy()', $reset);
        self::assertStringContainsString("terminateSession('navegacion_atras')", $session);
        self::assertStringContainsString("terminateSession('cache_restaurada')", $session);
        self::assertStringContainsString("'same_site' => env('SESSION_SAME_SITE', 'strict')", $sessionConfig);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root.'/'.$relativePath);

        self::assertIsString($contents, 'No fue posible leer '.$relativePath);

        return $contents;
    }
}
