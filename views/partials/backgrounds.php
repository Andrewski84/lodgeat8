<div class="background-slides" aria-hidden="true">
    <?php foreach (gallery_items_for_display((array) $config['backgrounds']) as $index => $background): ?>
        <?php $backgroundFile = (string) ($background['file'] ?? ''); ?>
        <div
            class="background-slide <?= $index === 0 ? 'is-visible' : '' ?>"
            style="background-image: url('<?= e(image_path($backgroundFile)) ?>')"
        ></div>
    <?php endforeach; ?>
</div>
