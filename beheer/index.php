<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require_once base_path('includes/admin.php');
require_once __DIR__ . '/admin-controller.php';
require_once __DIR__ . '/partials/form-helpers.php';

$adminState = admin_controller_state($config);
$config = $adminState['config'];
$messages = $adminState['messages'];
$errors = $adminState['errors'];
$section = $adminState['section'];
$configured = $adminState['configured'];
$loggedIn = $adminState['loggedIn'];
$csrfToken = $adminState['csrfToken'];
$adminUsername = $adminState['adminUsername'];
$bookingWidget = $adminState['bookingWidget'];
$siteLogo = $adminState['siteLogo'];
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
                <?php require __DIR__ . '/partials/section-content.php'; ?>
            </div>
        </main>
    <?php endif; ?>

    <?php if ($loggedIn): ?>
        <?php require __DIR__ . '/partials/modals.php'; ?>
    <?php endif; ?>
</body>
</html>
