<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SwafiStorageService
{
    public function defaultDisk(): string
    {
        $disk = $this->resolveDisk((string) config(
            'filesystems.swafi_disk',
            config('filesystems.default', 'local')
        ));

        if (
            app()->environment('production')
            && $disk === 'local'
            && !config('filesystems.swafi_allow_local_in_production', false)
        ) {
            throw new RuntimeException(
                'SWAFI no permite almacenar nuevos archivos en el disco local de producción. '
                . 'Adjunta Object Storage y configura SWAFI_STORAGE_DISK=s3.'
            );
        }

        return $disk;
    }

    public function legacyDisk(): string
    {
        return $this->resolveDisk((string) config(
            'filesystems.swafi_legacy_disk',
            'local'
        ));
    }

    public function resolveDisk(?string $disk): string
    {
        $disk = trim((string) $disk);

        if ($disk === '') {
            $disk = (string) config(
                'filesystems.swafi_disk',
                config('filesystems.default', 'local')
            );
        }

        if (!config("filesystems.disks.{$disk}")) {
            throw new RuntimeException(
                "El disco de almacenamiento [{$disk}] no está configurado."
            );
        }

        return $disk;
    }

    public function recordDisk(?string $disk): string
    {
        return $this->resolveDisk($disk ?: $this->legacyDisk());
    }

    public function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#/+#', '/', $path) ?: $path;
        $path = ltrim($path, '/');

        if (
            $path === ''
            || str_contains($path, '../')
            || str_starts_with($path, '..')
        ) {
            throw new RuntimeException('La ruta del archivo no es válida.');
        }

        return $path;
    }

    /**
     * @return array{
     *     disk:string,
     *     path:string,
     *     mime_type:string,
     *     tamano_bytes:int,
     *     hash_sha256:string
     * }
     */
    public function storeUploadedFile(
        UploadedFile $file,
        string $directory,
        ?string $storedName = null,
        ?string $disk = null
    ): array {
        if (!$file->isValid()) {
            throw new RuntimeException('El archivo cargado no es válido.');
        }

        $sourcePath = $file->getRealPath();

        if (!$sourcePath || !is_file($sourcePath)) {
            throw new RuntimeException(
                'No fue posible acceder al archivo temporal cargado.'
            );
        }

        $storedName = $storedName
            ?: $this->safeStoredName($file->getClientOriginalName());

        $targetPath = trim($directory, '/')
            . '/'
            . ltrim($storedName, '/');

        return $this->storeLocalFile(
            sourcePath: $sourcePath,
            targetPath: $targetPath,
            mimeType: $file->getMimeType()
                ?: 'application/octet-stream',
            disk: $disk
        );
    }

    /**
     * @return array{
     *     disk:string,
     *     path:string,
     *     mime_type:string,
     *     tamano_bytes:int,
     *     hash_sha256:string
     * }
     */
    public function storeLocalFile(
        string $sourcePath,
        string $targetPath,
        ?string $mimeType = null,
        ?string $disk = null
    ): array {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new RuntimeException(
                'El archivo origen no existe o no puede leerse.'
            );
        }

        $disk = $this->resolveDisk($disk ?: $this->defaultDisk());
        $targetPath = $this->normalizePath($targetPath);

        $expectedHash = hash_file('sha256', $sourcePath);
        $size = filesize($sourcePath);

        if ($expectedHash === false || $size === false) {
            throw new RuntimeException(
                'No fue posible calcular la integridad del archivo origen.'
            );
        }

        $stream = fopen($sourcePath, 'rb');

        if ($stream === false) {
            throw new RuntimeException(
                'No fue posible abrir el archivo origen.'
            );
        }

        try {
            $written = Storage::disk($disk)->writeStream(
                $targetPath,
                $stream
            );
        } finally {
            fclose($stream);
        }

        if (
            $written !== true
            || !Storage::disk($disk)->exists($targetPath)
        ) {
            throw new RuntimeException(
                "No fue posible almacenar el archivo en el disco [{$disk}]."
            );
        }

        $storedHash = $this->hash($disk, $targetPath);

        if (
            !hash_equals(
                strtolower($expectedHash),
                strtolower($storedHash)
            )
        ) {
            Storage::disk($disk)->delete($targetPath);

            throw new RuntimeException(
                'El archivo almacenado no superó la verificación SHA-256.'
            );
        }

        return [
            'disk' => $disk,
            'path' => $targetPath,
            'mime_type' => $mimeType
                ?: $this->mimeType($disk, $targetPath),
            'tamano_bytes' => (int) $size,
            'hash_sha256' => $expectedHash,
        ];
    }

    public function exists(?string $disk, string $path): bool
    {
        try {
            return Storage::disk(
                $this->recordDisk($disk)
            )->exists(
                $this->normalizePath($path)
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{
     *     ok:bool,
     *     disk:string,
     *     path:string,
     *     hash_sha256:?string,
     *     mime_type:?string,
     *     tamano_bytes:?int,
     *     message:?string
     * }
     */
    public function validate(
        ?string $disk,
        string $path,
        ?string $expectedHash = null
    ): array {
        try {
            $disk = $this->recordDisk($disk);
            $path = $this->normalizePath($path);

            if (!Storage::disk($disk)->exists($path)) {
                return [
                    'ok' => false,
                    'disk' => $disk,
                    'path' => $path,
                    'hash_sha256' => null,
                    'mime_type' => null,
                    'tamano_bytes' => null,
                    'message' => "El archivo no fue localizado en el disco [{$disk}].",
                ];
            }

            $actualHash = $this->hash($disk, $path);

            if (
                $expectedHash
                && !hash_equals(
                    strtolower($expectedHash),
                    strtolower($actualHash)
                )
            ) {
                return [
                    'ok' => false,
                    'disk' => $disk,
                    'path' => $path,
                    'hash_sha256' => $actualHash,
                    'mime_type' => $this->mimeType($disk, $path),
                    'tamano_bytes' => $this->size($disk, $path),
                    'message' => 'La integridad SHA-256 del archivo no coincide con la registrada.',
                ];
            }

            return [
                'ok' => true,
                'disk' => $disk,
                'path' => $path,
                'hash_sha256' => $actualHash,
                'mime_type' => $this->mimeType($disk, $path),
                'tamano_bytes' => $this->size($disk, $path),
                'message' => null,
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'disk' => trim((string) $disk) ?: 'desconocido',
                'path' => trim($path),
                'hash_sha256' => null,
                'mime_type' => null,
                'tamano_bytes' => null,
                'message' => $exception->getMessage(),
            ];
        }
    }

    public function hash(?string $disk, string $path): string
    {
        $disk = $this->recordDisk($disk);
        $path = $this->normalizePath($path);

        $stream = Storage::disk($disk)->readStream($path);

        if (!is_resource($stream)) {
            throw new RuntimeException(
                "No fue posible leer el archivo [{$path}] "
                . "del disco [{$disk}]."
            );
        }

        $context = hash_init('sha256');

        try {
            $updated = hash_update_stream($context, $stream);

            if ($updated === false) {
                throw new RuntimeException(
                    "No fue posible procesar el archivo [{$path}] "
                    . "del disco [{$disk}] para calcular su hash."
                );
            }
        } finally {
            fclose($stream);
        }

        return hash_final($context);
    }

    /**
     * Verifica que el contenido de un archivo coincida con el hash SHA-256
     * registrado en la base de datos.
     *
     * Funciona con almacenamiento local y con Object Storage S3 porque
     * utiliza el método hash(), que procesa el archivo mediante streaming.
     */
    public function verifyHash(
        ?string $disk,
        string $path,
        string $expectedHash
    ): bool {
        $expectedHash = strtolower(trim($expectedHash));

        /*
         * Un hash SHA-256 válido debe contener exactamente
         * 64 caracteres hexadecimales.
         */
        if (
            $expectedHash === ''
            || preg_match('/^[a-f0-9]{64}$/', $expectedHash) !== 1
        ) {
            return false;
        }

        $actualHash = strtolower(
            $this->hash($disk, $path)
        );

        return hash_equals(
            $expectedHash,
            $actualHash
        );
    }

    public function contents(?string $disk, string $path): string
    {
        $disk = $this->recordDisk($disk);
        $path = $this->normalizePath($path);

        $contents = Storage::disk($disk)->get($path);

        if (!is_string($contents)) {
            throw new RuntimeException(
                'No fue posible recuperar el contenido del archivo.'
            );
        }

        return $contents;
    }

    /**
     * @return resource
     */
    public function readStream(?string $disk, string $path)
    {
        $disk = $this->recordDisk($disk);
        $path = $this->normalizePath($path);

        $stream = Storage::disk($disk)->readStream($path);

        if (!is_resource($stream)) {
            throw new RuntimeException(
                'No fue posible abrir el flujo de lectura del archivo.'
            );
        }

        return $stream;
    }

    public function inlineResponse(
        ?string $disk,
        string $path,
        string $downloadName,
        ?string $mimeType = null,
        array $headers = []
    ): StreamedResponse {
        return $this->streamResponse(
            disk: $disk,
            path: $path,
            downloadName: $downloadName,
            mimeType: $mimeType,
            disposition: 'inline',
            headers: $headers
        );
    }

    public function downloadResponse(
        ?string $disk,
        string $path,
        string $downloadName,
        ?string $mimeType = null,
        array $headers = []
    ): StreamedResponse {
        return $this->streamResponse(
            disk: $disk,
            path: $path,
            downloadName: $downloadName,
            mimeType: $mimeType,
            disposition: 'attachment',
            headers: $headers
        );
    }

    public function streamResponse(
        ?string $disk,
        string $path,
        string $downloadName,
        ?string $mimeType,
        string $disposition,
        array $headers = []
    ): StreamedResponse {
        $disk = $this->recordDisk($disk);
        $path = $this->normalizePath($path);
        $mimeType = $mimeType ?: $this->mimeType($disk, $path);

        $downloadName = str_replace(
            ["\r", "\n", '"'],
            '',
            basename($downloadName)
        );

        $downloadName = $downloadName !== ''
            ? $downloadName
            : 'archivo';

        $headers = array_merge([
            'Content-Type' => $mimeType,
            'Content-Disposition' => $disposition
                . '; filename="'
                . $downloadName
                . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ], $headers);

        return response()->stream(
            function () use ($disk, $path): void {
                $stream = Storage::disk($disk)->readStream($path);

                if (!is_resource($stream)) {
                    return;
                }

                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            },
            200,
            $headers
        );
    }

    public function copyToTemporaryFile(
        ?string $disk,
        string $path,
        string $tempDirectory,
        ?string $fileName = null
    ): string {
        $disk = $this->recordDisk($disk);
        $path = $this->normalizePath($path);

        File::ensureDirectoryExists($tempDirectory);

        $fileName = $fileName ?: basename($path);
        $fileName = basename(
            str_replace('\\', '/', $fileName)
        );

        $fileName = $fileName !== ''
            ? $fileName
            : Str::uuid()->toString();

        $target = rtrim(
            $tempDirectory,
            DIRECTORY_SEPARATOR
        )
            . DIRECTORY_SEPARATOR
            . Str::random(10)
            . '_'
            . $fileName;

        $source = Storage::disk($disk)->readStream($path);

        if (!is_resource($source)) {
            throw new RuntimeException(
                'No fue posible abrir el archivo remoto '
                . 'para crear una copia temporal.'
            );
        }

        $destination = fopen($target, 'wb');

        if ($destination === false) {
            fclose($source);

            throw new RuntimeException(
                'No fue posible crear el archivo temporal.'
            );
        }

        try {
            $copied = stream_copy_to_stream(
                $source,
                $destination
            );
        } finally {
            fclose($source);
            fclose($destination);
        }

        if ($copied === false || !is_file($target)) {
            @unlink($target);

            throw new RuntimeException(
                'No fue posible completar la copia temporal del archivo.'
            );
        }

        return $target;
    }

    /**
     * @return array{
     *     disk:string,
     *     path:string,
     *     mime_type:string,
     *     tamano_bytes:int,
     *     hash_sha256:string
     * }
     */
    public function copyBetweenDisks(
        ?string $sourceDisk,
        string $sourcePath,
        ?string $targetDisk,
        ?string $targetPath = null,
        ?string $expectedHash = null
    ): array {
        $sourceDisk = $this->recordDisk($sourceDisk);

        $targetDisk = $this->resolveDisk(
            $targetDisk ?: $this->defaultDisk()
        );

        $sourcePath = $this->normalizePath($sourcePath);

        $targetPath = $this->normalizePath(
            $targetPath ?: $sourcePath
        );

        $validation = $this->validate(
            $sourceDisk,
            $sourcePath,
            $expectedHash
        );

        if (!$validation['ok']) {
            throw new RuntimeException(
                $validation['message']
                    ?: 'El archivo origen no es válido.'
            );
        }

        if (
            $sourceDisk === $targetDisk
            && $sourcePath === $targetPath
        ) {
            return [
                'disk' => $targetDisk,
                'path' => $targetPath,
                'mime_type' => $validation['mime_type']
                    ?: 'application/octet-stream',
                'tamano_bytes' => (int) (
                    $validation['tamano_bytes'] ?? 0
                ),
                'hash_sha256' => (string) $validation['hash_sha256'],
            ];
        }

        $source = Storage::disk(
            $sourceDisk
        )->readStream(
            $sourcePath
        );

        if (!is_resource($source)) {
            throw new RuntimeException(
                'No fue posible abrir el archivo origen para migrarlo.'
            );
        }

        try {
            $written = Storage::disk(
                $targetDisk
            )->writeStream(
                $targetPath,
                $source
            );
        } finally {
            fclose($source);
        }

        if (
            $written !== true
            || !Storage::disk($targetDisk)->exists($targetPath)
        ) {
            throw new RuntimeException(
                "No fue posible copiar el archivo "
                . "al disco [{$targetDisk}]."
            );
        }

        $targetHash = $this->hash(
            $targetDisk,
            $targetPath
        );

        $sourceHash = (string) $validation['hash_sha256'];

        if (
            !hash_equals(
                strtolower($sourceHash),
                strtolower($targetHash)
            )
        ) {
            Storage::disk($targetDisk)->delete($targetPath);

            throw new RuntimeException(
                'La copia no superó la verificación SHA-256.'
            );
        }

        return [
            'disk' => $targetDisk,
            'path' => $targetPath,
            'mime_type' => $validation['mime_type']
                ?: 'application/octet-stream',
            'tamano_bytes' => (int) (
                $validation['tamano_bytes'] ?? 0
            ),
            'hash_sha256' => $targetHash,
        ];
    }

    public function delete(?string $disk, string $path): bool
    {
        try {
            return Storage::disk(
                $this->recordDisk($disk)
            )->delete(
                $this->normalizePath($path)
            );
        } catch (\Throwable) {
            return false;
        }
    }

    public function mimeType(?string $disk, string $path): string
    {
        try {
            return Storage::disk(
                $this->recordDisk($disk)
            )->mimeType(
                $this->normalizePath($path)
            ) ?: 'application/octet-stream';
        } catch (\Throwable) {
            return 'application/octet-stream';
        }
    }

    public function size(?string $disk, string $path): ?int
    {
        try {
            return (int) Storage::disk(
                $this->recordDisk($disk)
            )->size(
                $this->normalizePath($path)
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeStoredName(string $originalName): string
    {
        $extension = strtolower(
            pathinfo($originalName, PATHINFO_EXTENSION)
        );

        $baseName = Str::slug(
            Str::ascii(
                pathinfo($originalName, PATHINFO_FILENAME)
            )
        ) ?: 'archivo';

        return now()->format('YmdHis')
            . '_'
            . Str::random(10)
            . '_'
            . $baseName
            . ($extension ? '.' . $extension : '');
    }
}
