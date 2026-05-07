<header class="site-header" data-site-header>
    <div class="header-actions">
        <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-label="Menu">
            <span class="nav-toggle-icon" aria-hidden="true"></span>
        </button>
        <?php $languagePickerClass = 'language-picker-mobile'; ?>
        <?php require __DIR__ . '/language-picker.php'; ?>
        <?php require __DIR__ . '/booking-widget.php'; ?>
    </div>
    <nav class="primary-nav" data-nav>
        <?php foreach ($config['navigation'] as $target => $label): ?>
            <a href="<?= e(url_for($target)) ?>"<?= is_active($pageKey, $target) ?>><?= e(navigation_label($config, $target, $currentLanguage)) ?></a>
        <?php endforeach; ?>
        <div class="mobile-nav-utility">
            <a class="mobile-nav-link" href="<?= e(url_for('voorwaarden')) ?>"<?= is_active($pageKey, 'voorwaarden') ?>><?= e(navigation_label($config, 'voorwaarden', $currentLanguage)) ?></a>
        </div>
    </nav>
    <?php $headerLogo = trim((string) ($config['site']['logo'] ?? '')); ?>
    <a class="brand" href="<?= e(url_for('home')) ?>" aria-label="<?= e($config['site']['name']) ?>">
        <?php if ($headerLogo !== ''): ?>
            <img class="brand-logo" src="<?= e(image_path($headerLogo)) ?>" alt="">
        <?php endif; ?>
        <span class="brand-text"><?= e($config['site']['name']) ?></span>
    </a>
</header>
