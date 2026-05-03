<?php
declare(strict_types=1);

function admin_append_deletion_message(array &$messages, array $deletionResult): void
{
    $deletedImages = $deletionResult['deleted'] ?? [];
    $failedImages = $deletionResult['failed'] ?? [];

    if ($deletedImages !== []) {
        $messages[] = 'Verwijderd uit assets: ' . implode(', ', $deletedImages);
    }

    if ($failedImages !== []) {
        $messages[] = 'De pagina is bewaard, maar deze bestanden konden niet fysiek uit assets/img worden verwijderd: '
            . implode(', ', $failedImages)
            . '. Controleer de bestandsrechten op de server.';
    }
}

function admin_is_ajax_request(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function admin_extract_posted_section(string $defaultSection): string
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['section'])) {
        return $defaultSection;
    }

    $postedSection = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $_POST['section']));

    return array_key_exists($postedSection, admin_sections()) ? $postedSection : $defaultSection;
}

function admin_wait_message(int $seconds): string
{
    $seconds = max(1, $seconds);

    return 'Te veel pogingen. Wacht ongeveer ' . $seconds . ' seconden en probeer opnieuw.';
}

function admin_controller_state(array $config): array
{
    admin_prepare_runtime();

    $messages = [];
    $errors = [];
    $section = admin_extract_posted_section(admin_requested_section());
    $isAjaxRequest = admin_is_ajax_request();
    $generatedResetLink = '';
    $requestedResetEmail = '';
    $resetToken = admin_reset_token_from_query($_GET['reset'] ?? '');

    if (isset($_GET['logout'])) {
        admin_logout();
        header('Location: index.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');

        try {
            if ($action === '' && $_POST === []) {
                $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
                $postLimit = admin_upload_post_limit_bytes();
                $errors[] = $contentLength > 0 && $postLimit > 0 && $contentLength > $postLimit
                    ? admin_upload_limit_message($contentLength)
                    : 'Er kwam geen formulierdata binnen. Controleer of de uploads niet groter zijn dan de PHP-limieten.';
            } elseif ($action === 'setup') {
                if (!admin_check_csrf($_POST)) {
                    $errors[] = 'De sessie is verlopen. Herlaad de pagina en probeer opnieuw.';
                } elseif (admin_is_configured()) {
                    $errors[] = 'Beheer is al ingesteld.';
                } else {
                    $username = trim((string) ($_POST['username'] ?? ''));
                    $password = (string) ($_POST['password'] ?? '');
                    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

                    if (!admin_login_email_is_valid($username)) {
                        $errors[] = 'Gebruik een geldig e-mailadres als login.';
                    } elseif (strlen($password) < 10) {
                        $errors[] = 'Kies een wachtwoord van minstens 10 tekens.';
                    } elseif ($password !== $confirmPassword) {
                        $errors[] = 'De wachtwoorden komen niet overeen.';
                    } else {
                        admin_save_credentials($username, $password);
                        admin_login($username, $password);
                        $messages[] = 'Beheer is ingesteld. Je bent nu aangemeld.';
                    }
                }
            } elseif ($action === 'login') {
                if (!admin_check_csrf($_POST)) {
                    $errors[] = 'De sessie is verlopen. Herlaad de pagina en probeer opnieuw.';
                } else {
                    $username = trim((string) ($_POST['username'] ?? ''));
                    $password = (string) ($_POST['password'] ?? '');
                    $waitSeconds = admin_login_throttle_seconds($username);

                    if ($waitSeconds > 0) {
                        $errors[] = admin_wait_message($waitSeconds);
                    } elseif (!admin_login($username, $password)) {
                        $waitSeconds = admin_login_throttle_seconds($username);
                        $errors[] = $waitSeconds > 0
                            ? admin_wait_message($waitSeconds)
                            : 'Ongeldige login of wachtwoord.';
                    }
                }
            } elseif ($action === 'request-password-reset') {
                $requestedResetEmail = trim((string) ($_POST['reset_email'] ?? ''));

                // Keep the public response neutral so the login page does not
                // reveal whether an e-mail address belongs to the admin account.
                if (!admin_check_csrf($_POST)) {
                    $errors[] = 'De sessie is verlopen. Herlaad de pagina en probeer opnieuw.';
                } elseif (!admin_login_email_is_valid($requestedResetEmail)) {
                    $errors[] = 'Vul een geldig e-mailadres in.';
                } elseif (!admin_is_configured()) {
                    $messages[] = admin_password_reset_request_message();
                } elseif (!admin_request_password_reset_email($config, $requestedResetEmail)) {
                    $errors[] = 'De resetmail kon niet worden verzonden. Controleer de mailinstellingen van de hosting.';
                } else {
                    $messages[] = admin_password_reset_request_message();
                    $requestedResetEmail = '';
                }
            } elseif ($action === 'complete-password-reset') {
                if (!admin_check_csrf($_POST)) {
                    $errors[] = 'De sessie is verlopen. Herlaad de pagina en probeer opnieuw.';
                } else {
                    $token = admin_reset_token_from_query($_POST['reset_token'] ?? '');
                    $password = (string) ($_POST['password'] ?? '');
                    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

                    if ($token === '' || !admin_password_reset_token_exists($token)) {
                        $errors[] = 'De resetlink is ongeldig of verlopen.';
                    } elseif (strlen($password) < 10) {
                        $errors[] = 'Kies een wachtwoord van minstens 10 tekens.';
                    } elseif ($password !== $confirmPassword) {
                        $errors[] = 'De wachtwoorden komen niet overeen.';
                    } elseif (!admin_consume_password_reset_token($token, $password)) {
                        $errors[] = 'De resetlink kon niet worden gebruikt.';
                    } else {
                        admin_login(admin_username(), $password);
                        $messages[] = 'Je wachtwoord is opnieuw ingesteld.';
                        $resetToken = '';
                    }
                }
            } elseif (!admin_is_logged_in()) {
                $errors[] = 'Meld je eerst aan.';
            } elseif (!admin_check_csrf($_POST)) {
                $errors[] = 'De sessie is verlopen. Probeer opnieuw.';
            } elseif ($action === 'save-credentials') {
                $username = trim((string) ($_POST['username'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

                if (!admin_login_email_is_valid($username)) {
                    $errors[] = 'Gebruik een geldig e-mailadres als login.';
                } elseif ($password !== '' && strlen($password) < 10) {
                    $errors[] = 'Kies een wachtwoord van minstens 10 tekens.';
                } elseif ($password !== $confirmPassword) {
                    $errors[] = 'De wachtwoorden komen niet overeen.';
                } else {
                    admin_update_credentials($username, $password === '' ? null : $password);
                    $messages[] = 'E-mail en wachtwoordinstellingen zijn bewaard.';
                }
            } elseif ($action === 'generate-password-reset-link') {
                $generatedResetLink = admin_generate_password_reset_link();
                $messages[] = 'Nieuwe resetlink aangemaakt. Deze link verloopt na ' . admin_password_reset_expires_minutes() . ' minuten.';
            } elseif ($action === 'save-general') {
                $config = admin_save_general($config, $_POST, $_FILES);
                save_content($config);
                $messages[] = 'Algemene instellingen zijn bewaard.';
                admin_append_deletion_message($messages, admin_flush_media_deletions($config));
            } elseif ($action === 'save-page') {
                $config = admin_save_page_content($config, (string) ($_POST['page_key'] ?? ''), $_POST, $_FILES);
                save_content($config);
                $messages[] = 'Pagina is bewaard.';
                admin_append_deletion_message($messages, admin_flush_media_deletions($config));
            } elseif ($action === 'save-room') {
                $config = admin_save_room_content($config, (string) ($_POST['room_key'] ?? ''), $_POST, $_FILES);
                save_content($config);
                $messages[] = 'Kamer is bewaard.';
                admin_append_deletion_message($messages, admin_flush_media_deletions($config));
            } elseif ($action === 'save-links') {
                $config = admin_save_links_content($config, $_POST);
                save_content($config);
                $messages[] = 'Links zijn bewaard.';
            } else {
                $errors[] = 'Onbekende actie.';
            }
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    $configured = admin_is_configured();
    $loggedIn = admin_is_logged_in();
    $resetTokenValid = $resetToken !== '' && admin_password_reset_token_exists($resetToken);

    if ($resetToken !== '' && !$resetTokenValid && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $errors[] = 'Deze resetlink is ongeldig of verlopen.';
    }

    if ($isAjaxRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $errors === [],
            'messages' => $messages,
            'errors' => $errors,
            'section' => $section,
            'reset_link' => $generatedResetLink,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    return [
        'config' => $config,
        'messages' => $messages,
        'errors' => $errors,
        'section' => $section,
        'configured' => $configured,
        'loggedIn' => $loggedIn,
        'csrfToken' => admin_csrf_token(),
        'adminUsername' => admin_username(),
        'requestedResetEmail' => $requestedResetEmail,
        'bookingWidget' => booking_widget_settings($config),
        'siteLogo' => admin_safe_media_filename((string) ($config['site']['logo'] ?? '')),
        'siteFavicon' => admin_safe_media_filename((string) ($config['site']['favicon'] ?? '')),
        'passwordReset' => [
            'token' => $resetToken,
            'isValid' => $resetTokenValid,
            'generatedLink' => $generatedResetLink,
            'expiresMinutes' => admin_password_reset_expires_minutes(),
        ],
    ];
}
