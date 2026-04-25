<?php
declare(strict_types=1);

function render_intro(array $paragraphs): void
{
    foreach ($paragraphs as $paragraph) {
        $html = rich_text_html((string) $paragraph);

        if ($html === '') {
            continue;
        }

        echo rich_text_is_block_html($html) ? $html : '<p>' . $html . '</p>';
    }
}

function rich_text_html(string $html): string
{
    $html = trim($html);

    if ($html === '') {
        return '';
    }

    // The admin rich-text editor stores a small HTML subset. Strip everything
    // else here before public templates render the content.
    $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html) ?? '';
    $html = strip_tags($html, '<a><b><strong><u><span><font><br><em><i><ul><ol><li><p><div>');
    $html = str_ireplace('</font>', '</span>', $html);

    $html = preg_replace_callback('/<a\b[^>]*>/i', static function (array $matches): string {
        $href = '';

        if (preg_match('/\bhref\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $matches[0], $hrefMatch) === 1) {
            $href = html_entity_decode((string) ($hrefMatch[2] ?? $hrefMatch[3] ?? $hrefMatch[4] ?? ''), ENT_QUOTES, 'UTF-8');
            $href = trim($href);
        }

        if ($href === '' || preg_match('/^(https?:|mailto:|tel:|\/|#)/i', $href) !== 1) {
            return '<a>';
        }

        return '<a href="' . e($href) . '" target="_blank" rel="noopener">';
    }, $html) ?? '';

    $html = preg_replace_callback('/<span\b[^>]*>/i', static function (array $matches): string {
        $color = rich_text_color_from_tag($matches[0]);

        return $color === '' ? '<span>' : '<span style="color: ' . e($color) . '">';
    }, $html) ?? '';

    $html = preg_replace_callback('/<font\b[^>]*>/i', static function (array $matches): string {
        $color = rich_text_color_from_tag($matches[0]);

        return $color === '' ? '<span>' : '<span style="color: ' . e($color) . '">';
    }, $html) ?? '';

    $html = preg_replace('/<(\/?)(b|strong|u|em|i)\b[^>]*>/i', '<$1$2>', $html) ?? '';
    $html = preg_replace('/<br\b[^>]*>/i', '<br>', $html) ?? '';
    $html = preg_replace('/<(\/?)(ul|ol|li|p|div)\b[^>]*>/i', '<$1$2>', $html) ?? '';

    return $html;
}

function rich_text_is_block_html(string $html): bool
{
    return preg_match('/^\s*<(ul|ol|p|div)\b/i', $html) === 1;
}

function rich_text_color_from_tag(string $tag): string
{
    $color = '';

    if (preg_match('/\bstyle\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $tag, $styleMatch) === 1) {
        $style = (string) ($styleMatch[2] ?? $styleMatch[3] ?? '');

        if (preg_match('/(?:^|;)\s*color\s*:\s*([^;]+)/i', $style, $colorMatch) === 1) {
            $color = trim((string) $colorMatch[1]);
        }
    }

    if ($color === '' && preg_match('/\bcolor\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $colorMatch) === 1) {
        $color = trim((string) ($colorMatch[2] ?? $colorMatch[3] ?? $colorMatch[4] ?? ''));
    }

    return preg_match('/^(#[0-9a-f]{3,8}|[a-z]+|rgba?\([\d\s.,%]+\))$/i', $color) === 1 ? $color : '';
}

function localized_field(array $item, string $field, string $language)
{
    // Prefer the requested translation, then fall back to the base content.
    if (isset($item['translations'][$language]) && array_key_exists($field, $item['translations'][$language])) {
        return $item['translations'][$language][$field];
    }

    return $item[$field] ?? null;
}

function localized_page(array $page, string $language): array
{
    foreach (['title', 'intro', 'items', 'columns', 'success_message'] as $field) {
        $value = localized_field($page, $field, $language);

        if ($value !== null) {
            $page[$field] = $value;
        }
    }

    return $page;
}

function localized_room(array $room, string $language): array
{
    foreach (['title', 'nav_title', 'summary', 'booking_url', 'features', 'prices_heading', 'prices', 'extra_info'] as $field) {
        $value = localized_field($room, $field, $language);

        if ($value !== null) {
            $room[$field] = $value;
        }
    }

    return $room;
}

function localize_route_page(array $page, string $language): array
{
    if (($page['type'] ?? '') === 'room' && isset($page['room'])) {
        $page['room'] = localized_room($page['room'], $language);
        $page['title'] = $page['room']['title'];

        return $page;
    }

    return localized_page($page, $language);
}

function navigation_label(array $config, string $target, string $language): string
{
    if (isset($config['rooms'][$target])) {
        $room = $config['rooms'][$target];
        $roomNavTitle = trim((string) localized_field($room, 'nav_title', $language));

        if ($roomNavTitle !== '') {
            return $roomNavTitle;
        }

        $roomTitle = trim((string) localized_field($room, 'title', $language));

        if ($roomTitle !== '') {
            return $roomTitle;
        }
    }

    if (isset($config['pages'][$target])) {
        return (string) localized_field($config['pages'][$target], 'title', $language);
    }

    return (string) ($config['navigation'][$target] ?? $target);
}

function booking_widget_settings(array $config): array
{
    $widget = $config['booking_widget'] ?? [];
    $site = $config['site'] ?? [];

    return [
        'enabled' => ($widget['enabled'] ?? true) === true,
        'title' => trim((string) ($widget['title'] ?? 'Reservatie')),
        'button_label' => trim((string) ($widget['button_label'] ?? 'Check availability')),
        'embed_code' => (string) ($widget['embed_code'] ?? ''),
        'fallback_action' => (string) ($site['reservation_url'] ?? ''),
    ];
}

function booking_widget_action(array $widget): string
{
    // Existing Cubilis snippets contain the booking URL in an embedded form.
    // Extract it so the site can render a modern native date form.
    if (preg_match('/<form\b[^>]*\baction=["\']([^"\']+)["\']/i', $widget['embed_code'], $matches) === 1) {
        return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }

    return $widget['fallback_action'];
}

function booking_widget_language(array $widget): string
{
    if (preg_match('/\b_TAAL\s*=\s*["\']([a-z]{2}(?:-[a-z]{2})?)["\']/i', $widget['embed_code'], $matches) === 1) {
        return strtolower($matches[1]);
    }

    return 'en';
}

function map_embed_url(string $mapUrl): string
{
    // Accept either a plain Maps URL or a pasted iframe and normalize it to an
    // embeddable Google Maps URL when possible.
    $mapUrl = trim($mapUrl);

    if ($mapUrl === '') {
        return '';
    }

    if (preg_match('/<iframe\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $mapUrl, $matches) === 1) {
        $mapUrl = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }

    if (filter_var($mapUrl, FILTER_VALIDATE_URL) === false) {
        return '';
    }

    $host = strtolower((string) parse_url($mapUrl, PHP_URL_HOST));
    $path = strtolower((string) parse_url($mapUrl, PHP_URL_PATH));

    if (str_contains($host, 'google.') && str_contains($path, '/maps') && !str_contains($path, '/maps/embed')) {
        $separator = str_contains($mapUrl, '?') ? '&' : '?';
        $mapUrl .= str_contains($mapUrl, 'output=') ? '' : $separator . 'output=embed';
    }

    return $mapUrl;
}

function page_template_for(string $type): string
{
    $templates = [
        'home' => 'home',
        'leuven' => 'leuven',
        'room' => 'room',
        'location' => 'location',
        'contact' => 'contact',
        'links' => 'links',
        'news' => 'news',
        'terms' => 'terms',
    ];

    return $templates[$type] ?? 'generic';
}
