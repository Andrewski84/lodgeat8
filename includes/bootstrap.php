<?php
declare(strict_types=1);

// Load language configuration first (required by helpers.php)
require_once __DIR__ . '/languages.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/content.php';

function app_security_headers(): array
{
    return [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        'Content-Security-Policy' => implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "frame-src 'self' https://www.google.com https://maps.google.com",
            "form-action 'self' https:",
        ]),
    ];
}

function app_send_security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    foreach (app_security_headers() as $name => $value) {
        header($name . ': ' . $value);
    }
}

function app_log_path(): string
{
    return storage_path('logs/app.log');
}

function app_log_message(string $level, string $message, array $context = []): void
{
    $level = strtoupper(preg_replace('/[^A-Z]/i', '', $level) ?? 'INFO');
    $level = $level === '' ? 'INFO' : $level;
    $message = trim(str_replace(["\r", "\n"], ' ', $message));

    if ($message === '') {
        return;
    }

    $context += [
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
        'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
    ];

    $json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $line = sprintf(
        "[%s] %s %s %s\n",
        date('c'),
        $level,
        $message,
        $json === false ? '{}' : $json
    );

    $directory = dirname(app_log_path());

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        error_log($line);
        return;
    }

    if (file_put_contents(app_log_path(), $line, FILE_APPEND | LOCK_EX) === false) {
        error_log($line);
    }
}

function app_log_exception(Throwable $exception, string $context = 'app'): void
{
    app_log_message('error', $context . ': ' . $exception->getMessage(), [
        'exception' => get_class($exception),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ]);
}

app_send_security_headers();

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/renderers.php';
require_once __DIR__ . '/contact.php';

$config = load_content();
