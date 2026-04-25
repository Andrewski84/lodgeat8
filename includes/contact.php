<?php
declare(strict_types=1);

function contact_messages_path(): string
{
    return storage_path('contact-messages.json');
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
    ];
}

function contact_clean_line(string $value): string
{
    return trim(str_replace(["\r", "\n"], ' ', $value));
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

function handle_contact_submission(array $config, array $post): array
{
    $result = contact_empty_result();
    $result['submitted'] = true;
    $result['values'] = [
        'name' => contact_clean_line((string) ($post['name'] ?? '')),
        'email' => contact_clean_line((string) ($post['email'] ?? '')),
        'phone' => contact_clean_line((string) ($post['phone'] ?? '')),
        'subject' => contact_clean_line((string) ($post['subject'] ?? '')),
        'message' => trim((string) ($post['message'] ?? '')),
    ];

    if (trim((string) ($post['website'] ?? '')) !== '') {
        $result['success'] = true;
        return $result;
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
    }

    if ($result['errors'] !== []) {
        return $result;
    }

    $message = $result['values'] + [
        'created_at' => date('c'),
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'email_sent' => false,
    ];

    $message['email_sent'] = contact_send_email($config, $message);
    contact_store_message($message);
    $result['success'] = true;

    return $result;
}
