<?php
declare(strict_types=1);

function admin_settings_path(): string
{
    return storage_path('admin.php');
}

function admin_settings(): array
{
    $path = admin_settings_path();

    if (!is_file($path)) {
        return [];
    }

    $settings = require $path;

    return is_array($settings) ? $settings : [];
}

function admin_write_settings(array $settings): void
{
    if (!is_dir(storage_path())) {
        if (!mkdir(storage_path(), 0775, true) && !is_dir(storage_path())) {
            throw new RuntimeException('De opslagmap kon niet worden aangemaakt.');
        }
    }

    $export = var_export($settings, true);

    if (file_put_contents(admin_settings_path(), "<?php\nreturn {$export};\n", LOCK_EX) === false) {
        throw new RuntimeException('De beheerlogin kon niet worden opgeslagen.');
    }
}

function admin_is_configured(): bool
{
    $settings = admin_settings();

    return isset($settings['username'], $settings['password_hash']) || isset($settings['password_hash']);
}

function admin_save_credentials(string $username, string $password): void
{
    admin_write_settings([
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
}

function admin_update_credentials(string $username, ?string $password = null): void
{
    $settings = admin_settings();
    $settings['username'] = $username;

    if ($password !== null && $password !== '') {
        $settings['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    admin_write_settings($settings);
}

function admin_username(): string
{
    return (string) (admin_settings()['username'] ?? 'admin');
}

function admin_login(string $username, string $password): bool
{
    $settings = admin_settings();
    $expectedUsername = (string) ($settings['username'] ?? 'admin');
    $hash = $settings['password_hash'] ?? null;

    if (!hash_equals($expectedUsername, $username) || !is_string($hash) || !password_verify($password, $hash)) {
        return false;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['admin_authenticated'] = true;

    return true;
}

function admin_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function admin_is_logged_in(): bool
{
    return ($_SESSION['admin_authenticated'] ?? false) === true;
}

function admin_csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function admin_check_csrf(array $post): bool
{
    $token = $post['csrf_token'] ?? '';

    return is_string($token) && hash_equals(admin_csrf_token(), $token);
}

function admin_sections(): array
{
    return [
        'algemeen' => 'Algemeen',
        'leuven' => 'Leuven',
        'kamer-1' => 'Room 1',
        'kamer-2' => 'Room 2',
        'kamer-3' => 'Room 3',
        'locatie' => 'Location',
        'contact' => 'Contact',
        'links' => 'Links',
        'voorwaarden' => 'Cancellation policy',
        'toegang' => 'Login en wachtwoord',
    ];
}

function admin_requested_section(): string
{
    $section = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($_GET['section'] ?? 'algemeen')));

    return array_key_exists($section, admin_sections()) ? $section : 'algemeen';
}

function admin_section_url(string $section): string
{
    return admin_script_url() . '?section=' . rawurlencode($section);
}

function admin_script_url(): string
{
    return (string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php');
}

function admin_translated_value(array $item, string $field, string $language, $fallback = '')
{
    if (isset($item['translations'][$language]) && array_key_exists($field, $item['translations'][$language])) {
        return $item['translations'][$language][$field];
    }

    return $item[$field] ?? $fallback;
}

function admin_translated_text(array $item, string $field, string $language): string
{
    return (string) admin_translated_value($item, $field, $language, '');
}

function admin_translated_lines(array $item, string $field, string $language): array
{
    $value = admin_translated_value($item, $field, $language, []);

    return is_array($value) ? $value : admin_lines_from_text((string) $value);
}

function admin_translated_pairs(array $item, string $field, string $language): array
{
    $value = admin_translated_value($item, $field, $language, []);

    return is_array($value) ? $value : admin_pairs_from_text((string) $value);
}

function admin_set_translation(array &$item, string $language, string $field, $value): void
{
    if (!isset($item['translations']) || !is_array($item['translations'])) {
        $item['translations'] = [];
    }

    if (!isset($item['translations'][$language]) || !is_array($item['translations'][$language])) {
        $item['translations'][$language] = [];
    }

    $item['translations'][$language][$field] = $value;
}

function admin_lines_from_text(string $text): array
{
    $lines = preg_split('/\R/', $text) ?: [];
    $clean = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line !== '') {
            $clean[] = $line;
        }
    }

    return $clean;
}

function admin_lines_from_value($value): array
{
    if (is_array($value)) {
        $clean = [];

        foreach ($value as $line) {
            $line = trim((string) $line);

            if ($line !== '') {
                $clean[] = $line;
            }
        }

        return $clean;
    }

    return admin_lines_from_text((string) $value);
}

function admin_text_from_lines(array $lines): string
{
    return implode(PHP_EOL, array_map('strval', $lines));
}

function admin_text_from_pairs(array $pairs): string
{
    $lines = [];

    foreach ($pairs as $label => $value) {
        $lines[] = $label . ' | ' . $value;
    }

    return implode(PHP_EOL, $lines);
}

function admin_pairs_from_text(string $text): array
{
    // Prices can come from a plain textarea or older rich-text markup. Normalize
    // both to simple "label | value" lines before parsing.
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/<br\b[^>]*>/i', PHP_EOL, $text) ?? $text;
    $text = preg_replace('/<\/(div|p|li|ul|ol)\b[^>]*>/i', PHP_EOL, $text) ?? $text;
    $text = strip_tags($text);

    $pairs = [];

    foreach (admin_lines_from_text($text) as $line) {
        $parts = array_map('trim', explode('|', $line, 2));

        if (count($parts) === 2 && $parts[0] !== '') {
            $pairs[$parts[0]] = $parts[1];
        }
    }

    return $pairs;
}

function admin_pairs_from_value($value): array
{
    if (is_array($value)) {
        if (isset($value['label']) || isset($value['value'])) {
            $labels = is_array($value['label'] ?? null) ? $value['label'] : [];
            $prices = is_array($value['value'] ?? null) ? $value['value'] : [];
            $pairs = [];

            foreach ($labels as $index => $label) {
                $cleanLabel = trim((string) $label);
                $cleanPrice = trim((string) ($prices[$index] ?? ''));

                if ($cleanLabel === '' || $cleanPrice === '') {
                    continue;
                }

                $pairs[$cleanLabel] = $cleanPrice;
            }

            return $pairs;
        }

        if (!is_list_array($value)) {
            $pairs = [];

            foreach ($value as $label => $price) {
                $cleanLabel = trim((string) $label);
                $cleanPrice = trim((string) $price);

                if ($cleanLabel === '' || $cleanPrice === '') {
                    continue;
                }

                $pairs[$cleanLabel] = $cleanPrice;
            }

            return $pairs;
        }
    }

    return admin_pairs_from_text((string) $value);
}

function admin_normalize_media_item($item): array
{
    if (is_string($item)) {
        return [
            'file' => $item,
            'title' => pathinfo($item, PATHINFO_FILENAME),
        ];
    }

    if (!is_array($item)) {
        return [
            'file' => '',
            'title' => '',
        ];
    }

    $file = (string) ($item['file'] ?? '');
    $title = (string) ($item['title'] ?? $item['alt'] ?? pathinfo($file, PATHINFO_FILENAME));

    return [
        'file' => $file,
        'title' => $title,
    ];
}

function admin_normalize_media_items(array $items): array
{
    $normalized = [];

    foreach ($items as $item) {
        $item = admin_normalize_media_item($item);

        if ($item['file'] !== '') {
            $normalized[] = $item;
        }
    }

    return $normalized;
}

function admin_media_from_post(array $post, array $uploaded = []): array
{
    $files = $post['file'] ?? [];
    $titles = $post['title'] ?? [];
    $remove = array_flip(array_map('strval', $post['remove'] ?? []));
    $items = [];

    foreach ($files as $index => $file) {
        $file = trim((string) $file);

        if ($file === '' || isset($remove[$file])) {
            continue;
        }

        $title = trim((string) ($titles[$index] ?? ''));
        $items[] = [
            'file' => $file,
            'title' => $title,
        ];
    }

    foreach ($uploaded as $file) {
        $items[] = [
            'file' => $file,
            'title' => '',
        ];
    }

    return $items;
}

function admin_media_post_has_files(array $post): bool
{
    foreach (($post['file'] ?? []) as $file) {
        if (trim((string) $file) !== '') {
            return true;
        }
    }

    return false;
}

function admin_removed_media_from_post(array $post): array
{
    $removed = [];

    foreach (($post['remove'] ?? []) as $file) {
        $file = admin_safe_media_filename((string) $file);

        if ($file !== '') {
            $removed[] = $file;
        }
    }

    return array_values(array_unique($removed));
}

function admin_append_uploaded_media(array $items, array $uploaded): array
{
    $items = admin_normalize_media_items($items);

    foreach ($uploaded as $file) {
        $items[] = [
            'file' => $file,
            'title' => '',
        ];
    }

    return $items;
}

function admin_safe_media_filename(string $file): string
{
    // Media paths come from editable JSON and form posts. Keep them relative to
    // assets/img and reject traversal or unexpected filename characters.
    $file = trim(str_replace('\\', '/', $file));

    if ($file === '') {
        return '';
    }

    $segments = array_values(array_filter(explode('/', $file), static function (string $segment): bool {
        return $segment !== '';
    }));

    if ($segments === []) {
        return '';
    }

    $safeSegments = [];

    foreach ($segments as $index => $segment) {
        if ($segment === '.' || $segment === '..') {
            return '';
        }

        $isFile = $index === count($segments) - 1;
        $pattern = $isFile
            ? '/^[A-Za-z0-9][A-Za-z0-9._-]*\.(jpe?g|png|gif|webp)$/i'
            : '/^[a-z0-9][a-z0-9-]*$/';

        if (preg_match($pattern, $segment) !== 1) {
            return '';
        }

        $safeSegments[] = $segment;
    }

    return implode('/', $safeSegments);
}

function admin_media_directory(string $context): string
{
    // Keep uploaded files grouped by the page or room they belong to.
    $context = preg_replace('/[^a-z0-9-]/', '', strtolower($context)) ?? '';

    if ($context === '') {
        return 'uploads';
    }

    return $context;
}

// Keep this collector conservative: files may be shared between pages, so deletion only happens
// after the updated content has been saved and all current references are known.
function admin_collect_referenced_media(array $content): array
{
    $referenced = [];
    $remember = static function ($file) use (&$referenced): void {
        $file = admin_safe_media_filename((string) $file);

        if ($file !== '') {
            $referenced[$file] = true;
        }
    };

    $remember($content['site']['logo'] ?? '');

    foreach (($content['backgrounds'] ?? []) as $item) {
        if (is_array($item)) {
            $remember($item['file'] ?? '');
        } else {
            $remember($item);
        }
    }

    foreach (($content['galleries'] ?? []) as $gallery) {
        foreach ((array) $gallery as $item) {
            if (is_array($item)) {
                $remember($item['file'] ?? '');
            } else {
                $remember($item);
            }
        }
    }

    foreach (($content['rooms'] ?? []) as $room) {
        if (is_array($room)) {
            $remember($room['image'] ?? '');
        }
    }

    return $referenced;
}

function admin_delete_unreferenced_media(array $files, array $content): array
{
    $referenced = admin_collect_referenced_media($content);
    $directory = realpath(base_path('assets/img'));
    $deleted = [];

    if ($directory === false) {
        return $deleted;
    }

    $directoryPrefix = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    foreach (array_values(array_unique($files)) as $file) {
        $file = admin_safe_media_filename((string) $file);

        if ($file === '' || isset($referenced[$file])) {
            continue;
        }

        $target = $directoryPrefix . $file;
        $targetPath = realpath($target);

        if ($targetPath === false) {
            continue;
        }

        if (!str_starts_with($targetPath, $directoryPrefix)) {
            continue;
        }

        if (!@unlink($targetPath)) {
            throw new RuntimeException('De afbeelding kon niet uit assets/img worden verwijderd: ' . $file);
        }

        $deleted[] = $file;
    }

    return $deleted;
}

// Removed media is queued during form parsing and flushed after save_content(), avoiding orphan
// files without deleting images that are still referenced elsewhere.
function admin_queue_media_deletion(array $files): void
{
    if (!isset($GLOBALS['admin_pending_media_deletions']) || !is_array($GLOBALS['admin_pending_media_deletions'])) {
        $GLOBALS['admin_pending_media_deletions'] = [];
    }

    foreach ($files as $file) {
        $file = admin_safe_media_filename((string) $file);

        if ($file !== '') {
            $GLOBALS['admin_pending_media_deletions'][] = $file;
        }
    }

    $GLOBALS['admin_pending_media_deletions'] = array_values(array_unique($GLOBALS['admin_pending_media_deletions']));
}

function admin_flush_media_deletions(array $content): array
{
    $files = $GLOBALS['admin_pending_media_deletions'] ?? [];
    $GLOBALS['admin_pending_media_deletions'] = [];

    return is_array($files) ? admin_delete_unreferenced_media($files, $content) : [];
}

function admin_link_rows_from_columns(array $columns): array
{
    $rows = [];

    foreach ($columns as $heading => $links) {
        foreach ($links as $link) {
            $rows[] = [
                'heading' => (string) $heading,
                'label' => (string) ($link[0] ?? ''),
                'url' => (string) ($link[1] ?? ''),
            ];
        }
    }

    return $rows;
}

function admin_link_sections_from_columns(array $columns): array
{
    // The public site stores links as columns; the admin UI edits them as named
    // sections with rows so labels and URLs stay aligned.
    $defaultHeadings = ['In Leuven', 'Logeren en reizen'];
    $sections = [];

    foreach ($columns as $heading => $links) {
        $rows = [];

        foreach ((array) $links as $link) {
            $rows[] = [
                'label' => (string) ($link[0] ?? ''),
                'url' => (string) ($link[1] ?? ''),
            ];
        }

        $sections[] = [
            'heading' => (string) $heading,
            'rows' => $rows,
        ];
    }

    for ($index = count($sections); $index < 2; $index++) {
        $sections[] = [
            'heading' => $defaultHeadings[$index],
            'rows' => [],
        ];
    }

    return array_slice($sections, 0, 2);
}

function admin_columns_from_link_rows(array $post): array
{
    $columns = [];
    $headings = $post['heading'] ?? [];
    $labels = $post['label'] ?? [];
    $urls = $post['url'] ?? [];

    foreach ($labels as $index => $label) {
        $heading = trim((string) ($headings[$index] ?? ''));
        $label = trim((string) $label);
        $url = trim((string) ($urls[$index] ?? ''));

        if ($heading === '' || $label === '' || $url === '') {
            continue;
        }

        $columns[$heading][] = [$label, $url];
    }

    return $columns;
}

function admin_columns_from_link_sections(array $sections): array
{
    $columns = [];

    foreach (array_slice($sections, 0, 2) as $section) {
        if (!is_array($section)) {
            continue;
        }

        $heading = trim((string) ($section['heading'] ?? ''));
        $linksPost = is_array($section['links'] ?? null) ? $section['links'] : [];
        $labels = $linksPost['label'] ?? [];
        $urls = $linksPost['url'] ?? [];
        $links = [];

        foreach ($labels as $index => $label) {
            $label = trim((string) $label);
            $url = trim((string) ($urls[$index] ?? ''));

            if ($label === '' || $url === '') {
                continue;
            }

            $links[] = [$label, $url];
        }

        if ($heading !== '' && $links !== []) {
            $columns[$heading] = $links;
        }
    }

    return $columns;
}

function admin_save_general(array $content, array $post, array $files = []): array
{
    $previousLogo = admin_safe_media_filename((string) ($content['site']['logo'] ?? ''));

    foreach (($post['site'] ?? []) as $key => $value) {
        if (!in_array($key, ['logo', 'logo_remove'], true) && array_key_exists($key, $content['site'])) {
            $content['site'][$key] = trim((string) $value);
        }
    }

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
            admin_set_translation($content['pages'][$pageKey], $language, 'success_message', trim((string) $languagePost['success_message']));
        }
    }

    if (array_key_exists('map_url', $post)) {
        $content['pages'][$pageKey]['map_url'] = trim((string) $post['map_url']);
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

        admin_set_translation($content['rooms'][$roomKey], $language, 'title', $title);
        admin_set_translation($content['rooms'][$roomKey], $language, 'nav_title', $navTitle);
        admin_set_translation($content['rooms'][$roomKey], $language, 'summary', $summary);
        admin_set_translation($content['rooms'][$roomKey], $language, 'booking_url', $bookingUrl);
        admin_set_translation($content['rooms'][$roomKey], $language, 'features', $features);
        admin_set_translation($content['rooms'][$roomKey], $language, 'prices_heading', $pricesHeading);
        admin_set_translation($content['rooms'][$roomKey], $language, 'prices', $prices);
        admin_set_translation($content['rooms'][$roomKey], $language, 'extra_info', $extraInfo);

        if ($language === 'nl') {
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

function admin_upload_images(array $files, string $directory = ''): array
{
    if (($files['name'] ?? []) === []) {
        return [];
    }

    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $temporaryNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $uploaded = [];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $relativeDirectory = trim(str_replace('\\', '/', $directory), '/');
    $targetDirectory = base_path('assets/img' . ($relativeDirectory === '' ? '' : '/' . $relativeDirectory));

    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
        throw new RuntimeException('De afbeeldingenmap kon niet worden aangemaakt.');
    }

    if (!is_writable($targetDirectory)) {
        throw new RuntimeException('De afbeeldingenmap is niet schrijfbaar. Controleer de rechten van assets/img.');
    }

    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'De afbeelding is groter dan de serverlimiet.',
        UPLOAD_ERR_FORM_SIZE => 'De afbeelding is groter dan toegestaan.',
        UPLOAD_ERR_PARTIAL => 'De afbeelding werd maar gedeeltelijk opgeladen.',
        UPLOAD_ERR_NO_TMP_DIR => 'De tijdelijke uploadmap ontbreekt op de server.',
        UPLOAD_ERR_CANT_WRITE => 'De server kon de afbeelding niet wegschrijven.',
        UPLOAD_ERR_EXTENSION => 'Een PHP-extensie heeft de upload gestopt.',
    ];

    foreach ($names as $index => $originalName) {
        if (($errors[$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if (($errors[$index] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException($errorMessages[$errors[$index]] ?? 'Een afbeelding kon niet worden geüpload.');
        }

        $temporaryName = (string) ($temporaryNames[$index] ?? '');

        if (!is_uploaded_file($temporaryName) || getimagesize($temporaryName) === false) {
            throw new RuntimeException('Upload alleen geldige afbeeldingsbestanden.');
        }

        $extension = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('Alleen jpg, png, gif en webp zijn toegestaan.');
        }

        $baseName = strtolower(pathinfo((string) $originalName, PATHINFO_FILENAME));
        $baseName = trim((string) preg_replace('/[^a-z0-9-]+/', '-', $baseName), '-');
        $baseName = $baseName === '' ? 'afbeelding' : $baseName;
        $fileName = $baseName . '.' . $extension;
        $target = $targetDirectory . '/' . $fileName;
        $counter = 2;

        while (is_file($target)) {
            $fileName = $baseName . '-' . $counter . '.' . $extension;
            $target = $targetDirectory . '/' . $fileName;
            $counter++;
        }

        if (!move_uploaded_file($temporaryName, $target)) {
            throw new RuntimeException('De afbeelding kon niet worden opgeslagen.');
        }

        $uploaded[] = $relativeDirectory === '' ? $fileName : $relativeDirectory . '/' . $fileName;
    }

    return $uploaded;
}
