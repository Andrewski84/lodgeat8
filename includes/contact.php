<?php
declare(strict_types=1);

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
        'values' => [
            'name' => '',
            'email' => '',
            'phone' => '',
            'subject' => '',
            'message' => '',
        ],
        'csrf_token' => '',
        'form_started_at' => 0,
    ];
}

function contact_clean_line(string $value): string
{
    return trim(str_replace(["\r", "\n"], ' ', $value));
}

function contact_client_ip(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    return $ip === '' ? 'unknown' : $ip;
}

function contact_read_rate_limit(): array
{
    $path = contact_rate_limit_path();

    if (!is_file($path)) {
        return [];
    }

    $json = file_get_contents($path);
    $data = json_decode((string) $json, true);

    return is_array($data) ? $data : [];
}

function contact_write_rate_limit(array $state): void
{
    if (!is_dir(storage_path())) {
        if (!mkdir(storage_path(), 0775, true) && !is_dir(storage_path())) {
            throw new RuntimeException('De opslagmap kon niet worden aangemaakt.');
        }
    }

    $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        throw new RuntimeException('De contactbeveiliging kon niet worden opgeslagen.');
    }

    $temporaryPath = contact_rate_limit_path() . '.tmp';

    if (file_put_contents($temporaryPath, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('De contactbeveiliging kon niet tijdelijk worden opgeslagen.');
    }

    if (!@rename($temporaryPath, contact_rate_limit_path())) {
        if (!@copy($temporaryPath, contact_rate_limit_path())) {
            @unlink($temporaryPath);
            throw new RuntimeException('De contactbeveiliging kon niet definitief worden opgeslagen.');
        }

        @unlink($temporaryPath);
    }
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
    $state = contact_read_rate_limit();
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

    contact_write_rate_limit($state);
}

function contact_store_message(array $message): void
{
    if (!is_dir(storage_path())) {
        if (!mkdir(storage_path(), 0775, true) && !is_dir(storage_path())) {
            throw new RuntimeException('De opslagmap kon niet worden aangemaakt.');
        }
    }

    $messages = [];
    $path = contact_messages_path();

    if (is_file($path)) {
        $json = file_get_contents($path);
        $decoded = json_decode((string) $json, true);
        $messages = is_array($decoded) ? $decoded : [];
    }

    $messages[] = $message;
    $json = json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        throw new RuntimeException('Het bericht kon niet naar JSON worden omgezet.');
    }

    // Store form submissions even when mail() is blocked by hosting.
    $temporaryPath = $path . '.tmp';

    if (file_put_contents($temporaryPath, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Het bericht kon niet worden opgeslagen.');
    }

    if (!@rename($temporaryPath, $path)) {
        if (!@copy($temporaryPath, $path)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Het bericht kon niet definitief worden opgeslagen.');
        }

        @unlink($temporaryPath);
    }
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
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: Lodging at 8 <' . $to . '>',
    ];

    if ($from !== false) {
        $headers[] = 'Reply-To: ' . $from;
    }

    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

function contact_submission_min_seconds(): int
{
    return 2;
}

function handle_contact_submission(array $config, array $post): array
{
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
        contact_reset_form_runtime();
        return $result;
    }

    $postedToken = (string) ($post['csrf_token'] ?? '');
    $validToken = hash_equals(contact_csrf_token(), $postedToken);

    if (!$validToken) {
        $result['errors'][] = 'Je sessie is verlopen. Herlaad de pagina en probeer opnieuw.';
    }

    $startedAt = (int) ($post['form_started_at'] ?? 0);
    $expectedStartedAt = contact_form_started_at();

    if ($startedAt <= 0 || $startedAt !== $expectedStartedAt) {
        $result['errors'][] = 'Kon de formulierstatus niet bevestigen. Herlaad de pagina en probeer opnieuw.';
    } elseif ((time() - $startedAt) < contact_submission_min_seconds()) {
        $result['errors'][] = 'Het formulier werd te snel verzonden. Probeer opnieuw.';
    }

    $ip = contact_client_ip();
    $waitSeconds = contact_rate_limit_wait_seconds($ip);

    if ($waitSeconds > 0) {
        $result['errors'][] = 'Je verstuurde recent al meerdere berichten. Wacht ' . $waitSeconds . ' seconden en probeer opnieuw.';
    }

    if ($result['values']['name'] === '') {
        $result['errors'][] = 'Vul je naam in.';
    }

    if (!filter_var($result['values']['email'], FILTER_VALIDATE_EMAIL)) {
        $result['errors'][] = 'Vul een geldig e-mailadres in.';
    }

    if ($result['values']['phone'] === '') {
        $result['errors'][] = 'Vul je telefoonnummer in.';
    }

    if ($result['values']['message'] === '') {
        $result['errors'][] = 'Vul je bericht in.';
    } elseif (mb_strlen($result['values']['message']) > 5000) {
        $result['errors'][] = 'Je bericht is te lang. Gebruik maximum 5000 tekens.';
    }

    if ($result['errors'] !== []) {
        return $result;
    }

    $message = $result['values'] + [
        'id' => bin2hex(random_bytes(8)),
        'created_at' => date('c'),
        'ip' => $ip,
        'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        'referer' => (string) ($_SERVER['HTTP_REFERER'] ?? ''),
        'email_sent' => false,
    ];

    $message['email_sent'] = contact_send_email($config, $message);
    contact_store_message($message);
    contact_record_submission($ip);
    contact_reset_form_runtime();
    $result['success'] = true;

    return $result;
}
