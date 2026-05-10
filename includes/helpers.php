<?php
declare(strict_types=1);

/*
 * Shared presentation helpers.
 *
 * Public templates and admin templates both use these helpers for escaping,
 * asset paths, safe URL checks, language wrappers, galleries and background
 * image normalization. Keeping that logic here prevents subtle differences
 * between public rendering and admin previews.
 */

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_list_array(array $array): bool
{
    if (function_exists('array_is_list')) {
        return array_is_list($array);
    }

    $index = 0;

    foreach ($array as $key => $_value) {
        if ($key !== $index) {
            return false;
        }

        $index++;
    }

    return true;
}

function supported_languages(): array
{
    return get_supported_languages();
}

function requested_language(): string
{
    return get_requested_language();
}

function current_language(): string
{
    return get_current_language();
}

function url_for(string $page, ?string $language = null): string
{
    return create_language_url($page, $language);
}

function is_safe_web_url(string $url): bool
{
    $url = trim($url);

    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

    return in_array($scheme, ['http', 'https'], true);
}

function asset(string $path): string
{
    return 'assets/' . ltrim($path, '/');
}

function image_path(string $file): string
{
    return asset('img/' . ltrim(str_replace('\\', '/', $file), '/'));
}

function gallery_items_for_display(array $gallery): array
{
    $items = [];

    foreach ($gallery as $item) {
        $file = is_array($item) ? (string) ($item['file'] ?? '') : (string) $item;
        $file = ltrim(str_replace('\\', '/', $file), '/');

        if ($file === '' || preg_match('#(^|/)\.\.(/|$)#', $file) === 1) {
            continue;
        }

        if (!is_file(base_path('assets/img/' . $file))) {
            continue;
        }

        $items[] = [
            'file' => $file,
            'alt' => is_array($item) ? (string) ($item['title'] ?? $item['alt'] ?? '') : '',
        ];
    }

    return $items;
}

function background_display_options(): array
{
    return [
        'cover-center' => [
            'label' => 'Cropped midden',
            'size' => 'cover',
            'position' => 'center center',
            'repeat' => 'no-repeat',
        ],
        'cover-top' => [
            'label' => 'Cropped boven',
            'size' => 'cover',
            'position' => 'center top',
            'repeat' => 'no-repeat',
        ],
        'cover-bottom' => [
            'label' => 'Cropped beneden',
            'size' => 'cover',
            'position' => 'center bottom',
            'repeat' => 'no-repeat',
        ],
        'cover-left' => [
            'label' => 'Cropped links',
            'size' => 'cover',
            'position' => 'left center',
            'repeat' => 'no-repeat',
        ],
        'cover-right' => [
            'label' => 'Cropped rechts',
            'size' => 'cover',
            'position' => 'right center',
            'repeat' => 'no-repeat',
        ],
        'cover-focus' => [
            'label' => 'Aangepast focuspunt',
            'size' => 'cover',
            'position' => 'center center',
            'repeat' => 'no-repeat',
        ],
        'contain-center' => [
            'label' => 'Volledig beeld',
            'size' => 'contain',
            'position' => 'center center',
            'repeat' => 'no-repeat',
        ],
    ];
}

function background_display_mode(string $mode): string
{
    return array_key_exists($mode, background_display_options()) ? $mode : 'cover-center';
}

function background_focus_value($value, float $fallback = 50.0): float
{
    if (!is_numeric($value)) {
        return $fallback;
    }

    return max(0.0, min(100.0, (float) $value));
}

function background_focus_position($x, $y): string
{
    $x = round(background_focus_value($x), 2);
    $y = round(background_focus_value($y), 2);

    return rtrim(rtrim(number_format($x, 2, '.', ''), '0'), '.') . '% '
        . rtrim(rtrim(number_format($y, 2, '.', ''), '0'), '.') . '%';
}

function background_item_pages($item): ?array
{
    if (!is_array($item) || !array_key_exists('pages', $item)) {
        return null;
    }

    if (!is_array($item['pages'])) {
        return [];
    }

    $pages = [];

    foreach ($item['pages'] as $page) {
        $page = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $page)) ?? '';

        if ($page !== '') {
            $pages[$page] = true;
        }
    }

    return array_keys($pages);
}

function background_item_is_enabled_for_page($item, string $pageKey): bool
{
    $pages = background_item_pages($item);

    return $pages === null || in_array($pageKey, $pages, true);
}

function background_items_for_display(array $backgrounds, string $pageKey): array
{
    $items = [];

    foreach ($backgrounds as $item) {
        if (!background_item_is_enabled_for_page($item, $pageKey)) {
            continue;
        }

        $file = is_array($item) ? (string) ($item['file'] ?? '') : (string) $item;
        $file = ltrim(str_replace('\\', '/', $file), '/');

        if ($file === '' || preg_match('#(^|/)\.\.(/|$)#', $file) === 1) {
            continue;
        }

        if (!is_file(base_path('assets/img/' . $file))) {
            continue;
        }

        $mode = is_array($item) ? background_display_mode((string) ($item['display'] ?? '')) : background_display_mode('');
        $display = background_display_options()[$mode];
        $position = $mode === 'cover-focus' && is_array($item)
            ? background_focus_position($item['focus_x'] ?? 50, $item['focus_y'] ?? 50)
            : $display['position'];

        $items[] = [
            'file' => $file,
            'alt' => is_array($item) ? (string) ($item['title'] ?? $item['alt'] ?? '') : '',
            'display' => $mode,
            'size' => $display['size'],
            'position' => $position,
            'repeat' => $display['repeat'],
        ];
    }

    return $items;
}

function ui_text(string $key, ?string $language = null): string
{
    if ($language === null) {
        $language = current_language();
    }
    $texts = [
        'availability' => [
            'nl' => 'Beschikbaarheid & reservatie',
            'en' => 'Availability & reservation',
            'fr' => 'Disponibilités & réservation',
        ],
        'price_per_night' => [
            'nl' => 'Prijs per nacht',
            'en' => 'Price per night',
            'fr' => 'Prix par nuit',
        ],
        'form_name' => [
            'nl' => 'Uw naam',
            'en' => 'Your name',
            'fr' => 'Votre nom',
        ],
        'form_email' => [
            'nl' => 'Uw email',
            'en' => 'Your email',
            'fr' => 'Votre e-mail',
        ],
        'form_phone' => [
            'nl' => 'Uw telefoonnummer',
            'en' => 'Your phone number',
            'fr' => 'Votre numéro de téléphone',
        ],
        'form_subject' => [
            'nl' => 'Onderwerp',
            'en' => 'Subject',
            'fr' => 'Sujet',
        ],
        'form_message' => [
            'nl' => 'Uw bericht',
            'en' => 'Your message',
            'fr' => 'Votre message',
        ],
        'form_send' => [
            'nl' => 'Verstuur',
            'en' => 'Send',
            'fr' => 'Envoyer',
        ],
    ];

    return (string) ($texts[$key][$language] ?? $texts[$key]['nl'] ?? $key);
}

function is_active(string $current, string $target): string
{
    return $current === $target ? ' aria-current="page" class="is-active"' : '';
}
