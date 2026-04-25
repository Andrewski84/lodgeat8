<?php
$room = $page['room'];
$summary = trim((string) ($room['summary'] ?? ''));
$features = isset($room['features']) && is_array($room['features']) ? array_filter($room['features']) : [];
$pricesHeading = trim((string) ($room['prices_heading'] ?? ui_text('price_per_night')));
$prices = isset($room['prices']) && is_array($room['prices']) ? array_filter($room['prices']) : [];
$showPricesSection = $pricesHeading !== '' || $prices !== [];
$bookingUrl = trim((string) ($room['booking_url'] ?? $config['site']['reservation_url'] ?? $config['site']['booking_url'] ?? ''));
?>
<div class="room-layout">
    <div>
        <?php if ($summary !== ''): ?>
            <p class="lead"><?= rich_text_html($summary) ?></p>
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
        <?php if ($showPricesSection): ?>
            <hr>
            <?php if ($pricesHeading !== ''): ?>
                <h2><?= rich_text_html($pricesHeading) ?></h2>
            <?php endif; ?>
            <?php if ($prices !== []): ?>
                <dl class="price-list">
                    <?php foreach ($prices as $label => $price): ?>
                        <div>
                            <dt><?= rich_text_html((string) $label) ?></dt>
                            <dd><?= rich_text_html((string) $price) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
