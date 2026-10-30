<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pemeriksa sintaks PHP tanpa memproses berkas.
 *
 * Memakai `token_get_all()` dengan flag TOKEN_PARSE sehingga parser PHP yang
 * sesungguhnya dipakai; berkas dengan sintaks tidak valid akan melemparkan
 * ParseError. Cara ini dipakai karena eksekusi subproses (`php -l`) tidak
 * selalu tersedia pada lingkungan terbatas.
 */
final class Linter
{
    /**
     * @param list<string> $directories
     * @return array{checked:int, errors:list<array{file:string,message:string,line:int}>}
     */
    public static function check(array $directories): array
    {
        $errors = [];
        $checked = 0;

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                // Lewati dependensi pihak ketiga bila ada di dalam direktori.
                if (str_contains($file->getPathname(), '/node_modules/') || str_contains($file->getPathname(), '/vendor/')) {
                    continue;
                }

                $checked++;
                $path = $file->getPathname();
                $source = (string) file_get_contents($path);

                if (trim($source) === '') {
                    $errors[] = ['file' => $path, 'message' => 'Berkas kosong', 'line' => 1];
                    continue;
                }

                if (!str_starts_with(trim($source), '<?php')) {
                    $errors[] = ['file' => $path, 'message' => 'Berkas tidak diawali <?php', 'line' => 1];
                    continue;
                }

                try {
                    // @phpstan-ignore-next-line token_get_all dengan TOKEN_PARSE memicu ParseError bila tidak valid
                    token_get_all($source, TOKEN_PARSE);
                } catch (\Throwable $e) {
                    $line = 0;
                    if (preg_match('/on line (\d+)/', $e->getMessage(), $matches) === 1) {
                        $line = (int) $matches[1];
                    }
                    $errors[] = [
                        'file' => $path,
                        'message' => $e->getMessage(),
                        'line' => $line,
                    ];
                }
            }
        }

        return ['checked' => $checked, 'errors' => $errors];
    }

    /**
     * Direktori yang diperiksa secara bawaan.
     *
     * @return list<string>
     */
    public static function defaultDirectories(): array
    {
        $base = Config::basePath();

        return array_values(array_filter([
            $base . '/app',
            $base . '/bootstrap',
            $base . '/config',
            $base . '/modules',
            $base . '/routes',
            $base . '/public',
            $base . '/tests',
            $base . '/database/seeds',
            $base . '/bin',
        ], 'is_dir'));
    }
}
