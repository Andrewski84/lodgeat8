<?php
declare(strict_types=1);

function admin_is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

    if ($forwardedProto === 'https') {
        return true;
    }

    return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function admin_session_fingerprint(): string
{
    $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

    return hash('sha256', $userAgent);
}

function admin_session_timeout_seconds(): int
{
    return 2 * 60 * 60;
}

function admin_refresh_session_state(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $fingerprint = admin_session_fingerprint();

    if (!isset($_SESSION['admin_session_fingerprint'])) {
        $_SESSION['admin_session_fingerprint'] = $fingerprint;
    } elseif (!hash_equals((string) $_SESSION['admin_session_fingerprint'], $fingerprint)) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['admin_session_fingerprint'] = $fingerprint;
    }

    $now = time();
    $lastActivity = (int) ($_SESSION['admin_last_activity'] ?? $now);
    $timeout = admin_session_timeout_seconds();

    if (($now - $lastActivity) > $timeout) {
        unset($_SESSION['admin_authenticated']);
        session_regenerate_id(true);
    }

    $lastRegeneration = (int) ($_SESSION['admin_last_regeneration'] ?? 0);

    if (($now - $lastRegeneration) > 15 * 60) {
        session_regenerate_id(true);
        $_SESSION['admin_last_regeneration'] = $now;
    }

    $_SESSION['admin_last_activity'] = $now;
}

function admin_prepare_runtime(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        admin_refresh_session_state();
        return;
    }

    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_samesite', 'Lax');

    if (admin_is_https_request()) {
        @ini_set('session.cookie_secure', '1');
    }

    if (session_name() !== 'lodging_admin') {
        session_name('lodging_admin');
    }

    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => admin_is_https_request(),
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);

    admin_refresh_session_state();
}

function admin_logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function admin_is_logged_in(): bool
{
    return ($_SESSION['admin_authenticated'] ?? false) === true;
}

function admin_csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function admin_check_csrf(array $post): bool
{
    $token = $post['csrf_token'] ?? '';

    return is_string($token) && hash_equals(admin_csrf_token(), $token);
}
