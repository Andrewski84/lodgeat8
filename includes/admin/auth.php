<?php
declare(strict_types=1);

function admin_normalized_username(string $username): string
{
    $username = strtolower(trim($username));

    return filter_var($username, FILTER_VALIDATE_EMAIL) === false ? '' : $username;
}

function admin_login_email_is_valid(string $username): bool
{
    return admin_normalized_username($username) !== '';
}

function admin_save_credentials(string $username, string $password): void
{
    $username = admin_normalized_username($username);

    if ($username === '') {
        throw new RuntimeException('Gebruik een geldig e-mailadres als login.');
    }

    admin_write_settings([
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'password_updated_at' => date('c'),
        'password_reset_tokens' => [],
    ]);
}

function admin_update_credentials(string $username, ?string $password = null): void
{
    $settings = admin_settings();
    $username = admin_normalized_username($username);

    if ($username === '') {
        throw new RuntimeException('Gebruik een geldig e-mailadres als login.');
    }

    $settings['username'] = $username;

    if ($password !== null && $password !== '') {
        $settings['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $settings['password_updated_at'] = date('c');
        $settings['password_reset_tokens'] = [];
    }

    admin_write_settings($settings);
}

function admin_client_ip(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    return $ip === '' ? 'unknown' : $ip;
}

function admin_login_attempts_path(): string
{
    return storage_path('admin-login-attempts.json');
}

function admin_login_attempt_keys(string $username): array
{
    $keys = ['ip:' . hash('sha256', admin_client_ip())];
    $username = admin_normalized_username($username);

    if ($username !== '') {
        $keys[] = 'user:' . hash('sha256', $username . '|' . admin_client_ip());
    }

    return $keys;
}

function admin_read_login_attempts(): array
{
    $path = admin_login_attempts_path();

    if (!is_file($path)) {
        return [];
    }

    $json = file_get_contents($path);
    $data = json_decode((string) $json, true);

    return is_array($data) ? $data : [];
}

function admin_write_login_attempts(array $attempts): void
{
    if (!is_dir(storage_path())) {
        if (!mkdir(storage_path(), 0775, true) && !is_dir(storage_path())) {
            throw new RuntimeException('De opslagmap kon niet worden aangemaakt.');
        }
    }

    $json = json_encode($attempts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        throw new RuntimeException('Loginbeveiliging kon niet worden opgeslagen.');
    }

    $temporaryPath = admin_login_attempts_path() . '.tmp';

    if (file_put_contents($temporaryPath, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Loginbeveiliging kon niet tijdelijk worden opgeslagen.');
    }

    if (!@rename($temporaryPath, admin_login_attempts_path())) {
        if (!@copy($temporaryPath, admin_login_attempts_path())) {
            @unlink($temporaryPath);
            throw new RuntimeException('Loginbeveiliging kon niet definitief worden opgeslagen.');
        }

        @unlink($temporaryPath);
    }
}

function admin_prune_login_attempts(array $attempts): array
{
    $minimumTimestamp = time() - 24 * 60 * 60;

    foreach ($attempts as $key => $entry) {
        if (!is_array($entry)) {
            unset($attempts[$key]);
            continue;
        }

        $updatedAt = (int) ($entry['updated_at'] ?? 0);

        if ($updatedAt < $minimumTimestamp) {
            unset($attempts[$key]);
        }
    }

    return $attempts;
}

function admin_login_throttle_seconds(string $username): int
{
    $attempts = admin_prune_login_attempts(admin_read_login_attempts());
    $now = time();
    $seconds = 0;

    foreach (admin_login_attempt_keys($username) as $key) {
        $lockUntil = (int) ($attempts[$key]['lock_until'] ?? 0);
        $seconds = max($seconds, max(0, $lockUntil - $now));
    }

    return $seconds;
}

function admin_record_failed_login(string $username): void
{
    $attempts = admin_prune_login_attempts(admin_read_login_attempts());
    $now = time();

    foreach (admin_login_attempt_keys($username) as $key) {
        $entry = is_array($attempts[$key] ?? null) ? $attempts[$key] : [];
        $count = max(0, (int) ($entry['count'] ?? 0)) + 1;
        $delay = min(15 * 60, 2 ** min($count, 10));

        $attempts[$key] = [
            'count' => $count,
            'lock_until' => $now + max(2, $delay),
            'updated_at' => $now,
        ];
    }

    admin_write_login_attempts($attempts);
}

function admin_clear_login_attempts(string $username): void
{
    $attempts = admin_prune_login_attempts(admin_read_login_attempts());

    foreach (admin_login_attempt_keys($username) as $key) {
        unset($attempts[$key]);
    }

    admin_write_login_attempts($attempts);
}

function admin_login(string $username, string $password): bool
{
    if (admin_login_throttle_seconds($username) > 0) {
        return false;
    }

    $settings = admin_settings();
    $username = admin_normalized_username($username);
    $expectedUsername = admin_normalized_username((string) ($settings['username'] ?? ''));
    $hash = (string) ($settings['password_hash'] ?? '');

    if (
        $username === ''
        || $expectedUsername === ''
        || $hash === ''
        || !hash_equals($expectedUsername, $username)
        || !password_verify($password, $hash)
    ) {
        admin_record_failed_login($username);
        return false;
    }

    admin_clear_login_attempts($username);
    session_regenerate_id(true);
    $_SESSION['admin_authenticated'] = true;

    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $settings['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $settings['password_updated_at'] = date('c');
        admin_write_settings($settings);
    }

    return true;
}

function admin_password_reset_ttl_seconds(): int
{
    return 60 * 60;
}

function admin_password_reset_expires_minutes(): int
{
    return (int) round(admin_password_reset_ttl_seconds() / 60);
}

function admin_password_reset_request_message(): string
{
    return 'Als dit e-mailadres gekoppeld is aan beheer, sturen we een resetlink.';
}

function admin_clean_mail_header(string $value): string
{
    return trim(str_replace(["\r", "\n"], ' ', $value));
}

/**
 * Send a password-reset link to the configured admin address.
 *
 * The reset flow deliberately keeps token generation and mail delivery in PHP
 * rather than relying on admin-only UI. That way a fresh production deployment
 * can recover access as soon as PHP mail() is configured by the host.
 */
function admin_send_password_reset_email(array $config, string $email, string $resetLink): bool
{
    $to = admin_normalized_username($email);

    if ($to === '' || $resetLink === '') {
        return false;
    }

    $siteName = admin_clean_mail_header((string) ($config['site']['name'] ?? 'Lodging at 8'));
    $from = filter_var((string) ($config['site']['email'] ?? ''), FILTER_VALIDATE_EMAIL);

    if ($from === false) {
        $from = $to;
    }

    $subject = admin_clean_mail_header('Wachtwoord opnieuw instellen voor ' . $siteName);
    $body = implode("\n", [
        'Er werd een resetlink aangevraagd voor het beheer van ' . $siteName . '.',
        '',
        'Open deze link om een nieuw wachtwoord in te stellen:',
        $resetLink,
        '',
        'De link verloopt na ' . admin_password_reset_expires_minutes() . ' minuten.',
        'Heb je dit niet aangevraagd? Dan mag je dit bericht negeren.',
    ]);
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $siteName . ' <' . $from . '>',
        'Reply-To: ' . $from,
    ];

    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

/**
 * Request a reset email without revealing whether an address is known.
 *
 * Unknown addresses return success without creating a token. The UI can show a
 * single neutral message, while the real admin still receives a usable link.
 */
function admin_request_password_reset_email(array $config, string $email): bool
{
    $email = admin_normalized_username($email);
    $configuredEmail = admin_username();

    if ($email === '' || $configuredEmail === '' || !hash_equals($configuredEmail, $email)) {
        return true;
    }

    return admin_send_password_reset_email($config, $email, admin_generate_password_reset_link());
}

function admin_normalized_password_reset_tokens(array $tokens): array
{
    $valid = [];
    $now = time();

    foreach ($tokens as $token) {
        if (!is_array($token)) {
            continue;
        }

        $hash = (string) ($token['hash'] ?? '');
        $expiresAt = (int) ($token['expires_at'] ?? 0);

        if ($hash === '' || strlen($hash) !== 64 || $expiresAt <= $now) {
            continue;
        }

        $valid[] = [
            'hash' => $hash,
            'expires_at' => $expiresAt,
            'created_at' => (int) ($token['created_at'] ?? $now),
            'requested_ip' => (string) ($token['requested_ip'] ?? ''),
        ];
    }

    return $valid;
}

function admin_password_reset_token_exists(string $token): bool
{
    if ($token === '') {
        return false;
    }

    $settings = admin_settings();
    $tokens = admin_normalized_password_reset_tokens((array) ($settings['password_reset_tokens'] ?? []));

    if (($settings['password_reset_tokens'] ?? []) !== $tokens) {
        $settings['password_reset_tokens'] = $tokens;
        admin_write_settings($settings);
    }

    $hash = hash('sha256', $token);

    foreach ($tokens as $entry) {
        if (hash_equals((string) $entry['hash'], $hash)) {
            return true;
        }
    }

    return false;
}

function admin_generate_password_reset_link(): string
{
    $settings = admin_settings();
    $tokens = admin_normalized_password_reset_tokens((array) ($settings['password_reset_tokens'] ?? []));
    $token = bin2hex(random_bytes(32));
    $tokens[] = [
        'hash' => hash('sha256', $token),
        'expires_at' => time() + admin_password_reset_ttl_seconds(),
        'created_at' => time(),
        'requested_ip' => admin_client_ip(),
    ];

    // Keep only recent reset tokens to cap storage growth.
    $tokens = array_slice($tokens, -5);
    $settings['password_reset_tokens'] = $tokens;
    admin_write_settings($settings);

    $baseUrl = admin_absolute_script_url();
    $separator = str_contains($baseUrl, '?') ? '&' : '?';

    return $baseUrl . $separator . 'reset=' . rawurlencode($token);
}

function admin_reset_token_from_query($value): string
{
    if (!is_string($value)) {
        return '';
    }

    $token = strtolower(trim($value));

    if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
        return '';
    }

    return $token;
}

function admin_consume_password_reset_token(string $token, string $newPassword): bool
{
    if ($token === '') {
        return false;
    }

    $settings = admin_settings();
    $tokens = admin_normalized_password_reset_tokens((array) ($settings['password_reset_tokens'] ?? []));
    $tokenHash = hash('sha256', $token);
    $hasMatch = false;

    foreach ($tokens as $entry) {
        if (hash_equals((string) $entry['hash'], $tokenHash)) {
            $hasMatch = true;
            break;
        }
    }

    if (!$hasMatch) {
        return false;
    }

    $settings['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $settings['password_updated_at'] = date('c');
    $settings['password_reset_tokens'] = [];
    admin_write_settings($settings);
    admin_clear_login_attempts((string) ($settings['username'] ?? ''));

    return true;
}
