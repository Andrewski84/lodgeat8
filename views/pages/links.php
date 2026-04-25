<div class="link-columns">
    <?php foreach ($page['columns'] as $heading => $links): ?>
        <section>
            <h2><?= e($heading) ?></h2>
            <ul>
                <?php foreach ($links as $link): ?>
                    <li><a href="<?= e($link[1]) ?>" target="_blank" rel="noopener"><?= e($link[0]) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</div>
