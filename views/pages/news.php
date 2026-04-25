<div class="news-list">
    <?php foreach ($page['items'] as $item): ?>
        <article>
            <time><?= e($item['date']) ?></time>
            <h2><?= e($item['title']) ?></h2>
            <p><?= e($item['body']) ?></p>
        </article>
    <?php endforeach; ?>
</div>
