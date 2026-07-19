<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use PHPUnit\Framework\TestCase;

class PtiiXssProtectionConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function test_blade_views_do_not_use_inline_javascript_event_handlers(): void
    {
        foreach ($this->bladeFiles() as $path => $contents) {
            self::assertDoesNotMatchRegularExpression(
                '/\s(?:onclick|onchange|oninput|onsubmit|onload|onerror|onmouseover|onfocus)\s*=/i',
                $contents,
                $path
            );
        }
    }

    public function test_every_inline_script_and_style_uses_the_request_nonce(): void
    {
        foreach ($this->bladeFiles() as $path => $contents) {
            foreach (preg_split('/\R/', $contents) ?: [] as $lineNumber => $line) {
                if (stripos($line, '<script') === false && stripos($line, '<style') === false) {
                    continue;
                }

                self::assertStringContainsString(
                    'nonce="{{ request()->attributes->get(\'csp_nonce\') }}"',
                    $line,
                    $path.':'.($lineNumber + 1)
                );
            }
        }
    }

    public function test_unescaped_blade_output_is_limited_to_static_svg_icon_helpers(): void
    {
        foreach ($this->bladeFiles() as $path => $contents) {
            preg_match_all('/\{!!\s*(.*?)\s*!!\}/s', $contents, $matches);

            foreach ($matches[1] ?? [] as $expression) {
                self::assertMatchesRegularExpression(
                    '/^\$(?:swafiIcon|loginIcon)\s*\(/',
                    trim($expression),
                    "Salida Blade sin escape no autorizada en {$path}: {$expression}"
                );
            }
        }
    }

    public function test_vite_assets_receive_the_same_request_nonce(): void
    {
        $middleware = $this->read('app/Http/Middleware/SecurityHeaders.php');

        self::assertStringContainsString('Vite::useCspNonce($nonce);', $middleware);
    }

    public function test_central_ui_script_replaces_inline_confirm_and_navigation_handlers(): void
    {
        $script = $this->read('public/assets/swafi/js/swafi-ptii.js');

        self::assertStringContainsString("form.dataset.confirm", $script);
        self::assertStringContainsString("target.dataset.autoSubmit", $script);
        self::assertStringContainsString("target.dataset.navigateBase", $script);
        self::assertStringContainsString('encodeURIComponent(target.value)', $script);
        self::assertStringNotContainsString('innerHTML', $script);
        self::assertStringNotContainsString('eval(', $script);
    }

    /** @return array<string,string> */
    private function bladeFiles(): array
    {
        $base = $this->root.'/resources/views';
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            $files[str_replace($this->root.'/', '', $file->getPathname())] = $contents;
        }

        return $files;
    }

    private function read(string $relative): string
    {
        $contents = file_get_contents($this->root.'/'.$relative);
        self::assertIsString($contents, $relative);

        return $contents;
    }
}
