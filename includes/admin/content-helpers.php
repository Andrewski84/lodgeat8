<?php
declare(strict_types=1);

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
    if (!is_array($value)) {
        return admin_lines_from_text((string) $value);
    }

    $clean = [];

    foreach ($value as $line) {
        $line = trim((string) $line);

        if ($line !== '') {
            $clean[] = $line;
        }
    }

    return $clean;
}

function admin_sanitize_plain_text(string $value): string
{
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = strip_tags($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}

function admin_sanitize_plain_lines($value): array
{
    $lines = is_array($value) ? $value : admin_lines_from_text((string) $value);
    $clean = [];

    foreach ($lines as $line) {
        $line = admin_sanitize_plain_text((string) $line);

        if ($line !== '') {
            $clean[] = $line;
        }
    }

    return $clean;
}

function admin_sanitize_rich_text(string $html): string
{
    $html = trim($html);

    if ($html === '') {
        return '';
    }

    return trim(rich_text_html($html));
}

function admin_sanitize_rich_text_lines(string $text): array
{
    $lines = preg_split('/\R/', $text) ?: [];
    $clean = [];

    foreach ($lines as $line) {
        $line = admin_sanitize_rich_text((string) $line);

        if ($line !== '') {
            $clean[] = $line;
        }
    }

    return $clean;
}

function admin_sanitize_rich_text_pairs(array $pairs): array
{
    $clean = [];

    foreach ($pairs as $label => $value) {
        $label = admin_sanitize_rich_text((string) $label);
        $value = admin_sanitize_rich_text((string) $value);

        if ($label !== '' && $value !== '') {
            $clean[$label] = $value;
        }
    }

    return $clean;
}

function admin_text_from_lines(array $lines): string
{
    return implode(PHP_EOL, array_map('strval', $lines));
}

function admin_pairs_from_text(string $text): array
{
    // Prices can come from a plain textarea or legacy rich-text markup.
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
        $file = admin_safe_media_filename($item);

        return [
            'file' => $file,
            'title' => pathinfo($file, PATHINFO_FILENAME),
        ];
    }

    if (!is_array($item)) {
        return [
            'file' => '',
            'title' => '',
        ];
    }

    $file = admin_safe_media_filename((string) ($item['file'] ?? ''));
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

function admin_background_page_options(): array
{
    $sections = admin_sections();
    $skip = [
        'algemeen' => true,
        'toegang' => true,
    ];
    $pages = [];

    foreach ($sections as $key => $label) {
        if (isset($skip[$key])) {
            continue;
        }

        $pages[$key] = $label;
    }

    return $pages;
}

function admin_background_upload_directory(): string
{
    // General background uploads are separate from the home carousel photos.
    return 'backgrounds';
}

function admin_background_file_for_storage(string $file): string
{
    $file = admin_safe_media_filename($file);

    if ($file === '' || strpos($file, admin_background_upload_directory() . '/') === 0) {
        return $file;
    }

    $mediaRoot = realpath(base_path('assets/img'));

    if ($mediaRoot === false) {
        return $file;
    }

    $source = $mediaRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);

    if (!is_file($source)) {
        return $file;
    }

    $targetDirectory = $mediaRoot . DIRECTORY_SEPARATOR . admin_background_upload_directory();

    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
        throw new RuntimeException('De background map kon niet worden aangemaakt.');
    }

    if (!is_writable($targetDirectory)) {
        throw new RuntimeException('De background map is niet schrijfbaar. Controleer de rechten van assets/img/backgrounds.');
    }

    $fileName = basename($file);
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    $baseName = pathinfo($fileName, PATHINFO_FILENAME);
    $target = $targetDirectory . DIRECTORY_SEPARATOR . $fileName;
    $counter = 2;

    while (is_file($target)) {
        if (@sha1_file($source) === @sha1_file($target)) {
            return admin_background_upload_directory() . '/' . basename($target);
        }

        $fileName = $baseName . '-' . $counter . '.' . $extension;
        $target = $targetDirectory . DIRECTORY_SEPARATOR . $fileName;
        $counter++;
    }

    if (!copy($source, $target)) {
        throw new RuntimeException('De background foto kon niet naar assets/img/backgrounds worden gekopieerd.');
    }

    if (function_exists('admin_compress_large_jpeg')) {
        admin_compress_large_jpeg($target);
    }

    return admin_background_upload_directory() . '/' . basename($target);
}

function admin_background_display_mode(string $mode): string
{
    return background_display_mode($mode);
}

function admin_background_focus_value($value): float
{
    return background_focus_value($value);
}

function admin_clean_background_pages($pages): array
{
    if (!is_array($pages)) {
        return [];
    }

    $validPages = array_fill_keys(array_keys(admin_background_page_options()), true);
    $clean = [];

    foreach ($pages as $page) {
        $page = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $page)) ?? '';

        if ($page !== '' && isset($validPages[$page])) {
            $clean[$page] = true;
        }
    }

    return array_keys($clean);
}

function admin_default_background_pages(): array
{
    return array_keys(admin_background_page_options());
}

function admin_media_form_key(string $file): string
{
    $file = admin_safe_media_filename($file);

    return $file === '' ? '' : sha1($file);
}

function admin_background_item_from_media_item(array $item, ?array $pages = null, string $display = '', $focusX = 50, $focusY = 50): array
{
    $file = admin_safe_media_filename((string) ($item['file'] ?? ''));

    return [
        'file' => $file,
        'title' => trim((string) ($item['title'] ?? '')),
        'pages' => $pages ?? admin_default_background_pages(),
        'display' => admin_background_display_mode($display),
        'focus_x' => admin_background_focus_value($focusX),
        'focus_y' => admin_background_focus_value($focusY),
    ];
}

function admin_normalize_background_item($item): array
{
    $mediaItem = admin_normalize_media_item($item);

    if ($mediaItem['file'] === '') {
        return admin_background_item_from_media_item($mediaItem);
    }

    if (!is_array($item)) {
        return admin_background_item_from_media_item($mediaItem);
    }

    $pages = array_key_exists('pages', $item)
        ? admin_clean_background_pages($item['pages'])
        : admin_default_background_pages();

    return admin_background_item_from_media_item(
        $mediaItem,
        $pages,
        (string) ($item['display'] ?? ''),
        $item['focus_x'] ?? 50,
        $item['focus_y'] ?? 50
    );
}

function admin_normalize_background_items(array $items): array
{
    $normalized = [];

    foreach ($items as $item) {
        $item = admin_normalize_background_item($item);

        if ($item['file'] !== '') {
            $normalized[] = $item;
        }
    }

    return $normalized;
}

function admin_backgrounds_from_post(array $post, array $uploaded = []): array
{
    $files = $post['file'] ?? [];
    $keys = $post['key'] ?? [];
    $titles = $post['title'] ?? [];
    $pagesByKey = is_array($post['pages'] ?? null) ? $post['pages'] : [];
    $displayByKey = is_array($post['display'] ?? null) ? $post['display'] : [];
    $focusXByKey = is_array($post['focus_x'] ?? null) ? $post['focus_x'] : [];
    $focusYByKey = is_array($post['focus_y'] ?? null) ? $post['focus_y'] : [];
    $remove = [];
    $items = [];

    foreach (($post['remove'] ?? []) as $file) {
        $file = admin_safe_media_filename((string) $file);

        if ($file !== '') {
            $remove[$file] = true;
        }
    }

    foreach ($files as $index => $file) {
        $file = admin_safe_media_filename((string) $file);

        if ($file === '' || isset($remove[$file])) {
            continue;
        }

        $key = (string) ($keys[$index] ?? admin_media_form_key($file));
        $expectedKey = admin_media_form_key($file);
        $storedFile = admin_background_file_for_storage($file);

        if ($key === '' || !hash_equals($expectedKey, $key)) {
            $key = $expectedKey;
        }

        $items[] = admin_background_item_from_media_item(
            [
                'file' => $storedFile,
                'title' => trim((string) ($titles[$index] ?? '')),
            ],
            array_key_exists($key, $pagesByKey) ? admin_clean_background_pages($pagesByKey[$key]) : admin_default_background_pages(),
            (string) ($displayByKey[$key] ?? ''),
            $focusXByKey[$key] ?? 50,
            $focusYByKey[$key] ?? 50
        );
    }

    foreach ($uploaded as $file) {
        $items[] = admin_background_item_from_media_item([
            'file' => $file,
            'title' => '',
        ]);
    }

    return $items;
}

function admin_append_uploaded_backgrounds(array $items, array $uploaded): array
{
    $items = admin_normalize_background_items($items);

    foreach ($uploaded as $file) {
        $items[] = admin_background_item_from_media_item([
            'file' => $file,
            'title' => '',
        ]);
    }

    return $items;
}

function admin_media_from_post(array $post, array $uploaded = []): array
{
    $files = $post['file'] ?? [];
    $titles = $post['title'] ?? [];
    $remove = [];
    $items = [];

    foreach (($post['remove'] ?? []) as $file) {
        $file = admin_safe_media_filename((string) $file);

        if ($file !== '') {
            $remove[$file] = true;
        }
    }

    foreach ($files as $index => $file) {
        $file = admin_safe_media_filename((string) $file);

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
        if (admin_safe_media_filename((string) $file) !== '') {
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

function admin_first_media_file(array $items): string
{
    foreach (admin_normalize_media_items($items) as $item) {
        $file = admin_safe_media_filename((string) ($item['file'] ?? ''));

        if ($file !== '') {
            return $file;
        }
    }

    return '';
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
