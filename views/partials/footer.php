<?php
/*
 * Public footer partial.
 *
 * The footer repeats a compact navigation set and reuses the same language
 * picker component as the header. Footer labels are localized from content so
 * editors can change navigation text without touching this template.
 */
?>
<footer class="site-footer">
    <div class="site-footer-shell">
        <nav class="site-footer-nav">
            <?php foreach ($config['footer_navigation'] as $target => $label): ?>
                <a href="<?= e(url_for($target)) ?>"<?= is_active($pageKey, $target) ?>><?= e(navigation_label($config, $target, $currentLanguage)) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php require __DIR__ . '/language-picker.php'; ?>
    </div>
</footer>
