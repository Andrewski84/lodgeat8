<?php
/*
 * Full-page background carousel.
 *
 * Background definitions are filtered by page, normalized for display mode and
 * rendered as decorative slides. The JavaScript rotates these independently
 * from the content gallery/lightbox.
 */
?>
<div class="background-slides" aria-hidden="true">
    <?php foreach (background_items_for_display((array) $config['backgrounds'], $pageKey) as $index => $background): ?>
        <?php $backgroundFile = (string) ($background['file'] ?? ''); ?>
        <div
            class="background-slide <?= $index === 0 ? 'is-visible' : '' ?>"
            style="background-image: url('<?= e(image_path($backgroundFile)) ?>'); background-size: <?= e((string) $background['size']) ?>; background-position: <?= e((string) $background['position']) ?>; background-repeat: <?= e((string) $background['repeat']) ?>"
        ></div>
    <?php endforeach; ?>
</div>
