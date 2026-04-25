<!doctype html>
<html lang="<?= e($currentLanguage) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Lodging at 8 is een Bed & Breakfast in Leuven, in een karaktervol huis uit 1918.">
    <title><?= e($page['title']) ?> | <?= e($config['site']['name']) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="page-<?= e($pageKey) ?>">
    <?php require __DIR__ . '/partials/backgrounds.php'; ?>
    <?php require __DIR__ . '/partials/header.php'; ?>
    <?php
    $isRoomPage = ($page['type'] ?? '') === 'room' && isset($page['room']) && is_array($page['room']);
    $roomExtraInfo = [];

    if ($isRoomPage) {
        foreach ((array) ($page['room']['extra_info'] ?? []) as $line) {
            $line = trim((string) $line);

            if ($line !== '') {
                $roomExtraInfo[] = $line;
            }
        }
    }
    ?>

    <main class="site-main">
        <section class="content-sheet <?= isset($page['gallery']) ? 'has-media' : 'single-column' ?> content-<?= e($page['type']) ?>">
            <div class="content-column">
                <h1><?= e($page['title']) ?></h1>
                <hr>

                <?php require __DIR__ . '/page.php'; ?>
            </div>

            <?php if (isset($page['gallery'], $config['galleries'][$page['gallery']])): ?>
                <div class="media-column">
                    <?php $gallery = $config['galleries'][$page['gallery']]; ?>
                    <?php require __DIR__ . '/partials/gallery.php'; ?>
                </div>
            <?php endif; ?>

            <?php if ($isRoomPage && $roomExtraInfo !== []): ?>
                <div class="room-extra-info room-extra-info-wide">
                    <?php render_intro($roomExtraInfo); ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php require __DIR__ . '/partials/footer.php'; ?>
    <?php $languageLabels = supported_languages(); ?>
    <details class="language-picker mobile-floating-language-picker">
        <summary aria-label="Taal kiezen">
            <span class="language-picker-label"><?= e($languageLabels[$currentLanguage] ?? strtoupper($currentLanguage)) ?></span>
            <span class="language-picker-icon" aria-hidden="true"></span>
        </summary>
        <div class="language-menu">
            <?php foreach ($languageLabels as $code => $label): ?>
                <a href="<?= e(url_for($pageKey, $code)) ?>"<?= $code === $currentLanguage ? ' aria-current="true"' : '' ?>>
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </details>

    <?php $footerLogo = trim((string) ($config['site']['logo'] ?? '')); ?>
    <?php if ($footerLogo !== ''): ?>
        <a class="footer-logo" href="<?= e(url_for('home')) ?>" aria-label="<?= e($config['site']['name']) ?>">
            <img src="<?= e(image_path($footerLogo)) ?>" alt="">
        </a>
    <?php endif; ?>

    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
