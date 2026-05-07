<div class="link-columns">
    <?php foreach ($page['columns'] as $heading => $links): ?>
        <section>
            <h2><?= e($heading) ?></h2>
            <ul>
                <?php foreach ($links as $link): ?>
                    <?php
                    $linkLabel = trim((string) ($link[0] ?? ''));
                    $linkUrl = trim((string) ($link[1] ?? ''));

                    if ($linkLabel === '' || !is_safe_web_url($linkUrl)) {
                        continue;
                    }
                    ?>
                    <li><a href="<?= e($linkUrl) ?>" target="_blank" rel="noopener"><?= e($linkLabel) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</div>
