<?php
declare(strict_types=1);

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__);

    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function storage_path(string $path = ''): string
{
    return base_path('storage' . ($path === '' ? '' : '/' . ltrim($path, '/')));
}

function content_json_path(): string
{
    return storage_path('content.json');
}

function default_content(): array
{
    return require base_path('includes/data.php');
}

function merge_content_defaults(array $content, array $defaults): array
{
    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $content)) {
            $content[$key] = $value;
            continue;
        }

        if (
            is_array($value)
            && is_array($content[$key])
            && !is_list_array($value)
            && !is_list_array($content[$key])
        ) {
            $content[$key] = merge_content_defaults($content[$key], $value);
        }
    }

    return $content;
}

function load_content(): array
{
    $defaults = default_content();
    $editablePath = content_json_path();

    if (is_file($editablePath)) {
        $json = file_get_contents($editablePath);
        $content = json_decode((string) $json, true);

        if (is_array($content)) {
            return merge_content_defaults($content, $defaults);
        }
    }

    return $defaults;
}

function save_content(array $content): void
{
    if (!is_dir(storage_path())) {
        if (!mkdir(storage_path(), 0775, true) && !is_dir(storage_path())) {
            throw new RuntimeException('De opslagmap kon niet worden aangemaakt.');
        }
    }

    $json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        throw new RuntimeException('De content kon niet naar JSON worden omgezet.');
    }

    $temporaryPath = content_json_path() . '.tmp';

    if (file_put_contents($temporaryPath, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('De tijdelijke content kon niet worden opgeslagen.');
    }

    $targetPath = content_json_path();

    if (!@rename($temporaryPath, $targetPath)) {
        if (!@copy($temporaryPath, $targetPath)) {
            @unlink($temporaryPath);
            throw new RuntimeException('De content kon niet naar storage/content.json worden geschreven.');
        }

        @unlink($temporaryPath);
    }
}

function image_files(): array
{
    $imagePath = rtrim(base_path('assets/img'), '/\\');

    if (!is_dir($imagePath)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($imagePath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        $relativePath = substr($fileInfo->getPathname(), strlen($imagePath) + 1);
        $relativePath = str_replace('\\', '/', $relativePath);

        if (preg_match('/\.(jpe?g|png|gif|webp)$/i', $relativePath) === 1) {
            $files[] = $relativePath;
        }
    }

    natcasesort($files);

    return array_values($files);
}
