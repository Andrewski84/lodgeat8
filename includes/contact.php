<?php
declare(strict_types=1);

/*
 * Public contact form handling.
 *
 * The form always keeps a JSON copy of messages in storage/contact-messages.json
 * and then attempts email delivery through the shared mail helper. Email can
 * fail on some hosting plans, so the JSON file is the reliable local record.
 *
 * Runtime defenses live here as well: session-backed CSRF, a hidden honeypot,
 * minimum submit-time validation and per-IP rate limiting. On validation errors
 * the visitor's values are kept; after successful submission they are cleared
 * so the rendered form returns empty.
 */

function contact_messages_path(): string
{
    return storage_path('contact-messages.json');
}

function contact_rate_limit_path(): string
{
    return storage_path('contact-rate-limit.json');
}

function contact_prepare_runtime(): void
{
    /*
     * The public form uses its own session name so visitor-facing CSRF state is
     * isolated from admin authentication state.
     */
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_samesite', 'Lax');

    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        @ini_set('session.cookie_secure', '1');
    }

    if (session_name() === 'PHPSESSID') {
        session_name('lodging_site');
    }

    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

function contact_csrf_token(): string
{
    contact_prepare_runtime();

    if (!isset($_SESSION['contact_csrf_token']) || !is_string($_SESSION['contact_csrf_token'])) {
        $_SESSION['contact_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['contact_csrf_token'];
}

function contact_form_started_at(): int
{
    contact_prepare_runtime();

    if (!isset($_SESSION['contact_form_started_at'])) {
        $_SESSION['contact_form_started_at'] = time();
    }

    return (int) $_SESSION['contact_form_started_at'];
}

function contact_reset_form_runtime(): void
{
    contact_prepare_runtime();
    $_SESSION['contact_csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['contact_form_started_at'] = time();
}

function contact_result_with_runtime(array $result): array
{
    $result['csrf_token'] = contact_csrf_token();
    $result['form_started_at'] = contact_form_started_at();

    return $result;
}

function contact_empty_result(): array
{
    return [
        'submitted' => false,
        'success' => false,
        'errors' => [],
        'values' => contact_empty_values(),
        'csrf_token' => '',
        'form_started_at' => 0,
        'phone_invalid' => false,
    ];
}

function contact_empty_values(): array
{
    return [
        'name' => '',
        'email' => '',
        'phone' => '',
        'subject' => '',
        'message' => '',
    ];
}

function contact_clean_line(string $value): string
{
    return trim(str_replace(["\r", "\n"], ' ', $value));
}

function contact_max_lengths(): array
{
    return [
        'name' => 120,
        'email' => 254,
        'phone' => 60,
        'subject' => 160,
        'message' => 5000,
        'user_agent' => 500,
        'referer' => 500,
    ];
}

function contact_max_length(string $field): int
{
    $lengths = contact_max_lengths();

    return (int) ($lengths[$field] ?? 500);
}

function contact_strlen(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function contact_substr(string $value, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
}

function contact_truncate(string $value, int $length): string
{
    return contact_strlen($value) > $length ? contact_substr($value, $length) : $value;
}

function contact_client_ip(): string
{
    /*
     * Check trusted proxy headers if available, otherwise fall back to REMOTE_ADDR.
     * This handles load balancers and CDN scenarios (e.g., Cloudflare, AWS ALB).
     */
    $ip = '';

    // Cloudflare
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    // Standard X-Forwarded-For (first IP in list)
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = trim((string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ips = array_map('trim', explode(',', $forwarded));
        $ip = $ips[0] ?? '';
    }
    // X-Real-IP (nginx proxy)
    elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = trim((string) $_SERVER['HTTP_X_REAL_IP']);
    }
    // Direct connection
    elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = trim((string) $_SERVER['REMOTE_ADDR']);
    }

    // Basic validation: ensure it looks like an IP
    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }

    return 'unknown';
}

function contact_read_rate_limit(): array
{
    return app_read_json_file(contact_rate_limit_path(), []);
}

function contact_write_rate_limit(array $state): void
{
    app_write_json_file(contact_rate_limit_path(), $state, 'De contactbeveiliging kon niet worden opgeslagen.');
}

function contact_rate_limit_max_per_hour(): int
{
    return 5;
}

function contact_pruned_timestamps(array $timestamps): array
{
    $minimum = time() - 3600;
    $clean = [];

    foreach ($timestamps as $timestamp) {
        $timestamp = (int) $timestamp;

        if ($timestamp >= $minimum) {
            $clean[] = $timestamp;
        }
    }

    return $clean;
}

function contact_rate_limit_wait_seconds(string $ip): int
{
    $state = contact_read_rate_limit();
    $timestamps = contact_pruned_timestamps((array) ($state[$ip] ?? []));

    if (count($timestamps) < contact_rate_limit_max_per_hour()) {
        return 0;
    }

    $oldest = min($timestamps);
    $wait = 3600 - (time() - $oldest);

    return max(0, $wait);
}

function contact_record_submission(string $ip): void
{
    app_update_json_file(
        contact_rate_limit_path(),
        static function (array $state) use ($ip): array {
            $state[$ip] = contact_pruned_timestamps((array) ($state[$ip] ?? []));
            $state[$ip][] = time();

            foreach ($state as $key => $timestamps) {
                $pruned = contact_pruned_timestamps((array) $timestamps);

                if ($pruned === []) {
                    unset($state[$key]);
                } else {
                    $state[$key] = $pruned;
                }
            }

            return $state;
        },
        [],
        'De contactbeveiliging kon niet worden opgeslagen.'
    );
}

function contact_store_message(array $message): void
{
    app_update_json_file(
        contact_messages_path(),
        static function (array $messages) use ($message): array {
            $messages[] = $message;

            return $messages;
        },
        [],
        'Het bericht kon niet worden opgeslagen.'
    );
}

function contact_send_email(array $config, array $message): bool
{
    $to = filter_var((string) ($config['site']['email'] ?? ''), FILTER_VALIDATE_EMAIL);

    if ($to === false) {
        return false;
    }

    $subject = contact_clean_line((string) ($message['subject'] ?: 'Nieuw bericht via Lodging at 8'));
    $from = filter_var((string) $message['email'], FILTER_VALIDATE_EMAIL);
    $body = implode("\n", [
        'Naam: ' . $message['name'],
        'E-mail: ' . $message['email'],
        'Telefoon: ' . $message['phone'],
        'Onderwerp: ' . $message['subject'],
        '',
        $message['message'],
    ]);
    return app_send_email($config, $to, $subject, $body, $from !== false ? $from : '');
}

function contact_validate_phone(string $phone): bool
{
    /*
     * Basic phone validation: allow digits, spaces, hyphens, parentheses, and plus sign.
     * Must be at least 5 characters (rough international minimum).
     */
    if (contact_strlen($phone) < 5) {
        return false;
    }

    // Allow: +1-234-567-8900, (123) 456 7890, +33 1 23 45 67 89, etc.
    $pattern = '/^[\d\s\-()+]+$/u';

    return (bool) preg_match($pattern, $phone);
}

function contact_submission_min_seconds(): int
{
    return 2;
}

function handle_contact_submission(array $config, array $post): array
{
    /*
     * Normalize all user-provided values into $result before validating. The
     * template then receives one consistent shape for untouched forms, errors
     * and successful submissions.
     */
    contact_prepare_runtime();

    $result = contact_empty_result();
    $result['submitted'] = true;
    $result['values'] = [
        'name' => contact_clean_line((string) ($post['name'] ?? '')),
        'email' => contact_clean_line((string) ($post['email'] ?? '')),
        'phone' => contact_clean_line((string) ($post['phone'] ?? '')),
        'subject' => contact_clean_line((string) ($post['subject'] ?? '')),
        'message' => trim((string) ($post['message'] ?? '')),
    ];

    // Honeypot field: real visitors never see it, simple spambots often fill it.
    if (trim((string) ($post['website'] ?? '')) !== '') {
        $result['success'] = true;
        $result['values'] = contact_empty_values();
        contact_reset_form_runtime();
        return $result;
    }

    /*
     * Runtime errors must block delivery, but visible field errors should stay
     * the most actionable feedback for visitors. A stale token combined with a
     * bad field value is still rejected, and the hidden state is refreshed below.
     */
    $runtimeErrors = [];
    $refreshRuntime = false;
    $postedToken = (string) ($post['csrf_token'] ?? '');
    $validToken = hash_equals(contact_csrf_token(), $postedToken);

    if (!$validToken) {
        $runtimeErrors[] = 'Je sessie is verlopen. Herlaad de pagina en probeer opnieuw.';
        $refreshRuntime = true;
    }

    $startedAt = (int) ($post['form_started_at'] ?? 0);
    $expectedStartedAt = contact_form_started_at();

    if ($startedAt <= 0 || $startedAt !== $expectedStartedAt) {
        $runtimeErrors[] = 'Kon de formulierstatus niet bevestigen. Herlaad de pagina en probeer opnieuw.';
        $refreshRuntime = true;
    } elseif ((time() - $startedAt) < contact_submission_min_seconds()) {
        $runtimeErrors[] = 'Het formulier werd te snel verzonden. Probeer opnieuw.';
    }

    $ip = contact_client_ip();
    $waitSeconds = contact_rate_limit_wait_seconds($ip);

    if ($waitSeconds > 0) {
        $runtimeErrors[] = 'Je verstuurde recent al meerdere berichten. Wacht ' . $waitSeconds . ' seconden en probeer opnieuw.';
    }

    if ($result['values']['name'] === '') {
        $result['errors'][] = 'Vul je naam in.';
    } elseif (contact_strlen($result['values']['name']) > contact_max_length('name')) {
        $result['errors'][] = 'Je naam is te lang. Gebruik maximum ' . contact_max_length('name') . ' tekens.';
    }

    if (!filter_var($result['values']['email'], FILTER_VALIDATE_EMAIL)) {
        $result['errors'][] = 'Vul een geldig e-mailadres in.';
    } elseif (contact_strlen($result['values']['email']) > contact_max_length('email')) {
        $result['errors'][] = 'Je e-mailadres is te lang.';
    }

    if ($result['values']['phone'] === '') {
        $result['errors'][] = 'Vul je telefoonnummer in.';
    } elseif (contact_strlen($result['values']['phone']) > contact_max_length('phone')) {
        $result['errors'][] = 'Je telefoonnummer is te lang. Gebruik maximum ' . contact_max_length('phone') . ' tekens.';
    } elseif (!contact_validate_phone($result['values']['phone'])) {
        // Invalid format: mark the field visually, but keep the message area quiet.
        $result['phone_invalid'] = true;
    }

    if (contact_strlen($result['values']['subject']) > contact_max_length('subject')) {
        $result['errors'][] = 'Je onderwerp is te lang. Gebruik maximum ' . contact_max_length('subject') . ' tekens.';
    }

    if ($result['values']['message'] === '') {
        $result['errors'][] = 'Vul je bericht in.';
    } elseif (contact_strlen($result['values']['message']) > contact_max_length('message')) {
        $result['errors'][] = 'Je bericht is te lang. Gebruik maximum ' . contact_max_length('message') . ' tekens.';
    }

    if ($result['errors'] !== [] || $result['phone_invalid']) {
        // Keep submitted values on validation failures so visitors can fix only
        // the fields that need attention.
        if ($refreshRuntime) {
            contact_reset_form_runtime();
        }

        return $result;
    }

    if ($runtimeErrors !== []) {
        $result['errors'] = $runtimeErrors;

        if ($refreshRuntime) {
            contact_reset_form_runtime();
        }

        return $result;
    }

    $message = $result['values'] + [
        'id' => bin2hex(random_bytes(8)),
        'created_at' => date('c'),
        'ip' => $ip,
        'user_agent' => contact_truncate((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), contact_max_length('user_agent')),
        'referer' => contact_truncate((string) ($_SERVER['HTTP_REFERER'] ?? ''), contact_max_length('referer')),
        'email_sent' => false,
    ];

    $emailSent = contact_send_email($config, $message);
    $message['email_sent'] = $emailSent;
    contact_store_message($message);
    contact_record_submission($ip);
    contact_reset_form_runtime();
    $result['success'] = true;

    /*
     * Email delivery is a best-effort attempt; message always saves locally in JSON.
     * If email failed, log it for admin review but don't show error to visitor
     * (message is safely stored). In production, consider logging to file or monitoring.
     */
    if (!$emailSent) {
        error_log('Contact form email delivery failed for message ID: ' . $message['id']);
    }

    // After a confirmed save/email attempt, return an empty form for the next
    // message. This is intentionally done after contact_store_message().
    $result['values'] = contact_empty_values();

    return $result;
}
