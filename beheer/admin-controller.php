<?php
declare(strict_types=1);

function admin_prepare_runtime(): void
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function admin_append_deletion_message(array &$messages, array $deletedImages): void
{
    if ($deletedImages === []) {
        return;
    }

    $messages[] = 'Verwijderd uit assets: ' . implode(', ', $deletedImages);
}

function admin_controller_state(array $config): array
{
    admin_prepare_runtime();

    $messages = [];
    $errors = [];
    $section = admin_requested_section();
    $isAjaxRequest = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['section'])) {
        $postedSection = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $_POST['section']));

        if (array_key_exists($postedSection, admin_sections())) {
            $section = $postedSection;
        }
    }

    if (isset($_GET['logout'])) {
        admin_logout();
        header('Location: index.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');

        try {
            if ($action === '' && $_POST === []) {
                $errors[] = 'Er kwam geen formulierdata binnen. Controleer of de uploads niet groter zijn dan de PHP-limieten.';
            } elseif ($action === 'setup') {
                $username = trim((string) ($_POST['username'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

                if ($username === '') {
                    $errors[] = 'Vul een loginnaam in.';
                } elseif (strlen($password) < 8) {
                    $errors[] = 'Kies een wachtwoord van minstens 8 tekens.';
                } elseif ($password !== $confirmPassword) {
                    $errors[] = 'De wachtwoorden komen niet overeen.';
                } else {
                    admin_save_credentials($username, $password);
                    admin_login($username, $password);
                    $messages[] = 'Beheer is ingesteld. Je bent nu aangemeld.';
                }
            } elseif ($action === 'login') {
                $username = trim((string) ($_POST['username'] ?? ''));

                if (!admin_login($username, (string) ($_POST['password'] ?? ''))) {
                    $errors[] = 'Ongeldige login of wachtwoord.';
                }
            } elseif (!admin_is_logged_in()) {
                $errors[] = 'Meld je eerst aan.';
            } elseif (!admin_check_csrf($_POST)) {
                $errors[] = 'De sessie is verlopen. Probeer opnieuw.';
            } elseif ($action === 'save-credentials') {
                $username = trim((string) ($_POST['username'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

                if ($username === '') {
                    $errors[] = 'Vul een loginnaam in.';
                } elseif ($password !== '' && strlen($password) < 8) {
                    $errors[] = 'Kies een wachtwoord van minstens 8 tekens.';
                } elseif ($password !== $confirmPassword) {
                    $errors[] = 'De wachtwoorden komen niet overeen.';
                } else {
                    admin_update_credentials($username, $password === '' ? null : $password);
                    $messages[] = 'Login en wachtwoordinstellingen zijn bewaard.';
                }
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
            }
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    if ($isAjaxRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $errors === [],
            'messages' => $messages,
            'errors' => $errors,
            'section' => $section,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $configured = admin_is_configured();
    $loggedIn = admin_is_logged_in();

    return [
        'config' => $config,
        'messages' => $messages,
        'errors' => $errors,
        'section' => $section,
        'configured' => $configured,
        'loggedIn' => $loggedIn,
        'csrfToken' => $loggedIn ? admin_csrf_token() : '',
        'adminUsername' => admin_username(),
        'bookingWidget' => booking_widget_settings($config),
        'siteLogo' => admin_safe_media_filename((string) ($config['site']['logo'] ?? '')),
    ];
}
