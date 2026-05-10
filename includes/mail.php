<?php
declare(strict_types=1);

/*
 * Mail delivery adapter.
 *
 * Mail settings are read from storage/mail-settings.json at runtime. The admin
 * area deliberately does not edit those technical settings; deployments can
 * choose native PHP mail() or PHPMailer SMTP by editing that JSON file directly
 * on the server.
 *
 * app_send_email() validates addresses, chooses sender defaults from site
 * content, and then tries PHPMailer only when SMTP is explicitly enabled.
 * Otherwise it falls back to native mail().
 */

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

function app_write_json_runtime_file(string $path, array $data, string $errorMessage): void
{
    app_write_json_file($path, $data, $errorMessage);
}

function app_clean_mail_header(string $value): string
{
    return trim(str_replace(["\r", "\n"], ' ', $value));
}

function app_mail_bootstrap_phpmailer(): bool
{
    /*
     * Support several deployment styles: Composer's vendor/autoload.php or a
     * manually uploaded PHPMailer/src directory in common locations. This keeps
     * the site usable on shared hosting where Composer may not run.
     */
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
        app_log_exception($exception, 'phpmailer send');
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
    /*
     * A false return means delivery could not be confirmed. Callers still keep
     * local JSON records where appropriate, because host-level mail failures
     * should not make visitor messages disappear.
     */
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
