<?php
declare(strict_types=1);

function admin_validate_optional_url(string $value, string $label): void
{
    if ($value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false) {
        throw new RuntimeException($label . ' heeft geen geldige URL.');
    }
}

function admin_validate_optional_email(string $value, string $label): void
{
    if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException($label . ' heeft geen geldig e-mailadres.');
    }
}

function admin_save_general(array $content, array $post, array $files = []): array
{
    $editableSiteFields = [
        'name',
        'reservation_url',
        'booking_url',
        'email',
        'phone',
        'address',
        'owner',
        'company',
        'business_number',
        'iban',
        'favicon',
    ];
    $previousLogo = admin_safe_media_filename((string) ($content['site']['logo'] ?? ''));
    $previousFavicon = admin_safe_media_filename((string) ($content['site']['favicon'] ?? ''));

    foreach (($post['site'] ?? []) as $key => $value) {
        if (!in_array($key, $editableSiteFields, true)) {
            continue;
        }

        $content['site'][$key] = trim((string) $value);
    }

    admin_validate_optional_url((string) ($content['site']['reservation_url'] ?? ''), 'Reservatielink');
    admin_validate_optional_url((string) ($content['site']['booking_url'] ?? ''), 'Booking URL');
    admin_validate_optional_email((string) ($content['site']['email'] ?? ''), 'Contact e-mail');

    $logoUploads = admin_upload_images($files['logo_upload'] ?? [], admin_media_directory('site'));

    if ($logoUploads !== []) {
        if ($previousLogo !== '' && $previousLogo !== $logoUploads[0]) {
            admin_queue_media_deletion([$previousLogo]);
        }

        $content['site']['logo'] = $logoUploads[0];
    } elseif (($post['site']['logo_remove'] ?? '0') === '1') {
        if ($previousLogo !== '') {
            admin_queue_media_deletion([$previousLogo]);
        }

        $content['site']['logo'] = '';
    } elseif (isset($post['site']['logo'])) {
        $content['site']['logo'] = admin_safe_media_filename((string) $post['site']['logo']);
    }

    $faviconUploads = admin_upload_images($files['favicon_upload'] ?? [], admin_media_directory('site'));

    if ($faviconUploads !== []) {
        if ($previousFavicon !== '' && $previousFavicon !== $faviconUploads[0]) {
            admin_queue_media_deletion([$previousFavicon]);
        }

        $content['site']['favicon'] = $faviconUploads[0];
    } elseif (($post['site']['favicon_remove'] ?? '0') === '1') {
        if ($previousFavicon !== '') {
            admin_queue_media_deletion([$previousFavicon]);
        }

        $content['site']['favicon'] = '';
    } elseif (isset($post['site']['favicon'])) {
        $content['site']['favicon'] = admin_safe_media_filename((string) $post['site']['favicon']);
    }

    $backgroundUploads = admin_upload_images($files['background_uploads'] ?? [], admin_media_directory('backgrounds'));
    $backgroundPost = (array) ($post['backgrounds'] ?? []);
    $removedBackgrounds = admin_removed_media_from_post($backgroundPost);

    if (isset($post['backgrounds']) && admin_media_post_has_files($backgroundPost)) {
        $content['backgrounds'] = admin_media_from_post($backgroundPost, $backgroundUploads);
    } elseif ($backgroundUploads !== []) {
        $content['backgrounds'] = admin_append_uploaded_media($content['backgrounds'] ?? [], $backgroundUploads);
    }

    if ($removedBackgrounds !== []) {
        admin_queue_media_deletion($removedBackgrounds);
    }

    if (isset($post['booking_widget']) && is_array($post['booking_widget'])) {
        $widgetPost = $post['booking_widget'];
        $content['booking_widget']['enabled'] = isset($widgetPost['enabled']);
        $content['booking_widget']['title'] = trim((string) ($widgetPost['title'] ?? 'Reservatie'));
        $content['booking_widget']['button_label'] = trim((string) ($widgetPost['button_label'] ?? 'Check availability'));
        $content['booking_widget']['embed_code'] = (string) ($widgetPost['embed_code'] ?? '');
    }

    return $content;
}

function admin_save_page_content(array $content, string $pageKey, array $post, array $files = []): array
{
    if (!isset($content['pages'][$pageKey])) {
        return $content;
    }

    foreach (supported_languages() as $language => $label) {
        $languagePost = $post['translations'][$language] ?? [];
        admin_set_translation($content['pages'][$pageKey], $language, 'title', trim((string) ($languagePost['title'] ?? '')));

        if (isset($languagePost['intro'])) {
            admin_set_translation($content['pages'][$pageKey], $language, 'intro', admin_lines_from_text((string) $languagePost['intro']));
        }

        if (isset($languagePost['success_message'])) {
            admin_set_translation($content['pages'][$pageKey], $language, 'success_message', trim((string) ($languagePost['success_message'] ?? '')));
        }
    }

    if (array_key_exists('map_url', $post)) {
        $mapUrl = trim((string) $post['map_url']);
        admin_validate_optional_url($mapUrl, 'Google Maps URL');
        $content['pages'][$pageKey]['map_url'] = $mapUrl;
    }

    $galleryUploads = admin_upload_images($files['gallery_uploads'] ?? [], admin_media_directory($pageKey));
    $galleryPost = (array) ($post['gallery'] ?? []);
    $removedGalleryItems = admin_removed_media_from_post($galleryPost);

    if (isset($post['gallery_key']) && isset($post['gallery']) && admin_media_post_has_files($galleryPost)) {
        $galleryKey = (string) $post['gallery_key'];
        $content['galleries'][$galleryKey] = admin_media_from_post($galleryPost, $galleryUploads);
        $content['pages'][$pageKey]['gallery'] = $galleryKey;
    } elseif ($galleryUploads !== []) {
        $galleryKey = (string) ($post['gallery_key'] ?? $content['pages'][$pageKey]['gallery'] ?? $pageKey);
        $content['galleries'][$galleryKey] = admin_append_uploaded_media($content['galleries'][$galleryKey] ?? [], $galleryUploads);
        $content['pages'][$pageKey]['gallery'] = $galleryKey;
    }

    if ($removedGalleryItems !== []) {
        admin_queue_media_deletion($removedGalleryItems);
    }

    return $content;
}

function admin_save_room_content(array $content, string $roomKey, array $post, array $files = []): array
{
    if (!isset($content['rooms'][$roomKey])) {
        return $content;
    }

    $content['rooms'][$roomKey]['gallery'] = $roomKey;

    foreach (supported_languages() as $language => $label) {
        $languagePost = $post['translations'][$language] ?? [];
        $title = trim((string) ($languagePost['title'] ?? ''));
        $navTitle = trim((string) ($languagePost['nav_title'] ?? ''));
        $summary = trim((string) ($languagePost['summary'] ?? ''));
        $bookingUrl = trim((string) ($languagePost['booking_url'] ?? ''));
        $features = admin_lines_from_value($languagePost['features'] ?? []);
        $pricesHeading = trim((string) ($languagePost['prices_heading'] ?? ''));
        $prices = admin_pairs_from_value($languagePost['prices'] ?? []);
        $extraInfo = admin_lines_from_text((string) ($languagePost['extra_info'] ?? ''));

        admin_validate_optional_url($bookingUrl, 'Booking link');

        admin_set_translation($content['rooms'][$roomKey], $language, 'title', $title);
        admin_set_translation($content['rooms'][$roomKey], $language, 'nav_title', $navTitle);
        admin_set_translation($content['rooms'][$roomKey], $language, 'summary', $summary);
        admin_set_translation($content['rooms'][$roomKey], $language, 'booking_url', $bookingUrl);
        admin_set_translation($content['rooms'][$roomKey], $language, 'features', $features);
        admin_set_translation($content['rooms'][$roomKey], $language, 'prices_heading', $pricesHeading);
        admin_set_translation($content['rooms'][$roomKey], $language, 'prices', $prices);
        admin_set_translation($content['rooms'][$roomKey], $language, 'extra_info', $extraInfo);

        if ($language === 'nl') {
            // Keep base fields aligned with Dutch content for front-end fallbacks.
            $content['rooms'][$roomKey]['title'] = $title;
            $content['rooms'][$roomKey]['nav_title'] = $navTitle;
            $content['rooms'][$roomKey]['summary'] = $summary;
            $content['rooms'][$roomKey]['booking_url'] = $bookingUrl;
            $content['rooms'][$roomKey]['features'] = $features;
            $content['rooms'][$roomKey]['prices_heading'] = $pricesHeading;
            $content['rooms'][$roomKey]['prices'] = $prices;
            $content['rooms'][$roomKey]['extra_info'] = $extraInfo;
        }
    }

    $galleryUploads = admin_upload_images($files['gallery_uploads'] ?? [], admin_media_directory($roomKey));
    $galleryPost = (array) ($post['gallery'] ?? []);
    $removedGalleryItems = admin_removed_media_from_post($galleryPost);

    if (isset($post['gallery']) && admin_media_post_has_files($galleryPost)) {
        $content['galleries'][$roomKey] = admin_media_from_post($galleryPost, $galleryUploads);
    } elseif ($galleryUploads !== []) {
        $content['galleries'][$roomKey] = admin_append_uploaded_media($content['galleries'][$roomKey] ?? [], $galleryUploads);
    }

    if ($removedGalleryItems !== []) {
        admin_queue_media_deletion($removedGalleryItems);
    }

    return $content;
}

function admin_save_links_content(array $content, array $post): array
{
    if (!isset($content['pages']['links'])) {
        return $content;
    }

    foreach (supported_languages() as $language => $label) {
        $languagePost = $post['translations'][$language] ?? [];
        admin_set_translation($content['pages']['links'], $language, 'title', trim((string) ($languagePost['title'] ?? '')));
        $columns = isset($languagePost['sections']) && is_array($languagePost['sections'])
            ? admin_columns_from_link_sections($languagePost['sections'])
            : admin_columns_from_link_rows($languagePost['links'] ?? []);

        admin_set_translation($content['pages']['links'], $language, 'columns', $columns);
    }

    return $content;
}
