<?php
declare(strict_types=1);

/*
 * Admin authentication and password reset.
 *
 * This module covers credential creation/update, login throttling, neutral
 * password reset requests, reset-token storage and reset completion. Runtime
 * state is stored in JSON files protected by shared lock helpers so concurrent
 * requests cannot corrupt counters or reuse reset tokens.
 */

function admin_normalized_username(string $username): string
{
    $username = strtolower(trim($username));

    return filter_var($username, FILTER_VALIDATE_EMAIL) === false ? '' : $username;
}

function admin_login_email_is_valid(string $username): bool
{
    return admin_normalized_username($username) !== '';
}

function admin_auth_lock_path(): string
{
    return app_named_lock_path('admin-auth');
}

function admin_with_auth_lock(callable $callback)
{
    // Use one broad auth lock when credential files and reset-token files must
    // change together. This keeps password changes and token invalidation atomic
    // from the admin user's point of view.
    return app_with_file_lock(admin_auth_lock_path(), $callback, 'De beheerlogin kon niet veilig worden bijgewerkt.');
}

function admin_save_credentials(string $username, string $password): void
{
    $username = admin_normalized_username($username);

    if ($username === '') {
        throw new RuntimeException('Gebruik een geldig e-mailadres als login.');
    }

    admin_with_auth_lock(static function () use ($username, $password): void {
        admin_write_settings([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'password_updated_at' => date('c'),
        ]);
        admin_write_password_reset_tokens([]);
    });
}

function admin_update_credentials(string $username, ?string $password = null): void
{
    $username = admin_normalized_username($username);

    if ($username === '') {
        throw new RuntimeException('Gebruik een geldig e-mailadres als login.');
    }

    admin_with_auth_lock(static function () use ($username, $password): void {
        $settings = admin_settings();
        unset($settings['password_reset_tokens']);
        $previousUsername = admin_normalized_username((string) ($settings['username'] ?? ''));
        $settings['username'] = $username;

        $passwordChanged = $password !== null && $password !== '';
        $usernameChanged = $previousUsername === '' || !hash_equals($previousUsername, $username);

        if ($passwordChanged) {
            $settings['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $settings['password_updated_at'] = date('c');
        }

        admin_write_settings($settings);

        if ($passwordChanged || $usernameChanged) {
            admin_write_password_reset_tokens([]);
        }
    });
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
    return app_read_json_file(admin_login_attempts_path(), []);
}

function admin_write_login_attempts(array $attempts): void
{
    app_write_json_file(admin_login_attempts_path(), $attempts, 'Loginbeveiliging kon niet worden opgeslagen.');
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
    app_update_json_file(
        admin_login_attempts_path(),
        static function (array $attempts) use ($username): array {
            $attempts = admin_prune_login_attempts($attempts);
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

            return $attempts;
        },
        [],
        'Loginbeveiliging kon niet worden opgeslagen.'
    );
}

function admin_clear_login_attempts(string $username): void
{
    app_update_json_file(
        admin_login_attempts_path(),
        static function (array $attempts) use ($username): array {
            $attempts = admin_prune_login_attempts($attempts);

            foreach (admin_login_attempt_keys($username) as $key) {
                unset($attempts[$key]);
            }

            return $attempts;
        },
        [],
        'Loginbeveiliging kon niet worden opgeslagen.'
    );
}

function admin_login(string $username, string $password): bool
{
    /*
     * Login verifies both the normalized email and password hash. Failed
     * attempts are recorded against both IP and email+IP scopes, making brute
     * force attempts expensive without permanently locking out valid users.
     */
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
        unset($settings['password_reset_tokens']);
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

function admin_password_reset_attempts_path(): string
{
    return storage_path('password-reset-attempts.json');
}

function admin_password_reset_attempt_scope(string $scope): string
{
    $scope = preg_replace('/[^a-z0-9_-]/', '', strtolower($scope)) ?? '';

    return $scope === '' ? 'request' : $scope;
}

function admin_password_reset_attempt_keys(string $email, string $scope = 'request'): array
{
    $scope = admin_password_reset_attempt_scope($scope);
    $keys = [$scope . ':ip:' . hash('sha256', admin_client_ip())];
    $email = admin_normalized_username($email);

    if ($email !== '') {
        $keys[] = $scope . ':email:' . hash('sha256', $email . '|' . admin_client_ip());
    }

    return $keys;
}

function admin_read_password_reset_attempts(): array
{
    return app_read_json_file(admin_password_reset_attempts_path(), []);
}

function admin_write_password_reset_attempts(array $attempts): void
{
    app_write_json_runtime_file(
        admin_password_reset_attempts_path(),
        $attempts,
        'De resetbeveiliging kon niet worden opgeslagen.'
    );
}

function admin_password_reset_window_seconds(): int
{
    return 60 * 60;
}

function admin_password_reset_max_attempts(): int
{
    return 3;
}

function admin_prune_password_reset_attempts(array $attempts): array
{
    $minimum = time() - admin_password_reset_window_seconds();

    foreach ($attempts as $key => $timestamps) {
        $clean = [];

        foreach ((array) $timestamps as $timestamp) {
            $timestamp = (int) $timestamp;

            if ($timestamp >= $minimum) {
                $clean[] = $timestamp;
            }
        }

        if ($clean === []) {
            unset($attempts[$key]);
        } else {
            $attempts[$key] = $clean;
        }
    }

    return $attempts;
}

function admin_password_reset_throttle_seconds(string $email, string $scope = 'request'): int
{
    $attempts = admin_prune_password_reset_attempts(admin_read_password_reset_attempts());
    $now = time();
    $waitSeconds = 0;

    foreach (admin_password_reset_attempt_keys($email, $scope) as $key) {
        $timestamps = array_map('intval', (array) ($attempts[$key] ?? []));

        if (count($timestamps) < admin_password_reset_max_attempts()) {
            continue;
        }

        $oldest = min($timestamps);
        $waitSeconds = max($waitSeconds, admin_password_reset_window_seconds() - ($now - $oldest));
    }

    return max(0, $waitSeconds);
}

function admin_record_password_reset_attempt(string $email, string $scope = 'request'): void
{
    app_update_json_file(
        admin_password_reset_attempts_path(),
        static function (array $attempts) use ($email, $scope): array {
            $attempts = admin_prune_password_reset_attempts($attempts);

            foreach (admin_password_reset_attempt_keys($email, $scope) as $key) {
                $timestamps = array_map('intval', (array) ($attempts[$key] ?? []));
                $timestamps[] = time();
                $attempts[$key] = array_slice($timestamps, -admin_password_reset_max_attempts());
            }

            return $attempts;
        },
        [],
        'De resetbeveiliging kon niet worden opgeslagen.'
    );
}

function admin_clear_password_reset_attempts(string $email, string $scope = 'request'): void
{
    app_update_json_file(
        admin_password_reset_attempts_path(),
        static function (array $attempts) use ($email, $scope): array {
            $attempts = admin_prune_password_reset_attempts($attempts);

            foreach (admin_password_reset_attempt_keys($email, $scope) as $key) {
                unset($attempts[$key]);
            }

            return $attempts;
        },
        [],
        'De resetbeveiliging kon niet worden opgeslagen.'
    );
}

function admin_clean_mail_header(string $value): string
{
    return trim(str_replace(["\r", "\n"], ' ', $value));
}

/**
 * Send a password-reset link to the configured admin address.
 *
 * Mail delivery goes through the shared mail helper, so the same PHPMailer
 * SMTP settings can be used for contact and password reset messages.
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
    return app_send_email($config, $to, $subject, $body, $from !== false ? $from : '');
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

function admin_password_reset_tokens_path(): string
{
    return storage_path('reset_tokens.json');
}

function admin_read_password_reset_tokens(): array
{
    return app_with_file_lock(
        app_lock_path_for_file(admin_password_reset_tokens_path()),
        static function (): array {
            return admin_read_password_reset_tokens_unlocked();
        },
        'De reset-tokens konden niet worden gelezen.',
        LOCK_SH
    );
}

function admin_read_password_reset_tokens_unlocked(): array
{
    return app_read_json_file_unlocked(admin_password_reset_tokens_path(), []);
}

function admin_write_password_reset_tokens_unlocked(array $tokens): void
{
    app_write_json_file_unlocked(
        admin_password_reset_tokens_path(),
        admin_normalized_password_reset_tokens($tokens, false),
        'De reset-tokens konden niet worden opgeslagen.'
    );
}

function admin_write_password_reset_tokens(array $tokens): void
{
    app_write_json_runtime_file(
        admin_password_reset_tokens_path(),
        admin_normalized_password_reset_tokens($tokens, false),
        'De reset-tokens konden niet worden opgeslagen.'
    );
}

function admin_normalized_password_reset_tokens(array $tokens, bool $onlyUsable = true): array
{
    $valid = [];
    $now = time();

    foreach ($tokens as $token) {
        if (!is_array($token)) {
            continue;
        }

        $hash = (string) ($token['hash'] ?? '');
        $expiresAt = (int) ($token['expires'] ?? ($token['expires_at'] ?? 0));
        $used = (bool) ($token['used'] ?? false);

        if ($hash === '' || strlen($hash) !== 64 || $expiresAt <= $now || ($onlyUsable && $used)) {
            continue;
        }

        $valid[] = [
            'hash' => $hash,
            'expires' => $expiresAt,
            'created' => (int) ($token['created'] ?? ($token['created_at'] ?? $now)),
            'used' => $used,
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

    $tokens = admin_normalized_password_reset_tokens(admin_read_password_reset_tokens());
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
    $token = bin2hex(random_bytes(32));

    app_with_file_lock(
        app_lock_path_for_file(admin_password_reset_tokens_path()),
        static function () use ($token): void {
            $tokens = admin_normalized_password_reset_tokens(admin_read_password_reset_tokens_unlocked(), false);
            $now = time();
            $tokens[] = [
                'hash' => hash('sha256', $token),
                'expires' => $now + admin_password_reset_ttl_seconds(),
                'created' => $now,
                'used' => false,
                'requested_ip' => admin_client_ip(),
            ];

            // Keep only recent reset tokens to cap storage growth.
            admin_write_password_reset_tokens_unlocked(array_slice($tokens, -5));
        },
        'De resetlink kon niet worden aangemaakt.'
    );

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

    return (bool) admin_with_auth_lock(static function () use ($token, $newPassword): bool {
        return (bool) app_with_file_lock(
            app_lock_path_for_file(admin_password_reset_tokens_path()),
            static function () use ($token, $newPassword): bool {
                $settings = admin_settings();
                $tokens = admin_normalized_password_reset_tokens(admin_read_password_reset_tokens_unlocked(), false);
                $now = time();
                $tokenHash = hash('sha256', $token);
                $hasMatch = false;

                foreach ($tokens as $entry) {
                    $isUsable = !(bool) ($entry['used'] ?? false) && (int) ($entry['expires'] ?? 0) > $now;

                    if ($isUsable && hash_equals((string) $entry['hash'], $tokenHash)) {
                        $hasMatch = true;
                        break;
                    }
                }

                if (!$hasMatch) {
                    return false;
                }

                $settings['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                $settings['password_updated_at'] = date('c');
                unset($settings['password_reset_tokens']);

                // Invalidate every outstanding reset token before writing the new
                // password, so a second concurrent submit cannot reuse this link.
                admin_write_password_reset_tokens_unlocked([]);
                admin_write_settings($settings);
                admin_clear_login_attempts((string) ($settings['username'] ?? ''));

                return true;
            },
            'De resetlink kon niet worden gebruikt.'
        );
    });
}
