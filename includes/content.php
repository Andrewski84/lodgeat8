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
    // Legacy single-file location kept for backward-compatible reads.
    return storage_path('content.json');
}

function content_directory_path(): string
{
    return storage_path('content');
}

function content_part_key_is_valid(string $key): bool
{
    return preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $key) === 1;
}

function content_part_path(string $key): string
{
    if (!content_part_key_is_valid($key)) {
        throw new InvalidArgumentException('Ongeldige content sleutel: ' . $key);
    }

    return content_directory_path() . '/' . strtolower($key) . '.json';
}

function content_decode_json_file(string $path, &$decoded): bool
{
    if (!is_file($path)) {
        return false;
    }

    $json = file_get_contents($path);

    if (!is_string($json)) {
        return false;
    }

    $decoded = json_decode($json, true);

    return json_last_error() === JSON_ERROR_NONE;
}

function default_content(): array
{
    return require base_path('includes/data.php');
}

function merge_content_defaults(array $content, array $defaults): array
{
    // Admin content is intentionally partial. Merge associative defaults so new
    // config keys can be deployed without overwriting edited site content.
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

function load_split_content(): ?array
{
    $directory = content_directory_path();

    if (!is_dir($directory)) {
        return null;
    }

    $files = glob($directory . '/*.json');

    if ($files === false || $files === []) {
        return null;
    }

    $content = [];

    foreach ($files as $file) {
        $key = strtolower((string) pathinfo($file, PATHINFO_FILENAME));

        if (!content_part_key_is_valid($key)) {
            continue;
        }

        $decoded = null;

        if (!content_decode_json_file($file, $decoded)) {
            return null;
        }

        $content[$key] = $decoded;
    }

    return $content === [] ? null : $content;
}

function load_legacy_content(): ?array
{
    $decoded = null;

    if (!content_decode_json_file(content_json_path(), $decoded)) {
        return null;
    }

    return is_array($decoded) ? $decoded : null;
}

function load_content(): array
{
    $defaults = default_content();
    $splitContent = load_split_content();

    if (is_array($splitContent)) {
        return merge_content_defaults($splitContent, $defaults);
    }

    $legacyContent = load_legacy_content();

    if (is_array($legacyContent)) {
        return merge_content_defaults($legacyContent, $defaults);
    }

    return $defaults;
}

function content_write_json_atomically(string $targetPath, string $json, string $errorContext): void
{
    $temporaryPath = $targetPath . '.tmp';

    if (file_put_contents($temporaryPath, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('De tijdelijke content kon niet worden opgeslagen voor ' . $errorContext . '.');
    }

    if (!@rename($temporaryPath, $targetPath)) {
        if (!@copy($temporaryPath, $targetPath)) {
            @unlink($temporaryPath);
            throw new RuntimeException('De content kon niet worden weggeschreven voor ' . $errorContext . '.');
        }

        @unlink($temporaryPath);
    }
}

function save_content(array $content): void
{
    if (!is_dir(storage_path())) {
        if (!mkdir(storage_path(), 0775, true) && !is_dir(storage_path())) {
            throw new RuntimeException('De opslagmap kon niet worden aangemaakt.');
        }
    }

    $contentDirectory = content_directory_path();

    if (!is_dir($contentDirectory)) {
        if (!mkdir($contentDirectory, 0775, true) && !is_dir($contentDirectory)) {
            throw new RuntimeException('De contentmap kon niet worden aangemaakt.');
        }
    }

    $writtenKeys = [];

    foreach ($content as $key => $value) {
        $key = strtolower((string) $key);

        if (!content_part_key_is_valid($key)) {
            throw new RuntimeException('De content bevat een ongeldige sleutel: ' . $key);
        }

        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new RuntimeException('De content kon niet naar JSON worden omgezet voor ' . $key . '.');
        }

        content_write_json_atomically(content_part_path($key), $json, $key);
        $writtenKeys[$key] = true;
    }

    $existingFiles = glob($contentDirectory . '/*.json') ?: [];

    foreach ($existingFiles as $file) {
        $existingKey = strtolower((string) pathinfo($file, PATHINFO_FILENAME));

        if (!content_part_key_is_valid($existingKey) || isset($writtenKeys[$existingKey])) {
            continue;
        }

        @unlink($file);
    }

    // Cleanup old single-file content after a successful split write.
    $legacyPath = content_json_path();

    if (is_file($legacyPath)) {
        @unlink($legacyPath);
    }
}

function image_files(): array
{
    // Return paths relative to assets/img, matching the way media references are
    // stored in content JSON and admin forms.
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
