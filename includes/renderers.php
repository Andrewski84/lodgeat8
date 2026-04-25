<?php
declare(strict_types=1);

function render_intro(array $paragraphs): void
{
    foreach ($paragraphs as $paragraph) {
        echo '<p>' . e($paragraph) . '</p>';
    }
}

function localized_field(array $item, string $field, string $language)
{
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
    foreach (['title', 'summary', 'booking_url', 'features', 'prices_heading', 'prices', 'extra_info'] as $field) {
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
        return (string) localized_field($config['rooms'][$target], 'title', $language);
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
