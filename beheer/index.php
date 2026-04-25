<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_once base_path('includes/admin.php');
require_once __DIR__ . '/partials/form-helpers.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

session_start();

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
            $deletedImages = admin_flush_media_deletions($config);
            $messages[] = 'Algemene instellingen zijn bewaard.';
            if ($deletedImages !== []) {
                $messages[] = 'Verwijderd uit assets: ' . implode(', ', $deletedImages);
            }
        } elseif ($action === 'save-page') {
            $config = admin_save_page_content($config, (string) ($_POST['page_key'] ?? ''), $_POST, $_FILES);
            save_content($config);
            $deletedImages = admin_flush_media_deletions($config);
            $messages[] = 'Pagina is bewaard.';
            if ($deletedImages !== []) {
                $messages[] = 'Verwijderd uit assets: ' . implode(', ', $deletedImages);
            }
        } elseif ($action === 'save-room') {
            $config = admin_save_room_content($config, (string) ($_POST['room_key'] ?? ''), $_POST, $_FILES);
            save_content($config);
            $deletedImages = admin_flush_media_deletions($config);
            $messages[] = 'Kamer is bewaard.';
            if ($deletedImages !== []) {
                $messages[] = 'Verwijderd uit assets: ' . implode(', ', $deletedImages);
            }
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
$csrfToken = $loggedIn ? admin_csrf_token() : '';
$adminUsername = admin_username();
$bookingWidget = booking_widget_settings($config);
$siteLogo = admin_safe_media_filename((string) ($config['site']['logo'] ?? ''));
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Beheer | <?= e($config['site']['name']) ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="../assets/js/admin.js" defer></script>
</head>
<body class="admin-body">
    <?php if (!$configured): ?>
        <main class="admin-auth">
            <section class="auth-panel">
                <p class="eyebrow">Eerste installatie</p>
                <h1>Maak je beheerlogin</h1>
                <?php require __DIR__ . '/partials/messages.php'; ?>
                <form method="post" action="<?= e(admin_script_url()) ?>">
                    <input type="hidden" name="action" value="setup">
                    <label>Login <input name="username" required autocomplete="username"></label>
                    <label>Wachtwoord <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
                    <label>Herhaal wachtwoord <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label>
                    <button type="submit">Beheer activeren</button>
                </form>
            </section>
        </main>
    <?php elseif (!$loggedIn): ?>
        <main class="admin-auth">
            <section class="auth-panel">
                <p class="eyebrow">Lodging at 8</p>
                <h1>Beheer aanmelden</h1>
                <?php require __DIR__ . '/partials/messages.php'; ?>
                <form method="post" action="<?= e(admin_script_url()) ?>">
                    <input type="hidden" name="action" value="login">
                    <label>Login <input name="username" required autocomplete="username"></label>
                    <label>Wachtwoord <input type="password" name="password" required autocomplete="current-password"></label>
                    <button type="submit">Aanmelden</button>
                </form>
            </section>
        </main>
    <?php else: ?>
        <header class="admin-header">
            <div>
                <p class="eyebrow">Beheer</p>
                <h1><?= e(admin_sections()[$section]) ?></h1>
            </div>
            <nav>
                <a href="../index.php?lang=nl&p=home" target="_blank" rel="noopener">Website bekijken</a>
                <a href="index.php?logout=1">Uitloggen</a>
            </nav>
        </header>

        <main class="admin-layout">
            <aside class="admin-sidebar" aria-label="Beheer navigatie">
                <?php foreach (admin_sections() as $key => $label): ?>
                    <a href="<?= e(admin_section_url($key)) ?>"<?= $key === $section ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                <?php endforeach; ?>
            </aside>

            <div class="admin-workspace">
                <?php require __DIR__ . '/partials/messages.php'; ?>

                <?php if ($section === 'algemeen'): ?>
                    <section class="admin-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Algemeen</p>
                                <h2>Website instellingen</h2>
                            </div>
                        </div>
                        <form class="admin-form" method="post" action="<?= e(admin_section_url($section)) ?>" enctype="multipart/form-data">
                            <?php beheer_hidden_fields($csrfToken, $section, 'save-general'); ?>
                            <div class="field-grid">
                                <label>Website naam <input name="site[name]" value="<?= e($config['site']['name'] ?? '') ?>"></label>
                                <div class="logo-manager wide" data-logo-manager>
                                    <input type="hidden" name="site[logo]" value="<?= e($siteLogo) ?>" data-logo-current>
                                    <input type="hidden" name="site[logo_remove]" value="0" data-logo-remove-input>
                                    <div class="logo-manager-head">
                                        <span>Logo</span>
                                        <label class="icon-button logo-upload-button" aria-label="Logo opladen">
                                            <input type="file" name="logo_upload" accept="image/*" data-logo-input>
                                            <span class="gallery-icon" aria-hidden="true"></span>
                                        </label>
                                    </div>
                                    <div class="logo-preview" data-logo-preview<?= $siteLogo === '' ? ' hidden' : '' ?>>
                                        <img src="<?= $siteLogo !== '' ? e('../' . image_path($siteLogo)) : '' ?>" alt="" data-logo-preview-image<?= $siteLogo === '' ? ' hidden' : '' ?>>
                                        <small data-logo-preview-name><?= e($siteLogo) ?></small>
                                        <button type="button" class="icon-button is-danger" data-logo-remove aria-label="Logo verwijderen">&#128465;</button>
                                    </div>
                                    <div class="logo-empty" data-logo-empty<?= $siteLogo !== '' ? ' hidden' : '' ?>>Geen logo ingesteld</div>
                                </div>
                                <label class="wide">
                                    Reservatie fallback link
                                    <input name="site[reservation_url]" value="<?= e($config['site']['reservation_url'] ?? '') ?>">
                                </label>
                                <div class="wide">
                                    <?php beheer_photo_grid('backgrounds', $config['backgrounds'] ?? [], 'background_uploads', 'Achtergrond foto\'s', 'Sleep foto\'s om de volgorde te wijzigen. Nieuwe uploads worden achteraan toegevoegd.'); ?>
                                </div>
                                <label class="checkbox-field">
                                    <input type="checkbox" name="booking_widget[enabled]" value="1"<?= $bookingWidget['enabled'] ? ' checked' : '' ?>>
                                    Booking dropdown tonen
                                </label>
                                <label>Dropdown titel <input name="booking_widget[title]" value="<?= e($bookingWidget['title']) ?>"></label>
                                <label>Knoptekst <input name="booking_widget[button_label]" value="<?= e($bookingWidget['button_label']) ?>"></label>
                                <label class="wide">
                                    Booking module code
                                    <textarea name="booking_widget[embed_code]" rows="14"><?= e($bookingWidget['embed_code']) ?></textarea>
                                </label>
                            </div>
                            <div class="save-bar is-bottom">
                                <span>Algemene instellingen bewaren</span>
                                <button type="submit">Bewaren</button>
                            </div>
                        </form>
                    </section>

                <?php elseif (in_array($section, ['leuven', 'locatie', 'voorwaarden'], true)): ?>
                    <?php $page = $config['pages'][$section]; ?>
                    <section class="admin-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Pagina</p>
                                <h2><?= e(admin_sections()[$section]) ?></h2>
                            </div>
                        </div>
                        <form class="admin-form" method="post" action="<?= e(admin_section_url($section)) ?>" enctype="multipart/form-data">
                            <?php beheer_hidden_fields($csrfToken, $section, 'save-page'); ?>
                            <input type="hidden" name="page_key" value="<?= e($section) ?>">
                            <div class="language-grid">
                                <?php beheer_page_fields($page); ?>
                            </div>
                            <?php if ($section === 'locatie'): ?>
                                <label class="wide">
                                    Google Maps URL
                                    <input name="map_url" value="<?= e($page['map_url'] ?? '') ?>" placeholder="https://www.google.com/maps/...">
                                    <small>Plak hier de Google Maps link of de src-url uit de Google Maps insluitcode.</small>
                                </label>
                            <?php endif; ?>
                            <?php if ($section === 'leuven'): ?>
                                <?php beheer_photo_grid('gallery', $config['galleries']['leuven'] ?? [], 'gallery_uploads', 'Carousel foto\'s', 'Deze foto\'s worden rechts op de Leuven-pagina getoond.'); ?>
                                <input type="hidden" name="gallery_key" value="leuven">
                            <?php endif; ?>
                            <div class="save-bar is-bottom"><span>Pagina bewaren</span><button type="submit">Bewaren</button></div>
                        </form>
                    </section>

                <?php elseif (in_array($section, ['kamer-1', 'kamer-2', 'kamer-3'], true)): ?>
                    <?php $room = $config['rooms'][$section]; ?>
                    <section class="admin-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Kamer</p>
                                <h2><?= e(admin_sections()[$section]) ?></h2>
                            </div>
                        </div>
                        <form class="admin-form" method="post" action="<?= e(admin_section_url($section)) ?>" enctype="multipart/form-data">
                            <?php beheer_hidden_fields($csrfToken, $section, 'save-room'); ?>
                            <input type="hidden" name="room_key" value="<?= e($section) ?>">
                            <div class="language-grid">
                                <?php beheer_room_fields($room); ?>
                            </div>
                            <?php beheer_photo_grid('gallery', $config['galleries'][$section] ?? [], 'gallery_uploads', 'Kamer carousel', 'Deze foto\'s worden rechts op de kamerpagina getoond.'); ?>
                            <div class="save-bar is-bottom"><span>Kamer bewaren</span><button type="submit">Bewaren</button></div>
                        </form>
                    </section>

                <?php elseif ($section === 'contact'): ?>
                    <?php $page = $config['pages']['contact']; ?>
                    <section class="admin-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Contact</p>
                                <h2>Adresgegevens en formulier</h2>
                            </div>
                        </div>
                        <form class="admin-form" method="post" action="<?= e(admin_section_url($section)) ?>">
                            <?php beheer_hidden_fields($csrfToken, $section, 'save-general'); ?>
                            <div class="field-grid">
                                <label>Adres <input name="site[address]" value="<?= e($config['site']['address'] ?? '') ?>"></label>
                                <label>Telefoon <input name="site[phone]" value="<?= e($config['site']['phone'] ?? '') ?>"></label>
                                <label>E-mail <input name="site[email]" value="<?= e($config['site']['email'] ?? '') ?>"></label>
                            </div>
                            <div class="save-bar is-bottom"><span>Adresgegevens bewaren</span><button type="submit">Bewaren</button></div>
                        </form>
                    </section>
                    <section class="admin-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Contact</p>
                                <h2>Teksten per taal</h2>
                            </div>
                        </div>
                        <form class="admin-form" method="post" action="<?= e(admin_section_url($section)) ?>">
                            <?php beheer_hidden_fields($csrfToken, $section, 'save-page'); ?>
                            <input type="hidden" name="page_key" value="contact">
                            <div class="language-grid">
                                <?php beheer_page_fields($page, true, true); ?>
                            </div>
                            <div class="save-bar is-bottom"><span>Contactpagina bewaren</span><button type="submit">Bewaren</button></div>
                        </form>
                    </section>

                <?php elseif ($section === 'links'): ?>
                    <?php $page = $config['pages']['links']; ?>
                    <section class="admin-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Links</p>
                                <h2>Links beheren</h2>
                            </div>
                        </div>
                        <form class="admin-form" method="post" action="<?= e(admin_section_url($section)) ?>">
                            <?php beheer_hidden_fields($csrfToken, $section, 'save-links'); ?>
                            <div class="language-grid">
                                <?php beheer_links_fields($page); ?>
                            </div>
                            <div class="save-bar is-bottom"><span>Links bewaren</span><button type="submit">Bewaren</button></div>
                        </form>
                    </section>
                <?php elseif ($section === 'toegang'): ?>
                    <section class="admin-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Admin</p>
                                <h2>Login en wachtwoord</h2>
                            </div>
                        </div>
                        <form class="field-grid" method="post" action="<?= e(admin_section_url($section)) ?>">
                            <?php beheer_hidden_fields($csrfToken, $section, 'save-credentials'); ?>
                            <label class="wide">Login <input name="username" value="<?= e($adminUsername) ?>" required autocomplete="username"></label>
                            <label class="wide">Nieuw wachtwoord <input type="password" name="password" minlength="8" autocomplete="new-password"></label>
                            <label class="wide">Herhaal nieuw wachtwoord <input type="password" name="confirm_password" minlength="8" autocomplete="new-password"></label>
                            <div class="form-actions"><button type="submit">Toegang bewaren</button></div>
                        </form>
                    </section>
                <?php endif; ?>
            </div>
        </main>
    <?php endif; ?>
</body>
</html>
