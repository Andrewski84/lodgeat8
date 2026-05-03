<section class="gallery" data-gallery>
    <div class="gallery-frame">
        <?php foreach ($gallery as $index => $item): ?>
            <?php
            $file = (string) ($item['file'] ?? '');
            $alt = (string) ($item['alt'] ?? '');

            if ($file === '') {
                continue;
            }
            ?>
            <button
                class="gallery-image-button <?= $index === 0 ? 'is-visible' : '' ?>"
                type="button"
                data-lightbox-trigger
                data-lightbox-index="<?= e((string) $index) ?>"
                aria-label="Foto openen"
            >
                <img
                    src="<?= e(image_path($file)) ?>"
                    alt="<?= e($alt) ?>"
                    loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"
                >
            </button>
        <?php endforeach; ?>
    </div>
    <div class="gallery-controls" data-gallery-controls>
        <button type="button" data-gallery-prev aria-label="Vorige foto">&lsaquo;</button>
        <button type="button" data-gallery-next aria-label="Volgende foto">&rsaquo;</button>
    </div>
</section>
