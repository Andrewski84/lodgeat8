<header class="site-header" data-site-header>
    <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-label="Menu">
        <span class="nav-toggle-icon" aria-hidden="true"></span>
    </button>
    <nav class="primary-nav" data-nav>
        <?php foreach ($config['navigation'] as $target => $label): ?>
            <a href="<?= e(url_for($target)) ?>"<?= is_active($pageKey, $target) ?>><?= e(navigation_label($config, $target, $currentLanguage)) ?></a>
        <?php endforeach; ?>
        <?php require __DIR__ . '/booking-widget.php'; ?>
    </nav>
    <a class="brand" href="<?= e(url_for('home')) ?>"><?= e($config['site']['name']) ?></a>
</header>
