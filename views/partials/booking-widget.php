<?php
/*
 * Booking widget partial.
 *
 * The admin can provide either a structured booking-engine action or a fallback
 * URL. When structured settings are available, JavaScript converts the visible
 * browser-native date inputs into the query parameters expected by the booking
 * provider.
 */
$bookingWidget = booking_widget_settings($config);
$bookingAction = booking_widget_action($bookingWidget);
$bookingLanguage = booking_widget_language($bookingWidget);
$bookingFallback = (string) ($bookingWidget['fallback_action'] ?? '');
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
<?php elseif (is_safe_web_url($bookingFallback)): ?>
    <a class="book-now" href="<?= e($bookingFallback) ?>" target="_blank" rel="noopener">
        <span class="book-now-text"><?= e($bookingCtaLabel) ?></span>
    </a>
<?php endif; ?>
