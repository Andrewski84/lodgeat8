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
