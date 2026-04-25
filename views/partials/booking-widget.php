<?php
$bookingWidget = booking_widget_settings($config);
$bookingAction = booking_widget_action($bookingWidget);
$bookingLanguage = booking_widget_language($bookingWidget);
$bookingCtaLabel = 'Book Now';
?>
<?php if ($bookingWidget['enabled'] && $bookingAction !== ''): ?>
    <details class="booking-dropdown" data-booking-dropdown>
        <summary class="book-now">
            <span class="book-now-text"><?= e($bookingCtaLabel) ?></span>
            <span class="book-now-icon" aria-hidden="true"></span>
        </summary>
        <div class="booking-panel">
            <form
                class="booking-form"
                action="<?= e($bookingAction) ?>"
                method="get"
                data-fastbooker
                data-language="<?= e($bookingLanguage) ?>"
            >
                <label>
                    Aankomst
                    <input type="date" name="startdate" data-arrival required>
                </label>
                <label>
                    Vertrek
                    <input type="date" name="enddate" data-departure required>
                </label>
                <button type="submit"><?= e($bookingWidget['button_label']) ?></button>
            </form>
        </div>
    </details>
<?php else: ?>
    <a class="book-now" href="<?= e($config['site']['reservation_url'] ?? $config['site']['booking_url'] ?? '') ?>" target="_blank" rel="noopener">
        <span class="book-now-text"><?= e($bookingCtaLabel) ?></span>
        <span class="book-now-icon" aria-hidden="true"></span>
    </a>
<?php endif; ?>
