<?php
declare(strict_types=1);

/*
 * Admin settings storage.
 *
 * Current credentials live in storage/admin.json. A legacy storage/admin.php
 * reader remains so older deployments can migrate automatically the first time
 * the admin area loads. The writer always stores only the current JSON shape,
 * which avoids carrying stale or sensitive legacy keys forward.
 */

function admin_settings_path(): string
{
    return storage_path('admin.json');
}

function admin_legacy_settings_path(): string
{
    return storage_path('admin.php');
}

function admin_default_settings(): array
{
    return [
        'username' => '',
        'password_hash' => '',
        'password_updated_at' => null,
    ];
}

function admin_settings(): array
{
    /*
     * Prefer JSON settings. If that file does not exist yet, attempt a one-time
     * read from the old PHP settings file and immediately write the JSON file so
     * future requests no longer need to parse legacy content.
     */
    $path = admin_settings_path();
    $defaults = admin_default_settings();
    $loadedLegacySettings = false;

    $settings = app_read_json_file($path, $defaults);

    if (!is_array($settings)) {
        return $defaults;
    }

    if ($settings === $defaults && !is_file($path)) {
        $settings = admin_legacy_settings();
        $loadedLegacySettings = $settings !== $defaults;
    }

    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $settings)) {
            $settings[$key] = $value;
        }
    }

    if ($loadedLegacySettings && ((string) ($settings['username'] ?? '') !== '' || (string) ($settings['password_hash'] ?? '') !== '')) {
        try {
            admin_write_settings($settings);
        } catch (Throwable $exception) {
            if (function_exists('app_log_exception')) {
                app_log_exception($exception, 'admin settings migration');
            }
        }
    }

    return $settings;
}

function admin_legacy_export_string(string $source, string $key): string
{
    $quotedKey = preg_quote($key, '/');

    if (preg_match("/['\"]{$quotedKey}['\"]\\s*=>\\s*'((?:\\\\.|[^'])*)'/", $source, $matches) !== 1) {
        return '';
    }

    return stripcslashes($matches[1]);
}

function admin_legacy_export_nullable_string(string $source, string $key)
{
    $quotedKey = preg_quote($key, '/');

    if (preg_match("/['\"]{$quotedKey}['\"]\\s*=>\\s*NULL/i", $source) === 1) {
        return null;
    }

    $value = admin_legacy_export_string($source, $key);

    return $value === '' ? null : $value;
}

function admin_legacy_settings(): array
{
    /*
     * Do not require the legacy PHP file. It may contain arbitrary PHP syntax
     * from an older deployment, so parse only the known exported string keys we
     * need for migration.
     */
    $path = admin_legacy_settings_path();
    $defaults = admin_default_settings();

    if (!is_file($path)) {
        return $defaults;
    }

    $source = file_get_contents($path);

    if (!is_string($source)) {
        return $defaults;
    }

    $settings = [
        'username' => admin_legacy_export_string($source, 'username'),
        'password_hash' => admin_legacy_export_string($source, 'password_hash'),
        'password_updated_at' => admin_legacy_export_nullable_string($source, 'password_updated_at'),
    ];

    return array_merge($defaults, array_filter(
        $settings,
        static function ($value): bool {
            return $value !== '';
        }
    ));
}

function admin_write_settings(array $settings): void
{
    app_write_json_file(
        admin_settings_path(),
        array_intersect_key($settings, admin_default_settings()),
        'De beheerinstellingen konden niet worden opgeslagen.'
    );
}

function admin_is_configured(): bool
{
    $settings = admin_settings();
    $username = trim((string) ($settings['username'] ?? ''));
    $passwordHash = $settings['password_hash'] ?? '';

    return filter_var($username, FILTER_VALIDATE_EMAIL) !== false
        && is_string($passwordHash)
        && $passwordHash !== '';
}

function admin_username(): string
{
    $username = trim((string) (admin_settings()['username'] ?? ''));

    return filter_var($username, FILTER_VALIDATE_EMAIL) === false ? '' : $username;
}

function admin_script_url(): string
{
    if (PHP_SAPI === 'cli') {
        return 'index.php';
    }

    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $requestPath = str_replace('\\', '/', (string) ($requestPath ?: ($_SERVER['SCRIPT_NAME'] ?? 'index.php')));

    if ($requestPath === '' || $requestPath === '/') {
        return 'index.php';
    }

    if (substr($requestPath, -1) === '/') {
        return $requestPath . 'index.php';
    }

    if (basename($requestPath) === 'index.php') {
        return $requestPath;
    }

    return rtrim($requestPath, '/') . '/index.php';
}

function admin_absolute_script_url(): string
{
    $scriptUrl = admin_script_url();
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if ($host === '') {
        return $scriptUrl;
    }

    $isHttps = function_exists('admin_is_https_request')
        ? admin_is_https_request()
        : ((!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $isHttps ? 'https://' : 'http://';

    return $scheme . $host . $scriptUrl;
}

function admin_section_url(string $section): string
{
    return admin_script_url() . '?section=' . rawurlencode($section);
}

function admin_sections(): array
{
    return [
        'algemeen' => 'Algemeen',
        'home' => 'Home',
        'leuven' => 'Leuven',
        'kamer-1' => 'Room 1',
        'kamer-2' => 'Room 2',
        'kamer-3' => 'Room 3',
        'locatie' => 'Location',
        'contact' => 'Contact',
        'links' => 'Links',
        'voorwaarden' => 'Cancellation policy',
        'toegang' => 'E-mail en wachtwoord',
    ];
}

function admin_requested_section(): string
{
    $section = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($_GET['section'] ?? 'algemeen')));

    return array_key_exists($section, admin_sections()) ? $section : 'algemeen';
}
