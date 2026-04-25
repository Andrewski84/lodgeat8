<?php
declare(strict_types=1);

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
    return [
        'nl' => 'Nederlands',
        'fr' => 'Français',
        'en' => 'English',
    ];
}

function requested_language(): string
{
    $language = strtolower((string) ($_GET['lang'] ?? 'nl'));

    return array_key_exists($language, supported_languages()) ? $language : 'nl';
}

function current_language(): string
{
    return (string) ($GLOBALS['currentLanguage'] ?? requested_language());
}

function url_for(string $page, ?string $language = null): string
{
    if ($language === null) {
        $language = current_language();
    }

    return 'index.php?lang=' . rawurlencode($language) . '&p=' . rawurlencode($page);
}

function asset(string $path): string
{
    return 'assets/' . ltrim($path, '/');
}

function image_path(string $file): string
{
    return asset('img/' . ltrim(str_replace('\\', '/', $file), '/'));
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
