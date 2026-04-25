<div class="background-slides" aria-hidden="true">
    <?php foreach ($config['backgrounds'] as $index => $background): ?>
        <?php $backgroundFile = is_array($background) ? (string) ($background['file'] ?? '') : (string) $background; ?>
        <?php if ($backgroundFile === '') {
            continue;
        } ?>
        <div
            class="background-slide <?= $index === 0 ? 'is-visible' : '' ?>"
            style="background-image: url('<?= e(image_path($backgroundFile)) ?>')"
        ></div>
    <?php endforeach; ?>
</div>
