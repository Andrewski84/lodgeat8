<?php
declare(strict_types=1);

function admin_settings_path(): string
{
    return storage_path('admin.php');
}

function admin_default_settings(): array
{
    return [
        'username' => '',
        'password_hash' => '',
        'password_updated_at' => null,
        'password_reset_tokens' => [],
    ];
}

function admin_settings(): array
{
    $path = admin_settings_path();
    $defaults = admin_default_settings();

    if (!is_file($path)) {
        return $defaults;
    }

    $settings = require $path;

    if (!is_array($settings)) {
        return $defaults;
    }

    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $settings)) {
            $settings[$key] = $value;
        }
    }

    return $settings;
}

function admin_write_settings(array $settings): void
{
    if (!is_dir(storage_path())) {
        if (!mkdir(storage_path(), 0775, true) && !is_dir(storage_path())) {
            throw new RuntimeException('De opslagmap kon niet worden aangemaakt.');
        }
    }

    $export = var_export($settings, true);
    $temporaryPath = admin_settings_path() . '.tmp';
    $payload = "<?php\nreturn {$export};\n";

    if (file_put_contents($temporaryPath, $payload, LOCK_EX) === false) {
        throw new RuntimeException('De beheerinstellingen konden niet worden opgeslagen.');
    }

    if (!@rename($temporaryPath, admin_settings_path())) {
        if (!@copy($temporaryPath, admin_settings_path())) {
            @unlink($temporaryPath);
            throw new RuntimeException('De beheerinstellingen konden niet definitief worden opgeslagen.');
        }

        @unlink($temporaryPath);
    }
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
