<?php
declare(strict_types=1);

/*
 * Content and storage infrastructure.
 *
 * The site ships with default content in includes/data.php, while production
 * edits are stored as JSON under storage/content/. This module also exposes the
 * shared filesystem primitives used by admin credentials, contact submissions,
 * reset tokens and runtime locks.
 *
 * The design intentionally favors small JSON files plus file locks: it keeps
 * shared-hosting requirements modest while preventing common race conditions
 * during admin saves, contact submissions and image uploads.
 */

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__);

    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function storage_path(string $path = ''): string
{
    return base_path('storage' . ($path === '' ? '' : '/' . ltrim($path, '/')));
}

function app_ensure_directory(string $directory, string $errorMessage): void
{
    if (is_dir($directory)) {
        return;
    }

    if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException($errorMessage);
    }
}

function app_lock_path_for_file(string $path): string
{
    return $path . '.lock';
}

function app_named_lock_path(string $name): string
{
    $name = preg_replace('/[^a-z0-9._-]/i', '-', $name) ?? 'lock';
    $name = trim($name, '-._');

    if ($name === '') {
        $name = 'lock';
    }

    return storage_path('locks/' . $name . '.lock');
}

function app_with_file_lock(string $lockPath, callable $callback, string $errorMessage, int $operation = LOCK_EX)
{
    /*
     * Central lock wrapper. Callers provide the exact critical section as a
     * callback, which keeps locking close to the filesystem operation without
     * duplicating flock boilerplate across the codebase.
     */
    app_ensure_directory(dirname($lockPath), $errorMessage);

    $handle = @fopen($lockPath, 'c');

    if ($handle === false) {
        throw new RuntimeException($errorMessage);
    }

    try {
        if (!flock($handle, $operation)) {
            throw new RuntimeException($errorMessage);
        }

        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
        }
    } finally {
        fclose($handle);
    }
}

function app_read_json_file_unlocked(string $path, array $default = []): array
{
    if (!is_file($path)) {
        return $default;
    }

    $json = file_get_contents($path);

    if (!is_string($json)) {
        return $default;
    }

    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : $default;
}

function app_read_json_file(string $path, array $default = []): array
{
    return app_with_file_lock(
        app_lock_path_for_file($path),
        static function () use ($path, $default): array {
            return app_read_json_file_unlocked($path, $default);
        },
        'Het JSON-bestand kon niet worden gelezen.',
        LOCK_SH
    );
}

function app_write_text_file_atomically_unlocked(string $targetPath, string $payload, string $errorMessage): void
{
    /*
     * Write through a temporary file and rename into place. The copy fallback
     * exists for hosts/filesystems where rename can fail even when both paths
     * are inside the same logical directory.
     */
    app_ensure_directory(dirname($targetPath), $errorMessage);

    $prefix = preg_replace('/[^a-z0-9._-]/i', '-', basename($targetPath)) ?? 'file';
    $temporaryPath = tempnam(dirname($targetPath), $prefix . '.tmp.');

    if ($temporaryPath === false) {
        throw new RuntimeException($errorMessage);
    }

    try {
        if (file_put_contents($temporaryPath, $payload) === false) {
            throw new RuntimeException($errorMessage);
        }

        @chmod($temporaryPath, 0664);

        if (!@rename($temporaryPath, $targetPath)) {
            if (!@copy($temporaryPath, $targetPath)) {
                throw new RuntimeException($errorMessage);
            }
        }
    } finally {
        if (is_file($temporaryPath)) {
            @unlink($temporaryPath);
        }
    }
}

function app_write_text_file_atomically(string $targetPath, string $payload, string $errorMessage): void
{
    app_with_file_lock(
        app_lock_path_for_file($targetPath),
        static function () use ($targetPath, $payload, $errorMessage): void {
            app_write_text_file_atomically_unlocked($targetPath, $payload, $errorMessage);
        },
        $errorMessage
    );
}

function app_json_encode(array $data, string $errorMessage): string
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        throw new RuntimeException($errorMessage);
    }

    return $json;
}

function app_write_json_file_unlocked(string $path, array $data, string $errorMessage): void
{
    app_write_text_file_atomically_unlocked($path, app_json_encode($data, $errorMessage) . PHP_EOL, $errorMessage);
}

function app_write_json_file(string $path, array $data, string $errorMessage): void
{
    app_with_file_lock(
        app_lock_path_for_file($path),
        static function () use ($path, $data, $errorMessage): void {
            app_write_json_file_unlocked($path, $data, $errorMessage);
        },
        $errorMessage
    );
}

function app_update_json_file(string $path, callable $updater, array $default, string $errorMessage): array
{
    return app_with_file_lock(
        app_lock_path_for_file($path),
        static function () use ($path, $updater, $default, $errorMessage): array {
            $updated = $updater(app_read_json_file_unlocked($path, $default));

            if (!is_array($updated)) {
                throw new RuntimeException($errorMessage);
            }

            app_write_json_file_unlocked($path, $updated, $errorMessage);

            return $updated;
        },
        $errorMessage
    );
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

function content_lock_path(): string
{
    return app_named_lock_path('content');
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

function load_content_unlocked(): array
{
    /*
     * Read order:
     * 1. preferred split JSON content;
     * 2. legacy single JSON file;
     * 3. defaults from includes/data.php.
     *
     * Merging defaults lets new code deploy extra keys without overwriting
     * content already edited through the admin.
     */
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

function load_content(): array
{
    return app_with_file_lock(
        content_lock_path(),
        static function (): array {
            return load_content_unlocked();
        },
        'De content kon niet worden gelezen.',
        LOCK_SH
    );
}

function content_write_json_atomically(string $targetPath, string $json, string $errorContext): void
{
    app_write_text_file_atomically_unlocked(
        $targetPath,
        $json . PHP_EOL,
        'De content kon niet worden weggeschreven voor ' . $errorContext . '.'
    );
}

function save_content_unlocked(array $content): void
{
    app_ensure_directory(storage_path(), 'De opslagmap kon niet worden aangemaakt.');

    $contentDirectory = content_directory_path();

    app_ensure_directory($contentDirectory, 'De contentmap kon niet worden aangemaakt.');

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

function save_content(array $content): void
{
    app_with_file_lock(
        content_lock_path(),
        static function () use ($content): void {
            save_content_unlocked($content);
        },
        'De content kon niet worden opgeslagen.'
    );
}

function content_changed_top_level_keys(array $originalContent, array $updatedContent): array
{
    $keys = array_fill_keys(array_merge(array_keys($originalContent), array_keys($updatedContent)), true);
    $changed = [];

    foreach (array_keys($keys) as $key) {
        if (($originalContent[$key] ?? null) !== ($updatedContent[$key] ?? null)) {
            $changed[] = (string) $key;
        }
    }

    return $changed;
}

function save_content_changes(array $originalContent, array $updatedContent): array
{
    /*
     * Merge only the top-level sections changed by the current form into the
     * latest content on disk. This prevents one admin save from overwriting a
     * different section that another tab or autosave just updated.
     */
    return app_with_file_lock(
        content_lock_path(),
        static function () use ($originalContent, $updatedContent): array {
            $latestContent = load_content_unlocked();
            $changedKeys = content_changed_top_level_keys($originalContent, $updatedContent);

            foreach ($changedKeys as $key) {
                if (array_key_exists($key, $updatedContent)) {
                    $latestContent[$key] = $updatedContent[$key];
                } else {
                    unset($latestContent[$key]);
                }
            }

            save_content_unlocked($latestContent);

            return $latestContent;
        },
        'De content kon niet worden opgeslagen.'
    );
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
