<?php
declare(strict_types=1);

function app_mail_settings_path(): string
{
    return storage_path('mail-settings.json');
}

function app_default_mail_settings(): array
{
    return [
        'enabled' => false,
        'host' => '',
        'port' => 587,
        'smtp_auth' => true,
        'username' => '',
        'password' => '',
        'encryption' => 'starttls',
        'from_email' => '',
        'from_name' => '',
    ];
}

function app_normalized_mail_encryption(string $value): string
{
    $value = strtolower(trim($value));

    return in_array($value, ['none', 'starttls', 'smtps'], true) ? $value : 'starttls';
}

function app_normalized_mail_settings(array $settings): array
{
    $defaults = app_default_mail_settings();
    $settings = array_merge($defaults, $settings);
    $port = (int) $settings['port'];

    $settings['enabled'] = (bool) $settings['enabled'];
    $settings['host'] = trim((string) $settings['host']);
    $settings['port'] = $port > 0 && $port <= 65535 ? $port : $defaults['port'];
    $settings['smtp_auth'] = (bool) $settings['smtp_auth'];
    $settings['username'] = trim((string) $settings['username']);
    $settings['password'] = (string) $settings['password'];
    $settings['encryption'] = app_normalized_mail_encryption((string) $settings['encryption']);
    $settings['from_email'] = trim((string) $settings['from_email']);
    $settings['from_name'] = trim((string) $settings['from_name']);

    return $settings;
}

function app_mail_settings(): array
{
    $path = app_mail_settings_path();

    if (!is_file($path)) {
        return app_default_mail_settings();
    }

    $json = file_get_contents($path);
    $decoded = json_decode((string) $json, true);

    return app_normalized_mail_settings(is_array($decoded) ? $decoded : []);
}

function app_mail_password_is_set(): bool
{
    return (string) (app_mail_settings()['password'] ?? '') !== '';
}

function app_write_json_runtime_file(string $path, array $data, string $errorMessage): void
{
    if (!is_dir(storage_path())) {
        if (!mkdir(storage_path(), 0775, true) && !is_dir(storage_path())) {
            throw new RuntimeException('De opslagmap kon niet worden aangemaakt.');
        }
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        throw new RuntimeException($errorMessage);
    }

    $temporaryPath = $path . '.tmp';

    if (file_put_contents($temporaryPath, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException($errorMessage);
    }

    if (!@rename($temporaryPath, $path)) {
        if (!@copy($temporaryPath, $path)) {
            @unlink($temporaryPath);
            throw new RuntimeException($errorMessage);
        }

        @unlink($temporaryPath);
    }
}

function app_write_mail_settings(array $settings): void
{
    app_write_json_runtime_file(
        app_mail_settings_path(),
        app_normalized_mail_settings($settings),
        'De mailinstellingen konden niet worden opgeslagen.'
    );
}

function app_save_mail_settings_from_post(array $post): void
{
    $current = app_mail_settings();
    $input = is_array($post['mail_settings'] ?? null) ? $post['mail_settings'] : [];
    $port = filter_var($input['port'] ?? 587, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    $password = array_key_exists('password', $input) && (string) $input['password'] !== ''
        ? (string) $input['password']
        : (string) ($current['password'] ?? '');

    if (($input['clear_password'] ?? '0') === '1') {
        $password = '';
    }

    $settings = app_normalized_mail_settings([
        'enabled' => (string) ($input['enabled'] ?? '0') === '1',
        'host' => (string) ($input['host'] ?? ''),
        'port' => $port === false ? 587 : $port,
        'smtp_auth' => (string) ($input['smtp_auth'] ?? '0') === '1',
        'username' => (string) ($input['username'] ?? ''),
        'password' => $password,
        'encryption' => (string) ($input['encryption'] ?? 'starttls'),
        'from_email' => (string) ($input['from_email'] ?? ''),
        'from_name' => (string) ($input['from_name'] ?? ''),
    ]);

    if ($settings['enabled'] && $settings['host'] === '') {
        throw new RuntimeException('Vul een SMTP-host in of schakel SMTP uit.');
    }

    if ($settings['smtp_auth'] && $settings['enabled'] && $settings['username'] === '') {
        throw new RuntimeException('Vul een SMTP-gebruikersnaam in of schakel SMTP-authenticatie uit.');
    }

    if ($settings['from_email'] !== '' && filter_var($settings['from_email'], FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Het afzenderadres heeft geen geldig e-mailadres.');
    }

    app_write_mail_settings($settings);
}

function app_clean_mail_header(string $value): string
{
    return trim(str_replace(["\r", "\n"], ' ', $value));
}

function app_mail_bootstrap_phpmailer(): bool
{
    if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        return true;
    }

    $autoloadPath = base_path('vendor/autoload.php');

    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
    }

    if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        return true;
    }

    foreach ([base_path('PHPMailer/src'), base_path('includes/PHPMailer/src'), base_path('lib/PHPMailer/src')] as $directory) {
        $phpmailerPath = $directory . DIRECTORY_SEPARATOR . 'PHPMailer.php';
        $smtpPath = $directory . DIRECTORY_SEPARATOR . 'SMTP.php';
        $exceptionPath = $directory . DIRECTORY_SEPARATOR . 'Exception.php';

        if (is_file($phpmailerPath) && is_file($smtpPath) && is_file($exceptionPath)) {
            require_once $exceptionPath;
            require_once $phpmailerPath;
            require_once $smtpPath;
            break;
        }
    }

    return class_exists(\PHPMailer\PHPMailer\PHPMailer::class);
}

function app_phpmailer_encryption(string $encryption): string
{
    if ($encryption === 'smtps') {
        return \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    }

    if ($encryption === 'starttls') {
        return \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    }

    return '';
}

function app_send_email_with_phpmailer(array $settings, string $to, string $subject, string $body, string $fromEmail, string $fromName, string $replyTo = ''): bool
{
    if (!app_mail_bootstrap_phpmailer()) {
        return false;
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = $settings['host'];
        $mail->SMTPAuth = (bool) $settings['smtp_auth'];
        $mail->Username = $settings['username'];
        $mail->Password = $settings['password'];
        $mail->SMTPSecure = app_phpmailer_encryption((string) $settings['encryption']);
        $mail->SMTPAutoTLS = $settings['encryption'] !== 'none';
        $mail->Port = (int) $settings['port'];
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);

        if ($replyTo !== '') {
            $mail->addReplyTo($replyTo);
        }

        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $body;

        return $mail->send();
    } catch (Throwable $exception) {
        return false;
    }
}

function app_send_email_with_native_mail(string $to, string $subject, string $body, string $fromEmail, string $fromName, string $replyTo = ''): bool
{
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . app_clean_mail_header($fromName) . ' <' . $fromEmail . '>',
    ];

    if ($replyTo !== '') {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

function app_send_email(array $config, string $to, string $subject, string $body, string $replyTo = ''): bool
{
    $to = filter_var($to, FILTER_VALIDATE_EMAIL);
    $settings = app_mail_settings();
    $fallbackFromEmail = filter_var((string) ($config['site']['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $fromEmail = filter_var((string) ($settings['from_email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $fromName = app_clean_mail_header((string) ($settings['from_name'] ?: ($config['site']['name'] ?? 'Lodging at 8')));
    $replyTo = filter_var($replyTo, FILTER_VALIDATE_EMAIL) === false ? '' : $replyTo;

    if ($to === false) {
        return false;
    }

    if ($fromEmail === false) {
        $fromEmail = $fallbackFromEmail !== false ? $fallbackFromEmail : $to;
    }

    $subject = app_clean_mail_header($subject);

    if ((bool) ($settings['enabled'] ?? false)) {
        if ((string) ($settings['host'] ?? '') === '') {
            return false;
        }

        return app_send_email_with_phpmailer($settings, $to, $subject, $body, $fromEmail, $fromName, $replyTo);
    }

    return app_send_email_with_native_mail($to, $subject, $body, $fromEmail, $fromName, $replyTo);
}
