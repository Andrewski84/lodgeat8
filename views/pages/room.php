<?php
$room = $page['room'];
$summary = trim((string) ($room['summary'] ?? ''));
$features = isset($room['features']) && is_array($room['features']) ? array_filter($room['features']) : [];
$pricesHeading = trim((string) ($room['prices_heading'] ?? ui_text('price_per_night')));
$prices = isset($room['prices']) && is_array($room['prices']) ? array_filter($room['prices']) : [];
$extraInfo = isset($room['extra_info']) && is_array($room['extra_info']) ? array_filter($room['extra_info']) : [];
$bookingUrl = trim((string) ($room['booking_url'] ?? $config['site']['reservation_url'] ?? $config['site']['booking_url'] ?? ''));
?>
<div class="room-layout">
    <div>
        <?php if ($summary !== ''): ?>
            <p class="lead"><?= e($summary) ?></p>
        <?php endif; ?>
        <?php if ($bookingUrl !== ''): ?>
            <p>
                <a class="button" href="<?= e($bookingUrl) ?>" target="_blank" rel="noopener">
                    <?= e(ui_text('availability')) ?>
                </a>
            </p>
        <?php endif; ?>
        <?php if ($features !== []): ?>
            <ul class="feature-list">
                <?php foreach ($features as $feature): ?>
                    <li><?= e($feature) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($prices !== []): ?>
            <hr>
            <?php if ($pricesHeading !== ''): ?>
                <h2><?= e($pricesHeading) ?></h2>
            <?php endif; ?>
            <dl class="price-list">
                <?php foreach ($prices as $label => $price): ?>
                    <div>
                        <dt><?= e($label) ?></dt>
                        <dd><?= e($price) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>
        <?php if ($extraInfo !== []): ?>
            <div class="room-extra-info">
                <?php render_intro($extraInfo); ?>
            </div>
        <?php endif; ?>
    </div>
</div>
