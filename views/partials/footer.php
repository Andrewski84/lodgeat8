<footer class="site-footer">
    <div class="site-footer-shell">
        <nav class="site-footer-nav">
            <?php foreach ($config['footer_navigation'] as $target => $label): ?>
                <a href="<?= e(url_for($target)) ?>"<?= is_active($pageKey, $target) ?>><?= e(navigation_label($config, $target, $currentLanguage)) ?></a>
            <?php endforeach; ?>
        </nav>
        <details class="language-picker">
            <summary aria-label="Taal kiezen">
                <span class="language-picker-label"><?= e(strtoupper($currentLanguage)) ?></span>
                <span class="language-picker-icon" aria-hidden="true"></span>
            </summary>
            <div class="language-menu">
                <?php foreach (supported_languages() as $code => $label): ?>
                    <a href="<?= e(url_for($pageKey, $code)) ?>"<?= $code === $currentLanguage ? ' aria-current="true"' : '' ?>>
                        <?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </details>
    </div>
</footer>
